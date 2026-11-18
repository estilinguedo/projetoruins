<?php
declare(strict_types=1);
namespace app\core\utils;
use PHPMailer\PHPMailer\PHPMailer;

final class Mail {
    public static function isSandbox(): bool {
        return Config::get('mail.transport')==='smtp'
            && strtolower((string)Config::get('mail.host'))==='sandbox.smtp.mailtrap.io';
    }

    public static function accountLink(string $to,string $type,string $token): void {
        if(!in_array($type,['ativacao','senha'],true)) throw new \InvalidArgumentException('Tipo de mensagem inválido.');
        $activation=$type==='ativacao';
        $subject=$activation ? 'Confirme seu e-mail' : 'Redefina sua senha';
        $action=$activation ? 'Confirmar meu e-mail' : 'Definir nova senha';
        $validity=$activation ? '24 horas' : '1 hora';
        $url=Web::url(($activation?'confirmar-email.html':'nova-senha.html').'?token='.$token);
        $body=$subject."\n\nAcesse: ".$url."\n\nValidade: ".$validity.".\nSe você não fez esta solicitação, ignore esta mensagem.";
        if(Config::get('mail.transport')==='log') {
            if(Config::get('app.env')!=='development') throw new \RuntimeException('E-mail de teste não permitido neste ambiente.');
            $file=Web::storage('mail').'/'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(8)).'.json';
            if(file_put_contents($file,json_encode(['to'=>$to,'subject'=>$subject,'body'=>$body,'url'=>$url],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX)===false) throw new \RuntimeException('Não foi possível gravar o e-mail de teste.');
            return;
        }
        if(Config::get('mail.transport')!=='smtp' || !class_exists(PHPMailer::class)) throw new \RuntimeException('Configure SMTP e instale as dependências do Composer.');
        $user=(string)Config::get('mail.user');
        $password=(string)Config::get('mail.password');
        if($user==='' || $password==='' || (self::isSandbox() && str_contains($password,'****'))) {
            throw new \RuntimeException('Preencha as credenciais SMTP completas em app/etc/config.json; a senha mascarada do print não funciona.');
        }
        $mail=new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host=(string)Config::get('mail.host');
        $mail->Port=(int)Config::get('mail.port',2525);
        $mail->SMTPAuth=true;
        $mail->Username=$user;
        $mail->Password=$password;
        $mail->SMTPSecure=(string)Config::get('mail.encryption','tls');
        $mail->SMTPDebug=0;
        $mail->Timeout=20;
        $mail->CharSet='UTF-8';
        $mail->setFrom((string)Config::get('mail.from_email'),(string)Config::get('mail.from_name'));
        $mail->addAddress($to);
        $mail->Subject=$subject;
        $mail->isHTML(false);
        $mail->Body=$body;
        $mail->send();
    }
}
