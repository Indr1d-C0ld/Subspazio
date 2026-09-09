<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Game\Ctx;
use App\Game\MediaAsset;

/**
 * Serve le immagini caricate. I file stanno fuori dal web (storage/uploads/):
 * questo controller e' l'unico modo di leggerli, e applica la regola di
 * visibilita' (solo `approved` per gli altri; il proprietario e l'admin
 * vedono anche il proprio `pending` tramite la rotta /gioco/profilo/media).
 */
final class MediaController
{
    /** GET /media/c/{id}/{kind} — avatar/logo APPROVATO di un comandante. */
    public function show(Request $request, string $id, string $kind): Response
    {
        if (!isset(MediaAsset::KINDS[$kind])) {
            return Response::text('Not found', 404);
        }
        $asset = MediaAsset::current('player', (int) $id, $kind);
        if ($asset === null) {
            return Response::text('Not found', 404);
        }
        return $this->stream($asset, $request, 'public, max-age=300');
    }

    /** GET /gioco/profilo/media/{kind} — anteprima del PROPRIO asset (pending o approvato). */
    public function mine(Request $request, string $kind): Response
    {
        if (!isset(MediaAsset::KINDS[$kind])) {
            return Response::text('Not found', 404);
        }
        $pid = (int) Ctx::$player['id'];
        $asset = MediaAsset::pendingFor('player', $pid, $kind)
            ?? MediaAsset::current('player', $pid, $kind);
        if ($asset === null) {
            return Response::text('Not found', 404);
        }
        return $this->stream($asset, $request, 'private, no-cache');
    }

    /** GET /admin/media/{id}/file — qualunque asset per id (solo admin, per la moderazione). */
    public function raw(Request $request, string $id): Response
    {
        $asset = MediaAsset::get((int) $id);
        if ($asset === null) {
            return Response::text('Not found', 404);
        }
        return $this->stream($asset, $request, 'private, no-cache');
    }

    /** @param array<string,mixed> $asset */
    private function stream(array $asset, Request $request, string $cacheControl): Response
    {
        $path = MediaAsset::absPath($asset);
        if (!is_file($path) || !is_readable($path)) {
            return Response::text('Not found', 404);
        }

        $etag = '"' . (string) $asset['sha1'] . '"';
        if (trim($request->header('If-None-Match')) === $etag) {
            return (new Response('', 304))
                ->withHeader('ETag', $etag)
                ->withHeader('Cache-Control', $cacheControl);
        }

        $body = (string) file_get_contents($path);
        return (new Response($body, 200, [
            'Content-Type'  => (string) $asset['mime'] ?: 'image/png',
            'Cache-Control' => $cacheControl,
            'ETag'          => $etag,
        ]));
    }
}
