<?php
declare(strict_types=1);
namespace app\models;
use app\core\database\DBConnection;
use app\core\utils\{HttpError,Web,Config};
final class Profile {
    public static function description(int $id,string $text): void {
        if(mb_strlen($text,'UTF-8')>200) throw new HttpError(422,'A descrição pode ter no máximo 200 caracteres.');
        $s=DBConnection::getConnection()->prepare('UPDATE usuarios SET descricao=? WHERE idUsuario=?'); $s->execute([$text,$id]);
    }
    public static function upload(int $id,string $encoded): void {
        if(strlen($encoded)>7000000) throw new HttpError(422,'A imagem deve ter até 5 MB.');
        $bytes=base64_decode($encoded,true);
        if($bytes===false || strlen($bytes)<1 || strlen($bytes)>5242880) throw new HttpError(422,'Imagem inválida ou maior que 5 MB.');
        $size=strlen($bytes); $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $image=@getimagesizefromstring($bytes);
        if(!in_array($mime,['image/png','image/jpeg'],true) || !$image || $image['mime']!==$mime || $image[0]<1 || $image[1]<1 || $image[0]*$image[1]>20000000) throw new HttpError(422,'Envie PNG ou JPEG válido, com até 20 milhões de pixels.');
        $name=bin2hex(random_bytes(24)).($mime==='image/png'?'.png':'.jpg'); $path=Web::storage('photos').'/'.$name;
        $db=DBConnection::getConnection(); $db->beginTransaction();
        try {
            $s=$db->prepare('SELECT idUsuario FROM usuarios WHERE idUsuario=? FOR UPDATE'); $s->execute([$id]);
            $s=$db->prepare('SELECT idFoto FROM fotosperfil WHERE idUsuario=?'); $s->execute([$id]);
            if($s->fetch()) throw new HttpError(409,'Você já possui uma foto. Um novo envio só é permitido quando o perfil estiver sem foto.');
            if(file_put_contents($path,$bytes,LOCK_EX)!==$size) throw new \RuntimeException('Falha ao armazenar a foto.');
            $s=$db->prepare('INSERT INTO fotosperfil (idUsuario,nomeArmazenado,mimeType,tamanhoBytes) VALUES (?,?,?,?)'); $s->execute([$id,$name,$mime,$size]);
            $db->commit();
        } catch(\Throwable $e) { if($db->inTransaction()) $db->rollBack(); if(is_file($path)) unlink($path); throw $e; }
    }
    public static function image(int $id): string {
        $photo=self::photo($id);
        if(!$photo) throw new HttpError(404,'Foto não encontrada.');
        $path=Web::storage('photos').'/'.basename($photo['nomeArmazenado']);
        if(!is_file($path)) throw new HttpError(404,'Foto não encontrada.');
        return 'data:'.$photo['mimeType'].';base64,'.base64_encode(file_get_contents($path));
    }
    public static function photo(int $userId): ?array {
        $s=DBConnection::getConnection()->prepare("SELECT f.* FROM fotosperfil f JOIN usuarios u ON u.idUsuario=f.idUsuario WHERE f.idUsuario=? AND u.ativacao='S'");
        $s->execute([$userId]); return $s->fetch() ?: null;
    }
}
