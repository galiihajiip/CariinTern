<?php

class HtmlParser
{
    private DOMDocument $dom;
    private DOMXPath $xpath;

    public function load(string $html): self
    {
        $this->dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $this->dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        $this->xpath = new DOMXPath($this->dom);

        return $this;
    }

    public function find(string $cssSelector): DOMNodeList
    {
        return $this->xpath->query($this->cssToXpath($cssSelector));
    }

    public function text(string $cssSelector): string
    {
        $node = $this->find($cssSelector)->item(0);
        return $node ? trim(preg_replace('/\s+/', ' ', $node->textContent) ?? '') : '';
    }

    public function attr(string $cssSelector, string $attribute): string
    {
        $node = $this->find($cssSelector)->item(0);
        return $node instanceof DOMElement ? trim($node->getAttribute($attribute)) : '';
    }

    public function all(string $cssSelector): array
    {
        $values = [];

        foreach ($this->find($cssSelector) as $node) {
            $values[] = trim(preg_replace('/\s+/', ' ', $node->textContent) ?? '');
        }

        return array_values(array_filter($values, static fn (string $value): bool => $value !== ''));
    }

    public function html(DOMNode $node): string
    {
        return $this->dom->saveHTML($node) ?: '';
    }

    private function cssToXpath(string $css): string
    {
        $css = trim($css);
        if ($css === '') {
            return '//*';
        }

        $parts = preg_split('/\s+/', $css) ?: [];
        $xpath = '.';

        foreach ($parts as $part) {
            $xpath .= '//' . $this->simpleSelectorToXpath($part);
        }

        return $xpath;
    }

    private function simpleSelectorToXpath(string $selector): string
    {
        $tag = '*';
        $conditions = [];

        if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*/', $selector, $match)) {
            $tag = $match[0];
        }

        if (preg_match('/#([a-zA-Z0-9_-]+)/', $selector, $match)) {
            $conditions[] = '@id="' . $match[1] . '"';
        }

        if (preg_match_all('/\.([a-zA-Z0-9_-]+)/', $selector, $matches)) {
            foreach ($matches[1] as $className) {
                $conditions[] = 'contains(concat(" ", normalize-space(@class), " "), " ' . $className . ' ")';
            }
        }

        if (preg_match_all('/\[([a-zA-Z0-9_-]+)(?:=["\']?([^"\']+)["\']?)?\]/', $selector, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $conditions[] = isset($match[2]) && $match[2] !== ''
                    ? '@' . $match[1] . '="' . $match[2] . '"'
                    : '@' . $match[1];
            }
        }

        return $tag . ($conditions !== [] ? '[' . implode(' and ', $conditions) . ']' : '');
    }
}
