@props(['title', 'subtitle' => null, 'labels' => [], 'values' => null, 'money' => false, 'horizontal' => false, 'seriesLabel' => '', 'height' => 'h-56'])

{{--
    Gráfico de barras de uma série. Com $values === null (valores ocultos) mostra o cadeado.
    Sempre tem "Ver tabela" com os mesmos números (acessibilidade).
--}}
<section {{ $attributes->class('card viz-root p-5') }}>
    <header class="mb-4">
        <h3 class="font-semibold text-white">{{ $title }}</h3>
        @if ($subtitle) <p class="text-xs text-zinc-500">{{ $subtitle }}</p> @endif
    </header>

    @if ($values === null)
        <div class="grid {{ $height }} place-items-center rounded-xl border border-dashed border-ink-600 text-center">
            <div>
                <x-icon name="eye-off" class="mx-auto size-7 text-zinc-500" />
                <p class="mt-2 text-sm text-zinc-400">Valores ocultos</p>
                <button type="button" @click="$store.finance.open = true" class="btn btn-secondary mt-3 px-3 py-1.5 text-xs">
                    <x-icon name="eye" class="size-4" /> Mostrar com senha
                </button>
            </div>
        </div>
    @elseif (array_sum($values) === 0)
        <div class="grid {{ $height }} place-items-center rounded-xl border border-dashed border-ink-600 text-sm text-zinc-500">
            Sem dados no período ainda.
        </div>
    @else
        <div class="relative {{ $height }}" x-data="barChart(@js(['labels' => array_values($labels), 'values' => array_values($values), 'money' => $money, 'horizontal' => $horizontal, 'seriesLabel' => $seriesLabel]))">
            <canvas x-ref="canvas" role="img" aria-label="{{ $title }}"></canvas>
        </div>
        <details class="mt-3 text-xs text-zinc-400">
            <summary class="cursor-pointer select-none text-zinc-500 hover:text-zinc-300">Ver tabela</summary>
            <div class="mt-2 max-h-48 overflow-y-auto rounded-lg border border-ink-700">
                <table class="w-full text-left">
                    <tbody class="divide-y divide-ink-700/70">
                        @foreach ($labels as $i => $label)
                            <tr>
                                <td class="px-3 py-1.5">{{ $label }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums text-zinc-200">
                                    {{ $money ? \App\Support\Money::format($values[$i]) : number_format($values[$i], 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif
</section>
