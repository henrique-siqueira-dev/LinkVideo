{{-- Modal do player. Aberto por $store.player.open(payload) em qualquer botão "Assistir". --}}
<div x-data
     x-cloak
     x-show="$store.player.video"
     x-transition.opacity.duration.200ms
     @keydown.escape.window="$store.player.close()"
     class="fixed inset-0 z-50 flex items-center justify-center p-0 sm:p-6"
     role="dialog" aria-modal="true" :aria-label="$store.player.video?.title">

    <div class="absolute inset-0 bg-black/85 backdrop-blur-sm" @click="$store.player.close()"></div>

    <template x-if="$store.player.video">
        {{-- vertical = vídeo gravado em pé (celular): no computador fica ao lado das informações. --}}
        <div x-data="{ vertical: !!$store.player.video?.vertical }"
             class="relative flex max-h-full w-full flex-col overflow-hidden bg-ink-900 shadow-2xl shadow-black sm:rounded-2xl sm:border sm:border-ink-700"
             :class="vertical ? 'max-w-4xl md:flex-row' : 'max-w-5xl'"
             x-transition:enter="transition duration-200 ease-out"
             x-transition:enter-start="scale-95 opacity-0"
             x-transition:enter-end="scale-100 opacity-100">

            <button type="button" @click="$store.player.close()"
                    class="absolute right-3 top-3 z-10 grid size-10 place-items-center rounded-full bg-black/60 text-white backdrop-blur transition hover:bg-brand-600"
                    aria-label="Fechar player">
                <x-icon name="x" />
            </button>

            {{-- O <video> é recriado a cada abertura (x-if) e destruído ao fechar, interrompendo o download. --}}
            <div class="shrink-0 bg-black" :class="vertical ? 'flex justify-center md:flex-1' : 'aspect-video w-full'">
                <template x-if="$store.player.video.state === 'ready'">
                    <video controls autoplay playsinline preload="metadata"
                           controlslist="nodownload" oncontextmenu="return false"
                           :class="vertical ? 'h-[65svh] w-auto max-w-full md:h-[min(85vh,860px)]' : 'size-full'"
                           @loadedmetadata="vertical = $el.videoHeight > $el.videoWidth"
                           x-init="$store.player.recover($el)"
                           :poster="$store.player.video.cover" :src="$store.player.video.src">
                        Seu navegador não suporta a reprodução deste vídeo.
                    </video>
                </template>

                {{-- Vídeo na Noxem Cloud ainda em preparo (ou que falhou): aviso no lugar do player. --}}
                <template x-if="$store.player.video.state !== 'ready'">
                    <div class="relative grid size-full min-h-56 place-items-center overflow-hidden">
                        <img :src="$store.player.video.cover" alt="" class="absolute inset-0 size-full object-cover opacity-25 blur-md">
                        <div class="relative mx-6 max-w-sm rounded-xl bg-black/70 px-5 py-4 text-center backdrop-blur-sm">
                            <template x-if="$store.player.video.state === 'processing'">
                                <div>
                                    <span class="mx-auto block size-8 animate-spin rounded-full border-[3px] border-white/20 border-t-white"></span>
                                    <p class="mt-3 text-sm font-semibold text-white">Preparando o vídeo…</p>
                                    <p class="mt-1 text-xs text-zinc-300">A Noxem Cloud está processando o arquivo. Atualize a página em alguns minutos.</p>
                                </div>
                            </template>
                            <template x-if="$store.player.video.state === 'failed'">
                                <div>
                                    <p class="text-sm font-semibold text-brand-300">Não foi possível preparar este vídeo.</p>
                                    <p class="mt-1 text-xs text-zinc-300">Edite o vídeo e envie o arquivo de novo (de preferência MP4 H.264).</p>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <div class="overflow-y-auto p-5 sm:p-6" :class="vertical && 'md:w-80 md:shrink-0 md:pt-16'">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                    <span class="rating-badge" :class="$store.player.video.ratingClasses"
                          :title="$store.player.video.ratingLabel" x-text="$store.player.video.rating"></span>
                    <h2 class="text-xl font-bold text-white sm:text-2xl" x-text="$store.player.video.title"></h2>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-x-2 text-sm text-zinc-400">
                    <span x-text="$store.player.video.genre"></span>
                    <template x-if="$store.player.video.duration">
                        <span><span class="text-ink-600">•</span> <span x-text="$store.player.video.duration"></span></span>
                    </template>
                    <span class="text-ink-600">•</span>
                    <span x-text="$store.player.video.ratingLabel"></span>
                </div>
                <p class="mt-4 whitespace-pre-line text-sm leading-relaxed text-zinc-300"
                   x-show="$store.player.video.description" x-text="$store.player.video.description"></p>
            </div>
        </div>
    </template>
</div>
