@props(['label', 'icon' => null])

{{-- Cartão de número (o número é o "gráfico"). Valor no slot; detalhe opcional em $sub. --}}
<div {{ $attributes->class('card flex flex-col gap-1 p-4') }}>
    <p class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-zinc-500">
        @if ($icon) <x-icon :name="$icon" class="size-4 text-brand-400" /> @endif
        {{ $label }}
    </p>
    <div class="text-2xl font-bold text-white">{{ $slot }}</div>
    @isset($sub)
        <p class="text-xs text-zinc-400">{{ $sub }}</p>
    @endisset
</div>
