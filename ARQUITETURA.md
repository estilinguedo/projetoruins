# Organização e contrato da API

`public/` é a raiz pública do Apache. As telas são arquivos HTML sem PHP. O único arquivo PHP público é `public/api/index.php`, que só retorna JSON. Não há renderer PHP nem views PHP. As mensagens SMTP também são texto simples, sem HTML no backend autoral.

Fluxo: evento no HTML → `public/assets/js/app.js` → `$.ajax` → `api/index.php` → Router → ApiController → Models → PDO / adaptador SMTP.

`app/core/database/DBConnection.php`, `DBQuery.php` e `Where.php`, os namespaces e o autoload preservam a base do modelo do professor. Apenas os recursos usados pela aplicação foram mantidos no backend autoral. Dependências de terceiros ficam em `vendor/`.

## Telas e CSS

Cada `public/NOME.html` inclui `assets/css/paginas/NOME.css`: login, cadastro, confirmar-email, reenviar-confirmacao, recuperar-senha, nova-senha, leaderboard, perfil, moderacao, confirmar-remocao, historico e erro. O CSS individual original foi mantido byte a byte. Bootstrap carrega antes dele; `compatibilidade.css` acrescenta somente regras funcionais de ocultação, texto e largura.

## Sessão e dados

As páginas HTML protegidas são estruturas estáticas sem informações pessoais. O JS redireciona quem não está logado. A proteção efetiva está no Router: sem sessão válida, os endpoints de ranking, perfil e foto retornam 401; sem papel moderador, os de moderação retornam 403. Esconder botões nunca é a autorização.

Primeiro faça `GET api/session`: retorna `data.user` (ou null) e `csrf`. Todas as respostas JSON incluem o token CSRF atual. As chamadas POST usam `Content-Type: application/json` e `X-CSRF-Token`. O JS atualiza o token depois de login/logout/reset. A senha e o hash nunca são enviados ao frontend.

Resposta de sucesso: `{"ok":true,"data":{...},"csrf":"..."}`. Erro: `{"ok":false,"message":"...","csrf":"..."}` com status HTTP 400/401/403/404/405/409/413/415/422/429/500/503.

## Endpoints

| Método e endpoint relativo a api/ | Acesso | Entrada principal |
| --- | --- | --- |
| GET session | Público | — |
| POST cadastro | Público + CSRF | nomeUsuario, email, senha, confirmacao |
| POST login | Público + CSRF | email, senha |
| POST confirmar-email | Público + CSRF | token |
| POST reenviar-confirmacao | Público + CSRF | email |
| POST recuperar-senha | Público + CSRF | email |
| POST nova-senha | Público + CSRF | token, senha, confirmacao |
| POST sair | Usuário + CSRF | objeto vazio |
| GET leaderboard | Usuário | pagina (query string) |
| GET perfil | Usuário | id opcional; omitido = próprio perfil |
| GET foto | Usuário | id; resposta contém imagem data URI |
| POST perfil/descricao | Usuário + CSRF | descricao |
| POST perfil/foto | Usuário + CSRF | foto: base64 puro, sem prefixo data URI |
| GET moderacao | Moderador | busca, pagina |
| GET moderacao/remover | Moderador | id, tipo (foto/descricao) |
| POST moderacao/remover | Moderador + CSRF | id, tipo, motivo, expected |
| GET moderacao/historico | Moderador | pagina |

Campos de texto POST devem ser strings. O id de quem edita o próprio perfil vem sempre da sessão. O GET de confirmação de remoção fornece `expected`, usado para rejeitar uma remoção caso o conteúdo tenha mudado. Listagens retornam rows, page e hasNext, com 50 registros por página.

Fotos são enviadas como JSON base64 e armazenadas fora da raiz pública; tipo MIME e dimensões são conferidos no servidor. Não há substituição de foto existente, mas o jogador pode reenviar depois da remoção pela moderação. Descrição: até 200 caracteres.

Tokens de e-mail são aleatórios, guardados como hash, de uso único. Ativação dura 24h; reset dura 1h. Reset incrementa authVersion e invalida sessões anteriores. SMTP é configurado somente no servidor.

## Portabilidade

Nomes de tabelas em minúsculas, paths derivados de `__DIR__`, autoload convertendo separadores de namespace para `/`, URLs relativas à pasta pública calculadas pelo endereço de app.js. Sem caminhos de XAMPP ou letras de unidade no código entregue. `app.url` serve para links de e-mail, não é derivado do cabeçalho Host do visitante.

O Apache fornece o frontend; o Router fornece somente API JSON. Routes desconhecidas e métodos errados não executam controllers arbitrários.

## Atualização: acesso do Godot

As rotas jogo/login, jogo/me, jogo/resultados e jogo/logout usam GameController. O Router permite somente as classes fixas ApiController e GameController; não instancia classes vindas de parâmetros HTTP. Endpoints do jogo usam Bearer token independente dos cookies; as rotas do site mantêm CSRF. HTTPS é obrigatório, salvo a opção explícita game.allow_local_http para conexões de loopback na escola.

GameAccess guarda hashes dos tokens em jogoacessos (24h, vinculados a authVersion); GameResult mantém a maior pontuação inteira, recalculando posições e serializando atualizações com uma linha de controle em jogocontrole. Nenhum ID de partida é recebido. Consulte INTEGRACAO_GODOT.md.
