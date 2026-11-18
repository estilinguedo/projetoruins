# Integração pronta: Godot → endpoints PHP → leaderboard

O jogo envia somente a pontuação. Não envia ID de partida, ID de jogador ou posição. O servidor identifica a conta pelo token e mantém a MAIOR pontuação recebida por ela. Os maiores valores ficam primeiro. Empates compartilham posição (1, 1, 2); o ID organiza somente a exibição entre empatados.

## 1. Publicar e preparar o banco

**Instalação nova:** importe `app/core/database/database.sql`, que já inclui as tabelas do jogo.

**Banco existente:** faça backup e importe somente `app/core/database/migrations/001-godot.sql` pelo phpMyAdmin, selecionando o banco do site. Não importe o SQL de instalação nova sobre as tabelas existentes. Alternativa com terminal na raiz do projeto: `php scripts/migrar-godot.php`. A migração pode ser repetida e não apaga usuários, fotos ou resultados.

Atualize também os arquivos do backend e `public/api/.htaccess`. Preserve sua configuração de banco/SMTP em `app/etc/config.json`. A nova chave opcional é `"game": {"allow_local_http": false}`. Na ausência dela, HTTPS continua obrigatório.

No cPanel, configure PHP 8.3 com PDO MySQL, mbstring, fileinfo e OpenSSL; crie/use o banco e usuário da hospedagem (não a conta root da escola). Ajuste `database` e `app.url` no JSON. `app.url` deve ser o endereço HTTPS real do site, sem `/api` nem `/login.html`. Não execute o instalador Ubuntu com sudo no cPanel.

O domínio deve servir somente a pasta `public/` do projeto; mantenha `app/`, `vendor/` e `storage/` fora da raiz pública. Se o cPanel não permitir apontar a raiz do domínio para `public/`, peça ao professor para ajustar a disposição das pastas e o caminho do autoload em `public/api/index.php`. Não publique a pasta inteira em uma área que permita baixar config.json. Garanta gravação nas subpastas de `storage/` pelo PHP.

**Na escola:** uma instalação nova pelo instalador inclui a migração e libera HTTP somente para chamadas de loopback do próprio computador. Para uma cópia já instalada, atualize os arquivos do projeto e execute a migração; repetir o instalador antigo sozinho preserva os arquivos e não substitui o código. Em cPanel mantenha `allow_local_http` falso. Não há confiança automática em X-Forwarded-Proto; se a hospedagem termina HTTPS em proxy, o administrador deve expor corretamente HTTPS ao PHP.

## 2. Endereço base

Se o site abre em `https://seusite.com/`, a base é `https://seusite.com/api`.
Se abre em `https://seusite.com/plataforma/`, a base é `https://seusite.com/plataforma/api`.

Os quatro endpoints abaixo já existem. Todas as respostas são JSON com `ok` e `data` em sucesso; em erro, `ok: false` e `message`. A resposta também pode conter `csrf`, usado pelo frontend do site: o Godot não precisa enviá-lo, pois os endpoints do jogo usam Bearer token.

## 3. Login

`POST /api/jogo/login`

Cabeçalho: `Content-Type: application/json`

```json
{"email":"jogador@exemplo.com","senha":"senha-digitada"}
```

Sucesso (HTTP 200):

```json
{
  "ok": true,
  "data": {
    "token": "64-caracteres-hexadecimais-gerados-pelo-servidor",
    "expiresAt": "2026-11-18T00:00:00Z",
    "usuario": {"idUsuario": 12, "nomeUsuario": "jogador"}
  }
}
```

O horário acima é ilustrativo. A validade real é de 24 horas a partir do login. Guarde o token em memória no jogo, não guarde a senha. A conta deve existir e estar confirmada; cadastro e recuperação continuam nas páginas do site. Cada login cria um acesso independente. Reset de senha invalida os acessos antigos do jogo e do site pelo authVersion. O banco guarda somente o hash do token.

## 4. Consultar jogador

`GET /api/jogo/me`

Cabeçalho: `Authorization: Bearer TOKEN_RECEBIDO`

Retorna `data.usuario` (idUsuario, nomeUsuario, descricao, papel e idFoto) e `data.ranking` (posicao, pontuacao e atualizadoEm), ou ranking nulo se o jogador não tem resultado. Não retorna senha ou hash. O formato da pontuação consultada no ranking mantém o texto usado pelo site; o envio deve ser um inteiro JSON.

## 5. Enviar pontuação

`POST /api/jogo/resultados`

Cabeçalhos:

```http
Content-Type: application/json
Authorization: Bearer TOKEN_RECEBIDO
```

Corpo exato:

```json
{"pontuacao":2500}
```

Envie um inteiro JSON entre 0 e 2147483647, SEM aspas. Não envie outros campos. Texto, decimais, negativos, booleanos e campos adicionais retornam 422.

Sucesso:

```json
{
  "ok": true,
  "data": {
    "message": "Recorde salvo.",
    "pontuacao": 2500,
    "posicao": 1,
    "novoRecorde": true
  }
}
```

Se o jogador já tem 3000 e envia 2500, retorna pontuacao 3000 e novoRecorde false. Não soma, não apaga os demais jogadores e não reduz o recorde. Repetir o envio é seguro para essa regra de máximo; não existe contagem de partidas nem histórico individual de partidas.

Os resultados são gravados em `leaderboardresultados`, a mesma tabela que a leaderboard e o perfil já consultam. O site mostra os novos valores ao abrir ou recarregar a página. A tela já aberta não consulta periodicamente o servidor.

Resultados legados de texto livre (ex.: "mil pontos") ou acima do limite precisam ser revisados antes do primeiro envio: o endpoint retorna 409, sem converter ou apagar silenciosamente esses dados. Após ativar o Godot, não use o importador administrativo de classificação completa para cada jogador.

## 6. Logout

`POST /api/jogo/logout`, com Authorization Bearer e Content-Type application/json. Envie `{}` no corpo. O servidor revoga somente o token enviado. O jogo deve apagar esse token de sua memória. A sessão do navegador é independente.

## 7. Exemplo de envio no Godot 4 / GDScript

Depois de fazer o login e guardar `resposta["data"]["token"]`, a cena que termina a partida pode usar um nó HTTPRequest:

```gdscript
func enviar_pontuacao(pontos: int, token: String) -> void:
    var cabecalhos := PackedStringArray([
        "Content-Type: application/json",
        "Authorization: Bearer " + token
    ])
    var dados := JSON.stringify({"pontuacao": pontos})
    var erro := $HTTPRequest.request(
        "https://seusite.com/api/jogo/resultados",
        cabecalhos,
        HTTPClient.METHOD_POST,
        dados
    )
    if erro != OK:
        print("Não foi possível iniciar o envio.")
```

Troque o domínio e, se necessário, acrescente a subpasta do site. Conecte o sinal request_completed do nó para ler o status HTTP e o JSON. Espere a resposta antes de mostrar “salvo”; em 401 peça novo login. Não inicie outra chamada no mesmo nó enquanto ele estiver ocupado. O trecho é um exemplo de integração, não um projeto Godot completo; não foi executado no editor Godot nesta entrega.

O mesmo HTTPRequest faz login por POST, enviando email/senha e Content-Type JSON; me é GET e logout é POST com `{}`. Não coloque credenciais do banco nem uma chave secreta compartilhada dentro do jogo. Para exportação Web em outro domínio, será necessário definir CORS explicitamente; esta entrega não libera origens arbitrárias e não implementa login compartilhado com a sessão do navegador.

## 8. Erros que o jogo deve tratar

| Status | Significado |
| --- | --- |
| 400 | JSON inválido ou corpo que não é objeto |
| 401 | Token ausente, revogado, expirado ou invalidado por reset |
| 403 | Conta ainda não confirmou o e-mail |
| 405 | Método incorreto, como GET no envio de pontuação |
| 409 | Resultado legado incompatível com ranking numérico |
| 415 | Content-Type não é application/json |
| 422 | Credenciais incorretas ou formato de pontuação inválido |
| 426 | Chamada precisa usar HTTPS |
| 429 | Limite de tentativas/envios atingido; aguarde 15 minutos |
| 500/503 | Configuração, banco ou migração precisa de atenção |

Login: 10 tentativas por IP e e-mail em 15 minutos. Resultados: 120 envios por jogador em 15 minutos.

O token autentica o jogador; ele não prova que a pontuação calculada no cliente é legítima. Esta integração valida formato e preserva o maior valor, mas não inclui verificação de jogadas por um servidor de jogo.

## 9. Arquivos para estudar

- `app/etc/routes.json`: os quatro endpoints e o controle de acesso.
- `app/core/utils/Router.php`: HTTPS, Bearer e separação da autenticação do navegador.
- `app/controllers/GameController.php`: login, me, resultados e logout.
- `app/models/GameAccess.php`: emissão, consulta e revogação dos tokens.
- `app/models/GameResult.php`: validação, gravação do recorde e cálculo de posição.
- `app/models/Leaderboard.php`: leitura dos resultados já usada pelo site.
- `app/core/database/migrations/001-godot.sql`: tabelas adicionais, sem apagar as antigas.

Referência do HTTPRequest: https://docs.godotengine.org/en/stable/tutorials/networking/http_request_class.html
