# Validação desta entrega

Realizada em 12/11/2026, com PHP 8.3.36 e MariaDB 10.4.32 em uma instância local isolada, sem modificar o banco da instalação original.

- Sintaxe de todos os 22 arquivos PHP autorais verificada com PHP 8.3.
- JavaScript conferido com `node --check`.
- API testada por HTTP: respostas JSON, CSRF, método incorreto, endpoint inexistente, exigência de login e papel de moderador.
- Cadastro e bloqueio antes da confirmação, consumo único do link de ativação, recuperação de senha, consumo único do reset e invalidação das sessões antigas.
- Descrição de até 200 caracteres, alteração somente do próprio perfil, upload de imagem válida, rejeição de arquivo inválido e foto duplicada.
- Remoção de foto e descrição, rejeição de remoção com conteúdo alterado, histórico e reenvio da foto após remoção.
- Leitura de ranking, posição no perfil e logout.
- Fluxos de login e edição de descrição exercitados no navegador via jQuery AJAX. Sem erros no console nas páginas exercitadas.
- Adaptador SMTP testado com receptor local: as duas mensagens são texto simples e apontam para as novas páginas `.html`.
- Todos os 12 arquivos HTML são estáticos, sem PHP. No backend autoral não há HTML, CSS ou JavaScript. Os caminhos dos assets foram conferidos com maiúsculas/minúsculas exatas.
- Os 12 arquivos CSS de páginas do ZIP original foram comparados byte a byte e estão preservados.
- Downloads de jQuery e Bootstrap conferidos contra seus hashes de integridade publicados.
- Normalização de tabelas testada no banco de testes já em minúsculas, sem alterar registros.

Limitações: não há Ubuntu disponível nesta máquina. O teste HTTP usou o servidor de desenvolvimento do PHP; a instalação completa com Apache nativo do Ubuntu e o renomeio de tabelas em um servidor Linux ainda precisam ser conferidos na escola. MySQL 8 não foi executado; o teste do SGBD foi com MariaDB 10.4. O SMTP foi validado localmente; não foi enviado e-mail novo ao Mailtrap durante esta refatoração. As credenciais SMTP recebidas no ZIP foram preservadas.

Use LEIA-ME-UBUNTU.md para configurar o Apache, importar o banco e confirmar os dois envios pelo Mailtrap no endereço final. Os testes de desenvolvimento não são incluídos neste pacote de apresentação.

## Preparação escolar

Instaladores Bash conferidos com bash -n. Preparador PHP validado no PHP 8.3: criação da estrutura em banco isolado, criação das duas contas, conferência das senhas contra seus hashes, repetição sem alterar contas existentes, rejeição de outro nome de banco e verificação de diretórios. As contas usadas para esses testes não estão no pacote. Não foi executada a instalação completa em Ubuntu; VERIFICAR-NA-ESCOLA.sh valida o ambiente real após instalar.

## Integração Godot — 16/11/2026

PHP 8.3.36 e MariaDB 10.4 isolado: 38 verificações HTTP passaram. Cobertura: login válido/incorreto, bloqueio da conta não confirmada, token armazenado somente como hash, token obrigatório, GET/POST correto, JSON numérico estrito e ausência de campos extras, recorde maior preservado em envios menores/iguais, preservação dos outros jogadores, ranking decrescente e empates, leitura do ranking no site, CSRF do navegador mantido, independência entre cookie e Bearer, logout, expiração, invalidação por authVersion e recusa de HTTP quando a exceção local está desativada. Quatro processos PHP concorrentes enviaram pontuações e o maior valor foi preservado. SQL novo e repetição da migração conferidos.

Não foi executado o editor Godot, nem feita publicação real em cPanel, teste de certificado HTTPS ou instalação Ubuntu. O exemplo GDScript é material de integração para o colega. A validação por HTTP exercitou diretamente os endpoints PHP.
