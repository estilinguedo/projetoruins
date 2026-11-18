<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
require dirname(__DIR__).'/autoload.php';
use app\core\utils\{Config,PasswordSecurity};
use app\core\database\DBConnection;
function requisitos(): void {
    if(PHP_MAJOR_VERSION!==8 || PHP_MINOR_VERSION!==3) throw new RuntimeException('É necessário PHP 8.3.');
    foreach(['pdo_mysql','mbstring','fileinfo','openssl','json'] as $extension) if(!extension_loaded($extension)) throw new RuntimeException('Extensão PHP ausente: '.$extension);
}
function banco(): PDO {
    if(Config::get('database.dbname')!=='plataforma_jogadores_escola') throw new RuntimeException('Este preparador só atua no banco separado plataforma_jogadores_escola.');
    return new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4',Config::get('database.host'),Config::get('database.port',3306)),Config::get('database.user'),Config::get('database.password'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
}
try {
    requisitos();
    $mode=$argv[1]??'';
    $root=dirname(__DIR__);
    if($mode==='requisitos') {echo "OK: PHP 8.3 e extensões de linha de comando.\n";exit;}
    if($mode==='configurar') {
        $path=$root.'/app/etc/config.json';$c=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
        $c['database']=array_merge($c['database'],['host'=>'127.0.0.1','port'=>3306,'dbname'=>'plataforma_jogadores_escola','user'=>'root','password'=>'senha']);
        $c['app']['url']='http://localhost/PlataformaJogadoresEscola';$c['app']['env']='production';$c['storage']['path']='storage';$c['game']['allow_local_http']=true;
        if(file_put_contents($path,json_encode($c,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n",LOCK_EX)===false) throw new RuntimeException('Não foi possível salvar a configuração.');
        echo "OK: configuração escolar salva; SMTP preservado.\n";exit;
    }
    if($mode==='banco') {
        $db=banco();$db->exec('CREATE DATABASE IF NOT EXISTS plataforma_jogadores_escola CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $db->exec('USE plataforma_jogadores_escola');
        $tables=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        if(!$tables) {$db->exec(file_get_contents($root.'/app/core/database/database.sql'));echo "OK: estrutura do banco criada.\n";}
        else {
            foreach(['usuarios','tokensconta','fotosperfil','acoesmoderacao','leaderboardresultados'] as $table) if(!in_array($table,$tables,true)) throw new RuntimeException('Banco existente com estrutura diferente. Nada foi apagado. Tabela esperada: '.$table);
            $db->exec(file_get_contents($root.'/app/core/database/migrations/001-godot.sql'));
            echo "OK: banco existente preservado e tabelas do jogo conferidas.\n";
        }
        exit;
    }
    if($mode==='demonstracao') {
        banco();$db=DBConnection::getConnection();$file=$root.'/CONTAS-DEMONSTRACAO.txt';
        $created=[];$db->beginTransaction();
        try {
            foreach(['jogador'=>'usuario','moderador'=>'moderador'] as $label=>$role) {
                $name='demo_'.$label;$email=$name.'@example.test';
                $s=$db->prepare('SELECT idUsuario FROM usuarios WHERE email=? OR nomeUsuario=?');$s->execute([$email,$name]);
                if($s->fetch()) {echo 'Conta '.$name." já existe; senha e papel preservados.\n";continue;}
                $password=bin2hex(random_bytes(8));
                $s=$db->prepare("INSERT INTO usuarios(nomeUsuario,email,senha,descricao,ativacao,papel,dataAtivacao) VALUES(?,?,?,?,'S',?,UTC_TIMESTAMP())");
                $s->execute([$name,$email,PasswordSecurity::hash($password),'Conta de demonstração da apresentação escolar.',$role]);
                $created[]=$label."\nE-mail: ".$email."\nSenha: ".$password."\n";
            }
            if($created) {
                $old=is_file($file)?file_get_contents($file):"CONTAS LOCAIS DE DEMONSTRAÇÃO — NÃO PUBLICAR\n\n";
                if(file_put_contents($file,$old.implode("\n",$created)."\n",LOCK_EX)===false) throw new RuntimeException('Não foi possível salvar as senhas de demonstração.');
                chmod($file,0600);
            }
            $db->commit();
        } catch(Throwable $e) {if($db->inTransaction()) $db->rollBack();throw $e;}
        echo "OK: contas de demonstração preparadas. Cadastros comuns continuam exigindo confirmação por e-mail.\n";exit;
    }
    if($mode==='verificar') {
        $db=DBConnection::getConnection();
        foreach(['usuarios','tokensconta','fotosperfil','acoesmoderacao','leaderboardresultados','jogoacessos','jogocontrole'] as $table) $db->query('SELECT 1 FROM '.$table.' LIMIT 1');
        foreach(['photos','mail','limits'] as $dir) if(!is_writable($root.'/storage/'.$dir)) throw new RuntimeException('Sem permissão de gravação em storage/'.$dir);
        if(Config::get('mail.transport')!=='smtp'||!Config::get('mail.user')||!Config::get('mail.password')) throw new RuntimeException('Configuração SMTP incompleta.');
        echo "OK: banco, tabelas, gravação de arquivos e campos SMTP. O envio real deve ser conferido pelo Mailtrap.\n";exit;
    }
    throw new RuntimeException('Modo inválido.');
} catch(PDOException $e) {
    fwrite(STDERR,"Falha no banco (SQLSTATE ".$e->getCode()."). Confira serviço, estrutura e acesso TCP root/senha. Se root usa unix_socket, peça ao professor a configuração da conta; este script não altera a autenticação do MariaDB.\n");exit(1);
} catch(Throwable $e) {fwrite(STDERR,'Falha: '.$e->getMessage()."\n");exit(1);}
