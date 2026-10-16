{{-- Barra de busca/filtros compartilhada entre a biblioteca e o gerenciamento. --}}
<form method="GET" class="flex flex-col gap-3 sm:flex-row sm:items-center">
    <div class="relative flex-1">
        <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-500" />
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar por título..." class="field-input pl-10">
    </div>
    <div class="grid grid-cols-2 gap-3 sm:flex">
        <select name="genre" class="field-input sm:w-44" onchange="this.form.submit()">
            <option value="">Todos os tipos</option>
            @foreach ($genres as $value => $label)
                <option value="{{ $value }}" @selected(($filters['genre'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="rating" class="field-input sm:w-40" onchange="this.form.submit()">
            <option value="">Classificação</option>
            @foreach ($ratings as $value => $label)
                <option value="{{ $value }}" @selected(($filters['rating'] ?? '') === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="btn btn-secondary">Filtrar</button>
    @if (array_filter($filters))
        <a href="{{ url()->current() }}" class="btn btn-ghost">Limpar</a>
    @endif
</form>
