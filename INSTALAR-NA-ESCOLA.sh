#!/usr/bin/env bash
set -Eeuo pipefail
export LC_ALL=C.UTF-8
unset ZTF_CONFIG
origem="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
destino=/var/www/PlataformaJogadoresEscola
conf=/etc/apache2/conf-available/plataforma-jogadores-escola.conf
demo=0
dependencias=0
for argumento in "$@"; do
  case "$argumento" in
    --com-demonstracao) demo=1 ;;
    --instalar-dependencias) dependencias=1 ;;
    *) echo "Opção desconhecida: $argumento"; exit 1 ;;
  esac
done
falha() { echo; echo "Não foi possível concluir: $*" >&2; exit 1; }
[[ "$(uname -s)" == Linux ]] || falha 'Este instalador é somente para Ubuntu Linux.'
[[ $EUID -eq 0 ]] || falha 'Execute com sudo: sudo bash INSTALAR-NA-ESCOLA.sh --com-demonstracao'
[[ -f /etc/os-release ]] || falha 'Sistema não identificado.'
. /etc/os-release
[[ "$ID" == ubuntu ]] || falha 'Este instalador foi preparado para Ubuntu.'
if (( dependencias )); then
  [[ "$VERSION_ID" == 24.04 ]] || falha 'A instalação automática de pacotes é para Ubuntu 24.04. Em outras versões, peça PHP 8.3 ao professor.'
  apt-get update
  apt-get install -y apache2 mariadb-server php8.3-cli libapache2-mod-php8.3 php8.3-mysql php8.3-mbstring php8.3-xml curl
fi
for programa in php8.3 apache2ctl a2enmod a2enconf curl runuser systemctl; do
  command -v "$programa" >/dev/null || falha "Falta $programa. Veja COMECE-AQUI.md."
done
php8.3 "$origem/scripts/preparar-escola.php" requisitos
[[ -f /etc/apache2/mods-available/php8.3.load ]] || falha 'Falta libapache2-mod-php8.3.'
for modulo in /etc/apache2/mods-enabled/php*.load; do
  [[ ! -e "$modulo" || "$(basename "$modulo")" == php8.3.load ]] || falha 'O Apache já usa outra versão de PHP. Peça ao professor para configurar PHP 8.3; o instalador não troca a versão dos outros sites.'
done
if [[ -e /etc/apache2/mods-enabled/mpm_event.load || -e /etc/apache2/mods-enabled/mpm_worker.load ]]; then
  falha 'O Apache usa MPM event/worker. Peça ao professor o modo prefork com mod_php 8.3, ou a instalação manual usando PHP-FPM 8.3. Veja COMECE-AQUI.md.'
fi
if [[ -L "$destino" || -L "$conf" ]]; then falha 'Destino ou configuração é um link simbólico. Verifique com o professor.'; fi
if [[ -e "$destino" ]]; then
  [[ -f "$destino/.instalacao-escola-v1" ]] || falha "Já existe $destino de outra instalação. Nada foi sobrescrito."
  echo 'Instalação anterior encontrada. Arquivos, configuração e registros serão preservados.'
else
  [[ ! -e "$conf" ]] || falha 'Já existe configuração Apache com esse nome. Nada foi sobrescrito.'
  mkdir -p "$destino"
  cp -a "$origem/." "$destino/"
  php8.3 "$destino/scripts/preparar-escola.php" configurar
  touch "$destino/.instalacao-escola-v1"
fi
if ! systemctl is-active --quiet mariadb && ! systemctl is-active --quiet mysql; then
  if systemctl cat mariadb.service >/dev/null 2>&1; then systemctl start mariadb
  elif systemctl cat mysql.service >/dev/null 2>&1; then systemctl start mysql
  else falha 'MariaDB/MySQL não está instalado como serviço.'; fi
fi
php8.3 "$destino/scripts/preparar-escola.php" banco
if (( demo )); then php8.3 "$destino/scripts/preparar-escola.php" demonstracao; fi
chown -R root:www-data "$destino"
find "$destino" -type d -exec chmod 755 {} +
find "$destino" -type f -exec chmod 644 {} +
chown -R www-data:www-data "$destino/storage"
find "$destino/storage" -type d -exec chmod 750 {} +
find "$destino/storage" -type f -exec chmod 640 {} +
chmod 640 "$destino/app/etc/config.json"
if [[ -f "$destino/CONTAS-DEMONSTRACAO.txt" ]]; then chmod 600 "$destino/CONTAS-DEMONSTRACAO.txt"; fi
if [[ -e "$conf" ]]; then
  cmp -s "$destino/deploy/escola.conf" "$conf" || falha 'A configuração Apache existente foi alterada. Confira com o professor antes de substituir.'
else
  install -m 644 "$destino/deploy/escola.conf" "$conf"
fi
a2enmod php8.3 rewrite headers
a2enconf plataforma-jogadores-escola
if ! apache2ctl configtest; then
  echo 'O Apache não foi reiniciado. Corrija o erro indicado; a configuração está em:'
  echo "$conf"
  exit 1
fi
systemctl restart apache2
runuser -u www-data -- php8.3 "$destino/scripts/preparar-escola.php" verificar
bash "$destino/VERIFICAR-NA-ESCOLA.sh"
echo
echo 'PRONTO: abra http://localhost/PlataformaJogadoresEscola/'
if (( demo )) && [[ -f "$destino/CONTAS-DEMONSTRACAO.txt" ]]; then
  echo 'Contas de demonstração (também salvas fora da área pública):'
  cat "$destino/CONTAS-DEMONSTRACAO.txt"
fi
echo 'Para conferir Mailtrap: cadastre uma conta nova e confirme pelo e-mail recebido no Sandbox.'
