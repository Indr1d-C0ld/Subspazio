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

Le prove sull'integrità economica passano alle funzioni la **stessa fotografia
più volte**: è esattamente ciò che vedrebbero due richieste concorrenti, e
riproduce la corsa senza dover orchestrare processi in parallelo.
