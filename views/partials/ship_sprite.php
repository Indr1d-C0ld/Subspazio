<?php
/**
 * Sagome dei vascelli di gioco. Incluso una sola volta nel layout, subito
 * dopo crest_sprite. Ogni <symbol> ha viewBox 0 0 24 24 e disegna con
 * currentColor (profilo laterale, prua a destra), solo riempimenti pieni
 * per restare leggibile a 24-30 px. Le chiavi corrispondono a
 * App\Game\Identity::SHIP_MARKS (= ship_types.ckey).
 */
?>
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
  <defs>
    <symbol id="ship-escape_pod" viewBox="0 0 24 24">
      <rect x="6" y="7" width="11" height="10" rx="5" fill="currentColor"/>
      <rect x="15" y="10" width="4" height="4" rx="1" fill="currentColor" opacity=".55"/>
      <circle cx="10.5" cy="12" r="2" fill="currentColor" opacity=".25"/>
    </symbol>
    <symbol id="ship-scout_marauder" viewBox="0 0 24 24">
      <path d="M2 12 L14 10 L22 12 L14 14 Z" fill="currentColor"/>
      <path d="M4 12 L8 5 L12 11 Z" fill="currentColor"/>
      <path d="M4 12 L8 19 L12 13 Z" fill="currentColor"/>
    </symbol>
    <symbol id="ship-merchant_cruiser" viewBox="0 0 24 24">
      <path d="M3 9 H16 L21 12 L16 15 H3 Z" fill="currentColor"/>
      <rect x="7" y="6" width="6" height="3" rx="1" fill="currentColor"/>
      <rect x="1.5" y="9.5" width="2.5" height="5" rx="1" fill="currentColor" opacity=".6"/>
    </symbol>
    <symbol id="ship-missile_frigate" viewBox="0 0 24 24">
      <path d="M4 10 H15 L22 12 L15 14 H4 Z" fill="currentColor"/>
      <rect x="9" y="5.5" width="9" height="3" rx="1.5" fill="currentColor" opacity=".8"/>
      <rect x="9" y="15.5" width="9" height="3" rx="1.5" fill="currentColor" opacity=".8"/>
    </symbol>
    <symbol id="ship-constellation" viewBox="0 0 24 24">
      <ellipse cx="14" cy="8" rx="8" ry="3" fill="currentColor"/>
      <rect x="10" y="10.5" width="3" height="6" fill="currentColor" opacity=".7"/>
      <rect x="3" y="15.5" width="16" height="3.5" rx="1.75" fill="currentColor"/>
      <rect x="17" y="14.5" width="4" height="5.5" rx="1.5" fill="currentColor" opacity=".8"/>
    </symbol>
    <symbol id="ship-merchant_freighter" viewBox="0 0 24 24">
      <rect x="2" y="11" width="20" height="2.5" fill="currentColor"/>
      <rect x="3" y="6.5" width="4.5" height="5" rx=".6" fill="currentColor"/>
      <rect x="8.5" y="6.5" width="4.5" height="5" rx=".6" fill="currentColor" opacity=".8"/>
      <rect x="14" y="6.5" width="4.5" height="5" rx=".6" fill="currentColor"/>
      <path d="M18 13.5 H22 L20 17 Z" fill="currentColor" opacity=".7"/>
    </symbol>
    <symbol id="ship-cargo_transport" viewBox="0 0 24 24">
      <rect x="2.5" y="7.5" width="14" height="9" rx="1.5" fill="currentColor"/>
      <path d="M16.5 8.5 L22 12 L16.5 15.5 Z" fill="currentColor" opacity=".7"/>
      <rect x="4.5" y="9.5" width="10" height="1.6" fill="currentColor" opacity=".3"/>
      <rect x="4.5" y="12.9" width="10" height="1.6" fill="currentColor" opacity=".3"/>
    </symbol>
    <symbol id="ship-colonial_transport" viewBox="0 0 24 24">
      <path d="M3 12 C3 7.5 7 5.5 12 5.5 C18 5.5 21 8.5 21 12 C21 15.5 18 18.5 12 18.5 C7 18.5 3 16.5 3 12 Z" fill="currentColor"/>
      <rect x="8.5" y="3" width="6" height="3" rx="1.4" fill="currentColor"/>
      <rect x="1" y="10" width="2.5" height="4" rx="1" fill="currentColor" opacity=".6"/>
      <circle cx="9" cy="12" r="1.4" fill="currentColor" opacity=".28"/>
      <circle cx="13" cy="12" r="1.4" fill="currentColor" opacity=".28"/>
    </symbol>
    <symbol id="ship-corporate_flagship" viewBox="0 0 24 24">
      <path d="M2 8 L9 10.5 H22 L22 13.5 H9 L2 16 L5 12 Z" fill="currentColor"/>
      <path d="M9 10 L7 4 L13 9 Z" fill="currentColor" opacity=".75"/>
      <path d="M9 14 L7 20 L13 15 Z" fill="currentColor" opacity=".75"/>
      <rect x="14" y="10.5" width="3.5" height="3" rx="1" fill="currentColor" opacity=".4"/>
    </symbol>
    <symbol id="ship-havoc_gunstar" viewBox="0 0 24 24">
      <path d="M3 12 L11 7 L17 9.5 V14.5 L11 17 Z" fill="currentColor"/>
      <rect x="16" y="8" width="7" height="1.8" rx=".9" fill="currentColor"/>
      <rect x="16" y="11.1" width="8" height="1.8" rx=".9" fill="currentColor"/>
      <rect x="16" y="14.2" width="7" height="1.8" rx=".9" fill="currentColor"/>
      <path d="M3 12 L1 8.5 L4.5 10.5 Z M3 12 L1 15.5 L4.5 13.5 Z" fill="currentColor" opacity=".55"/>
    </symbol>
    <symbol id="ship-imperial_starship" viewBox="0 0 24 24">
      <path d="M1 19 L23 12 L1 5 Z" fill="currentColor"/>
      <rect x="4" y="7.5" width="4" height="3" rx=".6" fill="currentColor" opacity=".35"/>
    </symbol>
    <symbol id="ship-tholian_sentinel" viewBox="0 0 24 24">
      <path d="M12 3 L20 8.5 L17 19 H7 L4 8.5 Z" fill="currentColor"/>
      <path d="M12 8 L16 11 V15 L12 17 L8 15 V11 Z" fill="currentColor" opacity=".3"/>
    </symbol>
    <symbol id="ship-interdictor" viewBox="0 0 24 24">
      <path d="M4 10 L13 9 L20 11 V13 L13 15 L4 14 Z" fill="currentColor"/>
      <circle cx="10" cy="5.5" r="3.4" fill="currentColor" opacity=".75"/>
      <circle cx="10" cy="18.5" r="3.4" fill="currentColor" opacity=".75"/>
      <circle cx="10" cy="5.5" r="1.3" fill="currentColor"/>
      <circle cx="10" cy="18.5" r="1.3" fill="currentColor"/>
    </symbol>
  </defs>
</svg>
