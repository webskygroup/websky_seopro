<?php
namespace Opencart\System\Library\Extension\WebskySeo;

/** Keeps other extensions' rewrite handlers as the fallback for non-owned routes. */
final class Urls extends \Opencart\System\Library\Url {
    public const ROUTES = ['product/product'=>['product','product_id'], 'product/category'=>['category','path'],
        'product/manufacturer.info'=>['manufacturer','manufacturer_id'], 'information/information'=>['information','information_id']];
    public const FRIENDLY = ['common/home'=>'', 'account/login'=>'login', 'account/register'=>'register',
        'account/forgotten'=>'forgotten-password', 'information/contact'=>'contact', 'information/sitemap'=>'site-map', 'product/special'=>'specials'];
    private string $base;
    private $config;
    private $db;
    private Repository $repo;
    private array $settings;
    private $original;
    private array $languages;
    private array $cache=[];
    public function __construct($registry, Repository $repo, array $settings, $original) {
        $this->base=rtrim($registry->get('config')->get('config_url'),'/').'/'; parent::__construct($this->base);
        $this->config=$registry->get('config'); $this->db=$registry->get('db'); $this->repo=$repo;
        $this->settings=$settings; $this->original=$original; $this->languages=array_column($repo->languages(),null,'code');
    }
    public function link(string $route,$args='',bool $js=false): string {
        if (!$this->settings['urls'] || !$this->config->get('config_seo_url')) return $this->original->link($route,$args,$js);
        $q=is_array($args)?$args:[];
        if (is_string($args)) parse_str(html_entity_decode($args,ENT_QUOTES,'UTF-8'),$q);
        $language=(string)($q['language']??$this->config->get('config_language'));
        if (!isset($this->languages[$language])) return $this->original->link($route,$args,$js);
        $lang=(int)$this->languages[$language]['language_id']; $store=(int)$this->config->get('config_store_id');
        $path=null;
        if (isset(self::ROUTES[$route])) {
            [$type,$key]=self::ROUTES[$route];
            if (!isset($q[$key]) || !is_scalar($q[$key])) return $this->original->link($route,$args,$js);
            $ids=explode('_',(string)$q[$key]); $id=(int)end($ids); $value=$this->repo->seoValue($type,$id);
            $cache=$store.':'.$lang.':'.$key.':'.$value;
            if (!array_key_exists($cache,$this->cache)) $this->cache[$cache]=$this->repo->keyword($key,$value,$store,$lang);
            $slug=$this->cache[$cache];
            if ($slug==='' || str_contains($slug,'/')) return $this->raw($route,$q,$js);
            $path=['manufacturer'=>'brand','information'=>'info'][$type]??$type;
            $path.='/'.rawurlencode($slug); unset($q[$key]);
            if ($type==='product') unset($q['path']);
        } elseif (array_key_exists($route,self::FRIENDLY)) $path=self::FRIENDLY[$route];
        elseif ($route==='product/search' && !empty($q['tag']) && empty($q['search'])) { $path='tags/'.rawurlencode($q['tag']); unset($q['tag']); }
        if ($path===null) return $this->original->link($route,$args,$js);
        if ($this->settings['language_prefix']) { $path=$language.($path!==''?'/'.$path:''); unset($q['language']); }
        else $q['language']=$language;
        if (!empty($q['page']) && (int)$q['page']>1 && $this->settings['pagination']) { $path.='/page/'.(int)$q['page']; unset($q['page']); }
        elseif (isset($q['page']) && (int)$q['page']<=1) unset($q['page']);
        if ($this->settings['trailing_slash'] && in_array($route,['product/category','product/manufacturer.info'],true)) $path.='/';
        $url=$this->base.$path.($q?'?'.http_build_query($q,'','&',PHP_QUERY_RFC3986):'');
        return $js?$url:str_replace('&','&amp;',$url);
    }
    private function raw(string $route,array $q,bool $js): string {
        $url=$this->base.'index.php?'.http_build_query(['route'=>$route]+$q,'','&',PHP_QUERY_RFC3986);
        return $js?$url:str_replace('&','&amp;',$url);
    }
    public function decode(string $path): ?array {
        if (!$this->settings['urls'] || !$this->config->get('config_seo_url')) return null;
        $parts=explode('/',trim($path,'/')); $language=(string)$this->config->get('config_language');
        $prefixed=isset($this->languages[$parts[0]??'']);
        if ($prefixed) $language=array_shift($parts);
        if (!isset($this->languages[$language])) return null;
        $q=['language'=>$language]; $n=count($parts);
        if ($n>=2 && $parts[$n-2]==='page' && ctype_digit($parts[$n-1])) { $q['page']=max(1,(int)array_pop($parts)); array_pop($parts); }
        $joined=implode('/',$parts); $friendly=array_search($joined,self::FRIENDLY,true);
        if ($friendly!==false && ($joined!==''||$prefixed)) return ['route'=>$friendly]+$q;
        if (count($parts)!==2) return $prefixed?['route'=>'error/not_found']+$q:null;
        if ($parts[0]==='tags') return ['route'=>'product/search','tag'=>rawurldecode($parts[1])]+$q;
        $map=['product'=>['product/product','product_id'],'category'=>['product/category','path'],'brand'=>['product/manufacturer.info','manufacturer_id'],'info'=>['information/information','information_id']];
        if (!isset($map[$parts[0]])) return $prefixed?['route'=>'error/not_found']+$q:null;
        [$route,$key]=$map[$parts[0]];
        $row=$this->db->query('SELECT value FROM `'.DB_PREFIX."seo_url` WHERE store_id=".(int)$this->config->get('config_store_id').' AND language_id='.(int)$this->languages[$language]['language_id']." AND `key`='".$this->db->escape($key)."' AND keyword='".$this->db->escape(rawurldecode($parts[1]))."' LIMIT 1")->row;
        return $row?['route'=>$route,$key=>$row['value']]+$q:['route'=>'error/not_found']+$q;
    }
    public function languageId(string $code): int { return (int)($this->languages[$code]['language_id']??0); }
}


