# Test di integrazione

```bash
php tests/run.php                 # tutti
php tests/run.php economica       # solo i file col nome corrispondente
```

Esce con codice `0` se tutto passa e non restano righe sintetiche, `1` altrimenti.

## Come funzionano

Non c'è un database di prova separato: i test girano su quello configurato in
`config.php`, ma creano e distruggono **solo righe sintetiche** col prefisso
`__test_`. Nessun dato reale viene letto o scritto.

La pulizia è registrata come *shutdown function*, quindi avviene anche se un
test muore per errore fatale. A ogni avvio il runner spazza inoltre via
eventuali resti di corse interrotte in precedenza, e alla fine verifica che il
conteggio dei residui sia zero.

Da evitare durante una sessione di gioco affollata: le prove aprono
transazioni su tabelle vive.

## Scrivere un nuovo test

Un file in questa cartella che restituisce una closure. `_harness.php` mette a
disposizione `Esito` (verifiche) e `Finti` (comandanti e navi usa-e-getta).

```php
<?php
return static function (): void {
    Esito::sezione('Che cosa sto verificando');

    [$p, $s] = Finti::comandante(1000, ['hold_ore' => 50]);

    Esito::scenario('descrizione della situazione di partenza');
    $r = App\Game\Qualcosa::azione($p, $s);

    Esito::verifica('l\'azione riesce', !empty($r['ok']), $r['error'] ?? '');
    Esito::uguale('il saldo è quello atteso', 800, Finti::crediti((int) $p['id']));
};
```

`Finti::ricarica($playerId)` rilegge dal database: serve a distinguere quel che
il codice ha scritto davvero da quel che la fotografia in memoria sosteneva —
distinzione al centro dei test sull'integrità economica.

## Che cosa è coperto oggi

| File | Copre |
|---|---|
| `integrita_economica.php` | Reperti 01 e 02 dell'audit: addebiti con guardia di capienza, scambi tutto-o-niente, confisca limitata al saldo reale, guardia sui nomi di colonna |
| `percorsi_normali.php` | Non-regressione: cantiere, mercato nero, porto in acquisto e vendita, i task del tick toccati dalla correzione |
| `temi.php` | Temi grafici: catalogo, fogli e caratteri coerenti (con la licenza accanto), ritorno solo a pagine interne, scelta sull'account e nel cookie, il tema più scelto e l'avviso sui temi nel notiziario, gli aiuti che elencano i temi dal catalogo |
| `sesto_audit.php` | Sesto audit (09/10/2026): notiziario intero in radio, avvisi di servizio corretti (taglie, moduli guasti, ammenda), al più due comunicati e quelli scaduti tolti dalla plancia, omicidio contato anche su capsule e navi di soccorso, mercantile già sparito che non costa nulla, abilità usata una volta per ricarica, stive guaste, `chiusuraAmmessa` in sola lettura (dentro una transazione annullata), annuncio di fine stagione dopo l'azzeramento |
| `quinto_audit.php` | Quinto audit (09/10/2026): niente premio per l'aggressore su nave di soccorso, attacco respinto che non brucia Nucleo e occultamento, hangar guasto, doppio «accendi», stalli rilanciati solo dentro le transazioni, stagione gia' chiusa, stream morti |
| `quarto_audit.php` | Quarto audit (09/10/2026): gare su NPC, potenziamenti, navi, consumabili e arresti; taglie confiscate; ingaggi del clock su righe rilette; hangar, affissi, occultamento, fasce, traguardi, stream, migrazioni in attesa |

Le prove sull'integrità economica passano alle funzioni la **stessa fotografia
più volte**: è esattamente ciò che vedrebbero due richieste concorrenti, e
riproduce la corsa senza dover orchestrare processi in parallelo.

**Mai chiamare dalle prove le operazioni che azzerano il gioco** — chiusura di
stagione (`Season::close`), Big Bang, rigenerazione dell'universo. Si provano
le regole che le governano (es. `Season::chiusuraAmmessa`) o il sorgente,
mai l'operazione. Il 09/10/2026 una prova che chiamava `Season::close` con un
parametro di sicurezza nuovo e' stata lanciata, per controprova, su una copia
del codice di prima: quel codice il parametro non lo conosceva, PHP l'ha
ignorato, e la stagione in corso e' stata chiusa davvero (poi riaperta).

Quando la corsa sta nel database (un lucchetto, una riga cambiata fra lettura
e scrittura) la fotografia non basta. `quarto_audit.php` fa la prima richiesta
a mano dentro una transazione aperta (blocca la riga, la cambia), lancia la
seconda in un processo separato (`_corsa.php`) e conferma solo quando quella
e' ferma ad aspettare: l'intreccio e' sempre lo stesso, senza affidarsi al
caso.
