{{-- Olhinho dos valores: pede a senha do membro para mostrar os valores em dinheiro. --}}
<div x-data x-init="@if (session('finance_error')) $store.finance.open = true @endif"
     x-cloak x-show="$store.finance.open" x-transition.opacity @keydown.escape.window="$store.finance.open = false"
     class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center sm:p-6" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-black/75 backdrop-blur-sm" @click="$store.finance.open = false"></div>

    <form method="POST" action="{{ route('finance.show') }}" class="relative w-full max-w-sm rounded-t-2xl border border-ink-700 bg-ink-900 p-6 shadow-2xl shadow-black sm:rounded-2xl"
          x-effect="$store.finance.open && $nextTick(() => $refs.password.focus())">
        @csrf
        <span class="grid size-12 place-items-center rounded-2xl bg-brand-600/15 text-brand-400"><x-icon name="eye" class="size-6" /></span>
        <h2 class="mt-4 text-lg font-bold text-white">Mostrar valores</h2>
        <p class="mt-1 text-sm text-zinc-400">
            Digite a sua senha para ver os valores por {{ \App\Support\Finance::minutes() }} minutos.
        </p>

        <input x-ref="password" type="password" name="password" required autocomplete="current-password"
               class="field-input mt-4" placeholder="Sua senha">
        @if (session('finance_error'))
            <p class="field-error">{{ session('finance_error') }}</p>
        @endif

        <div class="mt-5 flex justify-end gap-2">
            <button type="button" @click="$store.finance.open = false" class="btn btn-secondary">Cancelar</button>
            <button type="submit" class="btn btn-primary"><x-icon name="eye" class="size-4" /> Mostrar</button>
        </div>
    </form>
</div>
