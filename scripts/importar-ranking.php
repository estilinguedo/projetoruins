<?php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/autoload.php';
if($argc!==2) { fwrite(STDERR,"Uso: php scripts/importar-ranking.php caminho/resultados.json\n"); exit(1); }
try {
    $rows=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
    if(!is_array($rows) || array_keys($rows)!==range(0,count($rows)-1) && $rows!==[]) throw new RuntimeException('Uma lista JSON Ã© obrigatÃ³ria.');
    app\models\Leaderboard::publicarResultados($rows);
    echo "ClassificaÃ§Ã£o importada: ".count($rows)." jogadores.\n";
} catch(Throwable $e) { fwrite(STDERR,'Falha: '.$e->getMessage()."\n"); exit(1); }
