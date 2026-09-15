#!/usr/bin/env bash
#
# Da un numero del notiziario al PDF A4 distribuibile.
#
#   ./genera-pdf.sh 2026-09-15-bollettino-di-carenaggio.html
#   ./genera-pdf.sh                      # tutti i numeri
#
# Nei sorgenti archiviati l'indirizzo del server e' un segnaposto
# (https://SERVER-DI-GIOCO/subspazio/), perche' il repo pubblico non deve
# contenere il dominio reale. Qui viene reiniettato al momento di generare,
# cosi' il PDF distribuibile ha il link giusto:
#
#   SUBSPAZIO_URL=https://miodominio.example/subspazio/ ./genera-pdf.sh
#
# Senza la variabile il PDF esce col segnaposto — utile per un'anteprima,
# non per la distribuzione.
#
# I file HTML sono pensati per lo schermo: qui vengono avvolti in un documento
# completo con `@page` A4 e passati a Chromium headless. Le regole di stampa
# stanno gia' dentro ogni numero, nel blocco @media print.
#
# Nota di metodo, imparata a caro prezzo su entrambi i numeri: Chromium non
# rispetta in modo affidabile `break-inside: avoid` sui contenitori grid e
# flex, che vengono spezzati a meta' pagina lo stesso. Per questo in stampa
# schede, quadri e voci di elenco tornano tutti a `display: block`. Se aggiungi
# un componente nuovo a un numero, ricordati di trattarlo allo stesso modo —
# e verifica con --stress, che accorcia la pagina per forzare molti piu' cambi
# di quanti ne avrebbe il documento vero.

set -euo pipefail
cd "$(dirname "$0")"

STRESS=0
if [[ "${1:-}" == "--stress" ]]; then
    STRESS=1
    shift
fi

chromium_bin() {
    for c in chromium chromium-browser google-chrome; do
        command -v "$c" >/dev/null 2>&1 && { echo "$c"; return; }
    done
    echo "Serve Chromium (o Google Chrome) per generare il PDF." >&2
    exit 1
}
CHROME="$(chromium_bin)"

SEGNAPOSTO='https://SERVER-DI-GIOCO/subspazio/'
if [[ -z "${SUBSPAZIO_URL:-}" ]]; then
    echo "  (nota: SUBSPAZIO_URL non impostata, i link resteranno al segnaposto)" >&2
fi

if [[ "$STRESS" == "1" ]]; then
    PAGINA='@page { size: 210mm 110mm; margin: 8mm; }'
    SUFFISSO='-stress'
else
    PAGINA='@page { size: A4; margin: 16mm 14mm 18mm; }'
    SUFFISSO=''
fi

numeri=("$@")
if [[ ${#numeri[@]} -eq 0 ]]; then
    mapfile -t numeri < <(ls -1 ????-??-??-*.html)
fi

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

for src in "${numeri[@]}"; do
    [[ -f "$src" ]] || { echo "manca: $src" >&2; continue; }

    titolo="$(sed -n 's:.*<title>\(.*\)</title>.*:\1:p' "$src" | head -1)"
    out="${titolo} - SubSpazio${SUFFISSO}.pdf"
    doc="$tmp/$(basename "$src")"

    {
        printf '<!doctype html>\n<html lang="it">\n<head>\n<meta charset="utf-8">\n'
        printf '<title>%s</title>\n' "$titolo"
        grep -o '<link [^>]*>' "$src" | head -20
        printf '<style>%s</style>\n</head>\n<body>\n' "$PAGINA"
        if [[ -n "${SUBSPAZIO_URL:-}" ]]; then
            sed "s#$SEGNAPOSTO#${SUBSPAZIO_URL}#g" "$src"
        else
            cat "$src"
        fi
        printf '\n</body>\n</html>\n'
    } > "$doc"

    "$CHROME" --headless --no-sandbox --disable-gpu \
        --print-to-pdf="$out" --print-to-pdf-no-header --no-pdf-header-footer \
        --run-all-compositor-stages-before-draw --virtual-time-budget=4000 \
        "$doc" >/dev/null 2>&1

    pagine="$(pdfinfo "$out" 2>/dev/null | sed -n 's/^Pages: *//p')"
    printf '  %-46s -> %s (%s pagine)\n' "$src" "$out" "${pagine:-?}"
done
