<?php
namespace Opencart\System\Library\Extension\WebskySeo;

final class Content {
    /** Only alters text nodes; never links inside anchors, code, scripts, headings or existing tooltips. */
    public static function links(string $html,array $rules): string {
        if (!$rules || trim($html)==='') return $html;
        $dom=new \DOMDocument('1.0','UTF-8'); $previous=libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="wsseo-content">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        $xpath=new \DOMXPath($dom); $used=[];
        $nodes=iterator_to_array($xpath->query('//text()[not(ancestor::a or ancestor::script or ancestor::style or ancestor::textarea or ancestor::code or ancestor::h1 or ancestor::h2 or ancestor::abbr)]'));
        foreach($nodes as $node) {
            foreach($rules as $i=>$rule) {
                if(isset($used[$i])||empty($rule['keyword']))continue;
                $pattern='/(?<![\p{L}\p{N}])'.preg_quote($rule['keyword'],'/').'(?![\p{L}\p{N}])/iu';
                if(!preg_match($pattern,$node->nodeValue,$m,PREG_OFFSET_CAPTURE))continue;
                [$word,$pos]=$m[0]; $value=$node->nodeValue; $parent=$node->parentNode;
                $before=$dom->createTextNode(substr($value,0,$pos)); $after=$dom->createTextNode(substr($value,$pos+strlen($word)));
                $link=$dom->createElement(empty($rule['target'])?'abbr':'a'); $link->appendChild($dom->createTextNode($word));
                if(!empty($rule['target']))$link->setAttribute('href',$rule['target']);
                if(!empty($rule['tooltip']))$link->setAttribute('title',$rule['tooltip']);
                $parent->insertBefore($before,$node);$parent->insertBefore($link,$node);$parent->insertBefore($after,$node);$parent->removeChild($node);$node=$after;$used[$i]=true;
                if(count($used)>=10)break 2;
            }
        }
        $wrapper=$dom->getElementById('wsseo-content'); if(!$wrapper)return $html;
        $out='';foreach($wrapper->childNodes as $child)$out.=$dom->saveHTML($child);return $out;
    }
    public static function generate(array $entity,array $settings,array $fields): array {
        $map=['meta_title'=>'title_template','meta_description'=>'description_template','meta_keyword'=>'keyword_template','h1'=>'h1_template','h2'=>'h2_template','image_alt'=>'image_alt_template','image_title'=>'image_title_template'];$out=[];
        foreach($fields as $field) {
            if(isset($map[$field])) $out[$field]=Text::trim(Text::render($settings[$map[$field]],$entity),$field==='meta_title'?$settings['trim_title']:($field==='meta_description'?$settings['trim_description']:255));
            elseif($field==='tag')$out[$field]=Text::keywords(($entity['name']??'').' '.($entity['brand']??'').' '.($entity['category']??''));
            elseif($field==='robots')$out[$field]=$settings['robots_default'];
        }
        return $out;
    }
}


