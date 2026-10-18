<x-layouts.app title="Gerenciar vídeos">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">Gerenciar vídeos</h1>
            <p class="mt-1 text-sm text-zinc-400">Cadastre os vídeos e copie o link fixo de cada um para enviar ou colocar na resposta automática.</p>
        </div>
        <a href="{{ route('videos.create') }}" class="btn btn-primary self-start sm:self-auto">
            <x-icon name="plus" class="size-4" /> Novo vídeo
        </a>
    </div>

    <div class="mt-6">
        @include('partials.filters')
    </div>

    <div class="card mt-6 overflow-hidden">
        @if ($storage['count'] > 0)
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 border-b border-ink-700 bg-ink-850/60 px-4 py-2.5 text-xs text-zinc-400">
                <x-icon name="database" class="size-4 text-brand-400" />
                Espaço usado no servidor pelos vídeos e capas:
                <strong class="text-zinc-200">{{ $storage['used'] }}</strong>
                <span class="text-zinc-600">({{ $storage['count'] }} {{ $storage['count'] === 1 ? 'vídeo' : 'vídeos' }})</span>
                @if ($storage['noxem'])
                    <span class="text-ink-600">•</span>
                    Na Noxem Cloud:
                    <strong class="text-zinc-200">{{ $storage['noxem']['used'] }}</strong>
                    @if ($storage['noxem']['cap'])
                        <span class="text-zinc-600">de {{ $storage['noxem']['cap'] }}</span>
                    @endif
                @endif
            </div>
        @endif

        @if ($videos->isEmpty())
            <div class="px-6 py-14 text-center text-sm text-zinc-400">
                {{ array_filter($filters) ? 'Nenhum vídeo encontrado com esses filtros.' : 'Nenhum vídeo cadastrado ainda.' }}
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="border-b border-ink-700 bg-ink-850 text-xs uppercase tracking-wide text-zinc-500">
                        <tr>
                            <th class="px-3 py-3 font-semibold">Vídeo</th>
                            <th class="hidden px-3 py-3 font-semibold 2xl:table-cell">Tipo</th>
                            <th class="hidden px-3 py-3 font-semibold 2xl:table-cell">Duração</th>
                            <th class="px-3 py-3 font-semibold">Espaço</th>
                            <th class="hidden px-3 py-3 font-semibold 2xl:table-cell">Cadastro</th>
                            <th class="px-3 py-3 font-semibold">Visualizações</th>
                            <th class="px-3 py-3 text-right font-semibold">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-700/70">
                        @foreach ($videos as $video)
                            <tr class="transition hover:bg-ink-850/60">
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-3">
                                        <img src="{{ $video->coverUrl() }}" alt="" loading="lazy" class="h-14 w-10 shrink-0 rounded-md object-cover ring-1 ring-ink-700">
                                        <div class="min-w-0">
                                            <p class="max-w-64 truncate font-semibold text-white">{{ $video->title }}</p>
                                            <p class="max-w-64 truncate text-xs text-zinc-500">{{ $video->video_original_name }}</p>
                                            @if ($video->noxem_video_id)
                                                @php
                                                    [$noxemLabel, $noxemClasses] = match ($video->noxem_status) {
                                                        'ready' => ['Noxem Cloud', 'bg-emerald-500/15 text-emerald-300'],
                                                        'failed' => ['Noxem · falhou', 'bg-brand-500/15 text-brand-300'],
                                                        default => ['Noxem · preparando', 'bg-sky-500/15 text-sky-300'],
                                                    };
                                                @endphp
                                                <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $noxemClasses }}"
                                                      title="{{ $video->noxem_status === 'failed' ? $video->noxem_error : 'Vídeo guardado e entregue pela Noxem Cloud' }}">{{ $noxemLabel }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden px-3 py-3 text-zinc-300 2xl:table-cell">{{ $video->genre->label() }}</td>
                                <td class="hidden px-3 py-3 text-zinc-300 2xl:table-cell">{{ $video->formattedDuration() ?? '—' }}</td>
                                @if ($video->noxemSize() > 0)
                                    <td class="px-3 py-3 whitespace-nowrap text-zinc-300" title="Vídeo {{ $video->formattedSize() }} na Noxem Cloud + capa {{ $video->formattedDiskUsage() }} no servidor">
                                        {{ $video->formattedSize() }}
                                        <span class="block text-xs text-zinc-600">na Noxem</span>
                                    </td>
                                @else
                                    <td class="px-3 py-3 whitespace-nowrap text-zinc-300" title="Vídeo {{ $video->formattedSize() }} + capa">
                                        {{ $video->formattedDiskUsage() }}
                                    </td>
                                @endif
                                <td class="hidden px-3 py-3 text-zinc-400 2xl:table-cell">
                                    {{ $video->created_at->format('d/m/Y') }}
                                    @if ($video->user)
                                        <span class="block text-xs text-zinc-600">por {{ strtok($video->user->name, ' ') }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-zinc-300" title="Pessoas (IPs diferentes) que abriram o link fixo">
                                    <x-icon name="eye" class="mr-1 inline size-4 -translate-y-px text-zinc-500" />{{ number_format((int) $video->views_total, 0, ',', '.') }}
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" class="btn btn-ghost px-2.5 py-1.5" title="Assistir (prévia)"
                                                @click="$store.player.open(@js($video->toPlayerPayload()))">
                                            <x-icon name="play" class="size-4" />
                                        </button>
                                        <span x-data="copyButton(@js($video->fixedUrl()))">
                                            <button type="button" @click="copy()" class="btn btn-primary whitespace-nowrap px-3 py-1.5 text-xs" title="{{ $video->fixedUrl() }}">
                                                <x-icon name="copy" class="size-3.5" /> <span x-text="copied ? 'Copiado!' : 'Copiar link fixo'">Copiar link fixo</span>
                                            </button>
                                        </span>
                                        <a href="{{ $video->fixedUrl() }}" target="_blank" class="btn btn-ghost px-2.5 py-1.5" title="Abrir o link fixo (sua prévia não conta visualização)">
                                            <x-icon name="link" class="size-4" />
                                        </a>
                                        <a href="{{ route('videos.edit', $video) }}" class="btn btn-ghost px-2.5 py-1.5" title="Editar vídeo">
                                            <x-icon name="pencil" class="size-4" />
                                        </a>
                                        <form method="POST" action="{{ route('videos.destroy', $video) }}"
                                              onsubmit="return confirm(@js('Excluir “'.$video->title.'”? O arquivo de vídeo e a capa serão apagados e o link fixo para de funcionar.'))">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-ghost px-2.5 py-1.5 hover:text-brand-400" title="Excluir vídeo">
                                                <x-icon name="trash" class="size-4" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{ $videos->links('partials.pagination') }}
</x-layouts.app>
