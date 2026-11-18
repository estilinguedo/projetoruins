# Integração do jogo

A integração Godot por endpoints já está implementada. Leia **INTEGRACAO_GODOT.md** para os corpos JSON, cabeçalhos, respostas, configuração no cPanel e migração do banco.

- POST api/jogo/login: email e senha; devolve token válido por 24 horas.
- GET api/jogo/me: consulta o jogador pelo Bearer token.
- POST api/jogo/resultados: somente `{"pontuacao":2500}`; mantém a maior pontuação.
- POST api/jogo/logout: revoga o token.

O usuário vem do token, não de um ID enviado pelo jogo. Não há ID de partida. O ranking é decrescente; pontuações iguais compartilham posição. O site continua consultando a tabela leaderboardresultados por AJAX.

O antigo método Leaderboard::publicarResultados e scripts/importar-ranking.php foram mantidos como ferramentas administrativas legadas para importar uma classificação COMPLETA, substituindo a anterior. Não use esse importador para enviar resultados individuais do Godot. Ele não faz parte dos endpoints novos.
