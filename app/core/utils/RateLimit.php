<?php
namespace app\core\utils;
final class RateLimit {
    public static function check(string $key,int $limit,int $seconds=900): void {
        $file=Web::storage('limits').'/'.hash('sha256',$key).'.json';
        $h=fopen($file,'c+');
        if(!$h || !flock($h,LOCK_EX)) throw new \RuntimeException('Não foi possível verificar o limite de tentativas.');
        try {
            $data=json_decode(stream_get_contents($h),true) ?: [];
            $data=array_values(array_filter($data,fn($t)=>$t>time()-$seconds));
            if(count($data)>=$limit) throw new HttpError(429,'Muitas tentativas. Aguarde 15 minutos antes de tentar novamente.');
            $data[]=time(); rewind($h); ftruncate($h,0); fwrite($h,json_encode($data));
        } finally { flock($h,LOCK_UN); fclose($h); }
    }
}
