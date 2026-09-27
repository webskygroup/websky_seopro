<?php
namespace Opencart\System\Library\Extension\WebskySeo;

final class Secrets {
    public static function encrypt(string $value,string $secret): string {
        if ($secret==='') throw new \RuntimeException('Configure WEBSKY_SEO_SECRET or the OpenCart encryption key first.');
        $iv=random_bytes(12);$tag='';
        $encrypted=openssl_encrypt($value,'aes-256-gcm',hash('sha256',$secret,true),OPENSSL_RAW_DATA,$iv,$tag);
        if($encrypted===false)throw new \RuntimeException('Cannot encrypt API key.');
        return base64_encode($iv.$tag.$encrypted);
    }
    public static function decrypt(string $value,string $secret): string {
        if($value==='')return '';
        $raw=base64_decode($value,true);
        if($raw===false||strlen($raw)<29||$secret==='')throw new \RuntimeException('Cannot decrypt API key. Save the key again.');
        $result=openssl_decrypt(substr($raw,28),'aes-256-gcm',hash('sha256',$secret,true),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
        if($result===false)throw new \RuntimeException('Cannot decrypt API key. Save the key again.');
        return $result;
    }
}


