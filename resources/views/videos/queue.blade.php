{{-- Fila de cadastro: vários filmes com os mesmos campos do cadastro, enviados um de cada vez. --}}
<x-layouts.app title="Novos vídeos">
    <div x-data="uploadQueue(@js($upload))" class="pb-36">
        <a href="{{ route('videos.index') }}" class="inline-flex items-center gap-1.5 text-sm text-zinc-400 transition hover:text-white">
            <x-icon name="arrow-left" class="size-4" /> Voltar para vídeos
        </a>
        <h1 class="mt-3 text-2xl font-bold tracking-tight text-white sm:text-3xl">Novos vídeos</h1>
        <p class="mt-1 text-sm text-zinc-400">
            Preencha um ou mais filmes e clique em <strong class="text-zinc-200">Carregar todos os filmes</strong>. Cada filme ganha o seu link fixo.
        </p>

        <div class="mt-4 flex gap-3 rounded-xl border border-amber-400/25 bg-amber-400/10 p-3.5 text-sm text-amber-100">
            <x-icon name="alert" class="size-5 shrink-0 text-amber-300" />
            <p>
                Tamanho máximo por envio: <strong>{{ $upload['batchMaxLabel'] }}</strong> somando todos os filmes
                (cada vídeo até {{ $upload['maxLabel'] }}). Para mais, carregue em mais de uma vez.
                Formato recomendado: <strong>MP4 (H.264)</strong>.
            </p>
        </div>

        {{-- Filmes já carregados: links fixos --}}
        <section id="queue-results" x-cloak x-show="done.length" class="mt-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-semibold text-white" x-text="done.length === 1 ? '1 filme carregado' : `${done.length} filmes carregados`"></h2>
                    <p class="text-sm text-emerald-100/80">Cada um já tem o seu link fixo. Quem clica assiste direto, sem opção de baixar.</p>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="btn btn-secondary" @click="copyAll()">
                        <x-icon name="copy" class="size-4" /> <span x-text="copiedAll ? 'Copiado!' : 'Copiar todos'"></span>
                    </button>
                    <a href="{{ route('videos.index') }}" class="btn btn-ghost">Ver lista</a>
                </div>
            </div>
            <ul class="mt-4 divide-y divide-emerald-500/15">
                <template x-for="film in done" :key="'done-' + film.key">
                    <li class="flex flex-col gap-2 py-2.5 sm:flex-row sm:items-center">
                        <span class="font-medium text-white sm:w-56 sm:truncate" x-text="film.title"></span>
                        <input type="text" readonly :value="film.fixedUrl" @focus="$el.select()" class="field-input flex-1 py-2 font-mono text-xs">
                        <button type="button" class="btn btn-secondary py-2 text-xs" @click="copy(film.fixedUrl, film)">
                            <x-icon name="copy" class="size-4" /> <span x-text="film.copied ? 'Copiado!' : 'Copiar'"></span>
                        </button>
                    </li>
                </template>
            </ul>
            <button type="button" x-show="finished" class="btn btn-primary mt-4" @click="restart()">
                <x-icon name="plus" class="size-4" /> Cadastrar mais filmes
            </button>
        </section>

        {{-- Um bloco por filme (mesmos campos do cadastro) --}}
        <div class="mt-6 space-y-5">
            <template x-for="(film, index) in films" :key="film.key">
                <section :id="'film-' + film.key" x-show="film.status !== 'done'" class="card p-5 sm:p-6"
                         :class="film.status === 'error' && 'ring-1 ring-brand-500/60'">
                    <header class="flex items-center gap-3">
                        <h2 class="text-base font-semibold text-white" x-text="`Filme ${index + 1}`"></h2>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                              :class="{
                                  'bg-ink-700 text-zinc-300': film.status === 'pending',
                                  'bg-sky-500/15 text-sky-300': film.status === 'uploading' || film.status === 'saving',
                                  'bg-brand-600/15 text-brand-300': film.status === 'error',
                              }"
                              x-text="{ pending: 'Na fila', uploading: `Enviando vídeo ${Math.floor(film.progress)}%`, saving: 'Salvando…', error: 'Corrigir' }[film.status]"></span>
                        <button type="button" class="btn btn-ghost ml-auto px-2.5 py-1.5 text-xs" x-show="!running" @click="removeFilm(film)" title="Tirar este filme da fila">
                            <x-icon name="x" class="size-4" /> Remover
                        </button>
                    </header>

                    <p class="field-error mt-2" x-show="film.error" x-text="film.error"></p>

                    <fieldset class="mt-4 grid gap-6 lg:grid-cols-[minmax(0,1fr)_220px]" :disabled="running">
                        <div class="space-y-4">
                            <div>
                                <label class="field-label" :for="'title-' + film.key">Título</label>
                                <input :id="'title-' + film.key" x-model="film.title" maxlength="150" class="field-input" placeholder="Ex.: Amor em Paris">
                                <p class="field-error" x-show="film.errors.title" x-text="film.errors.title"></p>
                            </div>
                            <div>
                                <label class="field-label" :for="'desc-' + film.key">Descrição</label>
                                <textarea :id="'desc-' + film.key" x-model="film.description" rows="3" maxlength="5000" class="field-input resize-y" placeholder="Sinopse do vídeo..."></textarea>
                                <p class="field-error" x-show="film.errors.description" x-text="film.errors.description"></p>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="field-label" :for="'genre-' + film.key">Tipo</label>
                                    <select :id="'genre-' + film.key" x-model="film.genre" class="field-input">
                                        <option value="">Selecione o tipo</option>
                                        @foreach ($genres as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <p class="field-error" x-show="film.errors.genre" x-text="film.errors.genre"></p>
                                </div>
                                <div>
                                    <label class="field-label" :for="'price-' + film.key">Valor de venda (R$)</label>
                                    <input :id="'price-' + film.key" x-model="film.price" data-money inputmode="numeric" maxlength="15" class="field-input" placeholder="0,00">
                                    <p class="field-error" x-show="film.errors.price" x-text="film.errors.price"></p>
                                </div>
                            </div>
                            <div>
                                <p class="field-label">Classificação indicativa</p>
                                <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
                                    @foreach ($ratings as $rating)
                                        <label class="cursor-pointer">
                                            <input type="radio" class="peer sr-only" :name="'rating-' + film.key" value="{{ $rating->value }}" x-model="film.age_rating">
                                            <span class="flex flex-col items-center gap-1.5 rounded-xl border border-ink-600 bg-ink-850 px-2 py-2.5 text-center transition hover:border-zinc-500 peer-checked:border-brand-500 peer-checked:bg-brand-600/10 peer-checked:ring-2 peer-checked:ring-brand-500/30">
                                                <x-rating-badge :rating="$rating" class="h-7 min-w-7 text-sm" />
                                                <span class="text-[11px] font-medium text-zinc-300">{{ $rating->label() }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                <p class="field-error" x-show="film.errors.age_rating" x-text="film.errors.age_rating"></p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            {{-- Capa --}}
                            <div>
                                <p class="field-label">Capa</p>
                                <label class="group relative block aspect-[2/3] w-full cursor-pointer overflow-hidden rounded-xl border-2 border-dashed border-ink-600 bg-ink-850 transition hover:border-zinc-500">
                                    <template x-if="film.coverUrl">
                                        <img :src="film.coverUrl" alt="" class="absolute inset-0 size-full object-cover">
                                    </template>
                                    <span class="absolute inset-0 flex flex-col items-center justify-center gap-1 text-center"
                                          :class="film.coverUrl && 'bg-black/60 opacity-0 transition group-hover:opacity-100'">
                                        <x-icon name="image" class="size-7 text-zinc-400" />
                                        <span class="text-xs font-medium text-zinc-200" x-text="film.coverUrl ? 'Trocar capa' : 'Selecionar capa'"></span>
                                        <span class="text-[10px] text-zinc-500">JPG, PNG, WEBP · ideal 2:3</span>
                                    </span>
                                    <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="pickCover(film, $event)">
                                </label>
                                <p class="field-error" x-show="film.errors.cover" x-text="film.errors.cover"></p>
                            </div>

                            {{-- Vídeo --}}
                            <div>
                                <p class="field-label">Arquivo de vídeo</p>
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border-2 border-dashed border-ink-600 bg-ink-850 p-3 transition hover:border-zinc-500">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-ink-800 text-brand-400"><x-icon name="upload" class="size-4" /></span>
                                    <span class="min-w-0 text-xs">
                                        <span class="block truncate font-medium text-zinc-200" x-text="film.videoFile ? film.videoFile.name : 'Selecionar vídeo'"></span>
                                        <span class="text-zinc-500" x-text="film.videoFile ? sizeLabel(film.videoFile.size) : 'MP4, MOV, WEBM…'"></span>
                                    </span>
                                    <input type="file" accept="{{ collect($upload['extensions'])->map(fn ($e) => '.'.$e)->implode(',') }},video/*" class="sr-only" @change="pickVideo(film, $event)">
                                </label>
                                <p class="field-error" x-show="film.errors.video" x-text="film.errors.video"></p>
                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-ink-700" x-show="film.status === 'uploading' || film.status === 'saving'">
                                    <div class="h-full rounded-full bg-gradient-to-r from-brand-600 to-brand-400 transition-[width] duration-300" :style="`width: ${film.progress}%`"></div>
                                </div>
                            </div>
                        </div>
                    </fieldset>
                </section>
            </template>
        </div>

        <button type="button" class="btn btn-secondary mt-5 w-full border-dashed py-3" x-show="!running && !finished" @click="addFilm()">
            <x-icon name="plus" class="size-4" /> Adicionar outro filme
        </button>

        {{-- Barra fixa: total e botão de carregar --}}
        <div x-show="pending.length && !finished" class="fixed inset-x-0 bottom-0 z-30 border-t border-ink-700 bg-ink-900/95 backdrop-blur lg:left-64">
            <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:px-6 lg:px-8">
                <div class="min-w-0 flex-1 text-sm">
                    <p :class="overLimit ? 'text-brand-300' : 'text-zinc-300'">
                        <strong x-text="pending.length"></strong> <span x-text="pending.length === 1 ? 'filme' : 'filmes'"></span> ·
                        <span x-text="totalLabel"></span> de {{ $upload['batchMaxLabel'] }}
                        <span x-show="overLimit">— passou do limite por envio, remova algum filme.</span>
                    </p>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-ink-700" x-show="running">
                        <div class="h-full rounded-full bg-emerald-500 transition-[width] duration-300" :style="`width: ${overallProgress}%`"></div>
                    </div>
                    <p class="mt-1 text-xs text-zinc-500" x-show="running">Carregando… não feche esta página.</p>
                </div>
                <button type="button" class="btn btn-primary py-3" @click="uploadAll()" :disabled="running || overLimit">
                    <x-icon name="upload" class="size-4" />
                    <span x-text="running ? 'Carregando…' : `Carregar ${pending.length === 1 ? 'o filme' : `todos os filmes (${pending.length})`}`"></span>
                </button>
            </div>
        </div>
    </div>
</x-layouts.app>
