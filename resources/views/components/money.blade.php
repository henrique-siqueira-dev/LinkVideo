@props(['cents'])

{{-- Valor em reais; oculto (R$ ••••) até o membro confirmar a senha no olhinho. --}}
@if ($cents === null)
    <span {{ $attributes }}>—</span>
@elseif (\App\Support\Finance::visible())
    <span {{ $attributes->class('tabular-nums') }}>{{ \App\Support\Money::format($cents) }}</span>
@else
    <button type="button" @click="$store.finance.open = true" title="Valores ocultos: clique para ver"
            {{ $attributes->class('cursor-pointer tracking-wider text-zinc-500 hover:text-zinc-300') }}>R$ ••••</button>
@endif
