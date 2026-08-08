<?php

namespace Opencart\Admin\Controller\Extension\WebskySeo\Module;

class WebskySeo extends \Opencart\System\Engine\Controller {
    public function index(): void {
        $this->load->language('extension/websky_seo/module/websky_seo');
        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module')
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/websky_seo/module/websky_seo', 'user_token=' . $this->session->data['user_token'])
        ];

        $data['save'] = $this->url->link('extension/websky_seo/module/websky_seo.save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');
        $data['status'] = (bool)$this->config->get('module_websky_seo_status');
        $data['sitemap'] = rtrim(HTTP_CATALOG, '/') . '/sitemap.xml';
        $data['version'] = '1.0.0';

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/websky_seo/module/websky_seo', $data));
    }

    public function save(): void {
        $this->load->language('extension/websky_seo/module/websky_seo');
        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/websky_seo/module/websky_seo')) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (!$json) {
            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting('module_websky_seo', [
                'module_websky_seo_status' => (int)($this->request->post['module_websky_seo_status'] ?? 0)
            ]);
            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function install(): void {
        $this->load->model('setting/startup');
        $this->model_setting_startup->deleteStartupByCode('websky_seo_catalog');
        $this->model_setting_startup->addStartup([
            'code' => 'websky_seo_catalog',
            'action' => 'catalog/extension/websky_seo/startup/seo',
            'status' => 1,
            'sort_order' => 6
        ]);

        $this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE `key` = 'route' AND `value` = 'extension/websky_seo/feed/sitemap'");
        $this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET `store_id` = 0, `language_id` = " . (int)$this->config->get('config_language_id') . ", `key` = 'route', `value` = 'extension/websky_seo/feed/sitemap', `keyword` = 'sitemap.xml', `sort_order` = -1");

        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('module_websky_seo', ['module_websky_seo_status' => 1]);
    }

    public function uninstall(): void {
        $this->load->model('setting/startup');
        $this->model_setting_startup->deleteStartupByCode('websky_seo_catalog');
        $this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE `key` = 'route' AND `value` = 'extension/websky_seo/feed/sitemap'");
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('module_websky_seo');
    }
}
