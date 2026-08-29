<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Contratti fra giocatori: taglie (paga chi distrugge il bersaglio) e
 * consegne (paga chi porta N unita' di merce in un settore).
 */
final class Contracts
{
    /** @return list<array<string,mixed>> board pubblico */
    public static function board(int $viewerId, int $limit = 60): array
    {
        return Database::all(
            "SELECT c.*, i.handle AS issuer, t.handle AS target
             FROM contracts c
             JOIN players i ON i.id = c.issuer_player_id
             LEFT JOIN players t ON t.id = c.target_player_id
             WHERE c.status = 'open' AND c.issuer_player_id <> ?
             ORDER BY c.id DESC LIMIT ?",
            [$viewerId, $limit]
        );
    }

    /** @return list<array<string,mixed>> emessi o riscossi dal giocatore */
    public static function mine(int $playerId, int $limit = 60): array
    {
        return Database::all(
            "SELECT c.*, i.handle AS issuer, t.handle AS target
             FROM contracts c
             JOIN players i ON i.id = c.issuer_player_id
             LEFT JOIN players t ON t.id = c.target_player_id
             WHERE c.issuer_player_id = ? OR c.claimed_by = ?
             ORDER BY c.id DESC LIMIT ?",
            [$playerId, $playerId, $limit]
        );
    }

    /**
     * @param array<string,mixed> $player
     * @return array{ok:bool, error?:string, id?:int}
     */
    public static function open(array $player, string $kind, array $p): array
    {
        $pid = (int) $player['id'];
        $reward = max(0, (int) ($p['reward'] ?? 0));
        $min = GameConfig::int('contract.min_reward', 500);
        if ($reward < $min) {
            return ['ok' => false, 'error' => "La ricompensa minima e' {$min} cr."];
        }
        if ((int) $player['credits'] < $reward) {
            return ['ok' => false, 'error' => 'Crediti insufficienti per la cauzione.'];
        }
        $open = (int) (Database::first("SELECT COUNT(*) c FROM contracts WHERE issuer_player_id = ? AND status = 'open'", [$pid])['c'] ?? 0);
        if ($open >= GameConfig::int('contract.max_open', 5)) {
            return ['ok' => false, 'error' => 'Hai gia\' troppi contratti aperti.'];
        }

        $expiry = GameConfig::int('contract.expiry_hours', 72);
        $target = null;
        $commodity = null;
        $qty = null;
        $sector = null;

        if ($kind === 'bounty') {
            $t = Database::first('SELECT id FROM players WHERE handle = ?', [trim((string) ($p['target'] ?? ''))]);
            if ($t === null) {
                return ['ok' => false, 'error' => 'Bersaglio inesistente.'];
            }
            if ((int) $t['id'] === $pid) {
                return ['ok' => false, 'error' => 'Non puoi mettere una taglia su te stesso.'];
            }
            $target = (int) $t['id'];
        } elseif ($kind === 'delivery') {
            $commodity = (string) ($p['commodity'] ?? '');
            if (!in_array($commodity, Economy::COMMODITIES, true)) {
                return ['ok' => false, 'error' => 'Merce non valida.'];
            }
            $qty = max(1, (int) ($p['qty'] ?? 0));
            $sector = (int) ($p['sector'] ?? 0);
            if (Database::first('SELECT 1 x FROM sectors WHERE id = ?', [$sector]) === null) {
                return ['ok' => false, 'error' => 'Settore di consegna inesistente.'];
            }
        } else {
            return ['ok' => false, 'error' => 'Tipo di contratto sconosciuto.'];
        }

        Database::run('UPDATE players SET credits = credits - ? WHERE id = ?', [$reward, $pid]);
        Database::run(
            'INSERT INTO contracts (kind, issuer_player_id, target_player_id, commodity, qty, sector_id, reward, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))',
            [$kind, $pid, $target, $commodity, $qty, $sector, $reward, $expiry]
        );
        $id = Database::lastInsertId();

        if ($kind === 'bounty') {
            Radio::system("TAGLIA: {$player['handle']} offre " . number_format($reward, 0, ',', '.') . " cr per la testa di un comandante.");
        }
        return ['ok' => true, 'id' => $id];
    }

    public static function cancel(array $player, int $id): array
    {
        $c = Database::first("SELECT * FROM contracts WHERE id = ? AND issuer_player_id = ? AND status = 'open'", [$id, $player['id']]);
        if ($c === null) {
            return ['ok' => false, 'error' => 'Contratto non annullabile.'];
        }
        Database::run("UPDATE contracts SET status = 'cancelled' WHERE id = ?", [$id]);
        Database::run('UPDATE players SET credits = credits + ? WHERE id = ?', [(int) $c['reward'], $player['id']]);
        return ['ok' => true, 'refund' => (int) $c['reward']];
    }

    /**
     * Consegna: il giocatore e' nel settore giusto con la merce a bordo.
     *
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     */
    public static function deliver(array $player, array $ship, int $id): array
    {
        $c = Database::first("SELECT * FROM contracts WHERE id = ? AND kind = 'delivery' AND status = 'open'", [$id]);
        if ($c === null) {
            return ['ok' => false, 'error' => 'Contratto inesistente o non aperto.'];
        }
        if ((int) $c['issuer_player_id'] === (int) $player['id']) {
            return ['ok' => false, 'error' => 'Non puoi consegnare a te stesso.'];
        }
        if ((int) $player['sector_id'] !== (int) $c['sector_id']) {
            return ['ok' => false, 'error' => "Devi essere nel settore {$c['sector_id']}."];
        }
        $col = Economy::shipColumn((string) $c['commodity']);
        if ((int) $ship[$col] < (int) $c['qty']) {
            return ['ok' => false, 'error' => "Ti servono {$c['qty']} " . Economy::label((string) $c['commodity']) . ' a bordo.'];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run("UPDATE ships SET {$col} = {$col} - ? WHERE id = ?", [(int) $c['qty'], $ship['id']]);
            Database::run('UPDATE players SET credits = credits + ? WHERE id = ?', [(int) $c['reward'], $player['id']]);
            Database::run("UPDATE contracts SET status = 'claimed', claimed_by = ? WHERE id = ?", [$player['id'], $id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        Achievements::award((int) $player['id'], 'contract_claim');
        Live::alert((int) $c['issuer_player_id'], 'contract', 'Consegna ricevuta',
            "{$player['handle']} ha completato il tuo contratto di consegna.", '/gioco/contratti');
        return ['ok' => true, 'reward' => (int) $c['reward']];
    }

    /** Chiamato quando un giocatore viene distrutto in combattimento. */
    public static function onPlayerKilled(int $victimPlayerId, int $killerPlayerId): void
    {
        if ($victimPlayerId === $killerPlayerId) {
            return;
        }
        $rows = Database::all(
            "SELECT * FROM contracts WHERE kind = 'bounty' AND status = 'open' AND target_player_id = ?",
            [$victimPlayerId]
        );
        foreach ($rows as $c) {
            if ((int) $c['issuer_player_id'] === $killerPlayerId) {
                // il mandante non puo' riscuotere la propria taglia: rimborso
                Database::run("UPDATE contracts SET status = 'cancelled' WHERE id = ?", [$c['id']]);
                Database::run('UPDATE players SET credits = credits + ? WHERE id = ?', [(int) $c['reward'], $c['issuer_player_id']]);
                continue;
            }
            Database::run("UPDATE contracts SET status = 'claimed', claimed_by = ? WHERE id = ?", [$killerPlayerId, $c['id']]);
            Database::run('UPDATE players SET credits = credits + ? WHERE id = ?', [(int) $c['reward'], $killerPlayerId]);
            Achievements::award($killerPlayerId, 'contract_claim');
            Live::alert($killerPlayerId, 'contract', 'Taglia riscossa',
                'Hai incassato ' . number_format((int) $c['reward'], 0, ',', '.') . ' cr per un bersaglio.', '/gioco/contratti');
            Live::alert((int) $c['issuer_player_id'], 'contract', 'Taglia riscossa',
                'La tua taglia e\' stata riscossa.', '/gioco/contratti');
        }
    }

    public static function expireDue(): int
    {
        $rows = Database::all("SELECT * FROM contracts WHERE status = 'open' AND expires_at IS NOT NULL AND expires_at <= NOW()");
        foreach ($rows as $c) {
            Database::run("UPDATE contracts SET status = 'expired' WHERE id = ?", [$c['id']]);
            Database::run('UPDATE players SET credits = credits + ? WHERE id = ?', [(int) $c['reward'], $c['issuer_player_id']]);
        }
        return count($rows);
    }
}
