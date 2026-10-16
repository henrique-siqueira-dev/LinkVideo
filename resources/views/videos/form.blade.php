@php
    $editing = $video->exists;
    $uploaderConfig = [
        ...$upload,
        'token' => old('video_token') ?: null,
        'name' => old('video_name'),
        'size' => (int) old('video_size'),
        'duration' => old('duration'),
    ];
@endphp

<x-layouts.app :title="$editing ? 'Editar vídeo' : 'Novo vídeo'">
    <a href="{{ route('videos.index') }}" class="inline-flex items-center gap-1.5 text-sm text-zinc-400 transition hover:text-white">
        <x-icon name="arrow-left" class="size-4" /> Voltar para vídeos
    </a>
    <h1 class="mt-3 text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $editing ? 'Editar vídeo' : 'Novo vídeo' }}</h1>
    <p class="mt-1 text-sm text-zinc-400">
        {{ $editing ? 'Altere as informações. Envie nova capa ou novo vídeo apenas se quiser substituir.' : 'Preencha as informações e envie a capa e o arquivo de vídeo.' }}
    </p>

    @if ($errors->any())
        <div class="mt-6 flex gap-3 rounded-xl border border-brand-500/30 bg-brand-600/10 p-4 text-sm text-zinc-200">
            <x-icon name="alert" class="size-5 text-brand-400" />
            <div>
                <p class="font-semibold">Revise os campos abaixo.</p>
                @if (! $editing && $errors->hasAny(['title', 'genre', 'age_rating', 'description']) && ! $errors->has('cover'))
                    <p class="mt-0.5 text-zinc-400">Por segurança o navegador não mantém a imagem selecionada: selecione a capa novamente.</p>
                @endif
            </div>
        </div>
    @endif

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('videos.update', $video) : route('videos.store') }}"
          x-data="videoUploader(@js($uploaderConfig))"
          @submit="onSubmit($event, @js(! $editing))"
          class="mt-8 grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        <input type="hidden" name="video_token" :value="token">
        <input type="hidden" name="video_name" :value="name">
        <input type="hidden" name="video_size" :value="size">
        <input type="hidden" name="duration" :value="duration">
        <input type="hidden" name="video_width" :value="width">
        <input type="hidden" name="video_height" :value="height">

        <div class="space-y-6 lg:col-span-2">
            {{-- Informações --}}
            <section class="card space-y-5 p-5 sm:p-6">
                <h2 class="text-base font-semibold text-white">Informações</h2>

                <div>
                    <label for="title" class="field-label">Título</label>
                    <input id="title" name="title" value="{{ old('title', $video->title) }}" required maxlength="150" class="field-input" placeholder="Ex.: Amor em Paris">
                    @error('title') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div x-data="{ count: {{ mb_strlen(old('description', $video->description ?? '')) }} }">
                    <div class="flex items-baseline justify-between">
                        <label for="description" class="field-label">Descrição</label>
                        <span class="text-xs text-zinc-500"><span x-text="count"></span>/5000</span>
                    </div>
                    <textarea id="description" name="description" rows="5" maxlength="5000" @input="count = $el.value.length"
                              class="field-input resize-y" placeholder="Sinopse do vídeo...">{{ old('description', $video->description) }}</textarea>
                    @error('description') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="genre" class="field-label">Tipo</label>
                    <select id="genre" name="genre" required class="field-input">
                        <option value="" disabled @selected(! old('genre', $video->genre?->value))>Selecione o tipo</option>
                        @foreach ($genres as $value => $label)
                            <option value="{{ $value }}" @selected(old('genre', $video->genre?->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('genre') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    @php $financeVisible = \App\Support\Finance::visible(); @endphp
                    <label for="price" class="field-label">Valor de venda (R$)</label>
                    <input id="price" name="price" data-money inputmode="numeric" maxlength="15" class="field-input"
                           value="{{ old('price', $financeVisible && $video->price_cents ? \App\Support\Money::plain($video->price_cents) : '') }}"
                           placeholder="{{ $editing && ! $financeVisible && $video->price_cents ? 'oculto · em branco mantém' : '0,00' }}">
                    @error('price') <p class="field-error">{{ $message }}</p> @enderror
                    @if ($editing && ! $financeVisible && $video->price_cents)
                        <p class="mt-1 text-xs text-zinc-500">Valor oculto. Clique em “Valores ocultos” no menu para ver.</p>
                    @endif
                </div>
                </div>

                <fieldset>
                    <legend class="field-label">Classificação indicativa</legend>
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
                        @foreach ($ratings as $rating)
                            <label class="cursor-pointer">
                                <input type="radio" name="age_rating" value="{{ $rating->value }}" class="peer sr-only" required
                                       @checked(old('age_rating', $video->age_rating?->value) === $rating->value)>
                                <span class="flex flex-col items-center gap-2 rounded-xl border border-ink-600 bg-ink-850 px-2 py-3 text-center transition hover:border-zinc-500 peer-checked:border-brand-500 peer-checked:bg-brand-600/10 peer-checked:ring-2 peer-checked:ring-brand-500/30 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/50">
                                    <x-rating-badge :rating="$rating" class="h-8 min-w-8 text-sm" />
                                    <span class="text-xs font-medium text-zinc-300">{{ $rating->label() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('age_rating') <p class="field-error">{{ $message }}</p> @enderror
                </fieldset>
            </section>

            {{-- Arquivo de vídeo --}}
            <section class="card p-5 sm:p-6">
                <div class="flex items-baseline justify-between gap-4">
                    <h2 class="text-base font-semibold text-white">Arquivo de vídeo</h2>
                    <span class="text-xs text-zinc-500">{{ strtoupper(implode(', ', $upload['extensions'])) }} · até {{ $upload['maxLabel'] }}</span>
                </div>

                @if ($editing)
                    <div class="mt-4 flex items-center gap-3 rounded-xl border border-ink-700 bg-ink-850 px-4 py-3" x-show="status !== 'done'">
                        <x-icon name="film" class="text-zinc-400" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-white">Atual: {{ $video->video_original_name }}</p>
                            <p class="text-xs text-zinc-500">{{ $video->formattedSize() }}{{ $video->formattedDuration() ? ' · '.$video->formattedDuration() : '' }}</p>
                        </div>
                        <button type="button" class="btn btn-ghost px-3 py-1.5 text-xs" @click="$store.player.open(@js($video->toPlayerPayload()))">
                            <x-icon name="play" class="size-3.5" /> Assistir
                        </button>
                    </div>
                @endif

                {{-- Seleção / arrastar e soltar --}}
                <label x-show="status === 'idle' || status === 'error'"
                       @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="onDrop($event)"
                       :class="dragging ? 'border-brand-500 bg-brand-600/10' : 'border-ink-600 hover:border-zinc-500 hover:bg-ink-850'"
                       class="mt-4 flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-6 py-10 text-center transition">
                    <span class="grid size-12 place-items-center rounded-full bg-ink-800 text-brand-400"><x-icon name="upload" class="size-6" /></span>
                    <span class="mt-3 text-sm font-semibold text-white">{{ $editing ? 'Enviar um novo vídeo para substituir' : 'Arraste o vídeo aqui ou clique para selecionar' }}</span>
                    <span class="mt-1 text-xs text-zinc-500">MP4 (H.264) é o formato com melhor compatibilidade nos navegadores</span>
                    <input type="file" class="sr-only" accept="{{ collect($upload['extensions'])->map(fn ($e) => '.'.$e)->implode(',') }},video/*" @change="onInput($event)">
                </label>

                <p class="field-error flex items-center gap-1.5" x-cloak x-show="status === 'error'">
                    <x-icon name="alert" class="size-4" /> <span x-text="error"></span>
                </p>
                @error('video_token') <p class="field-error" x-show="status !== 'done'">{{ $message }}</p> @enderror

                {{-- Enviando / concluído --}}
                <div x-cloak x-show="status === 'uploading' || status === 'done'" class="mt-4 rounded-xl border border-ink-700 bg-ink-850 p-4">
                    <div class="flex items-center gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-lg"
                              :class="status === 'done' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-brand-600/15 text-brand-400'">
                            <template x-if="status === 'done'"><x-icon name="check" /></template>
                            <template x-if="status === 'uploading'"><x-icon name="upload" class="size-5 animate-pulse" /></template>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-white" x-text="name"></p>
                            <p class="text-xs text-zinc-500">
                                <template x-if="status === 'uploading'"><span x-text="`${uploadedLabel} de ${sizeLabel}`"></span></template>
                                <template x-if="status === 'done'"><span x-text="`${sizeLabel} · envio concluído`"></span></template>
                            </p>
                        </div>
                        <button type="button" class="btn btn-ghost px-3 py-1.5 text-xs" @click="reset()"
                                x-text="status === 'uploading' ? 'Cancelar' : 'Trocar vídeo'"></button>
                    </div>
                    <div class="mt-3 flex items-center gap-3" x-show="status === 'uploading'">
                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-ink-700">
                            <div class="h-full rounded-full bg-gradient-to-r from-brand-600 to-brand-400 transition-[width] duration-300" :style="`width: ${progress}%`"></div>
                        </div>
                        <span class="w-10 text-right text-xs font-semibold tabular-nums text-zinc-300" x-text="`${Math.floor(progress)}%`"></span>
                    </div>
                </div>
            </section>
        </div>

        {{-- Capa --}}
        <aside class="space-y-6">
            <section class="card p-5 sm:p-6" x-data="coverPreview(@js($editing ? $video->coverUrl() : null))">
                <h2 class="text-base font-semibold text-white">Capa</h2>
                <p class="mt-1 text-xs text-zinc-500">JPG, PNG ou WEBP · até {{ intdiv(config('linkvideo.uploads.cover_max_kb'), 1024) }} MB · ideal 2:3 (ex.: 800×1200)</p>

                <label class="group relative mt-4 block aspect-[2/3] cursor-pointer overflow-hidden rounded-xl border-2 border-dashed border-ink-600 bg-ink-850 transition hover:border-zinc-500">
                    <template x-if="url">
                        <img :src="url" alt="Pré-visualização da capa" class="absolute inset-0 size-full object-cover">
                    </template>
                    <span class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-center transition"
                          :class="url ? 'bg-black/60 opacity-0 group-hover:opacity-100' : ''">
                        <x-icon name="image" class="size-8 text-zinc-400" />
                        <span class="text-sm font-medium text-zinc-200" x-text="url ? 'Trocar capa' : 'Selecionar capa'"></span>
                    </span>
                    <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="pick($event)" @required(! $editing)>
                </label>
                @error('cover') <p class="field-error">{{ $message }}</p> @enderror
            </section>

            <div class="flex gap-3 lg:flex-col">
                <button type="submit" class="btn btn-primary flex-1 py-3" :disabled="status === 'uploading' || submitting">
                    <x-icon name="check" class="size-4" />
                    <span x-text="submitting ? 'Salvando...' : (status === 'uploading' ? `Enviando vídeo... ${Math.floor(progress)}%` : @js($editing ? 'Salvar alterações' : 'Cadastrar vídeo'))">
                        {{ $editing ? 'Salvar alterações' : 'Cadastrar vídeo' }}
                    </span>
                </button>
                <a href="{{ route('videos.index') }}" class="btn btn-secondary flex-1 py-3">Cancelar</a>
            </div>
        </aside>
    </form>
</x-layouts.app>
