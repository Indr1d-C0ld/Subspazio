<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AdminController
{
    public function dashboard(Request $request): Response
    {
        $counts = Database::first(
            "SELECT
                SUM(status = 'pending')   AS pending,
                SUM(status = 'active')    AS active,
                SUM(status = 'suspended') AS suspended,
                SUM(status = 'banned')    AS banned,
                COUNT(*)                  AS total
             FROM users"
        ) ?? [];

        $pending = Database::all(
            "SELECT id, username, email, created_at
             FROM users WHERE status = 'pending'
             ORDER BY created_at ASC"
        );

        $recent = Database::all(
            "SELECT id, username, email, status, role, created_at, last_login_at
             FROM users
             ORDER BY created_at DESC
             LIMIT 50"
        );

        $audit = Database::all(
            "SELECT a.id, a.action, a.created_at, a.target_id, u.username AS actor
             FROM audit_log a
             LEFT JOIN users u ON u.id = a.actor_user_id
             ORDER BY a.id DESC
             LIMIT 30"
        );

        return Response::html(view('admin/dashboard', [
            'title'   => 'Amministrazione',
            'counts'  => $counts,
            'pending' => $pending,
            'recent'  => $recent,
            'audit'   => $audit,
        ]));
    }

    public function approve(Request $request, string $id): Response
    {
        return $this->transition((int) $id, 'active', 'user.approve', $request, [
            'from' => ['pending', 'suspended'],
            'ok'   => 'Account attivato.',
            'set_approved' => true,
        ]);
    }

    public function suspend(Request $request, string $id): Response
    {
        return $this->transition((int) $id, 'suspended', 'user.suspend', $request, [
            'from' => ['active', 'pending'],
            'ok'   => 'Account sospeso.',
        ]);
    }

    public function reject(Request $request, string $id): Response
    {
        $uid = (int) $id;
        $target = Database::first('SELECT id, username, status, role FROM users WHERE id = ?', [$uid]);

        if ($target === null) {
            Session::flash('error', 'Utente inesistente.');
            return redirect('/admin');
        }
        if ($target['role'] === 'admin') {
            Session::flash('error', 'Non puoi rifiutare un amministratore.');
            return redirect('/admin');
        }
        if ($target['status'] !== 'pending') {
            Session::flash('error', 'Si possono rifiutare solo gli account in attesa.');
            return redirect('/admin');
        }

        Database::run('DELETE FROM users WHERE id = ?', [$uid]);
        $this->audit('user.reject', $uid, $request->ip(), ['username' => $target['username']]);
        Session::flash('success', 'Richiesta rifiutata ed eliminata.');
        return redirect('/admin');
    }

    /**
     * @param array{from:list<string>, ok:string, set_approved?:bool} $opt
     */
    private function transition(int $uid, string $to, string $action, Request $request, array $opt): Response
    {
        $target = Database::first('SELECT id, username, status, role FROM users WHERE id = ?', [$uid]);

        if ($target === null) {
            Session::flash('error', 'Utente inesistente.');
            return redirect('/admin');
        }
        if ((int) $target['id'] === Auth::id()) {
            Session::flash('error', 'Non puoi modificare lo stato del tuo stesso account.');
            return redirect('/admin');
        }
        if ($target['role'] === 'admin') {
            Session::flash('error', 'Non puoi modificare lo stato di un altro amministratore.');
            return redirect('/admin');
        }
        if (!in_array($target['status'], $opt['from'], true)) {
            Session::flash('error', "Transizione non valida da '{$target['status']}' a '{$to}'.");
            return redirect('/admin');
        }

        if (!empty($opt['set_approved'])) {
            Database::run(
                'UPDATE users SET status = ?, approved_at = NOW(), approved_by = ? WHERE id = ?',
                [$to, Auth::id(), $uid]
            );
        } else {
            Database::run('UPDATE users SET status = ? WHERE id = ?', [$to, $uid]);
        }

        $this->audit($action, $uid, $request->ip(), ['username' => $target['username'], 'to' => $to]);
        Session::flash('success', $opt['ok']);
        return redirect('/admin');
    }

    private function audit(string $action, int $targetId, string $ip, array $meta = []): void
    {
        try {
            Database::run(
                'INSERT INTO audit_log (actor_user_id, action, target_type, target_id, meta, ip)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [
                    Auth::id(),
                    $action,
                    'user',
                    $targetId,
                    $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
                    @inet_pton($ip) ?: null,
                ]
            );
        } catch (\Throwable) {
            // non bloccante
        }
    }
}
