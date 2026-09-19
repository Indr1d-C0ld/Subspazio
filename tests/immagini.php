<?php

declare(strict_types=1);

/**
 * Immagini del profilo: inquadratura e approvazione automatica.
 *
 * Prima ogni avatar restava fermo in una coda finche' l'amministratore non lo
 * guardava. Ora entra in linea da solo, e chi carica decide l'inquadratura dal
 * browser. Qui si verifica la meta' che gira sul server: il ritaglio, la
 * ricodifica e il nuovo percorso di approvazione — compreso il fatto che la
 * vecchia coda sia ancora li', pronta se si cambia idea.
 *
 * NESSUN file reale viene toccato: proprietario sintetico fuori intervallo,
 * immagini generate qui, tutto cancellato alla fine.
 */

use App\Core\Database;
use App\Game\GameConfig;
use App\Game\MediaAsset;

return static function (): void {
    /** Proprietario inesistente: media_assets non ha vincoli sull'owner. */
    $finto = 900000001;

    $temporanei = [];

    /** Genera un'immagine di prova e restituisce la voce di $_FILES. */
    $immagine = static function (int $w, int $h, string $formato = 'jpeg') use (&$temporanei): array {
        $im = imagecreatetruecolor($w, $h);
        // Due bande: serve a riconoscere DOVE ha tagliato, non solo quanto.
        imagefilledrectangle($im, 0, 0, $w, (int) ($h / 2), imagecolorallocate($im, 220, 40, 40));
        imagefilledrectangle($im, 0, (int) ($h / 2), $w, $h, imagecolorallocate($im, 40, 40, 220));
        $f = (string) tempnam(sys_get_temp_dir(), 'sstest');
        match ($formato) {
            'webp' => imagewebp($im, $f, 92),
            'png'  => imagepng($im, $f, 6),
            default => imagejpeg($im, $f, 90),
        };
        imagedestroy($im);
        $temporanei[] = $f;
        return ['name' => 'prova.' . $formato, 'tmp_name' => $f, 'error' => UPLOAD_ERR_OK, 'size' => (int) filesize($f)];
    };

    $autoPrima = (string) GameConfig::str('media.auto_approve', '1');

    try {
        // --- la chiave -----------------------------------------------------

        Esito::sezione('Immagini — chi decide se pubblicarle');

        Esito::scenario('l\'approvazione automatica e\' una chiave, non una scelta scolpita');
        GameConfig::set('media.auto_approve', '1');
        GameConfig::forget();
        Esito::verifica('accesa, approva da sola', MediaAsset::autoApprove());
        GameConfig::set('media.auto_approve', '0');
        GameConfig::forget();
        Esito::verifica('spenta, si torna alla coda', !MediaAsset::autoApprove());
        GameConfig::set('media.auto_approve', '1');
        GameConfig::forget();

        // --- ricodifica -----------------------------------------------------

        Esito::sezione('Immagini — il ritaglio sul server');

        Esito::scenario('un avatar esce quadrato, comunque entri');
        $r = MediaAsset::storeUpload('player', $finto, 'avatar', $immagine(900, 600), true);
        Esito::verifica('il caricamento riesce', !empty($r['ok']), implode(' ', $r['errors'] ?? []));
        $a = $r['asset'] ?? [];
        $lato = (int) MediaAsset::KINDS['avatar']['box'];
        Esito::uguale('largo quanto il riquadro', $lato, (int) $a['width']);
        Esito::uguale('alto quanto il riquadro', $lato, (int) $a['height']);
        Esito::uguale('ricodificato in PNG', 'image/png', (string) $a['mime']);
        Esito::verifica('il file esiste davvero', is_file(MediaAsset::absPath($a)));

        Esito::scenario('un logo invece mantiene le proporzioni');
        $rl = MediaAsset::storeUpload('player', $finto, 'logo', $immagine(900, 600), true);
        $l = $rl['asset'] ?? [];
        Esito::verifica('non viene squadrato', (int) $l['width'] !== (int) $l['height'],
            (int) $l['width'] . '×' . (int) $l['height']);
        Esito::verifica('rientra nel lato massimo', max((int) $l['width'], (int) $l['height']) <= $lato);

        Esito::scenario('il formato che manda l\'inquadratura dal browser');
        if (function_exists('imagewebp')) {
            // ritaglio.js esporta in WebP: se il server lo rifiutasse, il
            // ritaglio lato client non arriverebbe mai a destinazione.
            $rw = MediaAsset::storeUpload('player', $finto, 'avatar', $immagine(700, 700, 'webp'), true);
            Esito::verifica('il WebP viene accettato', !empty($rw['ok']), implode(' ', $rw['errors'] ?? []));
            Esito::uguale('e riesce quadrato', $lato, (int) ($rw['asset']['width'] ?? 0));
        } else {
            Esito::verifica('GD senza WebP: prova saltata', true, 'imagewebp assente');
        }

        Esito::scenario('cio\' che non e\' un\'immagine non entra');
        $falso = (string) tempnam(sys_get_temp_dir(), 'sstest');
        file_put_contents($falso, "<?php echo 'non sono una foto';");
        $temporanei[] = $falso;
        $rf = MediaAsset::storeUpload('player', $finto, 'avatar', [
            'name' => 'finta.png', 'tmp_name' => $falso, 'error' => UPLOAD_ERR_OK, 'size' => (int) filesize($falso),
        ], true);
        Esito::verifica('viene respinto', empty($rf['ok']));

        Esito::scenario('nemmeno un francobollo');
        $rp = MediaAsset::storeUpload('player', $finto, 'avatar', $immagine(16, 16), true);
        Esito::verifica('viene respinto', empty($rp['ok']));

        // --- approvazione ---------------------------------------------------

        Esito::sezione('Immagini — l\'amministratore esce dal passaggio');

        Esito::scenario('con la chiave accesa l\'immagine e\' subito in linea');
        $ra = MediaAsset::storeUpload('player', $finto, 'avatar', $immagine(800, 800), true);
        $aa = Database::first('SELECT * FROM media_assets WHERE id = ?', [(int) $ra['asset']['id']]);
        Esito::uguale('lo stato e\' approvato', 'approved', (string) $aa['status']);
        Esito::verifica('il chiamante lo sa', !empty($ra['auto_approved']));
        // Nessuno l'ha guardata: attribuirla a qualcuno sarebbe una bugia in
        // tabella, e il registro di moderazione serve proprio a distinguere.
        Esito::verifica('nessun revisore le viene attribuito', $aa['reviewed_by'] === null);
        Esito::uguale('e si capisce perche\'', 'approvazione automatica', (string) $aa['review_note']);
        MediaAsset::flushCache();
        Esito::verifica('gli altri comandanti la vedono', MediaAsset::current('player', $finto, 'avatar') !== null);

        Esito::scenario('una nuova immagine ritira la precedente');
        $vecchioId = (int) $ra['asset']['id'];
        $rb = MediaAsset::storeUpload('player', $finto, 'avatar', $immagine(640, 480), true);
        Esito::uguale(
            'la vecchia viene messa da parte',
            'rejected',
            (string) Database::first('SELECT status FROM media_assets WHERE id = ?', [$vecchioId])['status']
        );
        MediaAsset::flushCache();
        $viva = MediaAsset::current('player', $finto, 'avatar');
        Esito::uguale('resta in linea solo la nuova', (int) $rb['asset']['id'], (int) $viva['id']);
        Esito::uguale(
            'una sola approvata alla volta',
            1,
            (int) Database::first(
                "SELECT COUNT(*) n FROM media_assets WHERE owner_id = ? AND kind = 'avatar' AND status = 'approved'",
                [$finto]
            )['n']
        );

        Esito::scenario('la vecchia coda e\' ancora tutta li\'');
        // Se un giorno si torna alla moderazione, non deve servire una riga di
        // codice nuova: solo la chiave da spegnere.
        $rc = MediaAsset::storeUpload('player', $finto, 'avatar', $immagine(500, 500), false);
        Esito::uguale(
            'senza approvazione resta in attesa',
            'pending',
            (string) Database::first('SELECT status FROM media_assets WHERE id = ?', [(int) $rc['asset']['id']])['status']
        );
        MediaAsset::flushCache();
        Esito::uguale(
            'e agli altri continua a mostrarsi la precedente',
            (int) $rb['asset']['id'],
            (int) MediaAsset::current('player', $finto, 'avatar')['id']
        );
        Esito::verifica(
            'l\'amministratore la trova nella sua coda',
            in_array((int) $rc['asset']['id'], array_map(
                static fn (array $m): int => (int) $m['id'],
                MediaAsset::queue()
            ), true)
        );

        // --- l'aggancio con il browser ---------------------------------------

        Esito::sezione('Immagini — l\'inquadratura lato browser');

        // Prova statica: il ritaglio vero e' nel browser e qui non gira. Serve
        // a che il riquadro non si scolleghi in silenzio dalle misure reali —
        // il caso in cui cambia KINDS['avatar']['box'] e nessuno se ne accorge.
        $vista = (string) file_get_contents(dirname(__DIR__) . '/views/game/profilo.php');
        $js    = dirname(__DIR__) . '/assets/js/ritaglio.js';
        Esito::verifica('il ritagliatore e\' al suo posto', is_file($js));
        Esito::verifica('la vista lo carica', str_contains($vista, 'js/ritaglio.js'));
        Esito::verifica(
            'il riquadro prende la misura da KINDS, non da un numero scritto a mano',
            (bool) preg_match("/data-ritaglio=\"' \\. \\(int\\) \\\$meta\\['box'\\]/", $vista)
        );
        Esito::verifica(
            'e si attacca solo ai tipi quadrati',
            str_contains($vista, "!empty(\$meta['square']) ?")
        );
        $sorgente = (string) file_get_contents($js);
        Esito::verifica(
            'senza canvas o DataTransfer si tira indietro',
            str_contains($sorgente, "typeof HTMLCanvasElement === 'undefined' || !window.DataTransfer")
        );
    } finally {
        GameConfig::set('media.auto_approve', $autoPrima);
        GameConfig::forget();

        // Prima i file, poi le righe: ogni percorso viene dal database. Alcuni
        // sono gia' spariti da soli — chi sostituisce un'immagine porta via la
        // precedente — e va benissimo: quello che conta e' che non ne resti
        // nessuno.
        $cartella = null;
        foreach (Database::all('SELECT * FROM media_assets WHERE owner_id = ?', [$finto]) as $m) {
            $abs = MediaAsset::absPath($m);
            $cartella ??= dirname($abs);
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
        Database::run('DELETE FROM media_assets WHERE owner_id = ?', [$finto]);
        foreach ($temporanei as $t) {
            @unlink($t);
        }
        MediaAsset::flushCache();

        Esito::uguale(
            'nessuna riga di prova lasciata indietro',
            0,
            (int) Database::first('SELECT COUNT(*) n FROM media_assets WHERE owner_id = ?', [$finto])['n']
        );
        $avanzi = $cartella !== null ? (glob($cartella . '/*') ?: []) : [];
        Esito::uguale('e nessun file rimasto sul disco', 0, count($avanzi));
        // Solo la cartella del proprietario sintetico: quella sopra la
        // condividono i giocatori veri.
        if ($cartella !== null && $avanzi === [] && is_dir($cartella)) {
            @rmdir($cartella);
        }
    }
};
