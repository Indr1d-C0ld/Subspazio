<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Identità del comandante e della flotta (Slice #1 roadmap post-beta):
 * colore d'accento, stemma di flotta, motto, registro della nave.
 * Il "titolo" non si salva: si deriva da grado + tier di fazione.
 */
final class Identity
{
    /** Palette curata: accenti leggibili sul tema scuro. chiave => etichetta. */
    public const PALETTE = [
        '#6be2ff' => 'Ciano di plancia',
        '#b394ff' => 'Viola nebulosa',
        '#5fe3a1' => 'Verde comm',
        '#ffcf6b' => 'Ambra allerta',
        '#ff8f8f' => 'Rosso segnale',
        '#8fb2ff' => 'Blu Federazione',
        '#ff9d5c' => 'Arancio Ferrengi',
        '#c9d24a' => 'Oro Egemonia',
        '#7fe0c4' => 'Turchese Frontiera',
        '#e58fd0' => 'Magenta',
        '#a7b4c4' => 'Grigio acciaio',
        '#d7e3f2' => 'Bianco stellare',
    ];
    public const DEFAULT_COLOR = '#6be2ff';

    /** Stemmi curati: le chiavi corrispondono ai <symbol> in partials/crest_sprite.php */
    public const CRESTS = [
        'delta', 'orbita', 'stella', 'corona', 'mirino', 'cometa',
        'sciabole', 'esagono', 'tridente', 'occhio', 'alloro', 'rotta',
    ];
    public const DEFAULT_CREST = 'delta';

    /** @param array<string,mixed> $player @return array{color:string,crest:string,motto:string,title:string} */
    public static function forPlayer(array $player): array
    {
        return [
            'color' => self::color($player),
            'crest' => self::crest($player),
            'motto' => (string) ($player['motto'] ?? ''),
            'title' => self::title($player),
        ];
    }

    /** @param array<string,mixed> $p */
    public static function color(array $p): string
    {
        $c = (string) ($p['color'] ?? '');
        return isset(self::PALETTE[$c]) ? $c : self::DEFAULT_COLOR;
    }

    /** @param array<string,mixed> $p */
    public static function crest(array $p): string
    {
        $c = (string) ($p['crest'] ?? '');
        return in_array($c, self::CRESTS, true) ? $c : self::DEFAULT_CREST;
    }

    /** Titolo derivato: grado + suffisso di fazione (o "Fuorilegge"). @param array<string,mixed> $player */
    public static function title(array $player): string
    {
        $rank = Ranks::title((int) ($player['experience'] ?? 0));
        $suffix = '';
        try {
            $reps  = Faction::all((int) $player['id']);
            $names = [
                'fed' => 'della Federazione', 'ferrengi' => 'del Consorzio Ferrengi',
                'hegemony' => "dell'Egemonia", 'frontier' => 'della Frontiera',
            ];
            if (Faction::tier((int) ($reps['fed'] ?? 0)) === 'hostile') {
                $suffix = ' · Fuorilegge';
            } else {
                $best = null;
                $bestVal = 0;
                foreach ($reps as $k => $v) {
                    if (Faction::tier((int) $v) === 'allied' && (int) $v > $bestVal) {
                        $best = $k;
                        $bestVal = (int) $v;
                    }
                }
                if ($best !== null) {
                    $suffix = ' · Alleato ' . ($names[$best] ?? $best);
                }
            }
        } catch (\Throwable) {
        }
        return $rank . $suffix;
    }

    /**
     * @param array<string,mixed> $in campi: color, crest, motto, ship_name, registry
     * @return array{ok:bool, errors?:list<string>}
     */
    public static function save(int $playerId, int $shipId, array $in): array
    {
        $color    = (string) ($in['color'] ?? '');
        $crest    = (string) ($in['crest'] ?? '');
        $motto    = trim((string) ($in['motto'] ?? ''));
        $shipName = trim((string) ($in['ship_name'] ?? ''));
        $registry = strtoupper(trim((string) ($in['registry'] ?? '')));

        $errors = [];
        if ($color !== '' && !isset(self::PALETTE[$color])) {
            $errors[] = 'Colore non valido.';
        }
        if ($crest !== '' && !in_array($crest, self::CRESTS, true)) {
            $errors[] = 'Stemma non valido.';
        }
        if (mb_strlen($motto) > 80) {
            $errors[] = 'Il motto non può superare 80 caratteri.';
        }
        if ($shipName !== '' && mb_strlen($shipName) > 40) {
            $errors[] = 'Il nome della nave è troppo lungo (max 40).';
        }
        if ($registry !== '' && !preg_match('/^[A-Z0-9-]{1,16}$/', $registry)) {
            $errors[] = 'Registro: solo lettere maiuscole, numeri e trattino, max 16.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        Database::run(
            'UPDATE players SET color = ?, crest = ?, motto = ? WHERE id = ?',
            [$color ?: null, $crest ?: null, $motto !== '' ? mb_substr($motto, 0, 80) : null, $playerId]
        );

        $s = Database::first('SELECT type_key FROM ships WHERE id = ?', [$shipId]);
        if ($s !== null && $s['type_key'] !== 'escape_pod') {
            Database::run(
                'UPDATE ships SET name = ?, registry = ? WHERE id = ?',
                [
                    $shipName !== '' ? mb_substr($shipName, 0, 40) : null,
                    $registry !== '' ? $registry : null,
                    $shipId,
                ]
            );
        }
        return ['ok' => true];
    }
}
