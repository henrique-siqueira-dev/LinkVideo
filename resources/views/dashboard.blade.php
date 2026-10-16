<x-layouts.app title="Início">
    <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">Olá, {{ strtok(auth()->user()->name, ' ') }}</h1>
        <p class="text-sm text-zinc-400">{{ $isMaster ? 'Quantas pessoas abriram os links fixos dos filmes (cada IP conta uma vez por filme). A biblioteca está logo abaixo.' : 'Escolha um vídeo e clique em assistir.' }}</p>
    </div>

    @if ($isMaster)
        {{-- Números principais --}}
        <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-5">
            <x-stat-tile label="Hoje" icon="eye">
                {{ number_format($overview['views_today'], 0, ',', '.') }}
                <x-slot:sub>visualizações</x-slot:sub>
            </x-stat-tile>
            <x-stat-tile label="Últimos 7 dias" icon="chart">
                {{ number_format($overview['views_7'], 0, ',', '.') }}
                <x-slot:sub>visualizações</x-slot:sub>
            </x-stat-tile>
            <x-stat-tile label="Últimos 30 dias" icon="chart">
                {{ number_format($overview['views_30'], 0, ',', '.') }}
                <x-slot:sub>visualizações</x-slot:sub>
            </x-stat-tile>
            <x-stat-tile label="Desde o início" icon="clock">
                {{ number_format($overview['views_total'], 0, ',', '.') }}
                <x-slot:sub>visualizações</x-slot:sub>
            </x-stat-tile>
            <x-stat-tile label="Vídeos" icon="film" class="col-span-2 lg:col-span-1">
                {{ $overview['videos'] }}
                <x-slot:sub>{{ $storage }} no servidor</x-slot:sub>
            </x-stat-tile>
        </div>

        {{-- Gráficos (uma série por gráfico) --}}
        <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
            <x-chart-card title="Visualizações por dia" subtitle="Últimos 30 dias · 1 por IP em cada filme" series-label="Visualizações"
                          :labels="$daily['labels']" :values="$daily['views']" />
            <x-chart-card title="Filmes mais assistidos" subtitle="Últimos 30 dias · top 5" series-label="Visualizações"
                          :labels="$topVideos->pluck('title')->all()" :values="$topVideos->pluck('views_period')->map(fn ($v) => (int) $v)->all()"
                          :horizontal="true" />
        </div>

        <h2 class="mt-10 text-xl font-bold tracking-tight text-white">Biblioteca</h2>
    @endif

    <div class="mt-8">
        @include('partials.filters')
    </div>

    @if ($videos->isEmpty())
        <div class="card mt-6 flex flex-col items-center px-6 py-16 text-center">
            <span class="grid size-14 place-items-center rounded-2xl bg-ink-800 text-zinc-400"><x-icon name="film" class="size-7" /></span>
            @if (array_filter($filters))
                <h2 class="mt-4 text-lg font-semibold text-white">Nenhum vídeo encontrado</h2>
                <p class="mt-1 text-sm text-zinc-400">Ajuste a busca ou limpe os filtros.</p>
            @else
                <h2 class="mt-4 text-lg font-semibold text-white">Sua biblioteca está vazia</h2>
                <p class="mt-1 text-sm text-zinc-400">Cadastre o primeiro vídeo para começar.</p>
                @if (auth()->user()->isMaster())
                    <a href="{{ route('videos.create') }}" class="btn btn-primary mt-6"><x-icon name="plus" class="size-4" /> Adicionar vídeo</a>
                @endif
            @endif
        </div>
    @else
        <div class="mt-6 grid grid-cols-2 gap-x-4 gap-y-7 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5">
            @foreach ($videos as $video)
                <article class="group flex flex-col">
                    <button type="button" class="relative block aspect-[2/3] overflow-hidden rounded-xl bg-ink-800 ring-1 ring-ink-700/60 transition group-hover:ring-brand-500/60"
                            @click="$store.player.open(@js($video->toPlayerPayload()))" aria-label="Assistir {{ $video->title }}">
                        <img src="{{ $video->coverUrl() }}" alt="" loading="lazy" class="size-full object-cover transition duration-500 group-hover:scale-105">
                        <span class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/0 to-black/0"></span>
                        <x-rating-badge :rating="$video->age_rating" class="absolute left-2.5 top-2.5 shadow-lg" />
                        @if ($video->formattedDuration())
                            <span class="absolute bottom-2.5 right-2.5 rounded-md bg-black/70 px-1.5 py-0.5 text-[11px] font-semibold text-white backdrop-blur">{{ $video->formattedDuration() }}</span>
                        @endif
                        <span class="absolute inset-0 grid place-items-center opacity-0 transition group-hover:opacity-100">
                            <span class="grid size-14 place-items-center rounded-full bg-brand-600/90 text-white shadow-xl shadow-black/50 backdrop-blur">
                                <x-icon name="play" class="size-6 translate-x-0.5" />
                            </span>
                        </span>
                    </button>

                    <h3 class="mt-3 line-clamp-1 font-semibold text-white" title="{{ $video->title }}">{{ $video->title }}</h3>
                    <p class="mt-0.5 text-xs text-zinc-400">{{ $video->genre->label() }}</p>

                    <button type="button" class="btn btn-primary mt-3 w-full py-2"
                            @click="$store.player.open(@js($video->toPlayerPayload()))">
                        <x-icon name="play" class="size-4" /> Assistir
                    </button>
                </article>
            @endforeach
        </div>

        {{ $videos->links('partials.pagination') }}
    @endif
</x-layouts.app>
