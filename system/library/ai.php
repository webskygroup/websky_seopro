<?php
namespace Opencart\System\Library\Extension\WebskySeo;

final class AI {
    private string $key;
    private string $endpoint;
    private string $model;
    public function __construct(string $key,string $endpoint,string $model) {
        $this->key=trim($key); $this->endpoint=rtrim(trim($endpoint),'/'); $this->model=trim($model);
    }
    public function generate(array $context,string $instructions=''): array {
        if ($this->key==='') throw new \RuntimeException('OpenAI API key is not configured.');
        if (!function_exists('curl_init')) throw new \RuntimeException('PHP cURL is required for the OpenAI connection.');
        $schema=['type'=>'object','additionalProperties'=>false,'properties'=>[
            'meta_title'=>['type'=>'string'],'meta_description'=>['type'=>'string'],'meta_keyword'=>['type'=>'string'],
            'tag'=>['type'=>'string'],'h1'=>['type'=>'string'],'h2'=>['type'=>'string'],'image_alt'=>['type'=>'string'],
            'image_title'=>['type'=>'string'],'robots'=>['type'=>'string'],'description'=>['type'=>'string']
        ],'required'=>['meta_title','meta_description','meta_keyword','tag','h1','h2','image_alt','image_title','robots','description']];
        $payload=['model'=>$this->model,'store'=>false,
            'instructions'=>trim(($instructions?:'You are an expert technical SEO editor for an OpenCart store. Preserve factual product details, avoid keyword stuffing, write in the requested language, and return only the requested JSON.').' Return valid JSON matching this schema. Use robots exactly index,follow unless the page should be hidden.'),
            'input'=>Text::json(['task'=>'Generate complete SEO fields','entity'=>$context]),
            'text'=>['format'=>['type'=>'json_schema','name'=>'seo_fields','strict'=>true,'schema'=>$schema]]];
        $ch=curl_init($this->endpoint);
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>60,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$this->key],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
        $raw=curl_exec($ch); $error=curl_error($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        if ($raw===false || $error) throw new \RuntimeException('OpenAI request failed: '.($error?:'network error'));
        $response=json_decode($raw,true);
        if ($status<200 || $status>=300) throw new \RuntimeException('OpenAI returned HTTP '.$status.': '.Text::trim((string)($response['error']['message']??'unknown error'),300));
        $text='';
        if (isset($response['output_text']) && is_string($response['output_text'])) $text=$response['output_text'];
        foreach (($response['output']??[]) as $item) foreach (($item['content']??[]) as $part) if (($part['type']??'')==='output_text') $text.=(string)($part['text']??'');
        $text=trim($text);
        if ($text==='') throw new \RuntimeException('OpenAI returned an empty response.');
        $text=preg_replace('/^```(?:json)?\s*|\s*```$/i','',$text)??$text;
        $data=json_decode($text,true);
        if (!is_array($data)) throw new \RuntimeException('OpenAI returned invalid JSON.');
        foreach ($schema['required'] as $field) $data[$field]=is_string($data[$field]??null)?$data[$field]:'';
        $data['robots']=in_array($data['robots'],['index,follow','noindex,follow','noindex,nofollow'],true)?$data['robots']:'index,follow';
        $usage=(int)($response['usage']['total_tokens']??0);
        return ['data'=>$data,'tokens'=>$usage,'response_id'=>(string)($response['id']??'')];
    }
}


