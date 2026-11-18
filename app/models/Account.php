<?php
declare(strict_types=1);
namespace app\models;
use app\core\database\DBConnection;
use app\core\utils\{HttpError,PasswordSecurity,Mail};
use app\models\Usuario;
use PDOException;
final class Account {
    public static function sendLink(string $email,string $type): void {
        $u=(new Usuario())->byEmail($email);
        if($u && ($type==='senha' || $u['ativacao']!=='S')) Mail::accountLink($email,$type,self::issue((int)$u['idUsuario'],$type));
    }
    public static function sendActivation(int $id,string $email): void { Mail::accountLink($email,'ativacao',self::issue($id,'ativacao')); }
    public static function authenticate(string $email,string $password): array {
        $u=(new Usuario())->byEmail($email);
        $dummy='$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        if(strlen($password)>1024 || !PasswordSecurity::verify($password,$u['senha']??$dummy) || !$u) throw new HttpError(422,'E-mail ou senha incorretos.');
        if($u['ativacao']!=='S') throw new HttpError(403,'Confirme seu e-mail pelo link no Mailtrap antes de entrar.');
        return $u;
    }
    public static function validPassword(string $password,string $confirm): void {
        if(mb_strlen($password)<8 || mb_strlen($password)>128) throw new HttpError(422,'A senha deve ter entre 8 e 128 caracteres.');
        if($password!==$confirm) throw new HttpError(422,'As senhas não conferem.');
    }
    public static function register(string $name,string $email,string $password,string $confirm): int {
        if(!preg_match('/^[A-Za-z0-9_]{3,30}$/D',$name)) throw new HttpError(422,'Use de 3 a 30 letras, números ou sublinhados no nome de usuário.');
        if(strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new HttpError(422,'Informe um e-mail válido.');
        self::validPassword($password,$confirm);
        try { return (int)(new Usuario())->insert(['nomeUsuario'=>$name,'email'=>strtolower($email),'senha'=>PasswordSecurity::hash($password)]); }
        catch(PDOException $e) { if(($e->errorInfo[1]??0)===1062) throw new HttpError(409,'Nome de usuário ou e-mail já cadastrado. Entre na conta ou solicite a recuperação.'); throw $e; }
    }
    public static function issue(int $id,string $type): string {
        if(!in_array($type,['ativacao','senha'],true)) throw new \InvalidArgumentException('Tipo de token inválido.');
        $raw=bin2hex(random_bytes(32)); $db=DBConnection::getConnection();
        // Mais de um link pode existir, mas usar um invalida TODOS do mesmo tipo.
        // Assim um pedido indevido de reenvio não invalida o e-mail já recebido.
        $s=$db->prepare('INSERT INTO tokensconta (idUsuario,tipo,tokenHash,expiraEm) VALUES (?,?,?,?)');
        $s->execute([$id,$type,hash('sha256',$raw),gmdate('Y-m-d H:i:s',time()+($type==='ativacao'?86400:3600))]);
        return $raw;
    }
    public static function validToken(string $raw,string $type): bool {
        if(!preg_match('/^[a-f0-9]{64}$/D',$raw)) return false;
        $s=DBConnection::getConnection()->prepare('SELECT idToken FROM tokensconta WHERE tokenHash=? AND tipo=? AND usadoEm IS NULL AND expiraEm>UTC_TIMESTAMP()');
        $s->execute([hash('sha256',$raw),$type]); return (bool)$s->fetch();
    }
    public static function consume(string $raw,string $type,?string $password=null): void {
        if(!preg_match('/^[a-f0-9]{64}$/D',$raw)) throw new HttpError(400,'Link inválido ou expirado. Solicite outro link.');
        $db=DBConnection::getConnection(); $db->beginTransaction();
        try {
            $s=$db->prepare('SELECT idUsuario FROM tokensconta WHERE tokenHash=? AND tipo=?');
            $s->execute([hash('sha256',$raw),$type]); $id=$s->fetchColumn();
            if(!$id) throw new HttpError(400,'Link inválido ou expirado. Solicite outro link.');
            // Serializa ativações/resets da mesma conta e impede consumo concorrente.
            $s=$db->prepare('SELECT idUsuario FROM usuarios WHERE idUsuario=? FOR UPDATE'); $s->execute([$id]);
            $s=$db->prepare('SELECT idToken FROM tokensconta WHERE tokenHash=? AND tipo=? AND usadoEm IS NULL AND expiraEm>UTC_TIMESTAMP() FOR UPDATE');
            $s->execute([hash('sha256',$raw),$type]);
            if(!$s->fetch()) throw new HttpError(400,'Link inválido, já utilizado ou expirado. Solicite outro link.');
            if($type==='ativacao') { $s=$db->prepare("UPDATE usuarios SET ativacao='S', dataAtivacao=COALESCE(dataAtivacao,UTC_TIMESTAMP()) WHERE idUsuario=?"); $s->execute([$id]); }
            elseif($type==='senha' && $password!==null) { $s=$db->prepare('UPDATE usuarios SET senha=?,authVersion=authVersion+1 WHERE idUsuario=?'); $s->execute([PasswordSecurity::hash($password),$id]); }
            else throw new \InvalidArgumentException('Operação inválida.');
            $s=$db->prepare('UPDATE tokensconta SET usadoEm=UTC_TIMESTAMP() WHERE idUsuario=? AND tipo=? AND usadoEm IS NULL'); $s->execute([$id,$type]);
            $db->commit();
        } catch(\Throwable $e) { if($db->inTransaction()) $db->rollBack(); throw $e; }
    }
}
