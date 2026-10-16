<x-layouts.app title="Usuários">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">Usuários</h1>
            <p class="mt-1 text-sm text-zinc-400">Quem tem acesso ao painel. Tudo o que cada um faz fica registrado em <a href="{{ route('activities.index') }}" class="text-brand-400 hover:underline">Atividades</a>.</p>
        </div>
        <a href="{{ route('members.create') }}" class="btn btn-primary self-start sm:self-auto">
            <x-icon name="plus" class="size-4" /> Novo usuário
        </a>
    </div>

    <div class="card mt-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="border-b border-ink-700 bg-ink-850 text-xs uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Usuário</th>
                        <th class="px-4 py-3 font-semibold">Perfil</th>
                        <th class="px-4 py-3 font-semibold">Último acesso</th>
                        <th class="px-4 py-3 font-semibold">Vídeos cadastrados</th>
                        <th class="px-4 py-3 text-right font-semibold">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-700/70">
                    @foreach ($members as $member)
                        @php $isMe = $member->is(auth()->user()); @endphp
                        <tr class="transition hover:bg-ink-850/60">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-ink-700 text-sm font-bold text-white">{{ mb_strtoupper(mb_substr($member->name, 0, 1)) }}</span>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-white">{{ $member->name }} @if ($isMe)<span class="ml-1 rounded-full bg-ink-700 px-2 py-0.5 text-[11px] font-medium text-zinc-300">você</span>@endif</p>
                                        <p class="truncate text-xs text-zinc-500">{{ $member->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3"><span class="rounded-full bg-brand-600/15 px-2.5 py-1 text-xs font-semibold text-brand-300">{{ $member->role->label() }}</span></td>
                            <td class="px-4 py-3 text-zinc-300">
                                {{ isset($lastLogins[$member->id]) ? \Illuminate\Support\Carbon::parse($lastLogins[$member->id])->format('d/m/Y H:i') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-zinc-300">{{ $member->videos_count }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('activities.index', ['user' => $member->id]) }}" class="btn btn-ghost px-2.5 py-1.5 text-xs" title="Ver o que este usuário fez">
                                        <x-icon name="clock" class="size-4" /> Atividades
                                    </a>
                                    <a href="{{ route('members.edit', $member) }}" class="btn btn-ghost px-2.5 py-1.5 text-xs">
                                        <x-icon name="pencil" class="size-4" /> {{ $isMe ? 'Minha conta' : 'Editar' }}
                                    </a>
                                    @unless ($isMe)
                                        <form method="POST" action="{{ route('members.destroy', $member) }}"
                                              onsubmit="return confirm(@js('Excluir o acesso de '.$member->name.'? Ele não vai mais conseguir entrar. Os registros de atividade dele continuam guardados.'))">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-ghost px-2.5 py-1.5 hover:text-brand-400" title="Excluir usuário">
                                                <x-icon name="trash" class="size-4" />
                                            </button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
