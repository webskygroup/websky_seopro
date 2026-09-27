<?php
namespace Opencart\Catalog\Controller\Extension\WebskySeo\Feed;
use Opencart\System\Library\Extension\WebskySeo\Settings;
class Robots extends \Opencart\System\Engine\Controller {
 public function index(): void { $settings=Settings::read($this->config);$base=rtrim((string)($this->config->get('config_ssl')?:$this->config->get('config_url')),'/');$lines=['User-agent: *','Allow: /','Disallow: /index.php?route=account/','Disallow: /index.php?route=checkout/','Disallow: /index.php?route=api/','Disallow: /*?sort=','Disallow: /*?filter=','Disallow: /*?limit=','Sitemap: '.$base.'/sitemap.xml'];$this->response->addHeader('Content-Type: text/plain; charset=utf-8');$this->response->setOutput(implode("\n",$lines)."\n"); }
}


