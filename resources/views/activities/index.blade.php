<x-layouts.app title="Atividades">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">Atividades</h1>
        <p class="mt-1 text-sm text-zinc-400">Tudo o que os usuários fizeram no painel: quem, o quê, quando e de onde.</p>
    </div>

    <form method="GET" class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-500" />
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Buscar na descrição, nome ou IP..." class="field-input pl-10">
        </div>
        <div class="grid grid-cols-2 gap-3 sm:flex">
            <select name="user" class="field-input sm:w-48" onchange="this.form.submit()">
                <option value="">Todos os usuários</option>
                @foreach ($members as $member)
                    <option value="{{ $member->id }}" @selected((string) $filters['user'] === (string) $member->id)>{{ $member->name }}</option>
                @endforeach
            </select>
            <select name="group" class="field-input sm:w-44" onchange="this.form.submit()">
                <option value="">Todas as ações</option>
                @foreach ($groups as $value => $label)
                    <option value="{{ $value }}" @selected($filters['group'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Filtrar</button>
        @if ($filters['user'] || $filters['group'] || $filters['q'] !== '')
            <a href="{{ route('activities.index') }}" class="btn btn-ghost">Limpar</a>
        @endif
    </form>

    <div class="card mt-6 overflow-hidden">
        @if ($logs->isEmpty())
            <div class="px-6 py-14 text-center text-sm text-zinc-400">Nenhuma atividade encontrada.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-left text-sm">
                    <thead class="border-b border-ink-700 bg-ink-850 text-xs uppercase tracking-wide text-zinc-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Quando</th>
                            <th class="px-4 py-3 font-semibold">Quem</th>
                            <th class="px-4 py-3 font-semibold">Ação</th>
                            <th class="px-4 py-3 font-semibold">O que fez</th>
                            <th class="px-4 py-3 font-semibold">De onde</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-700/70">
                        @foreach ($logs as $log)
                            <tr class="align-top transition hover:bg-ink-850/60">
                                <td class="whitespace-nowrap px-4 py-3 text-zinc-300">
                                    {{ $log->created_at->format('d/m/Y') }}
                                    <span class="block text-xs text-zinc-500">{{ $log->created_at->format('H:i:s') }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-medium text-white">{{ $log->user_name ?? '—' }}</span>
                                    @if ($log->user_id && ! $log->user)
                                        <span class="block text-xs text-zinc-500">usuário excluído</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold {{ $log->badgeClasses() }}">{{ $log->groupLabel() }}</span>
                                </td>
                                <td class="px-4 py-3 text-zinc-200">
                                    {{ $log->description }}
                                    @if (! empty($log->properties['campos']))
                                        <span class="block text-xs text-zinc-500">alterou: {{ implode(', ', $log->properties['campos']) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-zinc-400">
                                    {{ $log->deviceLabel() ?? '—' }}
                                    <span class="block text-xs text-zinc-500">IP {{ $log->ip ?? '—' }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{ $logs->links('partials.pagination') }}
</x-layouts.app>
