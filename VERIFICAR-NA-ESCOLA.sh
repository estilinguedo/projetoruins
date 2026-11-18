#!/usr/bin/env bash
set -Eeuo pipefail
unset ZTF_CONFIG
raiz="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
url=http://localhost/PlataformaJogadoresEscola
[[ "$(uname -s)" == Linux ]] || { echo 'Execute no Ubuntu.'; exit 1; }
[[ $EUID -eq 0 ]] || { echo 'Use sudo bash VERIFICAR-NA-ESCOLA.sh'; exit 1; }
runuser -u www-data -- php8.3 "$raiz/scripts/preparar-escola.php" verificar
arquivo="$(mktemp "$raiz/public/verificacao-XXXXXXXX.php")"
trap 'rm -f -- "$arquivo"' EXIT
cat > "$arquivo" <<'PHP'
<?php
if(!in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)) {http_response_code(404);exit;}
header('Content-Type: application/json');
echo json_encode(['php'=>PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,'mysql'=>extension_loaded('pdo_mysql'),'mbstring'=>extension_loaded('mbstring'),'fileinfo'=>extension_loaded('fileinfo'),'openssl'=>extension_loaded('openssl')]);
PHP
chmod 644 "$arquivo"
curl --noproxy '*' -fsS --max-time 15 "$url/$(basename "$arquivo")" | php8.3 -r '
$r=json_decode(stream_get_contents(STDIN),true);
if(!is_array($r)||($r["php"]??"")!=="8.3"||empty($r["mysql"])||empty($r["mbstring"])||empty($r["fileinfo"])||empty($r["openssl"])) {fwrite(STDERR,"FALHA: confira PHP 8.3 e extensões no Apache.\n");exit(1);}
echo "OK: PHP 8.3 e extensões executando pelo Apache.\n";'
curl --noproxy '*' -fsS --max-time 15 "$url/api/session" | php8.3 -r '
$r=json_decode(stream_get_contents(STDIN),true);
if(!is_array($r)||($r["ok"]??false)!==true||empty($r["csrf"])) {fwrite(STDERR,"FALHA: a API não respondeu JSON. Confira rewrite e Alias.\n");exit(1);}echo "OK: rotas e resposta JSON.\n";'
status="$(curl --noproxy '*' -sS --max-time 15 -o /dev/null -w '%{http_code}' "$url/api/leaderboard")"
[[ "$status" == 401 ]] || { echo "FALHA: ranking sem login retornou $status; esperado 401."; exit 1; }
curl --noproxy '*' -fsS --max-time 15 -o /dev/null "$url/login.html"
echo 'OK: página acessível e ranking protegido por login.'
echo 'Conferência automática concluída. Teste login e Mailtrap pelo navegador.'
