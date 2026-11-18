<?php
declare(strict_types=1);
namespace app\models;
use app\core\database\DBConnection;
/**
 * Leitura da classificação compartilhada pelo site e pelo Godot.
 * Godot envia somente pontuacao a POST api/jogo/resultados (GameResult::saveBest).
 * publicarResultados é o importador administrativo legado de classificação completa;
 * não deve ser usado para enviar a pontuação individual do jogo.
 */
final class Leaderboard {
    public static function list(int $offset=0): array {
        $s=DBConnection::getConnection()->query("SELECT r.posicao,r.pontuacao,r.atualizadoEm,u.idUsuario,u.nomeUsuario,f.idFoto FROM leaderboardresultados r JOIN usuarios u ON u.idUsuario=r.idUsuario LEFT JOIN fotosperfil f ON f.idUsuario=u.idUsuario WHERE u.ativacao='S' ORDER BY r.posicao,u.idUsuario LIMIT 51 OFFSET ".max(0,$offset));
        return $s->fetchAll();
    }
    public static function forUser(int $id): ?array {
        $s=DBConnection::getConnection()->prepare('SELECT posicao,pontuacao,atualizadoEm FROM leaderboardresultados WHERE idUsuario=?'); $s->execute([$id]); return $s->fetch() ?: null;
    }
    public static function publicarResultados(array $results): void {
        // Recebe um snapshot COMPLETO: substitui a classificação anterior atomicamente.
        // Ex.: [['idUsuario'=>12,'posicao'=>1,'pontuacao'=>'2500']]
        // Posições repetidas são aceitas; a regra de empate pertence ao jogo.
        $seen=[];
        foreach($results as $r) {
            if(!is_array($r) || !isset($r['idUsuario'],$r['posicao'],$r['pontuacao']) || !is_int($r['idUsuario']) || $r['idUsuario']<1 || !is_int($r['posicao']) || $r['posicao']<1 || $r['posicao']>4294967295 || !is_string($r['pontuacao']) || !mb_check_encoding($r['pontuacao'],'UTF-8') || mb_strlen($r['pontuacao'])<1 || mb_strlen($r['pontuacao'])>40 || isset($seen[$r['idUsuario']])) throw new \InvalidArgumentException('Resultado inválido ou jogador repetido.');
            $seen[$r['idUsuario']]=true;
        }
        $db=DBConnection::getConnection(); $db->beginTransaction();
        try {
            $db->exec('DELETE FROM leaderboardresultados');
            $s=$db->prepare('INSERT INTO leaderboardresultados (idUsuario,posicao,pontuacao) VALUES (?,?,?)');
            foreach($results as $r) $s->execute([$r['idUsuario'],$r['posicao'],$r['pontuacao']]);
            $db->commit();
        } catch(\Throwable $e) { if($db->inTransaction()) $db->rollBack(); throw $e; }
    }
}
