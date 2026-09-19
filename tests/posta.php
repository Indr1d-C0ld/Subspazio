<?php

declare(strict_types=1);

/**
 * Coda della posta in uscita.
 *
 * Portata da Atlantik, dove era nata perché un audit aveva scoperto messaggi
 * persi quando l'SMTP non rispondeva. Qui regge la verifica dell'indirizzo,
 * che è l'unica porta d'ingresso al gioco: un'e-mail perduta è un giocatore
 * che non entra mai più.
 *
 * Nessuna prova spedisce davvero: `Posta::trasportoDiProva()` sostituisce il
 * Mailer con una funzione controllata, e si può chiamare solo da riga di
 * comando proprio perché non svegli la casella di nessuno.
 */

use App\Core\Database;
use App\Core\Posta;
use App\Game\GameConfig;

return static function (): void {
    $creati = [];
    $tettoSalvato = GameConfig::str('mail.tetto_24h', '140');

    try {
        Esito::sezione('Coda posta — consegna riuscita');

        Posta::trasportoDiProva(static fn () => ['ok' => true]);
        Esito::scenario('un messaggio che parte al primo colpo');
        $r = Posta::invia('__test_a@invalid.test', 'oggetto', 'corpo', 'prova', 1);
        $creati[] = (int) $r['id'];
        $m = Database::first('SELECT inviato_at, tentativi, rinunciato_at FROM mail_queue WHERE id = ?', [$r['id']]);

        Esito::verifica('riporta esito positivo', !empty($r['ok']));
        Esito::verifica('risulta inviato', $m['inviato_at'] !== null);
        Esito::uguale('un solo tentativo', 1, (int) $m['tentativi']);

        Esito::sezione('Coda posta — SMTP che non risponde');

        Posta::trasportoDiProva(static fn () => ['ok' => false, 'error' => 'connessione rifiutata']);
        Esito::scenario('il messaggio non si perde: resta in coda per il prossimo giro');
        $r2 = Posta::invia('__test_b@invalid.test', 'oggetto', 'corpo', 'prova', 1);
        $creati[] = (int) $r2['id'];
        $m2 = Database::first(
            'SELECT inviato_at, rinunciato_at, tentativi, ultimo_errore,
                    TIMESTAMPDIFF(MINUTE, NOW(), prossimo_at) fra_minuti
             FROM mail_queue WHERE id = ?',
            [$r2['id']]
        );

        Esito::verifica('l\'esito dice che è rimandato', empty($r2['ok']));
        Esito::verifica('non risulta inviato', $m2['inviato_at'] === null);
        Esito::verifica('non è stato abbandonato', $m2['rinunciato_at'] === null);
        Esito::uguale('ha consumato un tentativo', 1, (int) $m2['tentativi']);
        Esito::uguale('conserva il motivo', 'connessione rifiutata', (string) $m2['ultimo_errore']);
        // La prima attesa è di 1 minuto: si verifica col calcolo fatto DAL
        // database, non confrontando date MySQL con l'orologio di PHP.
        Esito::verifica(
            'il prossimo tentativo è programmato a breve',
            (int) $m2['fra_minuti'] >= 0 && (int) $m2['fra_minuti'] <= 2,
            'fra ' . (int) $m2['fra_minuti'] . ' min'
        );

        Esito::scenario('dopo troppi tentativi si rinuncia, invece di riprovare in eterno');
        $max = GameConfig::int('mail.max_tentativi', 6);
        for ($i = 0; $i < $max + 2; $i++) {
            Database::run('UPDATE mail_queue SET prossimo_at = NOW() WHERE id = ?', [$r2['id']]);
            Posta::tenta((int) $r2['id']);
        }
        $m3 = Database::first('SELECT tentativi, rinunciato_at FROM mail_queue WHERE id = ?', [$r2['id']]);
        Esito::uguale('i tentativi si fermano al massimo configurato', $max, (int) $m3['tentativi']);
        Esito::verifica('il messaggio risulta abbandonato', $m3['rinunciato_at'] !== null);

        Esito::sezione('Coda posta — tetto del provider');

        Esito::scenario('raggiunto il tetto, si rimanda senza bruciare un tentativo');
        GameConfig::set('mail.tetto_24h', '1');
        GameConfig::forget();
        Posta::trasportoDiProva(static fn () => ['ok' => true]);
        $r3 = Posta::invia('__test_c@invalid.test', 'oggetto', 'corpo', 'prova', 1);
        $creati[] = (int) $r3['id'];
        $m4 = Database::first('SELECT tentativi, inviato_at, ultimo_errore FROM mail_queue WHERE id = ?', [$r3['id']]);

        Esito::verifica('non parte', empty($r3['ok']) && $m4['inviato_at'] === null);
        Esito::uguale('nessun tentativo consumato', 0, (int) $m4['tentativi']);
        Esito::verifica(
            'e il motivo è il tetto, non un errore di consegna',
            str_contains((string) $m4['ultimo_errore'], 'tetto'),
            (string) $m4['ultimo_errore']
        );

        GameConfig::set('mail.tetto_24h', $tettoSalvato);
        GameConfig::forget();

        Esito::sezione('Coda posta — smistamento e priorità');

        Esito::scenario('la verifica (priorità 1) passa davanti agli avvisi (priorità 7)');
        Posta::trasportoDiProva(static fn () => ['ok' => true]);
        $bassa = Posta::accoda('__test_bassa@invalid.test', 'avviso', 'corpo', 'prova', 7);
        $alta  = Posta::accoda('__test_alta@invalid.test', 'verifica', 'corpo', 'prova', 1);
        $creati[] = $bassa;
        $creati[] = $alta;

        $esito = Posta::smista(1);   // ne tenta uno solo: deve essere quello urgente
        $mAlta  = Database::first('SELECT inviato_at FROM mail_queue WHERE id = ?', [$alta]);
        $mBassa = Database::first('SELECT inviato_at FROM mail_queue WHERE id = ?', [$bassa]);

        Esito::uguale('ne smista uno', 1, (int) $esito['tentati']);
        Esito::verifica('ed è quello a priorità alta', $mAlta['inviato_at'] !== null);
        Esito::verifica('quello a priorità bassa aspetta ancora', $mBassa['inviato_at'] === null);

        Esito::sezione('Coda posta — riepilogo di stato');

        $stato = Posta::stato();
        foreach (['in_coda', 'inviate_24h', 'rinunciate', 'tetto'] as $chiave) {
            Esito::verifica("riporta «{$chiave}»", is_int($stato[$chiave] ?? null));
        }
    } finally {
        Posta::trasportoDiProva(null);
        GameConfig::set('mail.tetto_24h', $tettoSalvato);
        GameConfig::forget();
        Database::run("DELETE FROM mail_queue WHERE destinatario LIKE '\\_\\_test%'");
        $residui = (int) (Database::first(
            "SELECT COUNT(*) n FROM mail_queue WHERE destinatario LIKE '\\_\\_test%'"
        )['n'] ?? 0);
        Esito::uguale('nessun messaggio di prova lasciato in coda', 0, $residui);
    }
};
