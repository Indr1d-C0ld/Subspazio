<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Config;
use App\Core\Database;

/**
 * Immagini caricate dagli utenti (avatar del comandante, logo di flotta).
 * Pipeline: upload -> validazione -> ricodifica GD (spoglia EXIF/payload) ->
 * file in storage/uploads/ -> riga `pending` -> approvazione admin.
 * Solo gli asset `approved` sono serviti agli altri giocatori.
 */
final class MediaAsset
{
    /** Vincoli per tipo. box = lato max in px del risultato ricodificato. */
    public const KINDS = [
        'avatar' => ['box' => 512, 'square' => true,  'label' => 'Avatar'],
        'logo'   => ['box' => 512, 'square' => false, 'label' => 'Logo di flotta'],
    ];

    /** MIME accettati in ingresso (l'output e' sempre PNG). */
    private const IN_MIME = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

    private const MIN_SIDE = 32;
    private const MAX_SIDE_IN = 4096;

    /** @var array<string,array<string,mixed>|null> cache di richiesta per current() */
    private static array $cache = [];

    // --- lettura ------------------------------------------------------------

    /** Svuota la cache di richiesta di current() (usata da approve/reject/remove; utile nei test). */
    public static function flushCache(): void
    {
        self::$cache = [];
    }

    /** Limite effettivo: il minimo fra config e limiti del php.ini (onesto verso l'utente). */
    public static function maxBytes(): int
    {
        $limits = [(int) Config::get('media.max_bytes', 2 * 1024 * 1024)];
        $up = self::iniBytes((string) ini_get('upload_max_filesize'));
        if ($up > 0) {
            $limits[] = $up;
        }
        $post = self::iniBytes((string) ini_get('post_max_size'));
        if ($post > 0) {
            $limits[] = $post - 16 * 1024; // margine per gli altri campi del form
        }
        return max(64 * 1024, (int) min($limits));
    }

    private static function iniBytes(string $v): int
    {
        $v = trim($v);
        if ($v === '') {
            return 0;
        }
        $n = (int) $v;
        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1024 * 1024 * 1024,
            'm' => $n * 1024 * 1024,
            'k' => $n * 1024,
            default => $n,
        };
    }

    /**
     * Ultimo asset approvato per (owner, kind) il cui file esiste ancora su
     * disco, o null. Il controllo del file fa sì che una perdita di dati
     * (file sparito ma riga rimasta) degradi con grazia allo stemma invece
     * di mostrare un'immagine rotta.
     * @return array<string,mixed>|null
     */
    public static function current(string $ownerType, int $ownerId, string $kind): ?array
    {
        $key = "$ownerType:$ownerId:$kind";
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }
        $row = Database::first(
            "SELECT * FROM media_assets
             WHERE owner_type = ? AND owner_id = ? AND kind = ? AND status = 'approved'
             ORDER BY id DESC LIMIT 1",
            [$ownerType, $ownerId, $kind]
        );
        if ($row !== null && !is_file(self::absPath($row))) {
            $row = null;
        }
        return self::$cache[$key] = $row;
    }

    /** true se l'asset ha ancora il suo file su disco. @param array<string,mixed> $asset */
    public static function fileExists(array $asset): bool
    {
        return isset($asset['path']) && is_file(self::absPath($asset));
    }

    /** Asset in attesa per (owner, kind), o null. @return array<string,mixed>|null */
    public static function pendingFor(string $ownerType, int $ownerId, string $kind): ?array
    {
        return Database::first(
            "SELECT * FROM media_assets
             WHERE owner_type = ? AND owner_id = ? AND kind = ? AND status = 'pending'
             ORDER BY id DESC LIMIT 1",
            [$ownerType, $ownerId, $kind]
        );
    }

    /**
     * Stato per la UI del profilo: per ogni kind, l'asset approvato, quello in
     * coda, l'ultimo rifiutato (per la motivazione) e un flag se l'approvato
     * ha perso il file su disco.
     * @return array<string,array{approved:?array<string,mixed>,pending:?array<string,mixed>,rejected:?array<string,mixed>,missing:bool}>
     */
    public static function forOwner(string $ownerType, int $ownerId): array
    {
        $out = [];
        foreach (array_keys(self::KINDS) as $kind) {
            $rejected = Database::first(
                "SELECT * FROM media_assets
                 WHERE owner_type = ? AND owner_id = ? AND kind = ? AND status = 'rejected'
                 ORDER BY id DESC LIMIT 1",
                [$ownerType, $ownerId, $kind]
            );
            $approvedRow = Database::first(
                "SELECT * FROM media_assets
                 WHERE owner_type = ? AND owner_id = ? AND kind = ? AND status = 'approved'
                 ORDER BY id DESC LIMIT 1",
                [$ownerType, $ownerId, $kind]
            );
            $missing = $approvedRow !== null && !self::fileExists($approvedRow);
            $out[$kind] = [
                'approved' => $missing ? null : $approvedRow,
                'pending'  => self::pendingFor($ownerType, $ownerId, $kind),
                'rejected' => $rejected,
                'missing'  => $missing,
            ];
        }
        return $out;
    }

    /** Coda di moderazione: tutti i pending con l'handle del proprietario. @return list<array<string,mixed>> */
    public static function queue(): array
    {
        return Database::all(
            "SELECT m.*, p.handle AS owner_handle
             FROM media_assets m
             LEFT JOIN players p ON p.id = m.owner_id AND m.owner_type = 'player'
             WHERE m.status = 'pending'
             ORDER BY m.created_at ASC"
        );
    }

    public static function pendingCount(): int
    {
        return (int) (Database::first("SELECT COUNT(*) AS n FROM media_assets WHERE status = 'pending'")['n'] ?? 0);
    }

    public static function get(int $id): ?array
    {
        return Database::first('SELECT * FROM media_assets WHERE id = ?', [$id]);
    }

    // --- percorsi ---------------------------------------------------------

    /**
     * Radice dei file caricati. In produzione va tenuta FUORI dall'albero di
     * git (come /data/subspazio-config/): un `git clean` o un redeploy non deve
     * poter cancellare i contenuti degli utenti. `paths.uploads` nel config
     * punta lì; il fallback sotto storage/ serve solo allo sviluppo.
     */
    private static function uploadsRoot(): string
    {
        $custom = trim((string) Config::get('paths.uploads', ''));
        if ($custom !== '') {
            return rtrim($custom, '/');
        }
        $root = (string) (Config::get('paths.root') ?: ($GLOBALS['__project_root'] ?? getcwd()));
        return rtrim($root, '/') . '/storage/uploads';
    }

    /** Percorso assoluto del file di un asset. @param array<string,mixed> $asset */
    public static function absPath(array $asset): string
    {
        return self::uploadsRoot() . '/' . ltrim((string) $asset['path'], '/');
    }

    // --- scrittura ------------------------------------------------------

    /**
     * Valida + ricodifica + registra un upload. Di norma entra come `pending`
     * e sostituisce l'eventuale pending precedente; con $autoApprove (upload
     * fatto da un admin) entra direttamente come `approved`, ritirando quello
     * approvato prima.
     *
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int} $file  voce di $_FILES
     * @return array{ok:bool, errors?:list<string>, asset?:array<string,mixed>, auto_approved?:bool}
     */
    public static function storeUpload(string $ownerType, int $ownerId, string $kind, array $file, bool $autoApprove = false): array
    {
        if (!isset(self::KINDS[$kind])) {
            return ['ok' => false, 'errors' => ['Tipo di immagine non valido.']];
        }
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'errors' => ['Nessun file selezionato.']];
        }
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return ['ok' => false, 'errors' => ['Il file supera la dimensione massima consentita.']];
        }
        if ($err !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'errors' => ['Caricamento non riuscito (codice ' . $err . ').']];
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_readable($tmp) || (!is_uploaded_file($tmp) && PHP_SAPI !== 'cli')) {
            return ['ok' => false, 'errors' => ['File temporaneo non valido.']];
        }

        $bytes = (int) (@filesize($tmp) ?: 0);
        if ($bytes <= 0) {
            return ['ok' => false, 'errors' => ['File vuoto.']];
        }
        if ($bytes > self::maxBytes()) {
            return ['ok' => false, 'errors' => [
                'Immagine troppo pesante (max ' . self::humanBytes(self::maxBytes()) . ').',
            ]];
        }

        $info = @getimagesize($tmp);
        if ($info === false) {
            return ['ok' => false, 'errors' => ['Il file non e\' un\'immagine valida.']];
        }
        [$w, $h] = $info;
        $mime = (string) ($info['mime'] ?? '');
        if (!in_array($mime, self::IN_MIME, true)) {
            return ['ok' => false, 'errors' => ['Formato non supportato: usa PNG, JPEG, WebP o GIF.']];
        }
        if ($w < self::MIN_SIDE || $h < self::MIN_SIDE) {
            return ['ok' => false, 'errors' => ['Immagine troppo piccola (minimo ' . self::MIN_SIDE . '×' . self::MIN_SIDE . ').']];
        }
        if ($w > self::MAX_SIDE_IN || $h > self::MAX_SIDE_IN) {
            return ['ok' => false, 'errors' => ['Immagine troppo grande (massimo ' . self::MAX_SIDE_IN . 'px per lato).']];
        }

        try {
            $png = self::reencode($tmp, $kind);
        } catch (\Throwable $e) {
            return ['ok' => false, 'errors' => ['Impossibile elaborare l\'immagine. Riprova con un altro file.']];
        }

        $sha1 = sha1($png['data']);
        $rel  = $ownerType . '/' . $ownerId . '/' . $kind . '-' . substr($sha1, 0, 16) . '.png';
        $abs  = self::uploadsRoot() . '/' . $rel;

        if (!self::ensureDir(dirname($abs))) {
            return ['ok' => false, 'errors' => ['Archiviazione non disponibile: contatta l\'amministratore.']];
        }
        if (@file_put_contents($abs, $png['data'], LOCK_EX) === false) {
            return ['ok' => false, 'errors' => ['Scrittura del file non riuscita.']];
        }
        @chmod($abs, 0664); // gruppo in scrittura: web (www-data) e CLI condividono il gruppo via setgid

        // registra il nuovo pending PRIMA di ritirare il vecchio, cosi' purgeFile()
        // non cancella un file condiviso (stesso sha1) fra i due.
        Database::run(
            "INSERT INTO media_assets (owner_type, owner_id, kind, status, mime, ext, bytes, width, height, sha1, path)
             VALUES (?, ?, ?, 'pending', 'image/png', 'png', ?, ?, ?, ?, ?)",
            [$ownerType, $ownerId, $kind, strlen($png['data']), $png['w'], $png['h'], $sha1, $rel]
        );
        $id = Database::lastInsertId();

        // ritira l'eventuale pending precedente (file compreso, se non condiviso)
        foreach (Database::all(
            "SELECT * FROM media_assets
             WHERE owner_type = ? AND owner_id = ? AND kind = ? AND status = 'pending' AND id <> ?",
            [$ownerType, $ownerId, $kind, $id]
        ) as $prev) {
            self::purgeFile($prev);
            Database::run(
                "UPDATE media_assets SET status = 'rejected', review_note = 'sostituito da un nuovo caricamento', reviewed_at = NOW() WHERE id = ?",
                [(int) $prev['id']]
            );
        }

        if ($autoApprove) {
            self::promote($id, $ownerId, 'auto-approvato (admin)');
        }

        return ['ok' => true, 'asset' => self::get($id) ?? [], 'auto_approved' => $autoApprove];
    }

    public static function approve(int $id, int $adminId): array
    {
        $a = self::get($id);
        if ($a === null) {
            return ['ok' => false, 'error' => 'Asset inesistente.'];
        }
        if ($a['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'Solo gli asset in attesa possono essere approvati.'];
        }
        self::promote($id, $adminId, null);
        return ['ok' => true];
    }

    /** Porta un asset a `approved`, ritirando quello approvato prima per lo stesso (owner, kind). */
    private static function promote(int $id, int $reviewerId, ?string $note): void
    {
        $a = self::get($id);
        if ($a === null) {
            return;
        }
        // Ritira OGNI altro approvato per lo stesso (owner, kind) — via DB, non
        // via current(), che ora filtra per file presente e mancherebbe le
        // righe il cui file e' andato perso.
        foreach (Database::all(
            "SELECT * FROM media_assets
             WHERE owner_type = ? AND owner_id = ? AND kind = ? AND status = 'approved' AND id <> ?",
            [(string) $a['owner_type'], (int) $a['owner_id'], (string) $a['kind'], $id]
        ) as $prev) {
            self::purgeFile($prev);
            Database::run(
                "UPDATE media_assets SET status = 'rejected', review_note = 'sostituito da #{$id}', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?",
                [$reviewerId, (int) $prev['id']]
            );
        }
        Database::run(
            "UPDATE media_assets SET status = 'approved', review_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?",
            [$note, $reviewerId, $id]
        );
        self::$cache = [];
    }

    public static function reject(int $id, int $adminId, string $note = ''): array
    {
        $a = self::get($id);
        if ($a === null) {
            return ['ok' => false, 'error' => 'Asset inesistente.'];
        }
        if ($a['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'Solo gli asset in attesa possono essere rifiutati.'];
        }
        self::purgeFile($a);
        Database::run(
            "UPDATE media_assets SET status = 'rejected', review_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?",
            [mb_substr(trim($note), 0, 255) ?: 'Non conforme alle linee guida.', $adminId, $id]
        );
        self::$cache = [];
        return ['ok' => true];
    }

    /** Rimozione da parte del proprietario (pending o approved). */
    public static function removeOwn(int $id, string $ownerType, int $ownerId): array
    {
        $a = self::get($id);
        if ($a === null || (string) $a['owner_type'] !== $ownerType || (int) $a['owner_id'] !== $ownerId) {
            return ['ok' => false, 'error' => 'Immagine non trovata.'];
        }
        if (!in_array($a['status'], ['pending', 'approved'], true)) {
            return ['ok' => false, 'error' => 'Niente da rimuovere.'];
        }
        self::purgeFile($a);
        Database::run('DELETE FROM media_assets WHERE id = ?', [$id]);
        self::$cache = [];
        return ['ok' => true];
    }

    // --- interni ---------------------------------------------------------

    /** @return array{data:string,w:int,h:int} */
    private static function reencode(string $tmp, string $kind): array
    {
        $raw = (string) file_get_contents($tmp);
        $src = @imagecreatefromstring($raw);
        if ($src === false) {
            throw new \RuntimeException('decode');
        }
        try {
            $sw = imagesx($src);
            $sh = imagesy($src);
            $box = (int) self::KINDS[$kind]['box'];
            $square = (bool) self::KINDS[$kind]['square'];

            if ($square) {
                $side = min($sw, $sh);
                $sx = (int) (($sw - $side) / 2);
                $sy = (int) (($sh - $side) / 2);
                $dw = $dh = min($box, $side);
                $dst = self::blankCanvas($dw, $dh);
                imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $dw, $dh, $side, $side);
            } else {
                $scale = min(1.0, $box / max($sw, $sh));
                $dw = max(1, (int) round($sw * $scale));
                $dh = max(1, (int) round($sh * $scale));
                $dst = self::blankCanvas($dw, $dh);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $dw, $dh, $sw, $sh);
            }

            ob_start();
            imagepng($dst, null, 6);
            $data = (string) ob_get_clean();
            imagedestroy($dst);

            if ($data === '') {
                throw new \RuntimeException('encode');
            }
            return ['data' => $data, 'w' => $dw, 'h' => $dh];
        } finally {
            imagedestroy($src);
        }
    }

    private static function blankCanvas(int $w, int $h): \GdImage
    {
        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        return $im;
    }

    private static function ensureDir(string $dir): bool
    {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        // Forza setgid + scrittura di gruppo sui livelli sotto uploads/: il bit
        // di gruppo che la umask del processo toglie va rimesso, cosi' web
        // (www-data) e CLI (proprietario del repo) — stesso gruppo via setgid —
        // possono entrambi gestire i file. Best-effort: @chmod fallisce in
        // silenzio sulle dir non di proprieta' del processo corrente.
        $root = rtrim(self::uploadsRoot(), '/');
        if (str_starts_with($dir, $root)) {
            $path = $root;
            foreach (array_filter(explode('/', trim(substr($dir, strlen($root)), '/'))) as $seg) {
                $path .= '/' . $seg;
                @chmod($path, 02775);
            }
        }
        return is_dir($dir);
    }

    /**
     * Rimuove il file di un asset, ma solo se nessun'altra riga punta allo
     * stesso path (due upload identici condividono il file per via dello sha1).
     * @param array<string,mixed> $asset
     */
    private static function purgeFile(array $asset): void
    {
        $rel = (string) ($asset['path'] ?? '');
        if ($rel === '') {
            return;
        }
        $shared = (int) (Database::first(
            'SELECT COUNT(*) AS n FROM media_assets WHERE path = ? AND id <> ?',
            [$rel, (int) ($asset['id'] ?? 0)]
        )['n'] ?? 0);
        if ($shared > 0) {
            return;
        }
        $p = self::absPath($asset);
        if ($p !== '' && is_file($p)) {
            @unlink($p);
        }
    }

    private static function humanBytes(int $b): string
    {
        return $b >= 1024 * 1024
            ? round($b / (1024 * 1024), 1) . ' MB'
            : max(1, (int) round($b / 1024)) . ' KB';
    }
}
