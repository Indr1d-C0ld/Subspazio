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
    . 'invece che distrutto (vedi «Fasce di rischio»); se a vincere è la scorta di un mercantile vieni <strong>respinto</strong>, '
    . 'a caccia e scudi azzerati, senza razzia. Un attacco a un NPC che arriva tardi (già abbattuto da altri, o '
    . 'cambiato nel frattempo) viene respinto senza costi: non perdi turni, consumabili né occultamento. '
    . 'Se ti distruggono sopravvivi in <strong>capsula di salvataggio</strong> '
    . 'allo StarDock: perdi carico e moduli installati (in parte recuperati in Leghe). Dei crediti a bordo, chi ti abbatte '
    . 'ne prende metà; senza <strong>capsula di salvataggio</strong> ne perdi anche metà di quelli rimasti, con la capsula nulla. '
    . 'Se sei a secco chiedi una nave di soccorso al Cantiere: finché non ne compri una vera, abbatterla non vale esperienza, '
    . 'uccisione, moduli né guadagni di reputazione, come una capsula, nemmeno quando è lei ad attaccare e tu ti difendi. '
    . 'I crediti che ha a bordo li perde comunque, e un omicidio la Federazione lo conta lo stesso. '
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
    . '<p>L\'<strong>ammenda</strong> azzera la notorietà, da ovunque: ' . number_format(\App\Game\GameConfig::int('legge.ammenda_per_punto', 400), 0, ',', '.')
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
    . \App\Game\Cloak::ricaricaMin() . ' minuti, e saltare non azzera la ricarica in corso); ogni aggancio può <strong>scoprirti</strong> (dal 5% nella Cintura al 40% nell\'Orlo, '
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
    . 'o li <strong>produci su ricetta</strong> con la raffineria. Un modulo che alza un tetto (hangar, stive) si toglie solo '
    . 'se quel che hai a bordo ci sta ancora: prima schieri i caccia in un settore o scarichi la merce; gli scudi in più si '
    . 'disperdono. Un modulo <strong>guasto</strong> non conta nei tetti finché non torna in linea: lo ripari al Cantiere, '
    . 'un Ingegnere a bordo può rimetterlo in sesto prima, e comunque si ripara da solo dopo '
    . (int) \App\Game\Subsystems::autoRepairHours() . ' ore. '
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
    . 'Alla chiusura di una stagione il gioco si ferma per qualche istante («Fine stagione in corso»): basta ricaricare la pagina poco dopo. '
    . 'In testa al notiziario della Federazione trovi i <strong>comunicati</strong> su disservizi e regole cambiate. '
    . 'L\'aspetto della plancia lo scegli tu: vedi «Temi grafici». '
    . '<a href="' . e(url('/gioco/classifica')) . '">Classifica</a> · <a href="' . e(url('/gioco/traguardi')) . '">Traguardi</a> · '
    . '<a href="' . e(url('/gioco/corp')) . '">Corp</a> · <a href="' . e(url('/gioco/albo')) . '">Albo</a></p>') ?>

  <?php
  // Il catalogo e' quello di App\Core\Temi: un tema nuovo compare qui da solo.
  $temaInUso = \App\Core\Temi::attuale();
  $righeTemi = '';
  foreach (\App\Core\Temi::CATALOGO as $k => $t) {
      $tinte = '';
      foreach ($t['anteprima'] as $c) {
          $tinte .= '<span style="background:' . e($c) . '"></span>';
      }
      $azione = $k === $temaInUso
          ? '<span class="pill ok">in uso</span>'
          : '<form method="post" action="' . e(url('/tema')) . '" class="inline">' . csrf_field()
            . '<input type="hidden" name="torna" value="/gioco/guida"><input type="hidden" name="tema" value="' . e($k) . '">'
            . '<button type="submit" class="btn xs ghost">Usa</button></form>';
      $righeTemi .= '<tr><td class="nowrap"><span class="tema-tinte" aria-hidden="true">' . $tinte . '</span> <strong>' . e($t['nome']) . '</strong></td>'
          . '<td>' . e($t['ispirazione']) . '</td><td class="ta-r nowrap">' . $azione . '</td></tr>';
  }
  ?>
  <?= $sec('🎨', 'Temi grafici',
      '<p>L\'interfaccia ha ' . count(\App\Core\Temi::CATALOGO) . ' <strong>temi</strong>, ispirati a plance e terminali '
    . 'della fantascienza. Un tema cambia colori, caratteri e forme di barre, pannelli e bottoni, e i colori della mappa '
    . 'stellare e delle fasce; <strong>non cambia il gioco</strong>: stesse pagine, stessi comandi, stessi numeri. In ogni '
    . 'tema gli avvisi tengono il colore del loro tipo: rosso per attacchi e distruzione, giallo per gli eventi.</p>'
    . '<div class="table-wrap"><table class="tbl compact"><tbody>' . $righeTemi . '</tbody></table></div>'
    . '<p>Si sceglie dal <a href="' . e(url('/gioco/profilo#tema')) . '">Profilo</a>, con un\'anteprima di ciascuno, '
    . 'o dal menu <strong>Tema</strong> in fondo a ogni schermata, anche prima di entrare. La scelta resta legata al tuo '
    . 'account e vale su ogni dispositivo. I caratteri dei temi stanno su SubSpazio: nessun servizio esterno, e scarichi '
    . 'solo quelli del tema che usi. Se il sistema chiede meno movimento, righe di scansione, pioggia e insegne restano ferme.</p>'
    . '<p class="hint">I temi sono omaggi, non riproduzioni: nessun logo, marchio o grafica originale, e nessuna affiliazione '
    . 'con le opere a cui si ispirano, i cui nomi appartengono ai rispettivi titolari.</p>',
      'guide-wide') ?>
</div>
