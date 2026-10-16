<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Registro de atividades: quem fez o quê no painel. */
class ActivityController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = [
            'user' => $request->query('user'),
            'group' => array_key_exists($request->query('group'), ActivityLog::GROUPS) || $request->query('group') === 'failed'
                ? $request->query('group') : null,
            'q' => trim((string) $request->query('q')),
        ];

        $logs = ActivityLog::query()
            ->when($filters['user'], fn ($q, $user) => $q->where('user_id', $user))
            ->when($filters['group'] === 'failed', fn ($q) => $q->whereIn('action', ['auth.failed', 'finance.failed']))
            ->when($filters['group'] && $filters['group'] !== 'failed', fn ($q) => $q->where('action', 'like', $filters['group'].'.%'))
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('description', 'like', '%'.$filters['q'].'%')
                ->orWhere('user_name', 'like', '%'.$filters['q'].'%')
                ->orWhere('ip', 'like', '%'.$filters['q'].'%')))
            ->latest('created_at')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('activities.index', [
            'logs' => $logs,
            'filters' => $filters,
            'members' => User::orderBy('name')->get(['id', 'name']),
            'groups' => ActivityLog::GROUPS + ['failed' => 'Senhas erradas'],
        ]);
    }
}
