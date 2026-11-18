<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
require dirname(__DIR__).'/autoload.php';
use app\core\database\DBConnection;
// Executar após importar um banco antigo, com o site sem usuários conectados.
// Não exclui registros. Preserva os nomes das colunas e as chaves estrangeiras.
try {
    $db=DBConnection::getConnection();
    $tables=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $changes=[];
    foreach(['usuarios','tokensconta','fotosperfil','acoesmoderacao','leaderboardresultados'] as $target) {
        $matches=array_values(array_filter($tables,fn($name)=>strtolower($name)===$target));
        if(count($matches)!==1) throw new RuntimeException('Tabela ausente ou duplicada: '.$target.'. Confira o banco selecionado.');
        if($matches[0]!==$target) $changes[]='`'.$matches[0].'` TO `'.$target.'`';
    }
    if($changes) $db->exec('RENAME TABLE '.implode(', ',$changes));
    echo 'Nomes de tabelas conferidos. Tabelas ajustadas: '.count($changes).PHP_EOL;
} catch(Throwable $e) {fwrite(STDERR,'Falha: '.$e->getMessage().PHP_EOL);exit(1);}
