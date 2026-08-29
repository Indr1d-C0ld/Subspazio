<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Corporazioni: versione base per il possesso condiviso di pianeti
 * (e per non farsi sparare addosso dalle difese dei soci). Le funzioni
 * di pianificazione e comunicazione arrivano con la Fase 5.
 */
final class Corp
{
    /** @return array<string,mixed>|null */
    public static function of(int $playerId): ?array
    {
        return Database::first(
            'SELECT c.*, m.role FROM corp_members m JOIN corporations c ON c.id = m.corp_id WHERE m.player_id = ?',
            [$playerId]
        );
    }

    public static function corpIdOf(int $playerId): ?int
    {
        $row = Database::first('SELECT corp_id FROM corp_members WHERE player_id = ?', [$playerId]);
        return $row ? (int) $row['corp_id'] : null;
    }

    /** Due giocatori sono soci della stessa corp o di corp alleate? */
    public static function areMates(int $a, int $b): bool
    {
        if ($a === $b) {
            return true;
        }
        $ca = self::corpIdOf($a);
        $cb = self::corpIdOf($b);
        if ($ca === null || $cb === null) {
            return false;
        }
        if ($ca === $cb) {
            return true;
        }
        return self::allied($ca, $cb);
    }

    public static function allied(int $c1, int $c2): bool
    {
        [$lo, $hi] = $c1 < $c2 ? [$c1, $c2] : [$c2, $c1];
        return Database::first(
            "SELECT 1 x FROM corp_alliances WHERE corp_lo = ? AND corp_hi = ? AND status = 'active'",
            [$lo, $hi]
        ) !== null;
    }

    /** @return list<array<string,mixed>> alleanze (attive e proposte) della corp */
    public static function alliances(int $corpId): array
    {
        return Database::all(
            "SELECT ca.*, c.name, c.tag,
                    IF(ca.corp_lo = ?, ca.corp_hi, ca.corp_lo) AS other_id
             FROM corp_alliances ca
             JOIN corporations c ON c.id = IF(ca.corp_lo = ?, ca.corp_hi, ca.corp_lo)
             WHERE ca.corp_lo = ? OR ca.corp_hi = ?
             ORDER BY ca.status DESC, c.name",
            [$corpId, $corpId, $corpId, $corpId]
        );
    }

    /** @param array<string,mixed> $player */
    public static function proposeAlliance(array $player, string $otherTag): array
    {
        $me = self::of((int) $player['id']);
        if ($me === null || $me['role'] !== 'ceo') {
            return ['ok' => false, 'error' => 'Solo il CEO puo\' proporre alleanze.'];
        }
        $other = Database::first('SELECT * FROM corporations WHERE tag = ? OR name = ?', [strtoupper(trim($otherTag)), trim($otherTag)]);
        if ($other === null) {
            return ['ok' => false, 'error' => 'Corporazione inesistente.'];
        }
        if ((int) $other['id'] === (int) $me['id']) {
            return ['ok' => false, 'error' => 'E\' la tua corporazione.'];
        }
        [$lo, $hi] = (int) $me['id'] < (int) $other['id'] ? [(int) $me['id'], (int) $other['id']] : [(int) $other['id'], (int) $me['id']];
        $existing = Database::first('SELECT * FROM corp_alliances WHERE corp_lo = ? AND corp_hi = ?', [$lo, $hi]);
        if ($existing !== null && $existing['status'] === 'active') {
            return ['ok' => false, 'error' => 'Gia\' alleati.'];
        }
        if ($existing !== null && (int) $existing['proposed_by'] !== (int) $me['id']) {
            // l'altra corp aveva gia' proposto: accetta
            Database::run('UPDATE corp_alliances SET status = ? WHERE corp_lo = ? AND corp_hi = ?', ['active', $lo, $hi]);
            Radio::system("ALLEANZA: {$me['name']} e {$other['name']} hanno siglato un patto.");
            return ['ok' => true, 'accepted' => true];
        }
        Database::run(
            'INSERT INTO corp_alliances (corp_lo, corp_hi, status, proposed_by) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE proposed_by = VALUES(proposed_by), status = ?',
            [$lo, $hi, 'proposed', $me['id'], 'proposed']
        );
        Live::corp((int) $other['id'], 'alliance', 'Proposta di alleanza', "{$me['name']} propone un'alleanza.");
        return ['ok' => true, 'proposed' => true];
    }

    /** @param array<string,mixed> $player */
    public static function dissolveAlliance(array $player, int $otherCorpId): array
    {
        $me = self::of((int) $player['id']);
        if ($me === null || $me['role'] !== 'ceo') {
            return ['ok' => false, 'error' => 'Solo il CEO.'];
        }
        [$lo, $hi] = (int) $me['id'] < $otherCorpId ? [(int) $me['id'], $otherCorpId] : [$otherCorpId, (int) $me['id']];
        Database::run('DELETE FROM corp_alliances WHERE corp_lo = ? AND corp_hi = ?', [$lo, $hi]);
        return ['ok' => true];
    }

    /** @return list<array<string,mixed>> */
    public static function members(int $corpId): array
    {
        return Database::all(
            'SELECT p.id, p.handle, m.role, m.joined_at
             FROM corp_members m JOIN players p ON p.id = m.player_id
             WHERE m.corp_id = ? ORDER BY m.role = \'ceo\' DESC, m.joined_at',
            [$corpId]
        );
    }

    /**
     * @param array<string,mixed> $player
     * @return array{ok:bool, error?:string, corp?:array<string,mixed>}
     */
    public static function create(array $player, string $name, string $tag, string $password): array
    {
        if (self::corpIdOf((int) $player['id']) !== null) {
            return ['ok' => false, 'error' => 'Sei gia\' in una corporazione.'];
        }
        $name = trim($name);
        $tag = strtoupper(trim($tag));
        if (!preg_match('/^[\p{L}0-9 .\'-]{3,48}$/u', $name)) {
            return ['ok' => false, 'error' => 'Nome non valido (3-48 caratteri).'];
        }
        if (!preg_match('/^[A-Z0-9]{2,6}$/', $tag)) {
            return ['ok' => false, 'error' => 'Sigla non valida (2-6 lettere/cifre).'];
        }
        if (strlen($password) < 4) {
            return ['ok' => false, 'error' => 'Password troppo corta.'];
        }
        $cost = GameConfig::int('corp.create_cost', 50000);
        if ((int) $player['credits'] < $cost) {
            return ['ok' => false, 'error' => "Servono {$cost} cr per fondare una corporazione."];
        }
        if (Database::first('SELECT 1 x FROM corporations WHERE name = ? OR tag = ?', [$name, $tag]) !== null) {
            return ['ok' => false, 'error' => 'Nome o sigla gia\' in uso.'];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('UPDATE players SET credits = credits - ? WHERE id = ?', [$cost, $player['id']]);
            Database::run(
                'INSERT INTO corporations (name, tag, password_hash, ceo_player_id, treasury) VALUES (?, ?, ?, ?, 0)',
                [$name, $tag, password_hash($password, PASSWORD_DEFAULT), $player['id']]
            );
            $cid = Database::lastInsertId();
            Database::run("INSERT INTO corp_members (player_id, corp_id, role) VALUES (?, ?, 'ceo')", [$player['id'], $cid]);
            Database::run('UPDATE players SET corp_id = ? WHERE id = ?', [$cid, $player['id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return ['ok' => true, 'corp' => self::of((int) $player['id'])];
    }

    /** @param array<string,mixed> $player */
    public static function join(array $player, string $name, string $password): array
    {
        if (self::corpIdOf((int) $player['id']) !== null) {
            return ['ok' => false, 'error' => 'Sei gia\' in una corporazione.'];
        }
        $corp = Database::first('SELECT * FROM corporations WHERE name = ? OR tag = ?', [trim($name), strtoupper(trim($name))]);
        if ($corp === null || !password_verify($password, (string) $corp['password_hash'])) {
            return ['ok' => false, 'error' => 'Corporazione o password errata.'];
        }
        $n = (int) (Database::first('SELECT COUNT(*) c FROM corp_members WHERE corp_id = ?', [$corp['id']])['c'] ?? 0);
        if ($n >= GameConfig::int('corp.max_members', 8)) {
            return ['ok' => false, 'error' => 'Corporazione al completo.'];
        }
        Database::run("INSERT INTO corp_members (player_id, corp_id, role) VALUES (?, ?, 'member')", [$player['id'], $corp['id']]);
        Database::run('UPDATE players SET corp_id = ? WHERE id = ?', [$corp['id'], $player['id']]);
        return ['ok' => true, 'corp' => self::of((int) $player['id'])];
    }

    /** @param array<string,mixed> $player */
    public static function leave(array $player): array
    {
        $m = Database::first('SELECT * FROM corp_members WHERE player_id = ?', [$player['id']]);
        if ($m === null) {
            return ['ok' => false, 'error' => 'Non sei in nessuna corporazione.'];
        }
        $cid = (int) $m['corp_id'];
        $others = (int) (Database::first('SELECT COUNT(*) c FROM corp_members WHERE corp_id = ? AND player_id <> ?', [$cid, $player['id']])['c'] ?? 0);

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('DELETE FROM corp_members WHERE player_id = ?', [$player['id']]);
            Database::run('UPDATE players SET corp_id = NULL WHERE id = ?', [$player['id']]);
            Database::run('UPDATE planets SET corp_id = NULL, owner_player_id = ? WHERE corp_id = ? AND owner_player_id = ?', [$player['id'], $cid, $player['id']]);

            if ($others === 0) {
                Database::run('UPDATE planets SET corp_id = NULL WHERE corp_id = ?', [$cid]);
                Database::run('DELETE FROM corporations WHERE id = ?', [$cid]);
            } elseif ($m['role'] === 'ceo') {
                $heir = Database::first('SELECT player_id FROM corp_members WHERE corp_id = ? ORDER BY joined_at LIMIT 1', [$cid]);
                Database::run("UPDATE corp_members SET role = 'ceo' WHERE player_id = ?", [$heir['player_id']]);
                Database::run('UPDATE corporations SET ceo_player_id = ? WHERE id = ?', [$heir['player_id'], $cid]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return ['ok' => true, 'disbanded' => $others === 0];
    }

    /** @param array<string,mixed> $player */
    public static function treasury(array $player, int $amount, string $dir): array
    {
        $corp = self::of((int) $player['id']);
        if ($corp === null) {
            return ['ok' => false, 'error' => 'Non sei in una corporazione.'];
        }
        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'Importo non valido.'];
        }
        if (!Database::first('SELECT 1 x FROM sectors WHERE id = ? AND is_stardock = 1', [(int) $player['sector_id']])) {
            return ['ok' => false, 'error' => 'Operazioni di cassa solo allo StarDock.'];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $p = Database::first('SELECT credits FROM players WHERE id = ? FOR UPDATE', [$player['id']]);
            $c = Database::first('SELECT treasury FROM corporations WHERE id = ? FOR UPDATE', [$corp['id']]);
            $cr = (int) $p['credits'];
            $tr = (int) $c['treasury'];
            if ($dir === 'deposit') {
                if ($cr < $amount) {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => 'Crediti insufficienti.'];
                }
                $cr -= $amount; $tr += $amount;
            } else {
                if ((string) $corp['role'] !== 'ceo') {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => 'Solo il CEO puo\' prelevare.'];
                }
                if ($tr < $amount) {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => 'Cassa insufficiente.'];
                }
                $cr += $amount; $tr -= $amount;
            }
            Database::run('UPDATE players SET credits = ? WHERE id = ?', [$cr, $player['id']]);
            Database::run('UPDATE corporations SET treasury = ? WHERE id = ?', [$tr, $corp['id']]);
            $pdo->commit();
            return ['ok' => true, 'credits' => $cr, 'treasury' => $tr];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
