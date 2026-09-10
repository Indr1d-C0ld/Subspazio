<?php
/**
 * Definizioni SVG degli stemmi di flotta. Incluso una sola volta nel layout.
 * Ogni <symbol> ha viewBox 0 0 24 24 e disegna con currentColor, così il
 * colore effettivo arriva dal CSS (.crest svg { color: … }).
 * Le chiavi corrispondono a App\Game\Identity::CRESTS.
 */
?>
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
  <defs>
    <symbol id="crest-delta" viewBox="0 0 24 24">
      <path d="M12 3 L21 20 H3 Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
      <path d="M12 9 L16.5 18 H7.5 Z" fill="currentColor" opacity=".55"/>
    </symbol>
    <symbol id="crest-orbita" viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="3.4" fill="currentColor"/>
      <ellipse cx="12" cy="12" rx="9" ry="4.2" fill="none" stroke="currentColor" stroke-width="2" transform="rotate(-25 12 12)"/>
    </symbol>
    <symbol id="crest-stella" viewBox="0 0 24 24">
      <path d="M12 2.5 L14.6 9.3 L21.8 9.6 L16.1 14 L18.1 21 L12 16.9 L5.9 21 L7.9 14 L2.2 9.6 L9.4 9.3 Z"
            fill="currentColor" stroke="currentColor" stroke-width="1" stroke-linejoin="round"/>
    </symbol>
    <symbol id="crest-corona" viewBox="0 0 24 24">
      <path d="M3 8 L7 13 L12 6 L17 13 L21 8 L19.5 19 H4.5 Z"
            fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
      <circle cx="3" cy="8" r="1.6" fill="currentColor"/>
      <circle cx="12" cy="6" r="1.6" fill="currentColor"/>
      <circle cx="21" cy="8" r="1.6" fill="currentColor"/>
    </symbol>
    <symbol id="crest-mirino" viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="7.5" fill="none" stroke="currentColor" stroke-width="2"/>
      <path d="M12 1.5 V6 M12 18 V22.5 M1.5 12 H6 M18 12 H22.5" stroke="currentColor" stroke-width="2"/>
      <circle cx="12" cy="12" r="1.8" fill="currentColor"/>
    </symbol>
    <symbol id="crest-cometa" viewBox="0 0 24 24">
      <path d="M20 4 L9 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      <path d="M16 4 L7 13 M20 8 L11 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity=".5"/>
      <circle cx="7.5" cy="16.5" r="3.6" fill="currentColor"/>
    </symbol>
    <symbol id="crest-sciabole" viewBox="0 0 24 24">
      <path d="M4 20 C10 18 16 12 20 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      <path d="M20 20 C14 18 8 12 4 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      <circle cx="12" cy="12" r="1.6" fill="currentColor"/>
    </symbol>
    <symbol id="crest-esagono" viewBox="0 0 24 24">
      <path d="M12 2.5 L20.5 7.25 V16.75 L12 21.5 L3.5 16.75 V7.25 Z"
            fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
      <path d="M12 7 L16.3 9.5 V14.5 L12 17 L7.7 14.5 V9.5 Z" fill="currentColor" opacity=".5"/>
    </symbol>
    <symbol id="crest-tridente" viewBox="0 0 24 24">
      <path d="M12 21 V8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      <path d="M5 5 V10 M12 4 V10 M19 5 V10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      <path d="M5 10 C5 15 19 15 19 10" fill="none" stroke="currentColor" stroke-width="2"/>
      <circle cx="12" cy="21" r="1.6" fill="currentColor"/>
    </symbol>
    <symbol id="crest-occhio" viewBox="0 0 24 24">
      <path d="M2 12 C6 5 18 5 22 12 C18 19 6 19 2 12 Z" fill="none" stroke="currentColor" stroke-width="2"/>
      <circle cx="12" cy="12" r="3.4" fill="currentColor"/>
    </symbol>
    <symbol id="crest-alloro" viewBox="0 0 24 24">
      <path d="M12 21 C6 17 5 10 7 3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      <path d="M12 21 C18 17 19 10 17 3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      <path d="M8 7 L5.5 6 M8.5 11 L6 10.5 M10 15 L8 15 M16 7 L18.5 6 M15.5 11 L18 10.5 M14 15 L16 15"
            stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </symbol>
    <symbol id="crest-rotta" viewBox="0 0 24 24">
      <path d="M4 19 C9 19 8 8 13 8 C18 8 17 5 20 5" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-dasharray="1 3.4"/>
      <circle cx="4" cy="19" r="2.2" fill="currentColor"/>
      <circle cx="20" cy="5" r="2.6" fill="none" stroke="currentColor" stroke-width="2"/>
    </symbol>
    <symbol id="crest-teschio" viewBox="0 0 24 24">
      <path d="M12 3 C7 3 4 6.5 4 11 C4 13.5 5.2 15 6.5 16 L6.5 19 H17.5 L17.5 16 C18.8 15 20 13.5 20 11 C20 6.5 17 3 12 3 Z"
            fill="currentColor"/>
      <circle cx="9" cy="11" r="2" fill="#000" opacity=".45"/>
      <circle cx="15" cy="11" r="2" fill="#000" opacity=".45"/>
      <path d="M11 15 L12 13 L13 15 Z" fill="#000" opacity=".45"/>
      <path d="M8.5 19 V21 M12 19 V21.5 M15.5 19 V21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </symbol>
    <symbol id="crest-fenice" viewBox="0 0 24 24">
      <path d="M12 21 C10 17 10 14 12 11 C14 14 14 17 12 21 Z" fill="currentColor"/>
      <path d="M12 12 C7 12 4 8 3 3 C8 5 10 6 12 9 C14 6 16 5 21 3 C20 8 17 12 12 12 Z" fill="currentColor" opacity=".8"/>
      <circle cx="12" cy="9.5" r="1.6" fill="currentColor"/>
    </symbol>
    <symbol id="crest-ancora" viewBox="0 0 24 24">
      <circle cx="12" cy="4.5" r="2.2" fill="none" stroke="currentColor" stroke-width="2"/>
      <path d="M12 6.5 V20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      <path d="M7 11 H17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      <path d="M4 14 C4 19 8.5 20.5 12 20.5 C15.5 20.5 20 19 20 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      <path d="M4 14 L3 11 L6.5 12.5 Z M20 14 L21 11 L17.5 12.5 Z" fill="currentColor"/>
    </symbol>
    <symbol id="crest-fulmine" viewBox="0 0 24 24">
      <path d="M13 2 L5 13 H11 L9 22 L19 10 H12.5 Z" fill="currentColor" stroke="currentColor" stroke-width="1" stroke-linejoin="round"/>
    </symbol>
    <symbol id="crest-nova" viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="3.2" fill="currentColor"/>
      <path d="M12 1 L13.4 7 L12 5 L10.6 7 Z M12 23 L10.6 17 L12 19 L13.4 17 Z M1 12 L7 10.6 L5 12 L7 13.4 Z M23 12 L17 13.4 L19 12 L17 10.6 Z"
            fill="currentColor"/>
      <path d="M4 4 L9 9 M20 4 L15 9 M4 20 L9 15 M20 20 L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity=".6"/>
    </symbol>
    <symbol id="crest-chiave" viewBox="0 0 24 24">
      <circle cx="7" cy="12" r="4.5" fill="none" stroke="currentColor" stroke-width="2"/>
      <circle cx="7" cy="12" r="1.4" fill="currentColor"/>
      <path d="M11.5 12 H21 M18 12 V16 M21 12 V15.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </symbol>
  </defs>
</svg>
