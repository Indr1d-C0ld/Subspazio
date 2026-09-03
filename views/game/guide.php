<?php
$sec = static function (string $title, string $body): string {
    return '<section class="panel guide-sec"><h2>' . e($title) . '</h2>' . $body . '</section>';
};
?>
<section class="statusbar">
  <div><span class="k">Guida</span><span class="v">SubSpazio</span></div>
  <div><a href="<?= e(url('/gioco')) ?>">← Plancia</a></div>
</section>

<section class="panel">
  <h1>Guida rapida</h1>
  <p class="hint">Riferimento sintetico dei sistemi. Ogni voce rimanda alla pagina relativa.</p>
</section>

<div class="guide-grid">
  <?= $sec('Turni & Warp',
      '<p>Hai un budget di <strong>turni</strong> che si ricarica ogni giorno (ora di Roma, reset alle 03:00). '
    . 'Ogni salto di <strong>warp</strong> verso un settore adiacente costa 1+ turni secondo lo scafo. '
    . 'Il <strong>computer di bordo</strong> (in plancia) traccia rotte e autopilota verso settori già esplorati. '
    . 'I settori bassi sono <strong>Federazione</strong>: niente attacchi, niente mine.</p>') ?>

  <?= $sec('Porti & contrattazione',
      '<p>Nei settori con un <strong>porto</strong> compri e vendi le tre merci (minerale, organico, equipaggiamento) a prezzi '
    . 'dinamici basati su domanda/offerta locale. Lo <strong>StarDock</strong> è il porto speciale, sempre presente e inespugnabile. '
    . 'Puoi <strong>contrattare</strong> (offerta/controproposta): le bande sono strette, non sperare miracoli. '
    . '<a href="' . e(url('/gioco/porto')) . '">Porto</a> · <a href="' . e(url('/gioco/mercato-nero')) . '">Mercato nero</a></p>') ?>

  <?= $sec('Banca IGB',
      '<p>Allo StarDock depositi crediti in banca e maturano <strong>interessi</strong> ogni giorno. '
    . 'Utile per non perdere metà del patrimonio se ti distruggono la nave. '
    . '<a href="' . e(url('/gioco/banca')) . '">Banca</a></p>') ?>

  <?= $sec('Combattimento',
      '<p>Fuori dalla Federazione puoi attaccare navi, porti, pianeti e <strong>NPC</strong> (pirati, Ferrengi, mercanti). '
    . 'Il duello è a caccia con scudi; attaccare costa turni. Se ti distruggono sopravvivi in <strong>capsula di salvataggio</strong> '
    . 'allo StarDock (perdi carico, moduli installati e metà crediti; se sei a secco chiedi una nave di soccorso al Cantiere). '
    . 'Puoi dispiegare <strong>caccia</strong> e <strong>mine</strong> nei settori. '
    . '<a href="' . e(url('/gioco/battaglie')) . '">Registro battaglie</a></p>') ?>

  <?= $sec('Cantiere & hardware',
      '<p>Allo StarDock compri <strong>navi</strong> (con permuta), potenzi stive/caccia/scudi e installi hardware: '
    . 'sonde, mine, scanner, transwarp, occultamento, <strong>laser minerario</strong>, capsula. '
    . '<a href="' . e(url('/gioco/cantiere')) . '">Cantiere</a></p>') ?>

  <?= $sec('Moduli & Officina',
      '<p>Combattimenti e relitti lasciano <strong>moduli</strong> di 5 fasce (Civile→Precursore) che si installano negli '
    . '<strong>slot</strong> dello scafo e ne cambiano le statistiche. In officina li smonti (→ Leghe di recupero), li potenzi '
    . 'di fascia, o li <strong>produci su ricetta</strong> con la raffineria. '
    . '<a href="' . e(url('/gioco/moduli')) . '">Officina moduli</a></p>') ?>

  <?= $sec('Equipaggio & missioni',
      '<p>Recluti <strong>ufficiali</strong> (6 ruoli) che occupano i posti dello scafo: danno bonus passivi, un\'abilità attiva '
    . 'e alimentano le <strong>missioni away</strong> a skill-check con esiti da Trionfo a Disastro. Salgono di livello, hanno una '
    . 'missione di lealtà. '
    . '<a href="' . e(url('/gioco/equipaggio')) . '">Equipaggio</a> · <a href="' . e(url('/gioco/missioni')) . '">Missioni</a></p>') ?>

  <?= $sec('Scansione & frontiera',
      '<p>La <strong>scansione</strong> (dalla scheda settore, costa turni) rivela relitti, depositi, anomalie, giacimenti e '
    . '<strong>pericoli</strong> ambientali — anche nei settori vicini con scanner o uno Scienziato in plancia. '
    . 'Le regioni di frontiera e profonde sono più ostili ma più ricche; gli hazard colpiscono all\'ingresso, meno se li conosci. '
    . 'Il <a href="' . e(url('/gioco/codex')) . '">Codex</a> raccoglie le scoperte.</p>') ?>

  <?= $sec('Fazioni & reputazione',
      '<p>Quattro potenze (Federazione, Consorzio Ferrengi, Egemonia di Korr, Liberi Mondi). La <strong>reputazione</strong> '
    . 'si muove con commercio, kill, missioni e assalti, e sblocca empori ed esenzioni. Se la Federazione ti diventa <strong>ostile</strong> '
    . 'i servizi StarDock si chiudono e arrivano cacciatori di taglie. '
    . '<a href="' . e(url('/gioco/fazioni')) . '">Fazioni</a></p>') ?>

  <?= $sec('Pianeti & industria',
      '<p>Coi <strong>siluri Genesi</strong> crei pianeti; li colonizzi, producono merce, costruisci Citadel e cannone Quasar. '
    . 'Un pianeta tuo può passare in <strong>modalità industria</strong>: converte il minerale di scorta in Componenti per te. '
    . '<a href="' . e(url('/gioco/pianeti')) . '">Pianeti</a></p>') ?>

  <?= $sec('Meta',
      '<p><strong>Stagioni</strong> con ladder e Albo d\'Oro, <strong>traguardi</strong>, <strong>corporazioni</strong> e alleanze, '
    . '<strong>contratti</strong> e taglie fra giocatori, <strong>radio</strong> subspaziale. '
    . '<a href="' . e(url('/gioco/classifica')) . '">Classifica</a> · <a href="' . e(url('/gioco/traguardi')) . '">Traguardi</a> · '
    . '<a href="' . e(url('/gioco/corp')) . '">Corp</a> · <a href="' . e(url('/gioco/albo')) . '">Albo</a></p>') ?>
</div>
