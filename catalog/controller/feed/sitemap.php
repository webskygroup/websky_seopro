<?php

namespace Opencart\Catalog\Controller\Extension\WebskySeo\Feed;

class Sitemap extends \Opencart\System\Engine\Controller {
    public function index(): void {
        $language = (string)$this->config->get('config_language');
        $urls = [];

        $urls[] = [
            'loc' => rtrim((string)$this->config->get('config_url'), '/') . '/',
            'changefreq' => 'daily',
            'priority' => '1.0'
        ];

        $products = $this->db->query("SELECT product_id, date_modified FROM `" . DB_PREFIX . "product` WHERE status = 1 ORDER BY product_id");
        foreach ($products->rows as $product) {
            $urls[] = [
                'loc' => $this->url->link('product/product', 'language=' . $language . '&product_id=' . (int)$product['product_id']),
                'lastmod' => substr((string)$product['date_modified'], 0, 10),
                'changefreq' => 'weekly',
                'priority' => '0.8'
            ];
        }

        $categories = $this->db->query("SELECT category_id, date_modified FROM `" . DB_PREFIX . "category` WHERE status = 1 ORDER BY category_id");
        foreach ($categories->rows as $category) {
            $urls[] = [
                'loc' => $this->url->link('product/category', 'language=' . $language . '&path=' . (int)$category['category_id']),
                'lastmod' => substr((string)$category['date_modified'], 0, 10),
                'changefreq' => 'weekly',
                'priority' => '0.7'
            ];
        }

        $information = $this->db->query("SELECT i.information_id FROM `" . DB_PREFIX . "information` i LEFT JOIN `" . DB_PREFIX . "information_to_store` i2s ON i2s.information_id = i.information_id WHERE i.status = 1 AND i2s.store_id = " . (int)$this->config->get('config_store_id') . " ORDER BY i.information_id");
        foreach ($information->rows as $page) {
            $urls[] = [
                'loc' => $this->url->link('information/information', 'language=' . $language . '&information_id=' . (int)$page['information_id']),
                'changefreq' => 'monthly',
                'priority' => '0.5'
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($url['loc'], ENT_QUOTES | ENT_XML1, 'UTF-8') . "</loc>\n";
            if (!empty($url['lastmod']) && $url['lastmod'] !== '0000-00-00') {
                $xml .= '    <lastmod>' . $url['lastmod'] . "</lastmod>\n";
            }
            $xml .= '    <changefreq>' . $url['changefreq'] . "</changefreq>\n";
            $xml .= '    <priority>' . $url['priority'] . "</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>';

        $this->response->addHeader('Content-Type: application/xml; charset=utf-8');
        $this->response->setOutput($xml);
    }
}
