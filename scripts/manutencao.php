<?php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/autoload.php';
use app\core\utils\Web;
use app\core\database\DBConnection;
$db=DBConnection::getConnection();
$db->exec('DELETE FROM jogoacessos WHERE expiraEm <= UTC_TIMESTAMP()');
$db->exec('DELETE FROM tokensconta WHERE expiraEm < UTC_TIMESTAMP() - INTERVAL 7 DAY');
$kept=array_flip($db->query('SELECT nomeArmazenado FROM fotosperfil')->fetchAll(PDO::FETCH_COLUMN));
$count=0;
foreach(glob(Web::storage('photos').'/*') as $path) {
    if(is_file($path) && !isset($kept[basename($path)]) && filemtime($path)<time()-86400) { unlink($path); $count++; }
}
foreach(['mail'=>86400*7,'limits'=>86400] as $folder=>$age) foreach(glob(Web::storage($folder).'/*.json') as $path) {
    if(is_file($path) && filemtime($path)<time()-$age) unlink($path);
}
echo "ManutenÃ§Ã£o concluÃ­da. Fotos Ã³rfÃ£s removidas: $count\n";
