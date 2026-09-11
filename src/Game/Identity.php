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
        'teschio', 'fenice', 'ancora', 'fulmine', 'nova', 'chiave',
        'scudo', 'spada', 'ala', 'bussola', 'ingranaggio', 'serpente',
        'atomo', 'diamante', 'luna', 'sole',
    ];
    public const DEFAULT_CREST = 'delta';

    /** Uno stemma a caso fra i curati — assegnato ai nuovi comandanti alla creazione. */
    public static function randomCrest(): string
    {
        return self::CRESTS[array_rand(self::CRESTS)];
    }

    /** Etichette leggibili degli stemmi astratti. */
    public const CREST_LABELS = [
        'delta' => 'Delta', 'orbita' => 'Orbita', 'stella' => 'Stella', 'corona' => 'Corona',
        'mirino' => 'Mirino', 'cometa' => 'Cometa', 'sciabole' => 'Sciabole', 'esagono' => 'Esagono',
        'tridente' => 'Tridente', 'occhio' => 'Occhio', 'alloro' => 'Alloro', 'rotta' => 'Rotta',
        'teschio' => 'Teschio', 'fenice' => 'Fenice', 'ancora' => 'Ancora', 'fulmine' => 'Fulmine',
        'nova' => 'Nova', 'chiave' => 'Chiave',
        'scudo' => 'Scudo', 'spada' => 'Spada', 'ala' => 'Ala', 'bussola' => 'Bussola',
        'ingranaggio' => 'Ingranaggio', 'serpente' => 'Serpente', 'atomo' => 'Atomo',
        'diamante' => 'Diamante', 'luna' => 'Luna', 'sole' => 'Sole',
    ];

    /**
     * Sagome di nave selezionabili come marca di flotta (prefisso "nave:").
     * Le chiavi = ship_types.ckey; i <symbol id="ship-…"> stanno in
     * partials/ship_sprite.php.
     */
    public const SHIP_MARKS = [
        'escape_pod', 'scout_marauder', 'merchant_cruiser', 'missile_frigate',
        'constellation', 'merchant_freighter', 'cargo_transport', 'colonial_transport',
        'corporate_flagship', 'havoc_gunstar', 'imperial_starship', 'tholian_sentinel',
        'interdictor',
    ];
    public const SHIP_MARK_LABELS = [
        'escape_pod' => 'Capsula di salvataggio', 'scout_marauder' => 'Scout Marauder',
        'merchant_cruiser' => 'Merchant Cruiser', 'missile_frigate' => 'Missile Frigate',
        'constellation' => 'Constellation', 'merchant_freighter' => 'Merchant Freighter',
        'cargo_transport' => 'Cargo Transport', 'colonial_transport' => 'Colonial Transport',
        'corporate_flagship' => 'Corporate Flagship', 'havoc_gunstar' => 'Havoc Gunstar',
        'imperial_starship' => 'Imperial StarShip', 'tholian_sentinel' => 'Tholian Sentinel',
        'interdictor' => 'Interdictor Cruiser',
    ];

    /** true se $k è uno stemma astratto valido o una sagoma "nave:<ckey>". */
    public static function validCrest(string $k): bool
    {
        if (in_array($k, self::CRESTS, true)) {
            return true;
        }
        if (str_starts_with($k, 'nave:')) {
            return in_array(substr($k, 5), self::SHIP_MARKS, true);
        }
        return false;
    }

    /** id del <symbol> SVG da referenziare con <use href="#…">. */
    public static function symbolId(string $crest): string
    {
        if (str_starts_with($crest, 'nave:') && in_array(substr($crest, 5), self::SHIP_MARKS, true)) {
            return 'ship-' . substr($crest, 5);
        }
        return 'crest-' . (in_array($crest, self::CRESTS, true) ? $crest : self::DEFAULT_CREST);
    }

    /** Etichetta leggibile di uno stemma o di una sagoma di nave. */
    public static function crestLabel(string $crest): string
    {
        if (str_starts_with($crest, 'nave:')) {
            return self::SHIP_MARK_LABELS[substr($crest, 5)] ?? substr($crest, 5);
        }
        return self::CREST_LABELS[$crest] ?? $crest;
    }

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
        return self::validCrest($c) ? $c : self::DEFAULT_CREST;
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
        if ($crest !== '' && !self::validCrest($crest)) {
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
