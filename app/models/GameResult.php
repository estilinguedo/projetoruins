<?php
declare(strict_types=1);
namespace app\models;

use app\core\database\DBConnection;
use app\core\utils\HttpError;

final class GameResult
{
    public static function saveBest(int $userId, mixed $score): array
    {
        if (!is_int($score) || $score < 0 || $score > 2147483647) {
            throw new HttpError(422, 'pontuacao deve ser um número inteiro entre 0 e 2147483647, sem aspas.');
        }
        $db = DBConnection::getConnection();
        $db->beginTransaction();
        try {
            // Serializa a alteração da classificação; dois envios concorrentes não perdem pontos.
            $lock = $db->query('SELECT id FROM jogocontrole WHERE id=1 FOR UPDATE')->fetchColumn();
            if (!$lock) throw new HttpError(503, 'Execute a migração do jogo antes de enviar resultados.');
            // O importador antigo permitia texto livre; não converta silenciosamente esses dados.
            $invalid = $db->query("SELECT idUsuario FROM leaderboardresultados WHERE pontuacao NOT REGEXP '^[0-9]+$' OR CHAR_LENGTH(pontuacao)>10 OR CAST(pontuacao AS UNSIGNED)>2147483647 LIMIT 1")->fetch();
            if ($invalid) throw new HttpError(409, 'Há resultados antigos não numéricos ou fora do limite. Revise a classificação antes de usar a integração do jogo.');

            $s = $db->prepare('SELECT pontuacao FROM leaderboardresultados WHERE idUsuario=?');
            $s->execute([$userId]);
            $previous = $s->fetchColumn();
            $changed = $previous === false || $score > (int)$previous;
            if ($previous === false) {
                $s = $db->prepare('INSERT INTO leaderboardresultados (idUsuario,posicao,pontuacao) VALUES (?,1,?)');
                $s->execute([$userId, (string)$score]);
            } elseif ($changed) {
                $s = $db->prepare('UPDATE leaderboardresultados SET pontuacao=?,atualizadoEm=UTC_TIMESTAMP() WHERE idUsuario=?');
                $s->execute([(string)$score, $userId]);
            }

            $rows = $db->query("SELECT r.idUsuario,r.pontuacao FROM leaderboardresultados r JOIN usuarios u ON u.idUsuario=r.idUsuario WHERE u.ativacao='S' ORDER BY CAST(r.pontuacao AS UNSIGNED) DESC,r.idUsuario")->fetchAll();
            $rank = 0; $last = null;
            $update = $db->prepare('UPDATE leaderboardresultados SET posicao=? WHERE idUsuario=?');
            foreach ($rows as $row) {
                $value = (int)$row['pontuacao'];
                // Pontuações iguais compartilham posição; não há critério adicional de desempate.
                if ($last === null || $value !== $last) $rank++;
                $last = $value;
                $update->execute([$rank, $row['idUsuario']]);
            }
            $ranking = Leaderboard::forUser($userId);
            $db->commit();
            return ['message' => $changed ? 'Recorde salvo.' : 'Seu recorde anterior foi mantido.',
                'pontuacao' => (int)$ranking['pontuacao'], 'posicao' => (int)$ranking['posicao'], 'novoRecorde' => $changed];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
}
