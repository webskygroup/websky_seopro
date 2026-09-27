<?php
namespace Opencart\Catalog\Controller\Extension\WebskySeo\Startup;

use Opencart\System\Library\Extension\WebskySeo\Repository;
use Opencart\System\Library\Extension\WebskySeo\Settings;
use Opencart\System\Library\Extension\WebskySeo\Text;
use Opencart\System\Library\Extension\WebskySeo\Urls;

class Seo extends \Opencart\System\Engine\Controller {
    public function index(): void {
        $settings = Settings::read($this->config);
        if (!$settings['status']) return;
        if ($settings['urls'] && $this->config->get('config_seo_url')) $this->registry->set('url', new Urls($this->registry, new Repository($this->db), $settings, $this->url));
        $this->handleRedirect($settings);
        $this->event->register('view/common/header/after', new \Opencart\System\Engine\Action('extension/websky_seo/startup/seo.head'), 100);
        $this->event->register('view/product/product/after', new \Opencart\System\Engine\Action('extension/websky_seo/startup/seo.productSchema'), 100);
        $this->event->register('view/error/not_found/after', new \Opencart\System\Engine\Action('extension/websky_seo/startup/seo.notFoundView'), 100);
    }
    private function handleRedirect(array $settings): void {
        if (!$settings['smart_redirect'] || !in_array($this->request->server['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) return;
        $path = (string)parse_url($this->request->server['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if ($path === '' || str_contains($path, '/admin/') || str_contains($path, '/index.php')) return;
        $repo = new Repository($this->db); $rule = $repo->redirect($path, (int)$this->config->get('config_store_id'));
        if ($rule) { $repo->hitRedirect((int)$rule['redirect_id']); $this->response->redirect($rule['target'], (int)$rule['code']); }
    }
    public function head(string &$route, array &$data, string &$output): void {
        $settings = Settings::read($this->config); if (!$settings['status']) return;
        if ($settings['log_404'] && (string)($this->request->get['route'] ?? '') === 'error/not_found') (new Repository($this->db))->log((int)$this->config->get('config_store_id'), '404', (string)($this->request->server['REQUEST_URI'] ?? '/'), '', (int)$settings['retention_days']);
        [$type, $id] = $this->page(); $repo = new Repository($this->db); $entity = $repo->entity($type, $id, (int)$this->config->get('config_store_id'), (int)$this->config->get('config_language_id'));
        if (!$entity) $entity = ['type' => $type, 'entity_id' => $id, 'name' => $this->config->get('config_name'), 'meta_title' => '', 'meta_description' => '', 'meta_keyword' => '', 'h1' => '', 'robots' => $settings['robots_default'], 'description' => ''];
        $title = Text::trim((string)($entity['meta_title'] ?: $entity['name']), (int)$settings['trim_title']); $description = Text::trim((string)($entity['meta_description'] ?: ($entity['description'] ?? '')), (int)$settings['trim_description']);
        if ($title !== '' && preg_match('/<title>.*?<\/title>/is', $output)) $output = preg_replace('/<title>.*?<\/title>/is', '<title>' . Text::escape($title) . '</title>', $output, 1);
        if ($description !== '' && preg_match('/<meta\s+name=["\']description["\'][^>]*>/i', $output)) $output = preg_replace('/<meta\s+name=["\']description["\'][^>]*>/i', '<meta name="description" content="' . Text::escape($description) . '"/>', $output, 1);
        $keywords = Text::plain((string)($entity['meta_keyword'] ?? '')); if ($keywords !== '' && preg_match('/<meta\s+name=["\']keywords["\'][^>]*>/i', $output)) $output = preg_replace('/<meta\s+name=["\']keywords["\'][^>]*>/i', '<meta name="keywords" content="' . Text::escape($keywords) . '"/>', $output, 1);
        $tags = []; $canonical = $this->canonical($type, $id);
        if ($settings['canonical'] && $canonical && !preg_match('/<link\s+[^>]*rel=["\']canonical["\']/i', $output)) $tags[] = '<link rel="canonical" href="' . Text::escape($canonical) . '"/>';
        if ($settings['hreflang'] && $type !== 'home') $tags = array_merge($tags, $this->hreflang($type, $id));
        $robots = Text::plain((string)($entity['robots'] ?? $settings['robots_default'])); if (!in_array($robots, ['index,follow', 'noindex,follow', 'noindex,nofollow'], true)) $robots = $settings['robots_default']; $tags[] = '<meta name="robots" content="' . Text::escape($robots) . '"/>';
        if ($settings['social']) { $tags[] = '<meta property="og:title" content="' . Text::escape($title) . '"/>'; if ($description) $tags[] = '<meta property="og:description" content="' . Text::escape($description) . '"/>'; if ($canonical) $tags[] = '<meta property="og:url" content="' . Text::escape($canonical) . '"/>'; $tags[] = '<meta name="twitter:card" content="summary_large_image"/>'; }
        if ($tags && stripos($output, '</head>') !== false) $output = preg_replace('/<\/head>/i', implode('', $tags) . '</head>', $output, 1);
    }
    private function page(): array { $route = (string)($this->request->get['route'] ?? 'common/home'); if ($route === 'product/product') return ['product', (int)($this->request->get['product_id'] ?? 0)]; if ($route === 'product/category') return ['category', (int)($this->request->get['path'] ?? 0)]; if (str_starts_with($route, 'product/manufacturer')) return ['manufacturer', (int)($this->request->get['manufacturer_id'] ?? 0)]; if ($route === 'information/information') return ['information', (int)($this->request->get['information_id'] ?? 0)]; return ['home', 0]; }
    private function canonical(string $type, int $id): string { $route = $type === 'product' ? 'product/product' : ($type === 'category' ? 'product/category' : ($type === 'manufacturer' ? 'product/manufacturer/info' : ($type === 'information' ? 'information/information' : 'common/home'))); $args = ['language' => $this->config->get('config_language')]; if ($type === 'product') $args['product_id'] = $id; if ($type === 'category') $args['path'] = $id; if ($type === 'manufacturer') $args['manufacturer_id'] = $id; if ($type === 'information') $args['information_id'] = $id; return $this->url->link($route, $args, true); }
    private function hreflang(string $type, int $id): array { $result = []; $repo = new Repository($this->db); foreach ($repo->languages() as $language) { $route = $type === 'product' ? 'product/product' : ($type === 'category' ? 'product/category' : ($type === 'manufacturer' ? 'product/manufacturer/info' : 'information/information')); $args = ['language' => $language['code']]; if ($type === 'product') $args['product_id'] = $id; if ($type === 'category') $args['path'] = $id; if ($type === 'manufacturer') $args['manufacturer_id'] = $id; if ($type === 'information') $args['information_id'] = $id; $result[] = '<link rel="alternate" hreflang="' . Text::escape($language['code']) . '" href="' . Text::escape($this->url->link($route, $args, true)) . '"/>'; } return $result; }
    public function productSchema(string &$route, array &$data, string &$output): void {
        $settings = Settings::read($this->config); if (!$settings['status'] || !$settings['schema'] || stripos($output, 'application/ld+json') !== false) return; $id = (int)($data['product_id'] ?? $this->request->get['product_id'] ?? 0); if (!$id) return; $this->load->model('catalog/product'); $product = $this->model_catalog_product->getProduct($id); if (!$product) return;
        $price = !empty($product['special']) ? (float)$product['special'] : (float)$product['price']; $currency = (string)($this->session->data['currency'] ?? $this->config->get('config_currency')); $price = $this->currency->format($price, $currency, 0, false); $price = preg_replace('/[^0-9.\-]/', '', (string)$price);
        $schema = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => (string)$product['name'], 'description' => Text::trim((string)($product['meta_description'] ?: $product['description']), 500), 'sku' => (string)$product['model'], 'url' => $this->canonical('product', $id), 'offers' => ['@type' => 'Offer', 'priceCurrency' => $currency, 'price' => $price, 'availability' => ((int)$product['quantity'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'), 'itemCondition' => 'https://schema.org/NewCondition']];
        if (!empty($data['popup'])) $schema['image'] = [(string)$data['popup']]; if (!empty($data['manufacturer'])) $schema['brand'] = ['@type' => 'Brand', 'name' => (string)$data['manufacturer']]; if (!empty($product['rating']) && $product['reviews']) $schema['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => (float)$product['rating'], 'reviewCount' => (int)$product['reviews']];
        $script = '<script type="application/ld+json">' . Text::json($schema) . '</script>'; $output = stripos($output, '</body>') !== false ? preg_replace('/<\/body>/i', $script . '</body>', $output, 1) : $output . $script;
    }
    public function notFound(string &$route, array &$args, &$output): void { $settings = Settings::read($this->config); $is404 = $route === 'error/not_found' || (string)($this->request->get['route'] ?? '') === 'error/not_found' || (is_string($output) && (stripos($output, 'The page you requested cannot be found') !== false || str_contains($output, 'صفحه مورد نظر پیدا نشد'))); if (!$settings['status'] || !$settings['log_404'] || !$is404) return; (new Repository($this->db))->log((int)$this->config->get('config_store_id'), '404', (string)($this->request->server['REQUEST_URI'] ?? '/'), '', (int)$settings['retention_days']); }
    public function notFoundView(string &$route, array &$data, string &$output): void { $settings=Settings::read($this->config); if(!$settings['status']||!$settings['log_404'])return; (new Repository($this->db))->log((int)$this->config->get('config_store_id'),'404',(string)($this->request->server['REQUEST_URI']??'/'),'',(int)$settings['retention_days']); }
}
