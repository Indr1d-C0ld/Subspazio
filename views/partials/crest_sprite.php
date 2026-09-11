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
    <symbol id="crest-scudo" viewBox="0 0 24 24">
      <path d="M12 2 L20 5 V11 C20 16.5 16.5 20 12 22 C7.5 20 4 16.5 4 11 V5 Z"
            fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
      <path d="M12 6.5 L16 8 V11 C16 14.3 14.2 16.6 12 17.8 C9.8 16.6 8 14.3 8 11 V8 Z" fill="currentColor" opacity=".55"/>
    </symbol>
    <symbol id="crest-spada" viewBox="0 0 24 24">
      <path d="M12 2 L14 5 V14 H10 V5 Z" fill="currentColor"/>
      <rect x="6" y="14" width="12" height="2.4" rx="1.2" fill="currentColor"/>
      <rect x="10.6" y="16.4" width="2.8" height="5.6" rx="1" fill="currentColor"/>
    </symbol>
    <symbol id="crest-ala" viewBox="0 0 24 24">
      <path d="M2 20 C4 20 4 16 8 15 C7 18 5 20 2 20 Z M2 20 C7 19 9 14 14 12 C12 16 9 20 2 20 Z M2 20 C10 18 13 11 20 7 C17 13 12 20 2 20 Z M2 20 C13 17 17 8 22 3 C20 12 15 20 2 20 Z"
            fill="currentColor"/>
    </symbol>
    <symbol id="crest-bussola" viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="9.5" fill="none" stroke="currentColor" stroke-width="2"/>
      <path d="M12 4 L14 12 L12 20 L10 12 Z" fill="currentColor"/>
      <path d="M4 12 L12 10 L20 12 L12 14 Z" fill="currentColor" opacity=".5"/>
    </symbol>
    <symbol id="crest-ingranaggio" viewBox="0 0 24 24">
      <path d="M12 2 L14 5 L17.5 4.3 L17 7.9 L20.6 9 L18 12 L20.6 15 L17 16.1 L17.5 19.7 L14 19 L12 22 L10 19 L6.5 19.7 L7 16.1 L3.4 15 L6 12 L3.4 9 L7 7.9 L6.5 4.3 L10 5 Z"
            fill="currentColor"/>
      <circle cx="12" cy="12" r="3" fill="none" stroke="#000" stroke-width="2.6" opacity=".4"/>
    </symbol>
    <symbol id="crest-serpente" viewBox="0 0 24 24">
      <path d="M6 20 C6 14 12 14 12 9 C12 5 8 5 8 8 C8 10 11 10 11 8"
            fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
      <path d="M6 20 C6 16 10 16 12 18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
      <circle cx="8" cy="8" r="1" fill="currentColor"/>
    </symbol>
    <symbol id="crest-atomo" viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="2.4" fill="currentColor"/>
      <ellipse cx="12" cy="12" rx="10" ry="4" fill="none" stroke="currentColor" stroke-width="1.8"/>
      <ellipse cx="12" cy="12" rx="10" ry="4" fill="none" stroke="currentColor" stroke-width="1.8" transform="rotate(60 12 12)"/>
      <ellipse cx="12" cy="12" rx="10" ry="4" fill="none" stroke="currentColor" stroke-width="1.8" transform="rotate(120 12 12)"/>
    </symbol>
    <symbol id="crest-diamante" viewBox="0 0 24 24">
      <path d="M6 4 H18 L22 10 L12 22 L2 10 Z" fill="currentColor" stroke="currentColor" stroke-width="1" stroke-linejoin="round"/>
      <path d="M2 10 H22 M6 4 L12 10 L18 4 M12 10 L12 22" stroke="#000" stroke-width="1" opacity=".3"/>
    </symbol>
    <symbol id="crest-luna" viewBox="0 0 24 24">
      <path d="M16 3 A10 10 0 1 0 16 21 A8 8 0 1 1 16 3 Z" fill="currentColor"/>
    </symbol>
    <symbol id="crest-sole" viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="4.5" fill="currentColor"/>
      <path d="M12 1 V5 M12 19 V23 M1 12 H5 M19 12 H23 M4 4 L7 7 M17 17 L20 20 M20 4 L17 7 M7 17 L4 20"
            stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </symbol>
  </defs>
</svg>
