<?php
$sec = static function (string $icon, string $title, string $body, string $classe = ''): string {
    return '<section class="panel guide-sec' . ($classe !== '' ? ' ' . $classe : '') . '"><h2><span class="sec-ic">' . $icon . '</span> '
        . e($title) . '</h2>' . $body . '</section>';
};
?>
<section class="statusbar">
  <div><span class="k">Guida</span><span class="v">SubSpazio</span></div>
  <div><a href="<?= e(url('/gioco')) ?>">← Plancia</a></div>
</section>

<section class="panel">
  <h1><span class="sec-ic">📖</span> Guida rapida</h1>
  <p class="hint">Riferimento sintetico dei sistemi. Ogni voce rimanda alla pagina relativa.</p>
</section>

<div class="guide-grid">
  <?= $sec('🚀', 'Turni & Warp',
      '<p>Hai un budget di <strong>turni</strong> che si ricarica ogni giorno (ora di Roma, reset alle 03:00). '
    . 'Ogni salto di <strong>warp</strong> verso un settore adiacente costa 1+ turni secondo lo scafo. '
    . 'Il <strong>computer di bordo</strong> (in plancia) traccia rotte e autopilota verso settori già esplorati. '
    . 'I settori bassi sono <strong>Federazione</strong>: niente attacchi, niente mine.</p>') ?>

  <?php
  // I numeri vengono dalla configurazione: la guida non promette cio' che il
  // pannello ha cambiato.
  $righeFasce = '';
  for ($fb = 1; $fb <= \App\Game\Fasce::MAX; $fb++) {
      [$ca, $cz] = \App\Game\Fasce::predoniCaccia($fb);
      $righeFasce .= '<tr class="fascia-' . $fb . '"><td><span class="tag fascia fascia-' . $fb . '">' . e(\App\Game\Fasce::ROMANI[$fb]) . '</span> '
          . e(\App\Game\Fasce::NOMI[$fb]) . '</td>'
          . '<td>' . number_format($ca, 0, ',', '.') . '–' . number_format($cz, 0, ',', '.')
          . ($fb >= \App\Game\Fasce::ferrengiDa() ? ' + Ferrengi' : '') . '</td>'
          . '<td>' . \App\Game\Fasce::ingaggioPct($fb) . '%</td>'
          . '<td>+' . (int) round(100 * \App\Game\Economy::premioFascia(['band' => $fb])) . '%</td>'
          . '<td>' . ($fb <= \App\Game\Fasce::razziaFinoA() ? 'razzia' : 'distruzione') . '</td></tr>';
  }
  ?>
  <?= $sec('🎯', 'Fasce di rischio',
      '<p>Attorno a Sol lo spazio è diviso in <strong>cinque fasce</strong>. Più ti allontani, più i nemici sono forti e '
    . 'attaccano spesso, ma più rendono i porti, il bottino (più probabile e più raro), le anomalie e le missioni. '
    . 'La fascia del settore è indicata in plancia; il numero romano sui bottoni di warp è quella di destinazione, '
    . 'e un salto che ne sale molte in una volta chiede conferma.</p>'
    . '<div class="table-wrap"><table class="tbl compact tbl-fasce"><thead><tr><th>Fascia</th><th>Caccia dei predoni</th><th>Ingaggio</th>'
    . '<th>Porti pagano</th><th>Se perdi</th></tr></thead><tbody>' . $righeFasce . '</tbody></table></div>'
    . '<p>Una <strong>razzia</strong> ti lascia la nave ma senza caccia né scudi, e i predoni si prendono il carico e il '
    . \App\Game\Fasce::razziaCreditiPct() . '% dei crediti a bordo (quelli in banca sono al sicuro). Poi per '
    . \App\Game\Fasce::treguaMin() . ' minuti i predoni di quelle fasce ti lasciano stare.</p>', 'guide-wide') ?>

  <?= $sec('🏪', 'Porti & contrattazione',
      '<p>Nei settori con un <strong>porto</strong> compri e vendi le tre merci (minerale, organico, equipaggiamento) a prezzi '
    . 'dinamici basati su domanda/offerta locale. Lo <strong>StarDock</strong> è il porto speciale, sempre presente e inespugnabile. '
    . 'Puoi <strong>contrattare</strong> (offerta/controproposta): le bande sono strette, non sperare miracoli. '
    . '<a href="' . e(url('/gioco/porto')) . '">Porto</a> · <a href="' . e(url('/gioco/mercato-nero')) . '">Mercato nero</a></p>') ?>

  <?= $sec('🏦', 'Banca IGB',
      '<p>Allo StarDock depositi crediti in banca e maturano <strong>interessi</strong> ogni giorno. '
    . 'Utile per non perdere metà del patrimonio se ti distruggono la nave. '
    . '<a href="' . e(url('/gioco/banca')) . '">Banca</a></p>') ?>

  <?= $sec('⚔️', 'Combattimento',
      '<p>Fuori dalla Federazione puoi attaccare navi, porti, pianeti e <strong>NPC</strong> (pirati, Ferrengi, mercanti). '
    . 'Il duello è a caccia con scudi; attaccare costa turni. Vicino a Sol chi perde contro un NPC viene <strong>razziato</strong> '
    . 'invece che distrutto (vedi «Fasce di rischio»). Se ti distruggono sopravvivi in <strong>capsula di salvataggio</strong> '
    . 'allo StarDock: perdi carico e moduli installati (in parte recuperati in Leghe). Dei crediti a bordo, chi ti abbatte '
    . 'ne prende metà; senza <strong>capsula di salvataggio</strong> ne perdi anche metà di quelli rimasti, con la capsula nulla. '
    . 'Se sei a secco chiedi una nave di soccorso al Cantiere: finché non ne compri una vera, chi la abbatte non ne ricava '
    . 'né esperienza né bottino, come da una capsula. '
    . 'Puoi dispiegare <strong>caccia</strong> e <strong>mine</strong> nei settori. '
    . '<a href="' . e(url('/gioco/battaglie')) . '">Registro battaglie</a></p>') ?>

  <?php
  // Anche qui i numeri vengono dalla configurazione.
  $soglieLegge = \App\Game\Legge::soglie();
  $effettiGradi = [
      1 => 'la Federazione ti tiene d\'occhio',
      2 => 'pattuglie a vista, una squadra d\'intercettazione ti caccia, taglia sulla testa',
      3 => 'due squadre, StarDock chiuso',
      4 => 'tre squadre pesanti, in ogni fascia',
  ];
  $righeLegge = '';
  foreach ($effettiGradi as $g => $effetto) {
      $righeLegge .= '<tr><td>' . e(\App\Game\Legge::nome($g)) . '</td><td>' . (int) $soglieLegge[$g - 1] . '</td><td>' . e($effetto) . '</td></tr>';
  }
  $crimini = [];
  foreach (\App\Game\Legge::ETICHETTE as $k => $etichetta) {
      $crimini[] = e(mb_strtolower($etichetta)) . ' ' . \App\Game\Legge::peso($k);
  }
  $perPunto = \App\Game\GameConfig::int('legge.taglia_per_punto', 300);
  ?>
  <?= $sec('⚖️', 'Legge & taglie',
      '<p>Aggredire chi non ti ha fatto niente alza la <strong>notorietà</strong>: ' . implode(', ', $crimini) . ' punti. '
    . 'Ogni crimine delle ultime ' . \App\Game\GameConfig::int('legge.recidiva_ore', 72) . ' ore fa pesare il successivo il '
    . (int) round(100 * \App\Game\GameConfig::float('legge.recidiva_passo', 0.25)) . '% in più; la notorietà si dimezza da sola ogni '
    . \App\Game\GameConfig::int('legge.dimezza_ore', 24) . ' ore. Pirati, Ferrengi, ricercati e comandanti fuorilegge si possono attaccare senza colpa.</p>'
    . '<div class="table-wrap"><table class="tbl compact"><thead><tr><th>Gradino</th><th>Da</th><th>Cosa succede</th></tr></thead><tbody>'
    . $righeLegge . '</tbody></table></div>'
    . '<p>Da Ricercato hai una <strong>taglia</strong> di ' . number_format($perPunto, 0, ',', '.') . ' cr per punto, e la '
    . '<strong>paghi tu</strong>: chi ti abbatte la incassa, confiscata prima dai tuoi crediti a bordo e poi dal tuo conto in banca, '
    . 'fino all\'importo. Se non hai nulla, non incassa nulla: la Federazione non mette crediti di tasca sua. Lo stesso vale per la '
    . 'taglia che si accumula uccidendo comandanti onesti (il ' . (int) round(100 * \App\Game\GameConfig::float('combat.bounty_pct', 0.1))
    . '% di ogni bottino). Abbattuto o arrestato da una pattuglia, torni sotto la soglia di Ricercato.</p>'
    . '<p>L\'<strong>ammenda</strong> azzera tutto, da ovunque: ' . number_format(\App\Game\GameConfig::int('legge.ammenda_per_punto', 400), 0, ',', '.')
    . ' cr per punto, di più se sei recidivo. Il mercato nero ripulisce la taglia da uccisioni. '
    . '<a href="' . e(url('/gioco/fazioni')) . '">Fazioni</a> · <a href="' . e(url('/gioco/mercato-nero')) . '">Mercato nero</a></p>', 'guide-wide') ?>

  <?= $sec('🛠️', 'Cantiere & hardware',
      '<p>Allo StarDock compri <strong>navi</strong> (con permuta), potenzi stive/caccia/scudi e installi hardware: '
    . 'sonde, mine, scanner, transwarp, occultamento, <strong>laser minerario</strong>, capsula. '
    . '<a href="' . e(url('/gioco/cantiere')) . '">Cantiere</a></p>') ?>

  <?= $sec('🌫️', 'Occultamento & Transwarp',
      '<p>L\'<strong>occultamento</strong> (hardware Cantiere) ti toglie dai sensori: ti vede solo chi ha uno '
    . 'scanner olografico nel tuo settore, e gli ostili possono non agganciarti. Ma è un attraversamento, non un rifugio: '
    . 'il dispositivo ha una <strong>riserva</strong> di ' . \App\Game\Cloak::caricaMax() . ' cariche (una per salto, ne torna una ogni '
    . \App\Game\Cloak::ricaricaMin() . ' minuti); ogni aggancio può <strong>scoprirti</strong> (dal 5% nella Cintura al 40% nell\'Orlo, '
    . '+20% contro pattuglie ed élite, meno coi modelli rari del dispositivo); e <strong>cade</strong> se apri il fuoco, commerci, '
    . 'attracchi a un pianeta, spogli un relitto, estrai, scansioni, attracchi allo StarDock o salti in Transwarp. '
    . '+' . \App\Game\Cloak::warpPenalty() . ' turno/i per warp, vietato in spazio Federazione, non ferma mine né Quasar.</p>'
    . '<p>Il <strong>drive Transwarp</strong> salta in un colpo verso qualunque settore <em>già esplorato</em>, '
    . 'ignorando le rotte, a costo fisso in turni. Comando in plancia, «Computer di bordo».</p>') ?>

  <?= $sec('🔩', 'Moduli & Officina',
      '<p>Combattimenti e relitti lasciano <strong>moduli</strong> di 5 rarità (Civile→Precursore) che si installano negli '
    . '<strong>slot</strong> dello scafo e ne cambiano le statistiche. Ce ne sono 18 <strong>famiglie</strong>: cannoni, hangar '
    . '(più caccia), interdizione (i mercantili non scappano), scudi, rigenerazione, deflettori, corazza, schermi ambientali, '
    . 'propulsori, manovra (elusione degli agganci), sensori, guerra elettronica, analisi del bottino, transponder (meno '
    . 'notorietà), stive, recupero, occultamento, fabbrica di caccia. Quelli trovati hanno spesso <strong>affissi</strong> '
    . 'casuali («…dell\'Assalto e della Fortuna») che aggiungono effetti: due moduli uguali non lo sono mai. In officina li '
    . 'smonti (→ Leghe di recupero), li potenzi alla rarità successiva <em>della stessa famiglia</em> (gli affissi restano), '
    . 'o li <strong>produci su ricetta</strong> con la raffineria. '
    . '<a href="' . e(url('/gioco/moduli')) . '">Officina moduli</a></p>') ?>

  <?= $sec('👥', 'Equipaggio & missioni',
      '<p>Recluti <strong>ufficiali</strong> (6 ruoli) che occupano i posti dello scafo: danno bonus passivi, un\'abilità attiva '
    . 'e alimentano le <strong>missioni away</strong> a skill-check con esiti da Trionfo a Disastro. Salgono di livello, hanno una '
    . 'missione di lealtà. '
    . '<a href="' . e(url('/gioco/equipaggio')) . '">Equipaggio</a> · <a href="' . e(url('/gioco/missioni')) . '">Missioni</a></p>') ?>

  <?= $sec('📡', 'Scansione & frontiera',
      '<p>La <strong>scansione</strong> (dalla scheda settore, costa turni) rivela relitti, depositi, anomalie, giacimenti e '
    . '<strong>pericoli</strong> ambientali — anche nei settori vicini con scanner o uno Scienziato in plancia. '
    . 'Più lontano da Sol le scoperte sono più ricche e i pericoli più duri (nella Cintura di Sol non ce ne sono); '
    . 'gli hazard colpiscono all\'ingresso, meno se li conosci. '
    . 'Il <a href="' . e(url('/gioco/codex')) . '">Codex</a> raccoglie le scoperte.</p>') ?>

  <?= $sec('🚩', 'Fazioni & reputazione',
      '<p>Quattro potenze (Federazione, Consorzio Ferrengi, Egemonia di Korr, Liberi Mondi). La <strong>reputazione</strong> '
    . 'si muove con commercio, kill, missioni e assalti, e sblocca empori ed esenzioni. Se la Federazione ti diventa <strong>ostile</strong> '
    . 'i servizi StarDock si chiudono e arrivano cacciatori di taglie. '
    . '<a href="' . e(url('/gioco/fazioni')) . '">Fazioni</a></p>') ?>

  <?= $sec('🪐', 'Pianeti & industria',
      '<p>Coi <strong>siluri Genesi</strong> crei pianeti; li colonizzi, producono merce, costruisci Citadel e cannone Quasar. '
    . 'Un pianeta tuo può passare in <strong>modalità industria</strong>: converte il minerale di scorta in Componenti per te. '
    . '<a href="' . e(url('/gioco/pianeti')) . '">Pianeti</a></p>') ?>

  <?= $sec('🏆', 'Meta',
      '<p><strong>Stagioni</strong> con ladder e Albo d\'Oro, <strong>traguardi</strong>, <strong>corporazioni</strong> e alleanze, '
    . '<strong>contratti</strong> e taglie fra giocatori, <strong>radio</strong> subspaziale. '
    . '<a href="' . e(url('/gioco/classifica')) . '">Classifica</a> · <a href="' . e(url('/gioco/traguardi')) . '">Traguardi</a> · '
    . '<a href="' . e(url('/gioco/corp')) . '">Corp</a> · <a href="' . e(url('/gioco/albo')) . '">Albo</a></p>') ?>
</div>
