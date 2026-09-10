<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Testi d'aiuto contestuali. Ogni sezione o comando non ovvio dell'interfaccia
 * ha una voce qui, richiamata da `views/partials/help.php` (marcatore «?»).
 * Chiave: «<schermata>.<slug>». Tenere le frasi brevi, una riga.
 */
final class Help
{
    /** @var array<string,string> */
    public const TEXT = [
        // --- Plancia ---------------------------------------------------
        'plancia.settore'        => 'Il settore in cui ti trovi: servizi presenti, uscite warp verso i settori adiacenti e chi altro è qui.',
        'plancia.warp'          => 'Salta a un settore adiacente. Ogni warp costa turni (di norma 1); l\'autopilota incatena più salti verso una destinazione nota.',
        'plancia.forze'         => 'Caccia schierati, campi minati e NPC presenti nel settore: possono intercettarti quando entri o riparti.',
        'plancia.servizi'       => 'Riepilogo della tua nave e situazione: scafo, moduli, equipaggio, risorse, griglia EPS, reputazione con le fazioni.',
        'plancia.mappa'         => 'Mappa dei settori esplorati. Anello ciano = adiacente, anello ambra = una preda con la tua Limpet, rosso = pericolo noto.',
        'plancia.giornale'      => 'Il registro di bordo: viaggi, combattimenti, incontri ed eventi che ti riguardano, dal più recente.',
        'plancia.rientro'       => 'Cosa è successo mentre eri via: turni ricaricati, colonie che hanno prodotto, lavori d\'officina finiti, contratti scaduti.',
        'plancia.prede'         => 'Le navi a cui hai agganciato una mina Limpet: ne vedi la posizione in tempo reale finché non raggiungono lo StarDock.',
        'plancia.primipassi'    => 'Obiettivi guidati per iniziare: completandoli tutti ricevi una ricompensa una tantum.',
        'plancia.incontro'      => 'Un evento di percorso: scegli come reagire: alcune opzioni fanno una prova di abilità di un ufficiale. Riparti senza scegliere e l\'occasione svanisce.',
        'plancia.notiziario'    => 'Il bollettino della Federazione, composto dallo stato reale del gioco. Versione integrale nella Radio.',
        'plancia.sonda'         => 'Lancia una sonda in un settore adiacente per vederne contenuto e pericoli senza entrarci.',
        'plancia.armi'          => 'Attacchi, assalti ai porti e dispiegamento di caccia e mine nel settore. Vietato in spazio Federazione.',
        'plancia.occultamento'  => 'Attiva/disattiva il dispositivo di occultamento (se installato). Da occultato sei fuori dai sensori ma più lento e disarmato.',
        'plancia.computer'      => 'Strumenti di navigazione: traccia una rotta, imposta il faro del settore e — se hai il drive — salta in Transwarp.',
        'plancia.nota'          => 'Un\'etichetta e una nota private su questo settore; spunta «preferito» per ritrovarlo in fretta.',

        // --- Porto / economia ---------------------------------------
        'porto.commercio'       => 'Il porto compra e vende tre merci. Il prezzo si muove con le sue scorte: ogni scambio sposta il mercato.',
        'porto.contratta'       => 'Tratta il prezzo con una controproposta invece di accettare quello «veloce»: banda stretta, poche battute.',
        'banca.conto'           => 'Deposita crediti per metterli al sicuro dai furti e maturare interesse composto; preleva quando vuoi.',
        'mercatonero.merce'     => 'Vendi merci sopra il prezzo equo, senza domande: ogni affare qui ti costa allineamento.',
        'mercatonero.hardware'  => 'Hardware scontato di provenienza dubbia. Sconto in cambio di allineamento.',
        'mercatonero.taglia'    => 'Paga per farti togliere una taglia dalla testa.',
        'contratti.bacheca'     => 'Incarichi pubblicati dai comandanti: taglie su un bersaglio o consegne di merce a un settore, con ricompensa.',
        'contratti.pubblica'    => 'Metti una taglia o richiedi una consegna: la ricompensa viene bloccata dal tuo saldo alla pubblicazione.',

        // --- Cantiere / moduli / EPS -------------------------------
        'cantiere.riparazioni'  => 'Rimette in linea i moduli messi fuori uso dai colpi incassati. Costo fisso per modulo, solo allo StarDock.',
        'cantiere.potenziamenti' => 'Compra stive, caccia e scudi per la nave attuale entro i limiti dello scafo.',
        'cantiere.hardware'     => 'Dispositivi permanenti: scanner, transwarp, occultamento, laser minerario, sonde, mine, capsula, Genesi.',
        'cantiere.navi'         => 'Cambia scafo: il prezzo è al netto della permuta. Caccia, scudi e hardware non passano alla nuova nave.',
        'eps.griglia'           => '8 tacche di reattore su 4 canali (Scudi/Armi/Motori/Sensori). Più tacche = quel sistema più forte, gli altri più deboli. Ritarare costa 1 turno.',
        'moduli.slot'           => 'I moduli installati negli slot dello scafo ne modificano le statistiche. Un modulo «fuori uso» non conta finché non lo ripari.',
        'moduli.inventario'     => 'I moduli che possiedi ma non hai installato. Montali in uno slot libero della categoria giusta.',
        'moduli.officina'       => 'Smonta un modulo per recuperarne Leghe, oppure potenzialo di fascia. Al banco, allo StarDock.',
        'moduli.raffineria'     => 'Trasforma minerale + equipaggiamento in Componenti, e i Componenti (su ricetta) in un modulo preciso.',
        'moduli.lavori'         => 'La produzione su ricetta non è istantanea: il lavoro matura in N minuti secondo la fascia. Puoi annullarlo (rimborso materiali).',

        // --- Equipaggio / missioni --------------------------------
        'equipaggio.plancia'    => 'Gli ufficiali assegnati danno bonus passivi e un\'abilità attiva secondo il ruolo. I posti dipendono dallo scafo.',
        'equipaggio.riserva'    => 'Ufficiali reclutati ma non in servizio: assegnali a un ruolo per attivarne gli effetti.',
        'equipaggio.reclutamento' => 'Ingaggia nuovi ufficiali. Guadagnano XP e salgono di livello con l\'uso; ad alta lealtà sbloccano il secondo livello dell\'abilità.',
        'missioni.disponibili'  => 'Missioni della squadra a terra: prova di abilità con esiti da trionfo a disastro. Impegnano l\'ufficiale per un po\'.',

        // --- Pianeti / corp --------------------------------------
        'pianeti.settore'       => 'I pianeti nel settore: chi li possiede, il livello di Citadel e se hanno un cannone Quasar.',
        'pianeta.produzione'    => 'I coloni divisi per specialità producono minerale, organico ed equipaggiamento; gli inattivi non rendono.',
        'pianeta.citadel'       => 'La fortezza del pianeta: ogni livello aumenta difesa, capienza e bonus. Sale con crediti e tempo sul tick.',
        'pianeta.assalto'       => 'Attacca il pianeta con i caccia: superata la guarnigione puoi bombardarlo o prenderne il controllo. Crolla l\'allineamento.',
        'corp.home'             => 'La tua corporazione: cassa condivisa, membri e possesso comune di pianeti e strutture.',
        'corp.alleanze'         => 'Patti di non aggressione fra corporazioni: gli alleati non fanno scattare le difese a vicenda.',

        // --- Meta: radio, classifica, fazioni, codex, registro ---
        'radio.canali'          => 'Messaggistica: canale pubblico, notiziario della Federazione, canale di corp e messaggi privati. Il badge conta i non letti.',
        'classifica.comandanti' => 'Graduatoria per rating: una formula che pesa esperienza, ricchezza, kill, pianeti e altro. Ricalcolata dal tick.',
        'classifica.corp'       => 'Le corporazioni ordinate per rating aggregato dei membri.',
        'fazioni.reputazione'   => 'La tua reputazione con le 4 potenze (-100..+100, 5 livelli). Commercio, kill e assalti la muovono; c\'è rivalità fra fazioni.',
        'fazioni.emporio'       => 'Merce e hardware riservati: sbloccati man mano che sali di livello con la fazione.',
        'codex.home'            => 'L\'enciclopedia di bordo: si popola studiando relitti, anomalie e reperti che incontri in giro.',
        'registro.spostamenti'  => 'Lo storico dei tuoi warp e i settori che frequenti di più, ricostruiti dai log di movimento.',
        'giornale.home'         => 'Il registro di bordo completo e paginato. Aprendolo, segni come letto.',
        'traguardi.home'        => 'Obiettivi permanenti dell\'account: si sbloccano una volta e restano fra una stagione e l\'altra.',
        'albo.home'             => 'L\'albo d\'oro: i comandanti che hanno chiuso in vetta le stagioni concluse.',

        // --- Identità -------------------------------------------
        'profilo.identita'      => 'Colore d\'accento, marca di flotta, motto e registro della nave: compaiono in plancia, classifica e liste.',
        'profilo.immagini'      => 'Avatar e logo di flotta. Ogni immagine passa da un controllo dell\'amministratore prima di diventare pubblica.',
    ];

    public static function get(string $key): ?string
    {
        return self::TEXT[$key] ?? null;
    }
}
