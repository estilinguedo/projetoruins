# Plataforma de Jogadores — instalação no Ubuntu

Esta versão usa páginas HTML, jQuery 3.7.1, Bootstrap 5.3.8, AJAX com JSON e uma API PHP. O código do professor foi mantido como base para autoload, PDO e consultas preparadas. As bibliotecas já estão incluídas; não é necessário baixar dependências para abrir as telas.

## 1. Preparar o computador

Os comandos abaixo são para Ubuntu 24.04, que oferece PHP 8.3. No computador da escola, confira primeiro o que o professor já instalou:

```bash
php -v
apache2 -v
mariadb --version
```

Se os programas ainda não estiverem instalados, no Ubuntu 24.04:

```bash
sudo apt update
sudo apt install apache2 mariadb-server php8.3 libapache2-mod-php8.3 php8.3-mysql php8.3-mbstring php8.3-xml
sudo a2enmod rewrite headers
sudo systemctl enable --now apache2 mariadb
```

Não instale XAMPP. Em outra versão de Ubuntu, confirme com o professor como disponibilizar PHP 8.3; não troque automaticamente a versão do PHP da escola.

## 2. Copiar a pasta

Extraia o ZIP. A pasta interna se chama `PlataformaJogadores`. Copie-a inteira para `/var/www/PlataformaJogadores`. Por exemplo, se estiver em Downloads:

```bash
sudo cp -a ~/Downloads/PlataformaJogadores /var/www/PlataformaJogadores
cd /var/www/PlataformaJogadores
```

Se já existir uma instalação nesse destino, faça uma cópia dela antes e use uma pasta nova para a migração. Não misture arquivos antigos e novos.

Somente `public/` fica acessível pelo navegador. `app/`, `vendor/` e `storage/` ficam fora da área pública. O site precisa gravar apenas em `storage/`:

```bash
sudo chown -R root:www-data /var/www/PlataformaJogadores
sudo find /var/www/PlataformaJogadores -type d -exec chmod 755 {} +
sudo find /var/www/PlataformaJogadores -type f -exec chmod 644 {} +
sudo chown -R www-data:www-data /var/www/PlataformaJogadores/storage
sudo find /var/www/PlataformaJogadores/storage -type d -exec chmod 750 {} +
sudo find /var/www/PlataformaJogadores/storage -type f -exec chmod 640 {} +
sudo chmod 640 /var/www/PlataformaJogadores/app/etc/config.json
```

## 3. Banco de dados

Em `app/etc/config.json`, a conexão já está configurada com:

- Servidor: `127.0.0.1`; porta: `3306`.
- Banco: `plataforma_jogadores`.
- Usuário: `root`; senha: `senha`, conforme solicitado.

Confira a conexão pelo terminal. Quando pedir a senha, digite `senha`:

```bash
mariadb -h 127.0.0.1 -u root -p
```

Crie o banco vazio, se ele ainda não existir:

```sql
CREATE DATABASE IF NOT EXISTS plataforma_jogadores CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

Escolha **uma** das opções:

**Começar sem registros:** importe o SQL de estrutura incluído:

```bash
mariadb -h 127.0.0.1 -u root -p plataforma_jogadores < app/core/database/database.sql
```

**Levar os usuários e resultados antigos:** importe seu arquivo `.sql` exportado pelo phpMyAdmin no banco vazio. Não importe também o SQL de instalação nova. Exemplo:

```bash
mariadb -h 127.0.0.1 -u root -p plataforma_jogadores < ~/Downloads/seu-backup.sql
sudo -u www-data php scripts/normalizar-tabelas.php
```

Pode importar pelo phpMyAdmin se ele estiver instalado. O ZIP recebido não continha um backup de registros do banco: o SQL incluído cria apenas a estrutura. As fotos presentes no ZIP foram mantidas em `storage/photos`; se copiar um banco mais recente, copie também as fotos correspondentes. Preserve `security.password_pepper` da configuração antiga para manter as senhas compatíveis.

O script de normalização trata nomes como `tokensConta` e `tokensconta`, que podem se comportar de modo diferente no Linux. Ele não apaga registros e pode ser executado novamente. Execute sem pessoas usando o site e mantenha seu backup.

Se `root` retornar “Access denied”: em instalações novas do MariaDB no Ubuntu, esse usuário pode usar autenticação pelo sistema (`unix_socket`) em vez de senha. Peça ao professor para habilitar o acesso TCP com a conta `root`/`senha` que ele definiu ou fornecer outra conta para a aplicação. Alterar somente o JSON não muda a senha nem o método de autenticação do servidor.

## 4. Configurar o Apache

Na pasta `/var/www/PlataformaJogadores`:

```bash
sudo cp deploy/plataforma-jogadores.conf /etc/apache2/conf-available/plataforma-jogadores.conf
sudo a2enconf plataforma-jogadores
sudo a2enmod rewrite headers
sudo apache2ctl configtest
sudo systemctl reload apache2
```

O teste deve mostrar `Syntax OK`. Abra:

**http://localhost/PlataformaJogadores/**

Não abra o HTML dando duplo clique no arquivo: o site precisa de HTTP, sessão e API. A primeira página será `login.html`.

## 5. Mailtrap e links

A configuração SMTP enviada no projeto foi preservada em `app/etc/config.json`. Confirmação de conta, reenvio e recuperação de senha usam Mailtrap Sandbox. As mensagens aparecem em **Mailtrap → My Sandbox**, não na caixa de entrada real do jogador. Precisa haver internet para enviar e acessar essas mensagens.

`app.url` deve ser o endereço que abre o site no navegador, sem `/api` e sem `/login.html`. Já está como `http://localhost/PlataformaJogadores`. Se o professor abrir de outro computador, coloque o IP/nome do servidor nesse campo, por exemplo `http://192.168.0.10/PlataformaJogadores`; `localhost` em outro computador apontaria para o próprio computador dele.

Cadastre uma conta, abra o e-mail no Mailtrap, clique no link e depois em “Confirmar meu e-mail”. Só então faça login. Para reset, clique em “Esqueci minha senha”, informe o e-mail, abra a mensagem nova no Mailtrap e defina a senha. Peça links novos depois de mudar o endereço do site: links antigos conservam o endereço antigo.

## 6. Tornar uma conta moderadora

Primeiro crie e confirme normalmente a conta. Depois, no banco:

```sql
UPDATE usuarios SET papel = 'moderador' WHERE email = 'email-da-conta@exemplo.com';
```

Ao entrar, aparecem Moderação e Histórico. O moderador pode remover foto e descrição com motivo; não ativa contas manualmente. Usuários comuns não conseguem chamar esses endpoints.

## 7. Onde mexer no projeto

- `public/*.html`: telas, sem PHP.
- `public/assets/css/paginas/`: CSS individual do seu amigo, preservado.
- `public/assets/js/app.js`: comportamento, preenchimento das telas e `$.ajax`.
- `app/etc/routes.json`: métodos, endpoints e permissões.
- `app/controllers/ApiController.php`: recebe as requisições e chama Models.
- `app/models/`: regras, acesso ao banco e solicitação de e-mails.
- `app/core/utils/Mail.php`: adaptador SMTP usado pelo Model de conta; mensagens em texto simples.
- `INTEGRACAO_JOGO.md`: ponto onde seu amigo fornecerá a classificação.

Os comentários antigos no início dos CSS podem citar uma view PHP: são somente comentários históricos. A tela atual com o mesmo nome está em `public/`, como `public/perfil.html`. A associação completa está em `ARQUITETURA.md`.

## 8. Se algo não abrir

- API com erro 404: confira `a2enmod rewrite`, `AllowOverride All`, o Alias e o nome das pastas.
- Erro 500 / banco: confira a conexão, o nome do banco e as tabelas importadas.
- Falha ao enviar foto: confira permissões de `storage` e `post_max_size` de pelo menos `8M` no PHP do Apache. A foto via JSON tem limite de 5 MB e exige um pouco mais de espaço na requisição.
- Falha SMTP: confira usuário/senha completos do Mailtrap, acesso à internet, limites da conta e saída TCP para a porta 2525. TLS permanece habilitado.
- Para ler o erro do servidor: `sudo tail -n 50 /var/log/apache2/error.log`.
- No Linux, `PlataformaJogadores` e `plataformajogadores` são caminhos diferentes. Use exatamente os nomes deste guia.

## Referências das ferramentas

- Ubuntu/PHP: https://ubuntu.com/server/docs/how-to/web-services/install-php/
- PHP 8.3 no Ubuntu 24.04: https://packages.ubuntu.com/noble/php8.3
- Autenticação MariaDB: https://mariadb.com/docs/server/reference/plugins/authentication-plugins/authentication-plugin-unix-socket
- jQuery: https://releases.jquery.com/
- Bootstrap: https://getbootstrap.com/docs/5.3/getting-started/download/
