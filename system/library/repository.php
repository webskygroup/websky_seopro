<?php
namespace Opencart\System\Library\Extension\WebskySeo;

final class Repository {
    private $db;
    private array $memo = [];
    public const TYPES = ['product','category','information','manufacturer','home'];
    public function __construct($db) { $this->db = $db; }
    private function q(string $s): string { return "'" . $this->db->escape($s) . "'"; }
    private function table(string $name): string { return '`' . DB_PREFIX . $name . '`'; }
    public function install(): void {
        $tables = [
            'wsseo_meta'=>"`type` varchar(20) NOT NULL, entity_id int NOT NULL, store_id int NOT NULL, language_id int NOT NULL, data mediumtext NOT NULL, revision int NOT NULL DEFAULT 1, date_modified datetime NOT NULL, PRIMARY KEY (`type`,entity_id,store_id,language_id)",
            'wsseo_history'=>"history_id bigint NOT NULL AUTO_INCREMENT, user_id int NOT NULL, `type` varchar(20) NOT NULL, entity_id int NOT NULL, store_id int NOT NULL, language_id int NOT NULL, before_data mediumtext NOT NULL, after_hash char(64) NOT NULL, date_added datetime NOT NULL, restored tinyint NOT NULL DEFAULT 0, PRIMARY KEY (history_id), KEY scope (store_id,language_id)",
            'wsseo_redirect'=>"redirect_id int NOT NULL AUTO_INCREMENT, store_id int NOT NULL, source varchar(768) NOT NULL, source_hash char(64) NOT NULL, target varchar(2048) NOT NULL, code int NOT NULL DEFAULT 301, hits int NOT NULL DEFAULT 0, date_added datetime NOT NULL, PRIMARY KEY (redirect_id), UNIQUE KEY source (store_id,source_hash)",
            'wsseo_link'=>"link_id int NOT NULL AUTO_INCREMENT, store_id int NOT NULL, language_id int NOT NULL, keyword varchar(200) NOT NULL, target varchar(2048) NOT NULL, tooltip varchar(500) NOT NULL, PRIMARY KEY (link_id), KEY scope (store_id,language_id)",
            'wsseo_log'=>"log_id bigint NOT NULL AUTO_INCREMENT, store_id int NOT NULL, kind varchar(20) NOT NULL, path varchar(768) NOT NULL, bot varchar(80) NOT NULL DEFAULT '', hash char(64) NOT NULL, hits int NOT NULL DEFAULT 1, date_added datetime NOT NULL, date_modified datetime NOT NULL, PRIMARY KEY (log_id), UNIQUE KEY log_hash (store_id,hash), KEY retention (date_modified)",
            'wsseo_ai_usage'=>"usage_id bigint NOT NULL AUTO_INCREMENT, store_id int NOT NULL, user_id int NOT NULL, model varchar(100) NOT NULL, tokens int NOT NULL DEFAULT 0, status varchar(20) NOT NULL, date_added datetime NOT NULL, PRIMARY KEY (usage_id), KEY quota (store_id,date_added)"
        ];
        foreach ($tables as $name=>$definition) $this->db->query('CREATE TABLE IF NOT EXISTS ' . $this->table($name) . ' (' . $definition . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
    public function languages(): array { return $this->db->query('SELECT * FROM ' . $this->table('language') . ' WHERE status=1 ORDER BY sort_order, language_id')->rows; }
    public function stores(): array { return array_merge([['store_id'=>0,'name'=>'Default','url'=>'']], $this->db->query('SELECT * FROM ' . $this->table('store') . ' ORDER BY store_id')->rows); }
    public function scope(int $store, int $language): void {
        if (!in_array($store, array_map('intval',array_column($this->stores(),'store_id')), true) || !in_array($language,array_map('intval',array_column($this->languages(),'language_id')),true)) throw new \InvalidArgumentException('Unknown store or language.');
    }
    public function type(string $type): string {
        if (!in_array($type,self::TYPES,true)) throw new \InvalidArgumentException('Unknown entity type.');
        return $type;
    }
    public function native(string $type, int $id, int $store, int $language): array {
        $this->type($type);
        if ($type==='home') {
            $settings=$this->db->query('SELECT `key`,value FROM ' . $this->table('setting') . " WHERE store_id=$store AND code='config'")->rows;
            $s=array_column($settings,'value','key');
            return ['entity_id'=>0,'name'=>$s['config_name']??'Store','meta_title'=>$s['config_meta_title']??'','meta_description'=>$s['config_meta_description']??'','meta_keyword'=>$s['config_meta_keyword']??'', 'description'=>''];
        }
        $key=$type.'_id';
        $sql='SELECT e.*, e.'.$key.' AS entity_id';
        if ($type!=='manufacturer') $sql.=',d.*';
        $sql.=' FROM '.$this->table($type).' e JOIN '.$this->table($type.'_to_store')." s ON s.$key=e.$key AND s.store_id=$store";
        if ($type!=='manufacturer') $sql.=' JOIN '.$this->table($type.'_description')." d ON d.$key=e.$key AND d.language_id=$language";
        $sql.=" WHERE e.$key=$id";
        $row=$this->db->query($sql)->row;
        if ($row && $type==='information') $row['name']=$row['title'];
        return $row;
    }
    public function meta(string $type, int $id, int $store, int $language): array {
        $key=implode(':',[$type,$id,$store,$language]);
        if (!array_key_exists($key,$this->memo)) {
            $row=$this->db->query('SELECT data FROM '.$this->table('wsseo_meta').' WHERE '.$this->where($type,$id,$store,$language))->row;
            $this->memo[$key]=$row ? (json_decode($row['data'],true)?:[]) : [];
        }
        return $this->memo[$key];
    }
    private function where(string $type,int $id,int $store,int $language): string {
        return '`type`='.$this->q($this->type($type))." AND entity_id=$id AND store_id=$store AND language_id=$language";
    }
    public function seoKey(string $type): string { return $type==='category'?'path':($type==='home'?'route':$this->type($type).'_id'); }
    public function seoValue(string $type,int $id): string { return $type==='home'?'common/home':($type==='category'?$this->categoryPath($id):(string)$id); }
    public function categoryPath(int $id): string {
        $rows=$this->db->query('SELECT path_id FROM '.$this->table('category_path')." WHERE category_id=$id ORDER BY level")->rows;
        return $rows?implode('_',array_column($rows,'path_id')):(string)$id;
    }
    public function keyword(string $key,string $value,int $store,int $lang): string {
        $r=$this->db->query('SELECT keyword FROM '.$this->table('seo_url').' WHERE `key`='.$this->q($key).' AND value='.$this->q($value)." AND store_id=$store AND language_id=$lang ORDER BY seo_url_id LIMIT 1")->row;
        return $r['keyword']??'';
    }
    public function entity(string $type,int $id,int $store,int $lang): array {
        $native=$this->native($type,$id,$store,$lang);
        if (!$native) return [];
        $r=array_replace($native,$this->meta($type,$id,$store,$lang));
        $r['type']=$type; $r['entity_id']=$id;
        $r['keyword']=$this->keyword($this->seoKey($type),$this->seoValue($type,$id),$store,$lang);
        $r['etag']=hash('sha256',Text::json($this->snapshot($type,$id,$store,$lang)));
        $r['audit']=Text::audit($r);
        return $r;
    }
    public function listing(string $type,int $store,int $lang,int $after=0,int $limit=25,string $search=''): array {
        $this->type($type); $limit=max(1,min(100,$limit));
        if ($type==='home') return $after ? [] : [$this->entity('home',0,$store,$lang)];
        $key=$type.'_id'; $name=$type==='information'?'title':'name';
        $sql='SELECT e.'.$key.' AS id FROM '.$this->table($type).' e JOIN '.$this->table($type.'_to_store')." s ON s.$key=e.$key AND s.store_id=$store";
        if ($type!=='manufacturer') $sql.=' JOIN '.$this->table($type.'_description')." d ON d.$key=e.$key AND d.language_id=$lang";
        $sql.=" WHERE e.$key>$after";
        if ($search!=='') $sql.=' AND '.($type==='manufacturer'?'e.':'d.').$name.' LIKE '.$this->q('%'.$search.'%');
        $sql.=" ORDER BY e.$key LIMIT $limit";
        return array_map(fn($r)=>$this->entity($type,(int)$r['id'],$store,$lang),$this->db->query($sql)->rows);
    }
    public function snapshot(string $type,int $id,int $store,int $lang): array {
        return ['meta'=>$this->meta($type,$id,$store,$lang),'keyword'=>$this->keyword($this->seoKey($type),$this->seoValue($type,$id),$store,$lang)];
    }
    public function save(string $type,int $id,int $store,int $lang,array $patch,string $etag,int $user): int {
        $this->scope($store,$lang);
        if (!$this->native($type,$id,$store,$lang)) throw new \InvalidArgumentException('Entity does not exist in this store/language.');
        $allowed=array_merge(Text::FIELDS,['keyword','image','related']);
        foreach ($patch as $key=>$value) {
            if (!in_array($key,$allowed,true)) throw new \InvalidArgumentException('Unknown field: '.$key);
            if ($key==='related') {
                if ($type!=='product'||!is_array($value)||count($value)>30) throw new \InvalidArgumentException('Invalid related products.');
                $patch[$key]=array_values(array_unique(array_filter(array_map('intval',$value),fn($v)=>$v>0&&$v!==$id)));
                continue;
            }
            if (!is_string($value)) throw new \InvalidArgumentException('Field must be text: '.$key);
            if (mb_strlen($value)>($key==='description'?20000:($key==='meta_description'?500:255))) throw new \InvalidArgumentException('Field too long: '.$key);
            if ($key==='image' && ($value!=='' && (!str_starts_with($value,'catalog/websky-seo/') || str_contains($value,'..')))) throw new \InvalidArgumentException('Invalid generated image path.');
            if ($key==='robots' && !in_array($value,['','index,follow','noindex,follow','noindex,nofollow'],true)) throw new \InvalidArgumentException('Invalid robots directive.');
            if ($key!=='description' && $key!=='image') $patch[$key]=Text::plain($value);
            if ($key==='description') $patch[$key]=nl2br(Text::escape(Text::plain($value)));
            if ($key==='keyword' && $value!=='' && (Text::slug($value)!==$value || in_array($value,['page','tags','sitemap','sitemap.xml'],true))) throw new \InvalidArgumentException('Use a unique slug made of letters, numbers and hyphens.');
        }
        $lock='wsseo_'.substr(hash('sha256',DB_PREFIX.':'.$store),0,40);
        if (!(int)$this->db->query('SELECT GET_LOCK('.$this->q($lock).',10) AS acquired')->row['acquired']) throw new \RuntimeException('SEO editor is busy. Retry.');
        try {
            $this->memo=[];
            $before=$this->snapshot($type,$id,$store,$lang);
            if ($etag!==hash('sha256',Text::json($before))) throw new \RuntimeException('This record changed. Reload before saving.');
            $this->db->query('START TRANSACTION');
            if (array_key_exists('keyword',$patch)) {
                $this->writeKeyword($type,$id,$store,$lang,$patch['keyword']);
                unset($patch['keyword']);
            }
            $meta=array_replace($before['meta'],$patch);
            $this->writeMeta($type,$id,$store,$lang,$meta);
            $after=$this->snapshot($type,$id,$store,$lang);
            $this->db->query('INSERT INTO '.$this->table('wsseo_history').' SET `type`='.$this->q($type).", entity_id=$id, store_id=$store, language_id=$lang, user_id=$user, before_data=".$this->q(Text::json($before)).', after_hash='.$this->q(hash('sha256',Text::json($after))).', date_added=NOW()');
            $history=(int)$this->db->getLastId();
            $this->db->query('COMMIT');
            return $history;
        } catch (\Throwable $e) { $this->db->query('ROLLBACK'); throw $e; }
        finally { $this->db->query('SELECT RELEASE_LOCK('.$this->q($lock).')'); $this->memo=[]; }
    }
    private function writeMeta(string $type,int $id,int $store,int $lang,array $meta): void {
        $this->db->query('INSERT INTO '.$this->table('wsseo_meta')." (`type`,entity_id,store_id,language_id,data,revision,date_modified) VALUES (".$this->q($type).",$id,$store,$lang,".$this->q(Text::json($meta)).",1,NOW()) ON DUPLICATE KEY UPDATE data=VALUES(data),revision=revision+1,date_modified=NOW()");
        $this->memo=[];
    }
    private function writeKeyword(string $type,int $id,int $store,int $lang,string $keyword): void {
        $key=$this->seoKey($type); $value=$this->seoValue($type,$id);
        if ($keyword!=='') {
            $conflict=$this->db->query('SELECT seo_url_id FROM '.$this->table('seo_url')." WHERE store_id=$store AND language_id=$lang AND keyword=".$this->q($keyword).' AND NOT (`key`='.$this->q($key).' AND value='.$this->q($value).') LIMIT 1');
            if ($conflict->num_rows) throw new \InvalidArgumentException('This SEO URL is already in use.');
        }
        $this->db->query('DELETE FROM '.$this->table('seo_url')." WHERE store_id=$store AND language_id=$lang AND `key`=".$this->q($key).' AND value='.$this->q($value));
        if ($keyword!=='') $this->db->query('INSERT INTO '.$this->table('seo_url')." SET store_id=$store,language_id=$lang,`key`=".$this->q($key).',value='.$this->q($value).',keyword='.$this->q($keyword).',sort_order=0');
    }
    public function uniqueSlug(string $slug,string $type,int $id,int $store,int $lang): string {
        $slug=Text::slug($slug)?:$type.'-'.$id;
        $base=$slug; $n=1;
        while ($this->db->query('SELECT seo_url_id FROM '.$this->table('seo_url')." WHERE store_id=$store AND language_id=$lang AND keyword=".$this->q($slug).' AND NOT (`key`='.$this->q($this->seoKey($type)).' AND value='.$this->q($this->seoValue($type,$id)).') LIMIT 1')->num_rows) $slug=$base.'-'.$id.'-'.++$n;
        return $slug;
    }
    public function restore(int $history,int $user): void {
        $row=$this->db->query('SELECT * FROM '.$this->table('wsseo_history')." WHERE history_id=$history AND restored=0")->row;
        if (!$row) throw new \InvalidArgumentException('History item is missing or already restored.');
        $before=json_decode($row['before_data'],true,512,JSON_THROW_ON_ERROR);
        $current=$this->snapshot($row['type'],(int)$row['entity_id'],(int)$row['store_id'],(int)$row['language_id']);
        $patch=array_fill_keys(array_keys($current['meta']),'');
        if (isset($patch['related'])) $patch['related']=[];
        $patch=array_replace($patch,$before['meta'],['keyword'=>$before['keyword']]);
        $this->save($row['type'],(int)$row['entity_id'],(int)$row['store_id'],(int)$row['language_id'],$patch,$row['after_hash'],$user);
        // Empty overlays should fall back to the exact original values after rollback.
        $this->writeMeta($row['type'],(int)$row['entity_id'],(int)$row['store_id'],(int)$row['language_id'],$before['meta']);
        $this->db->query('UPDATE '.$this->table('wsseo_history')." SET restored=1 WHERE history_id=$history");
    }
    public function context(array $r,int $store,int $lang): array {
        $home=$this->native('home',0,$store,$lang);
        $r['store']=$home['name']; $r['brand']=''; $r['category']='';
        if ($r['type']==='product') {
            $brand=$this->db->query('SELECT name FROM '.$this->table('manufacturer').' WHERE manufacturer_id='.(int)($r['manufacturer_id']??0))->row;
            $r['brand']=$brand['name']??'';
            $category=$this->db->query('SELECT d.name FROM '.$this->table('product_to_category').' pc JOIN '.$this->table('category_description')." d ON d.category_id=pc.category_id AND d.language_id=$lang WHERE pc.product_id=".(int)$r['entity_id'].' ORDER BY pc.category_id LIMIT 1')->row;
            $r['category']=$category['name']??'';
        }
        $r['description']=Text::plain((string)($r['description']??''));
        return $r;
    }
    public function related(int $id,int $store): array {
        $rows=$this->db->query('SELECT p.product_id,COUNT(DISTINCT b.category_id) AS relevance FROM '.$this->table('product').' p JOIN '.$this->table('product_to_store')." s ON s.product_id=p.product_id AND s.store_id=$store JOIN ".$this->table('product_to_category').' a ON a.product_id=p.product_id JOIN '.$this->table('product_to_category')." b ON b.category_id=a.category_id AND b.product_id=$id WHERE p.product_id<>$id AND p.status=1 AND p.date_available<=NOW() GROUP BY p.product_id ORDER BY relevance DESC,p.product_id LIMIT 8")->rows;
        return array_map('intval',array_column($rows,'product_id'));
    }
    public function redirects(int $store): array { return $this->db->query('SELECT * FROM '.$this->table('wsseo_redirect')." WHERE store_id=$store ORDER BY redirect_id DESC LIMIT 200")->rows; }
    public function redirect(string $source,int $store): array { return $this->db->query('SELECT * FROM '.$this->table('wsseo_redirect')." WHERE store_id=$store AND source_hash=".$this->q(hash('sha256',$source)))->row; }
    public function addRedirect(int $store,string $source,string $target,int $code=301): void {
        $source=Text::path($source); $target=Text::path($target);
        if (!in_array($code,[301,302,307,308],true)||$source===$target) throw new \InvalidArgumentException('Invalid redirect.');
        $seen=[$source]; $next=$target;
        for($i=0;$i<20;$i++) {
            if (in_array($next,$seen,true)) throw new \InvalidArgumentException('Redirect loop detected.');
            $seen[]=$next; $rule=$this->redirect($next,$store);
            if (!$rule) break; $next=$rule['target'];
            if ($i===19) throw new \InvalidArgumentException('Redirect chain too long.');
        }
        $this->db->query('INSERT INTO '.$this->table('wsseo_redirect')." SET store_id=$store,source=".$this->q($source).',source_hash='.$this->q(hash('sha256',$source)).',target='.$this->q($target).",code=$code,date_added=NOW() ON DUPLICATE KEY UPDATE target=VALUES(target),code=VALUES(code)");
    }
    public function hitRedirect(int $id): void { $this->db->query('UPDATE '.$this->table('wsseo_redirect')." SET hits=hits+1 WHERE redirect_id=$id"); }
    public function deleteRedirect(int $id,int $store): void { $this->db->query('DELETE FROM '.$this->table('wsseo_redirect')." WHERE redirect_id=$id AND store_id=$store"); }
    public function links(int $store,int $lang): array { return $this->db->query('SELECT * FROM '.$this->table('wsseo_link')." WHERE store_id=$store AND language_id=$lang ORDER BY CHAR_LENGTH(keyword) DESC LIMIT 100")->rows; }
    public function addLink(int $store,int $lang,string $word,string $target,string $tooltip): void {
        $word=Text::plain($word); $tooltip=Text::plain($tooltip);
        if ($word===''||mb_strlen($word)>200||mb_strlen($tooltip)>500||strlen($target)>2048) throw new \InvalidArgumentException('Invalid link fields.');
        if ($target!=='' && !preg_match('~^https?://~i',$target)) $target=Text::path($target);
        elseif ($target!=='' && !filter_var($target,FILTER_VALIDATE_URL)) throw new \InvalidArgumentException('Invalid link URL.');
        $this->db->query('INSERT INTO '.$this->table('wsseo_link')." SET store_id=$store,language_id=$lang,keyword=".$this->q($word).',target='.$this->q($target).',tooltip='.$this->q($tooltip));
    }
    public function deleteLink(int $id,int $store,int $lang): void { $this->db->query('DELETE FROM '.$this->table('wsseo_link')." WHERE link_id=$id AND store_id=$store AND language_id=$lang"); }
    public function log(int $store,string $kind,string $path,string $bot,int $days): void {
        // Do not retain query strings, user identifiers, IP addresses or full user agents.
        $path=mb_substr((string)parse_url($path,PHP_URL_PATH),0,768);
        $hash=hash('sha256',$kind.':'.$path.':'.$bot.':'.date('Y-m-d'));
        $this->db->query('INSERT INTO '.$this->table('wsseo_log')." SET store_id=$store,kind=".$this->q($kind).',path='.$this->q($path).',bot='.$this->q($bot).',hash='.$this->q($hash).',date_added=NOW(),date_modified=NOW() ON DUPLICATE KEY UPDATE hits=hits+1,date_modified=NOW()');
        $this->db->query('DELETE FROM '.$this->table('wsseo_log').' WHERE date_modified<DATE_SUB(NOW(),INTERVAL '.max(1,min(90,$days)).' DAY) LIMIT 100');
    }
    public function reports(int $store,int $lang): array {
        return ['logs'=>$this->db->query('SELECT kind,path,bot,hits,date_modified FROM '.$this->table('wsseo_log')." WHERE store_id=$store ORDER BY date_modified DESC LIMIT 100")->rows,
            'history'=>$this->db->query('SELECT history_id,`type`,entity_id,date_added,restored FROM '.$this->table('wsseo_history')." WHERE store_id=$store AND language_id=$lang ORDER BY history_id DESC LIMIT 100")->rows,
            'ai'=>$this->db->query('SELECT model,status,COUNT(*) AS requests,SUM(tokens) AS tokens FROM '.$this->table('wsseo_ai_usage')." WHERE store_id=$store AND date_added>=CURDATE() GROUP BY model,status")->rows];
    }
    public function reserveAi(int $store,int $user,string $model,int $limit): int {
        $lock='wsseo_ai_'.substr(hash('sha256',DB_PREFIX.':'.$store),0,35);
        if (!(int)$this->db->query('SELECT GET_LOCK('.$this->q($lock).',5) AS acquired')->row['acquired']) throw new \RuntimeException('AI service busy.');
        try {
            $n=(int)$this->db->query('SELECT COUNT(*) AS n FROM '.$this->table('wsseo_ai_usage')." WHERE store_id=$store AND date_added>=CURDATE()")->row['n'];
            if ($n >= $limit) throw new \RuntimeException('Daily AI request limit reached.');
            $this->db->query('INSERT INTO '.$this->table('wsseo_ai_usage')." SET store_id=$store,user_id=$user,model=".$this->q($model).",status='pending',date_added=NOW()");
            return (int)$this->db->getLastId();
        } finally { $this->db->query('SELECT RELEASE_LOCK('.$this->q($lock).')'); }
    }
    public function finishAi(int $id,string $status,int $tokens): void { $this->db->query('UPDATE '.$this->table('wsseo_ai_usage').' SET status='.$this->q($status).',tokens='.max(0,$tokens)." WHERE usage_id=$id"); }
}


