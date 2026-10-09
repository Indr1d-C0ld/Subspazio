<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Temi;

/** Cambio di tema grafico, da qualunque pagina (anche prima dell'accesso). */
final class TemaController
{
    public function scegli(Request $request): Response
    {
        $tema = $request->str('tema');
        if (Temi::scegli($tema)) {
            Session::flash('success', 'Tema grafico: ' . Temi::CATALOGO[$tema]['nome'] . '.');
        } else {
            Session::flash('error', 'Tema sconosciuto.');
        }
        return redirect(self::ritorno($request->str('torna', '/')));
    }

    /**
     * Torna alla pagina da cui si e' scelto. Solo percorsi interni
     * dell'applicazione, mai un indirizzo intero o «//altro-sito».
     */
    private static function ritorno(string $torna): string
    {
        return preg_match('#^/(?!/)[a-z0-9/_-]*$#', $torna) === 1 && $torna !== '/tema' ? $torna : '/';
    }
}
