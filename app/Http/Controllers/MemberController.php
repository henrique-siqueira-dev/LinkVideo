<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Membros (masters) que acessam o painel. */
class MemberController extends Controller
{
    public function index(): View
    {
        $lastLogins = ActivityLog::query()
            ->where('action', 'auth.login')
            ->whereNotNull('user_id')
            ->selectRaw('user_id, MAX(created_at) AS last_login')
            ->groupBy('user_id')
            ->pluck('last_login', 'user_id');

        return view('members.index', [
            'members' => User::query()->orderBy('name')->withCount('videos')->get(),
            'lastLogins' => $lastLogins,
        ]);
    }

    public function create(): View
    {
        return view('members.form', ['member' => new User]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, new User);

        $member = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => UserRole::Master,
        ]);
        $member->forceFill(['email_verified_at' => now()])->save();

        ActivityLog::record('member.created', "Cadastrou o usuário {$member->name} ({$member->email})", $member);

        return redirect()->route('members.index')->with('success', "Usuário {$member->name} cadastrado. Ele já pode entrar com o e-mail e a senha definidos.");
    }

    public function edit(User $member): View
    {
        return view('members.form', ['member' => $member]);
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        $data = $this->validated($request, $member);
        $passwordChanged = filled($data['password'] ?? null);

        $member->fill(['name' => $data['name'], 'email' => $data['email']]);
        if ($passwordChanged) {
            $member->password = $data['password'];
        }
        $member->save();

        $self = $member->is($request->user());
        ActivityLog::record('member.updated', ($self ? 'Editou a própria conta' : "Editou o usuário {$member->name}")
            .($passwordChanged ? ' e trocou a senha' : ''), $member);

        return $self
            ? redirect()->route('members.edit', $member)->with('success', 'Seus dados foram atualizados.')
            : redirect()->route('members.index')->with('success', "Dados de {$member->name} atualizados.");
    }

    public function destroy(Request $request, User $member): RedirectResponse
    {
        if ($member->is($request->user())) {
            return back()->with('error', 'Você não pode excluir a própria conta.');
        }

        if ($member->isMaster() && User::where('role', UserRole::Master)->count() <= 1) {
            return back()->with('error', 'É preciso manter pelo menos um master.');
        }

        $member->delete();
        ActivityLog::record('member.deleted', "Excluiu o usuário {$member->name} ({$member->email})", $member);

        return redirect()->route('members.index')->with('success', "Usuário {$member->name} excluído. Os registros de atividade dele continuam guardados.");
    }

    private function validated(Request $request, User $member): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('users', 'email')->ignore($member->id)],
            'password' => [$member->exists ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ], [], [
            'name' => 'nome',
            'email' => 'e-mail',
            'password' => 'senha',
        ]);
    }
}
