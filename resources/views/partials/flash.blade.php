@if (session('success') || session('error'))
    @php $isError = (bool) session('error'); @endphp
    <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-transition
         class="fixed bottom-5 right-5 left-5 z-40 sm:left-auto sm:w-96">
        <div @class([
            'flex items-start gap-3 rounded-xl border p-4 shadow-2xl shadow-black/50 backdrop-blur',
            'border-emerald-500/30 bg-emerald-950/90 text-emerald-100' => ! $isError,
            'border-brand-500/30 bg-ink-900/95 text-zinc-100' => $isError,
        ])>
            <x-icon :name="$isError ? 'alert' : 'check'" :class="'mt-0.5 size-5 '.($isError ? 'text-brand-400' : 'text-emerald-400')" />
            <p class="flex-1 text-sm">{{ session('success') ?? session('error') }}</p>
            <button type="button" @click="show = false" class="opacity-60 transition hover:opacity-100" aria-label="Fechar">
                <x-icon name="x" class="size-4" />
            </button>
        </div>
    </div>
@endif
