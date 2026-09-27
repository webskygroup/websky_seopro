<?php
namespace Opencart\Admin\Controller\Extension\WebskySeo\Event;

class Seo extends \Opencart\System\Engine\Controller {
    public function adminProductForm(string &$route, array &$data, string &$output): void {
        if (stripos($output, 'websky-seo-assistant') !== false || empty($data['product_id'])) return;
        $token = 'user_token=' . $this->session->data['user_token'];
        $href = $this->url->link('extension/websky_seo/module/websky_seo', $token . '&type=product&entity_id=' . (int)$data['product_id']);
        $button = '<a id="websky-seo-assistant" class="btn btn-outline-primary" href="' . htmlspecialchars(html_entity_decode($href, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '"><i class="fa-solid fa-wand-magic-sparkles"></i> SEO assistant</a>';
        if (stripos($output, '</form>') !== false) $output = preg_replace('/<\/form>/i', $button . '</form>', $output, 1);
        else $output .= $button;
    }
}
