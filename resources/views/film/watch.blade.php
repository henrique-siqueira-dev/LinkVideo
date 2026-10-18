{{-- Tela do link fixo: player centralizado com a capa e o botão grande de assistir. Um clique
     e o filme toca com som em tela cheia (resources/js/film.js). Vídeo em pé: altura da tela;
     deitado: largura da tela. Sem opção de download: sem botão de baixar e sem clique direito. --}}
<x-layouts.public :title="$video->title" :footer="$footer">
    <x-slot:head>
        <link rel="preload" as="image" href="{{ $coverUrl }}" fetchpriority="high">
    </x-slot:head>

    <div data-film data-vertical="{{ $video->isVertical() ? 1 : 0 }}" oncontextmenu="return false"
         class="group relative w-full overflow-hidden bg-black data-[vertical=0]:aspect-video data-[vertical=0]:max-h-svh data-[vertical=1]:h-svh">
        {{-- A própria capa, desfocada, preenche as laterais. --}}
        <div class="absolute inset-0 scale-110 bg-cover bg-center opacity-40 blur-2xl" style="background-image: url('{{ $coverUrl }}')"></div>

        @if ($video->playbackState() !== 'ready')
            {{-- Vídeo na Noxem Cloud ainda em preparo (ou que falhou): sem player, só o aviso por cima da capa. --}}
            <img src="{{ $coverUrl }}" alt="" class="absolute inset-0 size-full object-contain">
            <div class="absolute inset-0 grid place-items-center bg-black/50 p-4">
                <div class="max-w-sm rounded-2xl bg-black/75 px-6 py-5 text-center backdrop-blur-sm">
                    <x-logo />
                    @if ($video->playbackState() === 'failed')
                        <p class="mt-4 text-base font-semibold text-white">Este filme não está disponível no momento.</p>
                        <p class="mt-1 text-sm text-zinc-300">Tente de novo mais tarde.</p>
                    @else
                        <p class="mt-4 text-base font-semibold text-white">Este filme está sendo preparado.</p>
                        <p class="mt-1 text-sm text-zinc-300">Volte em alguns minutos e abra o link de novo.</p>
                    @endif
                </div>
            </div>
        @else
        {{-- Os controles (play, volume, tela cheia) aparecem depois do clique em assistir.
             Na Noxem Cloud só os metadados carregam antes do clique (o resto vem da CDN ao tocar). --}}
        <video class="absolute inset-0 size-full object-contain" playsinline preload="{{ $video->noxem_video_id ? 'metadata' : 'auto' }}"
               controlslist="nodownload noremoteplayback" disableremoteplayback
               oncontextmenu="return false" draggable="false"
               poster="{{ $coverUrl }}" src="{{ $streamUrl }}">
            Seu navegador não suporta a reprodução deste vídeo.
        </video>

        {{-- Logo e título por cima enquanto o filme não está rodando. No celular com vídeo deitado
             o player é baixo: fica só o botão (o título aparece logo abaixo do player). --}}
        <div class="pointer-events-none absolute inset-x-0 top-0 bg-gradient-to-b from-black/75 via-black/30 to-transparent px-4 pb-12 pt-4 transition-opacity duration-500 group-data-[playing]:opacity-0 group-data-[vertical=0]:max-sm:hidden sm:px-6">
            <x-logo />
            <p class="mt-3 max-w-xl truncate text-lg font-bold text-white drop-shadow sm:text-xl">{{ $video->title }}</p>
        </div>

        <div data-film-loading hidden class="pointer-events-none absolute inset-0 grid place-items-center">
            <div class="flex flex-col items-center gap-3 rounded-2xl bg-black/55 px-6 py-4 backdrop-blur-sm">
                <span class="size-10 animate-spin rounded-full border-[3px] border-white/20 border-t-white"></span>
                <span class="text-sm font-medium text-white/90">Carregando…</span>
            </div>
        </div>

        {{-- Clique em qualquer lugar do player: toca com som e abre a tela cheia. --}}
        <button type="button" data-film-start class="absolute inset-0 grid w-full cursor-pointer place-items-center" aria-label="Assistir {{ $video->title }}">
            <span class="flex flex-col items-center gap-4 group-data-[vertical=0]:max-sm:gap-3">
                <span class="relative grid size-20 place-items-center rounded-full bg-brand-600 text-white shadow-2xl shadow-black/60 ring-8 ring-white/15 transition group-hover:scale-105 group-data-[vertical=0]:max-sm:size-16 sm:size-24">
                    <span class="absolute inset-0 animate-ping rounded-full bg-brand-500/30"></span>
                    <x-icon name="play" class="relative size-9 translate-x-0.5 group-data-[vertical=0]:max-sm:size-7 sm:size-10" />
                </span>
                <span class="rounded-full bg-black/60 px-4 py-1.5 text-sm font-semibold text-white backdrop-blur-sm">
                    <span class="pointer-coarse:hidden">Clique para assistir</span><span class="hidden pointer-coarse:inline">Toque para assistir</span>
                </span>
            </span>
        </button>

        <p data-film-error hidden class="absolute inset-x-4 top-1/2 mx-auto max-w-sm -translate-y-1/2 rounded-xl bg-black/80 px-4 py-3 text-center text-sm text-zinc-200">
            Não foi possível carregar o vídeo. Verifique a internet e abra o link de novo.
        </p>
        @endif
    </div>

    <main class="mx-auto w-full max-w-4xl flex-1 px-4 py-6 sm:px-6">
        <div class="flex flex-wrap items-center gap-3">
            <x-rating-badge :rating="$video->age_rating" />
            <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $video->title }}</h1>
        </div>
        <p class="mt-2 text-sm text-zinc-400">
            {{ $video->genre->label() }}
            @if ($video->formattedDuration()) <span class="text-ink-600">•</span> {{ $video->formattedDuration() }} @endif
            <span class="text-ink-600">•</span> {{ $video->age_rating->description() }}
        </p>

        @if ($video->description)
            <p class="mt-5 whitespace-pre-line text-sm leading-relaxed text-zinc-300">{{ $video->description }}</p>
        @endif
    </main>
</x-layouts.public>
