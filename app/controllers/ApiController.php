<?php
declare(strict_types=1);
namespace app\controllers;
use app\core\utils\{Web,HttpError,RateLimit};
use app\models\{Usuario,Account,Profile,Leaderboard,Moderation};
final class ApiController {
    public function __construct(private ?array $user) {}
    private function id(): int { return (int)$this->user['idUsuario']; }
    private function page(): int { return max(1,min(100000,(int)(is_scalar($_GET['pagina']??1)?($_GET['pagina']??1):1))); }
    private function paged(array $rows): array { return ['rows'=>array_slice($rows,0,50),'page'=>$this->page(),'hasNext'=>count($rows)>50]; }
    private function limit(string $action,int $max): void {
        RateLimit::check($action.':ip:'.($_SERVER['REMOTE_ADDR']??''),$max);
        RateLimit::check($action.':email:'.strtolower(Web::input('email')),$max);
    }
    public function session(): array { return ['user'=>$this->user ? array_intersect_key($this->user,array_flip(['idUsuario','nomeUsuario','papel'])) : null]; }
    public function login(): array { $this->limit('login',10); Web::login(Account::authenticate(strtolower(Web::input('email')),Web::input('senha',false))); return ['message'=>'Login realizado.']; }
    public function logout(): array { Web::logout();return ['message'=>'Você saiu da conta.']; }
    public function register(): array {
        $this->limit('cadastro',5);$email=strtolower(Web::input('email'));
        $id=Account::register(Web::input('nomeUsuario'),$email,Web::input('senha',false),Web::input('confirmacao',false));
        try { Account::sendActivation($id,$email); } catch(\Throwable $e) { error_log($e->getMessage());throw new HttpError(503,'Conta criada, mas o envio falhou. Confira o envio de e-mail e use Reenviar confirmação.'); }
        return ['message'=>'Conta criada. Abra a mensagem no Mailtrap e confirme seu e-mail antes de entrar.'];
    }
    public function confirm(): array { Account::consume(Web::input('token'),'ativacao');return ['message'=>'E-mail confirmado. Você já pode entrar.']; }
    private function link(string $type): array {
        $this->limit('link-'.$type,5);$email=strtolower(Web::input('email'));
        if(strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new HttpError(422,'Informe um e-mail válido.');
        try { Account::sendLink($email,$type); } catch(\Throwable $e) { error_log($e->getMessage());throw new HttpError(503,'Não foi possível enviar ao Mailtrap. Confira a configuração de envio e tente novamente.'); }
        return ['message'=>'Se houver uma conta elegível, a mensagem estará no Mailtrap → My Sandbox.'];
    }
    public function resend(): array { return $this->link('ativacao'); }
    public function forgot(): array { return $this->link('senha'); }
    public function reset(): array {
        Account::validPassword(Web::input('senha',false),Web::input('confirmacao',false));
        Account::consume(Web::input('token'),'senha',Web::input('senha',false)); Web::logout();
        return ['message'=>'Senha alterada. Entre novamente com a nova senha.'];
    }
    public function leaderboard(): array { return $this->paged(Leaderboard::list(($this->page()-1)*50)); }
    public function profile(): array {
        $id=isset($_GET['id'])?Web::id($_GET['id']):$this->id();$u=(new Usuario())->publicProfile($id);
        if(!$u) throw new HttpError(404,'Perfil não encontrado.');
        return ['profile'=>$u,'own'=>$id===$this->id(),'ranking'=>Leaderboard::forUser($id)];
    }
    public function description(): array { Profile::description($this->id(),Web::input('descricao'));return ['message'=>'Descrição atualizada.']; }
    public function upload(): array { Profile::upload($this->id(),Web::input('foto',false));return ['message'=>'Foto enviada.']; }
    public function photo(): array { return ['image'=>Profile::image(Web::id($_GET['id']??null))]; }
    public function moderation(): array {
        $search=is_string($_GET['busca']??null)?mb_substr(trim($_GET['busca']),0,30):'';
        return $this->paged((new Usuario())->listActive($search,($this->page()-1)*50));
    }
    public function preview(): array {
        $id=Web::id($_GET['id']??null);$type=is_string($_GET['tipo']??null)?$_GET['tipo']:'';
        if(!isset(Moderation::TYPES[$type])) throw new HttpError(422,'Conteúdo inválido.');
        $u=(new Usuario())->publicProfile($id);if(!$u) throw new HttpError(404,'Perfil não encontrado.');
        $content=$type==='foto'?(string)($u['idFoto']??''):$u['descricao'];
        if($content==='') throw new HttpError(409,'Este conteúdo já está vazio.');
        return ['profile'=>$u,'type'=>$type,'expected'=>hash('sha256',$content)];
    }
    public function remove(): array { Moderation::remove($this->id(),Web::id(Web::input('id')),Web::input('tipo'),Web::input('motivo'),Web::input('expected'));return ['message'=>'Conteúdo removido.']; }
    public function history(): array { return $this->paged(Moderation::history(($this->page()-1)*50)); }
}
