<?php

require_once __DIR__ . '/HttpClient.php';
require_once __DIR__ . '/HtmlParser.php';

class ScraperEngine
{
    private PDO $db;
    private int $logId = 0;
    private HttpClient $http;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->http = new HttpClient();
    }

    public function run(int $sourceId): array
    {
        $sourceStmt = $this->db->prepare('SELECT * FROM scraper_sources WHERE id = :id LIMIT 1');
        $sourceStmt->execute([':id' => $sourceId]);
        $source = $sourceStmt->fetch(PDO::FETCH_ASSOC);

        if (!$source) {
            return ['found' => 0, 'new' => 0, 'duplicate' => 0, 'error' => 'Source tidak ditemukan'];
        }

        $this->logId = $this->startLog($sourceId);

        try {
            $items = $this->scrape($source);
            $stats = $this->saveItems($items, $sourceId);
            $stats['found'] = count($items);

            $this->db->prepare(
                'UPDATE scraper_sources
                 SET last_scraped_at = NOW(), total_scraped = total_scraped + :items_new
                 WHERE id = :id'
            )->execute([
                ':items_new' => $stats['new'],
                ':id' => $sourceId,
            ]);

            $this->finishLog($this->logId, 'success', $stats);

            return $stats;
        } catch (Throwable $exception) {
            $stats = ['found' => 0, 'new' => 0, 'duplicate' => 0];
            $this->finishLog($this->logId, 'failed', $stats, $exception->getMessage());
            error_log('Scraper source ' . $sourceId . ' failed: ' . $exception->getMessage());

            return $stats + ['error' => $exception->getMessage()];
        }
    }

    private function scrape(array $source): array
    {
        return match ((string) $source['type']) {
            'website' => $this->scrapeWebsite($source),
            'rss' => $this->scrapeRSS($source),
            'telegram' => $this->scrapeTelegram($source),
            'google_cse' => $this->scrapeGoogleCSE($source),
            default => throw new RuntimeException('Tipe scraper tidak didukung'),
        };
    }

    private function scrapeWebsite(array $source): array
    {
        sleep(rand(1, 3));
        $html = $this->http->get((string) $source['url']);
        if ($html === null) {
            return [];
        }

        $selectors = json_decode((string) ($source['css_selectors'] ?? ''), true);
        if (!is_array($selectors) || empty($selectors['job_container'])) {
            throw new RuntimeException('CSS selector website belum lengkap');
        }

        $parser = (new HtmlParser())->load($html);
        $containers = $parser->find((string) $selectors['job_container']);
        $items = [];

        foreach ($containers as $container) {
            $itemParser = (new HtmlParser())->load($parser->html($container));
            $title = $this->cleanText($itemParser->text((string) ($selectors['title'] ?? '')));
            $company = $this->cleanText($itemParser->text((string) ($selectors['company'] ?? '')));
            $location = $this->cleanText($itemParser->text((string) ($selectors['location'] ?? '')));
            $description = $this->cleanText($itemParser->text((string) ($selectors['description'] ?? $selectors['title'] ?? '')));
            $deadlineText = $this->cleanText($itemParser->text((string) ($selectors['deadline'] ?? '')));
            $sourceUrl = $itemParser->attr((string) ($selectors['url'] ?? 'a[href]'), 'href');

            if ($title === '') {
                continue;
            }

            $sourceUrl = $this->absoluteUrl((string) $source['url'], $sourceUrl);

            $items[] = [
                'external_id' => $this->generateHash($sourceUrl, $title, $company),
                'title' => $title,
                'company_name' => $company !== '' ? $company : null,
                'location' => $location !== '' ? $location : null,
                'description' => $description,
                'requirements' => null,
                'deadline' => $this->parseDate($deadlineText),
                'source_url' => $sourceUrl !== '' ? $sourceUrl : (string) $source['url'],
                'raw_data' => compact('title', 'company', 'location', 'deadlineText'),
            ];
        }

        return $items;
    }

    private function scrapeRSS(array $source): array
    {
        $xml = $this->http->get((string) $source['url']);
        if ($xml === null) {
            return [];
        }

        libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml);
        libxml_clear_errors();

        if (!$feed) {
            throw new RuntimeException('RSS/Atom feed tidak valid');
        }

        $items = [];
        $nodes = [];

        if (isset($feed->channel->item)) {
            $nodes = iterator_to_array($feed->channel->item);
        } elseif (isset($feed->entry)) {
            $nodes = iterator_to_array($feed->entry);
        }

        foreach ($nodes as $node) {
            $title = $this->cleanText((string) ($node->title ?? ''));
            $link = (string) ($node->link ?? '');
            if ($link === '' && isset($node->link['href'])) {
                $link = (string) $node->link['href'];
            }
            $description = $this->cleanText((string) ($node->description ?? $node->summary ?? $node->content ?? ''));
            $dateText = (string) ($node->pubDate ?? $node->updated ?? $node->published ?? '');

            if ($title === '' || !$this->passesKeywordFilter($source, $title . ' ' . $description)) {
                continue;
            }

            $items[] = [
                'external_id' => $this->generateHash($link, $title),
                'title' => $title,
                'company_name' => null,
                'location' => null,
                'description' => $description,
                'requirements' => null,
                'deadline' => $this->parseDate($dateText),
                'source_url' => $link !== '' ? $link : (string) $source['url'],
                'raw_data' => ['pubDate' => $dateText],
            ];
        }

        return $items;
    }

    private function scrapeTelegram(array $source): array
    {
        $config = $this->decodeJson($source['config'] ?? null);
        $method = (string) ($config['method'] ?? 'web_scrape');

        if ($method === 'bot_api' && !empty($config['bot_token'])) {
            $json = $this->http->getJson('https://api.telegram.org/bot' . $config['bot_token'] . '/getUpdates');
            $updates = is_array($json['result'] ?? null) ? $json['result'] : [];
            $items = [];

            foreach ($updates as $update) {
                $message = $update['channel_post']['text'] ?? $update['message']['text'] ?? '';
                $items = array_merge($items, $this->telegramTextToItems((string) $message, (string) $source['url'], $source));
            }

            return $items;
        }

        $html = $this->http->get((string) $source['url']);
        if ($html === null) {
            return [];
        }

        $parser = (new HtmlParser())->load($html);
        $messages = $parser->all('.tgme_widget_message_text');
        $items = [];

        foreach ($messages as $message) {
            $items = array_merge($items, $this->telegramTextToItems($message, (string) $source['url'], $source));
        }

        return $items;
    }

    private function scrapeGoogleCSE(array $source): array
    {
        $config = $this->decodeJson($source['config'] ?? null);
        $apiKey = (string) ($config['api_key'] ?? '');
        $cx = (string) ($config['cx'] ?? '');

        if ($apiKey === '' || $cx === '' || str_contains($apiKey, 'GANTI_') || str_contains($cx, 'GANTI_')) {
            throw new RuntimeException('Google CSE API key dan CX ID belum dikonfigurasi');
        }

        $json = $this->http->getJson((string) $source['url'], [
            'key' => $apiKey,
            'cx' => $cx,
            'q' => (string) ($config['query'] ?? 'lowongan magang'),
            'dateRestrict' => (string) ($config['date_restrict'] ?? 'd7'),
        ]);

        $items = [];
        foreach (($json['items'] ?? []) as $result) {
            $title = $this->cleanText((string) ($result['title'] ?? ''));
            $link = (string) ($result['link'] ?? '');
            $description = $this->cleanText((string) ($result['snippet'] ?? ''));

            if ($title === '') {
                continue;
            }

            $items[] = [
                'external_id' => $this->generateHash($link, $title),
                'title' => $title,
                'company_name' => null,
                'location' => null,
                'description' => $description,
                'requirements' => null,
                'deadline' => null,
                'source_url' => $link !== '' ? $link : (string) $source['url'],
                'raw_data' => $result,
            ];
        }

        return $items;
    }

    private function saveItems(array $items, int $sourceId): array
    {
        $stats = ['found' => count($items), 'new' => 0, 'duplicate' => 0];
        $existsStmt = $this->db->prepare(
            'SELECT id FROM scraped_jobs WHERE source_id = :source_id AND external_id = :external_id LIMIT 1'
        );
        $insertStmt = $this->db->prepare(
            'INSERT INTO scraped_jobs
                (source_id, external_id, title, company_name, location, description, requirements, deadline, source_url, category_id, raw_data)
             VALUES
                (:source_id, :external_id, :title, :company_name, :location, :description, :requirements, :deadline, :source_url, :category_id, :raw_data)'
        );

        foreach ($items as $item) {
            $title = $this->cleanText((string) ($item['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $description = $this->cleanText((string) ($item['description'] ?? ''));
            $externalId = (string) ($item['external_id'] ?? $this->generateHash($item['source_url'] ?? '', $title));

            $existsStmt->execute([
                ':source_id' => $sourceId,
                ':external_id' => $externalId,
            ]);

            if ($existsStmt->fetchColumn()) {
                $stats['duplicate']++;
                continue;
            }

            $categoryId = $this->autoDetectCategory($title, $description);

            $insertStmt->execute([
                ':source_id' => $sourceId,
                ':external_id' => $externalId,
                ':title' => substr($title, 0, 200),
                ':company_name' => $this->nullable(substr($this->cleanText((string) ($item['company_name'] ?? '')), 0, 150)),
                ':location' => $this->nullable(substr($this->cleanText((string) ($item['location'] ?? '')), 0, 100)),
                ':description' => $description,
                ':requirements' => $this->nullable($this->cleanText((string) ($item['requirements'] ?? ''))),
                ':deadline' => $item['deadline'] ?? null,
                ':source_url' => (string) ($item['source_url'] ?? ''),
                ':category_id' => $categoryId,
                ':raw_data' => json_encode($item['raw_data'] ?? $item, JSON_UNESCAPED_UNICODE),
            ]);
            $stats['new']++;
        }

        return $stats;
    }

    private function generateHash(string ...$parts): string
    {
        return hash('sha256', implode('|', array_map('trim', $parts)));
    }

    private function startLog(int $sourceId): int
    {
        $stmt = $this->db->prepare('INSERT INTO scraper_logs (source_id, status) VALUES (:source_id, \'running\')');
        $stmt->execute([':source_id' => $sourceId]);

        return (int) $this->db->lastInsertId();
    }

    private function finishLog(int $logId, string $status, array $stats, string $error = ''): void
    {
        $stmt = $this->db->prepare(
            'UPDATE scraper_logs
             SET finished_at = NOW(),
                 status = :status,
                 items_found = :items_found,
                 items_new = :items_new,
                 items_duplicate = :items_duplicate,
                 error_message = :error_message
             WHERE id = :id'
        );
        $stmt->execute([
            ':status' => $status,
            ':items_found' => (int) ($stats['found'] ?? 0),
            ':items_new' => (int) ($stats['new'] ?? 0),
            ':items_duplicate' => (int) ($stats['duplicate'] ?? 0),
            ':error_message' => $error !== '' ? $error : null,
            ':id' => $logId,
        ]);
    }

    private function autoDetectCategory(string $title, string $description): ?int
    {
        $keywordMap = [
            'teknologi' => ['programming', 'software', 'developer', 'coding', 'backend', 'frontend', 'web', 'mobile', 'android', 'ios', 'data', 'ai', 'machine learning', 'python', 'javascript', 'php'],
            'bisnis' => ['marketing', 'sales', 'business', 'finance', 'accounting', 'hr', 'human resource', 'management', 'administrasi', 'bisnis'],
            'desain' => ['design', 'ui', 'ux', 'graphic', 'visual', 'creative', 'illustrat', 'motion', 'video', 'photo'],
            'engineering' => ['electrical', 'mechanical', 'civil', 'industri', 'teknik', 'manufacturing', 'production'],
        ];

        $combinedText = strtolower($title . ' ' . $description);
        $scores = [];

        foreach ($keywordMap as $categorySlug => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                if (str_contains($combinedText, strtolower($keyword))) {
                    $score++;
                }
            }
            $scores[$categorySlug] = $score;
        }

        arsort($scores);
        $topCategory = array_key_first($scores);

        if ($topCategory === null || $scores[$topCategory] === 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT id FROM internship_categories WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $topCategory]);
        $categoryId = $stmt->fetchColumn();

        return $categoryId ? (int) $categoryId : null;
    }

    private function cleanText(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text) ?? '';

        return trim(substr($text, 0, 2000));
    }

    private function telegramTextToItems(string $message, string $sourceUrl, array $source): array
    {
        $message = $this->cleanText($message);
        if ($message === '' || !$this->passesKeywordFilter($source, $message)) {
            return [];
        }

        $title = $this->extractLineTitle($message);
        $company = null;
        $deadline = null;

        if (preg_match('/(?:perusahaan|company|di)\s*:?\s*([A-Za-z0-9 .,&-]+)/i', $message, $match)) {
            $company = trim($match[1]);
        }

        if (preg_match('/deadline\s*:?\s*([0-9]{1,2}[\/\-\s][A-Za-z0-9\/\-\s]{3,20})/i', $message, $match)) {
            $deadline = $this->parseDate($match[1]);
        }

        return [[
            'external_id' => $this->generateHash($sourceUrl, $message),
            'title' => $title,
            'company_name' => $company,
            'location' => null,
            'description' => $message,
            'requirements' => null,
            'deadline' => $deadline,
            'source_url' => $sourceUrl,
            'raw_data' => ['message' => $message],
        ]];
    }

    private function passesKeywordFilter(array $source, string $text): bool
    {
        $config = $this->decodeJson($source['config'] ?? null);
        $keywords = $config['keyword_filter'] ?? ['magang', 'internship', 'lowongan', 'intern'];

        foreach ((array) $keywords as $keyword) {
            if (str_contains(strtolower($text), strtolower((string) $keyword))) {
                return true;
            }
        }

        return false;
    }

    private function parseDate(string $dateText): ?string
    {
        $dateText = trim($dateText);
        if ($dateText === '') {
            return null;
        }

        $timestamp = strtotime($dateText);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    private function decodeJson(?string $json): array
    {
        $decoded = json_decode((string) $json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function absoluteUrl(string $baseUrl, string $url): string
    {
        if ($url === '' || preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        $base = parse_url($baseUrl);
        if (!$base || empty($base['scheme']) || empty($base['host'])) {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            return $base['scheme'] . '://' . $base['host'] . $url;
        }

        $path = isset($base['path']) ? dirname($base['path']) : '';
        return $base['scheme'] . '://' . $base['host'] . rtrim($path, '/') . '/' . ltrim($url, '/');
    }

    private function extractLineTitle(string $message): string
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/[\r\n.]+/', $message) ?: [])));
        return substr($lines[0] ?? 'Info Magang', 0, 200);
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);
        return $value !== '' ? $value : null;
    }
}
