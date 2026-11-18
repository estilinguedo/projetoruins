# Levar para a escola

Este pacote instala o projeto no Apache do Ubuntu. Não precisa de GitHub, XAMPP ou servidor do Windows.

## Antes de sair de casa

Leve o ZIP inteiro no pendrive. O pacote já contém PHP do projeto, bibliotecas, CSS, SQL da estrutura, configuração Mailtrap e o instalador. **Os programas Apache, PHP 8.3 e MariaDB precisam existir no computador da escola**; os executáveis desses programas não estão no ZIP.

Avise ao professor que o instalador precisa de `sudo` para configurar o Apache e reiniciar seu serviço. Ele cria uma instalação separada em `/var/www/PlataformaJogadoresEscola` e um banco `plataforma_jogadores_escola`, usando `root` e senha `senha`. Não altera a senha do MariaDB nem apaga bancos existentes.

## Na escola: extrair, instalar, abrir

1. Extraia o ZIP, preferencialmente para a pasta Downloads do Ubuntu.
2. Entre na pasta extraída `PlataformaJogadores`. Clique com o botão direito em uma área vazia e escolha **Abrir no terminal**. O terminal precisa estar na pasta que contém `INSTALAR-NA-ESCOLA.sh`.
3. Execute:

```bash
sudo bash INSTALAR-NA-ESCOLA.sh --com-demonstracao
```

A senha que o `sudo` pede é a **senha do usuário do Ubuntu**, não a senha do banco. O professor pode precisar digitá-la. É normal não aparecerem asteriscos enquanto digita.

O instalador copia o projeto, ajusta permissões, cria a estrutura de um banco vazio, prepara duas contas, configura o Apache e verifica o site por HTTP. As contas recebem senhas aleatórias que aparecem ao final. Anote-as.

4. Quando aparecer **PRONTO**, abra no navegador:

**http://localhost/PlataformaJogadoresEscola/**

Não remova o pendrive durante a cópia. Depois de instalado, os arquivos usados pelo site ficam no computador, e o pendrive pode ser retirado.

## Contas prontas para apresentar

A opção `--com-demonstracao` cria:

- `demo_jogador@example.test`: conta de jogador.
- `demo_moderador@example.test`: conta de moderador.

Cada uma recebe uma senha aleatória. Para rever as senhas no computador onde foram criadas:

```bash
sudo cat /var/www/PlataformaJogadoresEscola/CONTAS-DEMONSTRACAO.txt
```

Esse arquivo fica fora da pasta pública. Não há senhas fixas compartilhadas entre instalações. Se rodar o instalador novamente, ele preserva as contas existentes e não redefine suas senhas.

Essas duas contas são inseridas confirmadas **apenas para a demonstração**. Isso não desativa a confirmação: qualquer conta criada pelo formulário continua bloqueada até confirmar pelo Mailtrap. Elas usam endereços fictícios e não são contas reais de e-mail.

O banco começa sem resultados do jogo e sem seus usuários antigos. A leaderboard vazia é esperada até a integração do jogo fornecer os dados. Para usar seus registros antigos, veja a instalação manual em `LEIA-ME-UBUNTU.md`; não importe um backup por cima do banco de demonstração já preenchido.

## Roteiro curto de apresentação

1. Entre com a conta de jogador; abra leaderboard e perfil.
2. Salve uma descrição e envie uma foto PNG/JPEG pequena que levou no pendrive.
3. Saia e entre como moderador; abra Moderação e revise o jogador.
4. Remova a foto ou descrição com um motivo e confira o Histórico.
5. Volte ao jogador para mostrar que ele pode reenviar a foto depois da remoção.
6. Se a rede permitir Mailtrap, cadastre uma conta nova, mostre que o login é bloqueado, confirme pelo link no Mailtrap e entre. Demonstre também “Esqueci minha senha”.

**Sem internet:** as telas, o login das contas já confirmadas, os perfis e a moderação funcionam localmente. Novos envios de confirmação e recuperação dependem da conexão SMTP com o Mailtrap. Firewall ou bloqueio do Mailtrap devem ser tratados com o professor; o pacote não contorna bloqueios.

## Se faltarem programas

Se for **Ubuntu 24.04**, houver internet e o professor autorizar a instalação de pacotes, execute:

```bash
sudo bash INSTALAR-NA-ESCOLA.sh --instalar-dependencias --com-demonstracao
```

Essa opção usa os repositórios do Ubuntu para instalar Apache, MariaDB, PHP 8.3 e extensões. Pode levar mais tempo e depender do firewall. Não deixe para descobrir isso na apresentação. Em outra versão do Ubuntu, peça ao professor para disponibilizar PHP 8.3; o script não adiciona repositórios externos.

Se disser que o Apache usa outra versão de PHP, MPM event/worker ou PHP-FPM, peça ao professor para ajustar o ambiente. O caminho automático usa `libapache2-mod-php8.3` com MPM prefork. Ele não desabilita outras versões nem troca o modo do servidor automaticamente. O projeto também pode ser configurado manualmente com PHP-FPM 8.3 pelo professor.

Se falhar `root`/`senha`, o banco pode estar configurado para autenticação por `unix_socket`. O professor precisa disponibilizar uma conta com acesso por senha/TCP. Alterar o JSON sozinho não altera a conta do servidor. A configuração da instalação fica em `/var/www/PlataformaJogadoresEscola/app/etc/config.json`.

## Nas próximas apresentações

A instalação precisa ser feita uma vez. Se o computador preservou os arquivos e o banco, abra o endereço normalmente. Para uma nova conferência:

```bash
cd /var/www/PlataformaJogadoresEscola
sudo bash VERIFICAR-NA-ESCOLA.sh
```

Se os serviços estiverem parados, o professor pode iniciá-los com `sudo systemctl start apache2 mariadb` (ou `mysql`, se esse for o serviço instalado).

O verificador confere PHP pelo Apache, extensões, banco, permissões, API JSON e bloqueio do ranking sem login. Ele não envia e-mails nem garante que o firewall permita Mailtrap; confira esse fluxo no navegador.

## Limite da preparação

O site foi testado anteriormente com PHP 8.3 e MariaDB 10. Os scripts de preparação foram verificados separadamente, mas a instalação completa não pôde ser executada em Ubuntu neste computador. Por isso o pacote inclui verificações que rodam no próprio PC da escola. Nenhum ZIP consegue garantir antecipadamente a senha administrativa, as versões instaladas ou as regras do firewall desse computador.

Referências: [PHP no Apache do Ubuntu](https://ubuntu.com/server/docs/how-to/web-services/install-php/) e [verificação de configuração do Apache](https://httpd.apache.org/docs/2.4/programs/apachectl.html).
