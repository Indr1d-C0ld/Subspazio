# Notiziario di SubSpazio

I numeri del bollettino rivolto ai giocatori, in ordine di uscita. Sorgente
HTML: è la forma canonica, il PDF si rigenera da qui.

| Uscita | Numero | Contenuto |
|---|---|---|
| 12/09/2026 | [Dispaccio di Flotta](2026-09-12-dispaccio-di-flotta.html) | Una settimana di meccaniche nuove: incontri di rotta, FedNews, occultamento e transwarp, mine Limpet, griglia di potenza, identità di comando, illustrazioni delle navi |
| 15/09/2026 | [Bollettino di Carenaggio](2026-09-15-bollettino-di-carenaggio.html) | Ciclo di revisione: scudo novizio esteso a mine e caccia, clock più veloce, conti di bordo blindati |

## Generare i PDF

```bash
SUBSPAZIO_URL=https://miodominio.example/subspazio/ ./genera-pdf.sh
./genera-pdf.sh 2026-09-15-bollettino-di-carenaggio.html   # un numero solo
```

I PDF non sono versionati: si ottengono in modo deterministico dall'HTML, e
tenerli nel repo aggiungerebbe solo binari da aggiornare a mano.

### L'indirizzo del server è un segnaposto

Nei sorgenti archiviati il pulsante «Entra in SubSpazio» punta a
`https://SERVER-DI-GIOCO/subspazio/`: il repo è pubblico e non deve contenere
il dominio reale. Lo script lo rimpiazza al momento di generare, se gli passi
`SUBSPAZIO_URL` — senza quella variabile il PDF esce col segnaposto, che va
bene per un'anteprima ma non per la distribuzione (lo script te lo ricorda).

Le versioni che i giocatori leggono davvero — artifact e PDF distribuito —
hanno sempre il link giusto.

## Scrivere un numero nuovo

Parti dall'ultimo: l'identità visiva è una sola per tutta la serie — IBM Plex
Sans e Mono, fondo scuro da console, accento teal `#4fd8c4`, firma di Lyra Venn
sulla Rete Comm della Flotta. Cambia il registro, non il vestito.

Il nome del file segue `AAAA-MM-GG-titolo-in-minuscolo.html`, così l'elenco si
ordina da solo.

### Due trappole, entrambe già pagate

**La stampa spezza griglie e flex.** Chromium non rispetta in modo affidabile
`break-inside: avoid` sui contenitori `display:grid` o `display:flex`: li
impagina lo stesso a metà. Per questo, dentro `@media print`, schede, quadri e
voci di elenco tornano tutti a `display:block`. Se aggiungi un componente
nuovo, trattalo allo stesso modo — è successo due volte su due di dimenticarlo.

Per verificarlo non basta guardare il PDF vero, che con poche pagine può
cavarsela per fortuna:

```bash
./genera-pdf.sh --stress        # pagine da 110mm: molti più cambi, difetti in evidenza
```

**L'ordine nella cascata.** Il blocco `@media print` deve stare **in fondo** al
foglio di stile. Una regola di schermo scritta dopo lo scavalca senza che nulla
lo segnali: una media query non aggiunge specificità, quindi vince l'ultima
dichiarazione. Anche qui, già successo.

### Prima di pubblicare

Il testo va letto anche dal lato di chi non ha scritto il codice. Per i numeri
che parlano di sicurezza vale una regola in più: si raccontano gli **esiti**,
mai i meccanismi. Dire come si sarebbe potuto manipolare qualcosa è un invito
a provarci; dire che è chiuso, e che i registri risultano integri, è ciò che
serve davvero a chi legge.
