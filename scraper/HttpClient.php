<?php

class HttpClient
{
    private array $defaultHeaders = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
        'Accept-Encoding: gzip, deflate, br',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1',
    ];

    public function get(string $url, array $extraHeaders = [], int $timeout = 30): ?string
    {
        return $this->request('GET', $url, null, $extraHeaders, $timeout, true);
    }

    public function getJson(string $url, array $params = []): ?array
    {
        if ($params !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
        }

        $response = $this->get($url, ['Accept: application/json']);
        if ($response === null) {
            return null;
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function post(string $url, array $data, array $headers = []): ?string
    {
        return $this->request('POST', $url, http_build_query($data), $headers, 30, true);
    }

    private function request(string $method, string $url, ?string $body, array $headers, int $timeout, bool $retry): ?string
    {
        if (!function_exists('curl_init')) {
            error_log('HttpClient requires cURL extension.');
            return null;
        }

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => array_merge($this->defaultHeaders, $headers),
        ]);

        if ($method === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body ?? '');
        }

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        sleep(rand(1, 3));

        if ($response === false) {
            error_log('HTTP request failed for ' . $url . ': ' . $error);
            return null;
        }

        if ($statusCode === 429 && $retry) {
            sleep(10);
            return $this->request($method, $url, $body, $headers, $timeout, false);
        }

        if ($statusCode >= 400 || $statusCode === 0) {
            error_log('HTTP request returned status ' . $statusCode . ' for ' . $url);
            return null;
        }

        return (string) $response;
    }
}
