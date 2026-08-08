<?php

namespace Opencart\Catalog\Controller\Extension\WebskySeo\Startup;

class Seo extends \Opencart\System\Engine\Controller {
    public function index(): void {
        if (!$this->config->get('module_websky_seo_status')) {
            return;
        }

        $this->event->register(
            'view/product/product/after',
            new \Opencart\System\Engine\Action('extension/websky_seo/startup/seo.productSchema'),
            100
        );
    }

    public function productSchema(string &$route, array &$data, string &$output): void {
        $productId = (int)($data['product_id'] ?? 0);

        if (!$productId || stripos($output, 'application/ld+json') !== false) {
            return;
        }

        $this->load->model('catalog/product');
        $product = $this->model_catalog_product->getProduct($productId);

        if (!$product) {
            return;
        }

        $amount = !empty($product['special']) ? (float)$product['special'] : (float)$product['price'];
        $amount = $this->tax->calculate($amount, (int)$product['tax_class_id'], $this->config->get('config_tax'));
        $currency = (string)$this->session->data['currency'];
        $amount = $this->currency->convert($amount, (string)$this->config->get('config_currency'), $currency);

        $description = trim((string)preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode((string)$product['meta_description'], ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
        if ($description === '') {
            $description = trim((string)preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode((string)$product['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => (string)$product['name'],
            'description' => $description,
            'sku' => (string)$product['model'],
            'url' => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $productId),
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => $currency,
                'price' => number_format($amount, 2, '.', ''),
                'availability' => ((int)$product['quantity'] > 0) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition'
            ]
        ];

        if (!empty($data['popup'])) {
            $schema['image'] = [(string)$data['popup']];
        }

        if (!empty($data['manufacturer'])) {
            $schema['brand'] = ['@type' => 'Brand', 'name' => (string)$data['manufacturer']];
        }

        if (!empty($product['rating']) && !empty($product['reviews'])) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (float)$product['rating'],
                'reviewCount' => (int)$product['reviews']
            ];
        }

        $json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $script = '<script type="application/ld+json">' . $json . '</script>';

        if (stripos($output, '</head>') !== false) {
            $output = preg_replace('/<\/head>/i', $script . '</head>', $output, 1);
        } else {
            $output .= $script;
        }
    }
}
