<?php
namespace Opencart\System\Library\Extension\WebskySeo;

final class Settings {
    public static function defaults(): array {
        return ['status'=>0, 'urls'=>1, 'language_prefix'=>1, 'trailing_slash'=>0, 'canonical'=>1,
            'hreflang'=>1, 'schema'=>1, 'social'=>1, 'pagination'=>1, 'sitemap'=>1, 'instant'=>0,
            'log_404'=>1, 'log_bots'=>0, 'smart_redirect'=>0, 'internal_links'=>0, 'product_h2'=>0,
            'store_links'=>0, 'trim_title'=>65, 'trim_description'=>165, 'ai_model'=>'gpt-4.1-mini',
            'ai_daily_limit'=>50, 'ai_instructions'=>'', 'publisher_url'=>'',
            'title_template'=>'{name} | {store}', 'description_template'=>'{name}. {description}',
            'h1_template'=>'{name}', 'h2_template'=>'{name}', 'image_alt_template'=>'{name} {brand}',
            'image_title_template'=>'{name}', 'keyword_template'=>'{name}, {brand}, {category}',
            'robots_default'=>'index,follow', 'retention_days'=>30];
    }
    public static function read($config): array {
        $data = self::defaults();
        foreach ($data as $key => $default) {
            $value = $config->get('module_websky_seo_' . $key);
            if ($value !== null && $value !== '') $data[$key] = is_int($default) ? (int)$value : (string)$value;
        }
        return $data;
    }
    public static function validate(array $input): array {
        $out = self::defaults();
        foreach ($out as $key => $default) {
            $value = $input[$key] ?? $default;
            if (!is_scalar($value)) throw new \InvalidArgumentException('Invalid setting: ' . $key);
            $out[$key] = is_int($default) ? (int)$value : trim((string)$value);
        }
        foreach (['trim_title'=>[20,255], 'trim_description'=>[50,500], 'ai_daily_limit'=>[1,1000], 'retention_days'=>[1,90]] as $key=>$range) {
            if ($out[$key] < $range[0] || $out[$key] > $range[1]) throw new \InvalidArgumentException('Out of range: ' . $key);
        }
        foreach ($out as $key=>$value) if (is_int($value) && !in_array($key, ['trim_title','trim_description','ai_daily_limit','retention_days'], true)) $out[$key] = (int)(bool)$value;
        if (!preg_match('/^[a-zA-Z0-9._-]{1,100}$/', $out['ai_model'])) throw new \InvalidArgumentException('Invalid model name.');
        if (!in_array($out['robots_default'], ['index,follow','noindex,follow','noindex,nofollow'], true)) throw new \InvalidArgumentException('Invalid robots directive.');
        if ($out['publisher_url'] && !filter_var($out['publisher_url'], FILTER_VALIDATE_URL)) throw new \InvalidArgumentException('Invalid publisher URL.');
        foreach ($out as $key=>$value) if (is_string($value) && mb_strlen($value)>2000) throw new \InvalidArgumentException('Setting too long: ' . $key);
        return $out;
    }
}


