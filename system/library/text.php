<?php
namespace Opencart\System\Library\Extension\WebskySeo;

final class Text {
    public const FIELDS = ['meta_title', 'meta_description', 'meta_keyword', 'tag', 'h1', 'h2', 'image_alt', 'image_title', 'description', 'robots'];

    public static function plain(string $text): string {
        return trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? '');
    }

    public static function trim(string $text, int $length): string {
        $text = self::plain($text);
        if (mb_strlen($text) <= $length) return $text;
        $cut = mb_substr($text, 0, $length - 1);
        $space = mb_strrpos($cut, ' ');
        return rtrim($space !== false && $space > $length * .65 ? mb_substr($cut, 0, $space) : $cut, ' ,،.-') . '…';
    }

    public static function slug(string $text): string {
        $text = strtr(mb_strtolower(self::plain($text)), ['ي'=>'ی', 'ك'=>'ک', '‌'=>'-', 'ۀ'=>'ه']);
        $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text) ?? '';
        return trim(mb_substr($text, 0, 160), '-');
    }

    public static function keywords(string $text): string {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(self::plain($text)), -1, PREG_SPLIT_NO_EMPTY);
        $stop = ['the','and','with','for','from','this','that','are','your','این','برای','است','های','یک','در','از','به','با','و'];
        $words = array_filter($words ?: [], fn($w) => mb_strlen($w) > 2 && !in_array($w, $stop, true));
        return implode(', ', array_slice(array_unique($words), 0, 12));
    }

    public static function render(string $template, array $context): string {
        $map = [];
        foreach ($context as $key => $value) if (is_scalar($value)) $map['{' . $key . '}'] = self::plain((string)$value);
        return self::plain(strtr($template, $map));
    }

    public static function escape(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function json(array $data): string {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    }

    public static function path(string $path): string {
        $path = rawurldecode(trim($path));
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//') || preg_match('/[\x00-\x20\\\\?#]/u', $path) || str_contains($path, '..')) {
            throw new \InvalidArgumentException('Enter a local absolute path without query, fragment or traversal.');
        }
        return $path;
    }

    public static function audit(array $row): array {
        $issues = [];
        $title = self::plain((string)($row['meta_title'] ?? ''));
        $description = self::plain((string)($row['meta_description'] ?? ''));
        if ($title === '') $issues['missing_title'] = 25;
        elseif (mb_strlen($title) < 20 || mb_strlen($title) > 65) $issues['title_length'] = 10;
        if ($description === '') $issues['missing_description'] = 25;
        elseif (mb_strlen($description) < 70 || mb_strlen($description) > 165) $issues['description_length'] = 10;
        if (empty($row['keyword']) && ($row['type'] ?? '') !== 'home') $issues['missing_url'] = 20;
        if (empty($row['h1'])) $issues['default_h1'] = 5;
        if (empty($row['image_alt']) && ($row['type'] ?? '') === 'product') $issues['default_alt'] = 5;
        if (mb_strlen(self::plain((string)($row['description'] ?? ''))) < 100) $issues['thin_content'] = 10;
        return ['score' => max(0, 100 - array_sum($issues)), 'issues' => array_keys($issues)];
    }
}


