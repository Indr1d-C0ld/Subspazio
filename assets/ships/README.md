# Illustrazioni dei modelli di nave

Un PNG per modello, **sfondo trasparente**, quadrato, consigliati 256–512 px.
Il file va nominato **esattamente** come la chiave `ship_types.ckey`:

- `escape_pod.png`
- `scout_marauder.png`
- `merchant_cruiser.png`
- `missile_frigate.png`
- `constellation.png`
- `merchant_freighter.png`
- `cargo_transport.png`
- `colonial_transport.png`
- `corporate_flagship.png`
- `havoc_gunstar.png`
- `imperial_starship.png`
- `tholian_sentinel.png`
- `interdictor.png`

Se un file manca, l'interfaccia ripiega automaticamente sulla sagoma SVG
vettoriale (`views/partials/ship_sprite.php`). Il rendering è gestito da
`ship_art()` / `views/partials/ship_art.php` ed è usato in plancia,
classifica, «navi qui» e catalogo del Cantiere.
