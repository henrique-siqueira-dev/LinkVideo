@props(['title' => null])

@php
    $user = auth()->user();
    $navItems = [
        ['route' => 'dashboard', 'active' => 'dashboard', 'label' => 'Início', 'icon' => 'grid', 'master' => false],
        ['route' => 'videos.index', 'active' => ['videos.index', 'videos.create', 'videos.edit'], 'label' => 'Vídeos', 'icon' => 'film', 'master' => true],
        ['route' => 'activities.index', 'active' => 'activities.*', 'label' => 'Atividades', 'icon' => 'clock', 'master' => true],
        ['route' => 'settings.edit', 'active' => ['settings.*', 'members.*'], 'label' => 'Configurações', 'icon' => 'cog', 'master' => true],
    ];
    $financeVisible = \App\Support\Finance::visible();
@endphp

<x-layouts.base :title="$title">
    <div x-data="{ menu: false }" @keydown.escape.window="menu = false">
        {{-- Barra superior (celular) --}}
        <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-ink-700/60 bg-ink-950/90 px-4 backdrop-blur-xl lg:hidden">
            <a href="{{ route('dashboard') }}"><x-logo /></a>
            <button type="button" class="btn btn-ghost px-2.5" @click="menu = true" aria-label="Abrir menu">
                <x-icon name="menu" />
            </button>
        </header>

        {{-- Fundo escuro do menu no celular --}}
        <div x-cloak x-show="menu" x-transition.opacity class="fixed inset-0 z-40 bg-black/70 lg:hidden" @click="menu = false"></div>

        {{-- Menu lateral --}}
        <aside class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-ink-700/60 bg-ink-900 transition-transform duration-200 lg:translate-x-0"
               :class="menu && 'translate-x-0'">
            <div class="flex h-16 shrink-0 items-center justify-between px-5">
                <a href="{{ route('dashboard') }}"><x-logo /></a>
                <button type="button" class="btn btn-ghost px-2 lg:hidden" @click="menu = false" aria-label="Fechar menu"><x-icon name="x" /></button>
            </div>

            @if ($user->isMaster())
                <div class="px-4 pb-2">
                    <a href="{{ route('videos.create') }}" class="btn btn-primary w-full"><x-icon name="plus" class="size-4" /> Novo vídeo</a>
                </div>
            @endif

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-2">
                @foreach ($navItems as $item)
                    @continue($item['master'] && ! $user->isMaster())
                    @php $active = request()->routeIs(...(array) $item['active']); @endphp
                    <a href="{{ route($item['route']) }}" @class([
                        'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                        'bg-brand-600/15 text-white ring-1 ring-brand-500/30' => $active,
                        'text-zinc-400 hover:bg-ink-800 hover:text-white' => ! $active,
                    ])>
                        <x-icon :name="$item['icon']" :class="$active ? 'size-5 text-brand-400' : 'size-5'" />
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="space-y-2 border-t border-ink-700/60 p-3">
                @if ($user->isMaster())
                    {{-- Olhinho dos valores --}}
                    @if ($financeVisible)
                        <form method="POST" action="{{ route('finance.hide') }}">
                            @csrf
                            <button class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-emerald-300 transition hover:bg-ink-800">
                                <x-icon name="eye" class="size-5" /> Valores visíveis
                                <span class="ml-auto text-xs text-zinc-500">ocultar</span>
                            </button>
                        </form>
                    @else
                        <button type="button" @click="$store.finance.open = true" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-zinc-400 transition hover:bg-ink-800 hover:text-white">
                            <x-icon name="eye-off" class="size-5" /> Valores ocultos
                            <span class="ml-auto text-xs text-zinc-500">mostrar</span>
                        </button>
                    @endif
                @endif

                <div class="flex items-center gap-3 rounded-xl bg-ink-850 p-2.5">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-ink-700 text-sm font-bold text-white">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                    <div class="min-w-0 flex-1 leading-tight">
                        <p class="truncate text-sm font-semibold text-white">{{ $user->name }}</p>
                        @if ($user->isMaster())
                            <a href="{{ route('members.edit', $user) }}" class="text-xs text-brand-400 hover:underline">Minha conta</a>
                        @else
                            <span class="text-xs text-zinc-500">{{ $user->role->label() }}</span>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="grid size-9 place-items-center rounded-lg text-zinc-400 transition hover:bg-ink-700 hover:text-white" title="Sair">
                            <x-icon name="logout" class="size-5" />
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <main x-data class="lg:pl-64">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                {{ $slot }}
            </div>
        </main>
    </div>

    @include('partials.flash')
    @include('partials.player-modal')
    @if ($user->isMaster())
        @include('partials.finance-unlock')
    @endif
</x-layouts.base>
