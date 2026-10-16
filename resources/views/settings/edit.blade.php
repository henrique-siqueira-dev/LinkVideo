<x-layouts.app title="Configurações">
    <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">Configurações</h1>
    <p class="mt-1 text-sm text-zinc-400">Ajustes do sistema. As alterações ficam registradas em Atividades.</p>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <form method="POST" action="{{ route('settings.update') }}" class="card space-y-6 p-5 sm:p-6">
            @csrf
            @method('PUT')

            <div>
                <h2 class="font-semibold text-white">Valores ocultos (olhinho)</h2>
                <p class="mt-1 text-xs text-zinc-500">O valor de venda dos filmes fica oculto. Depois de digitar a senha no olhinho, por quanto tempo ele fica visível.</p>
                <select name="finance_minutes" class="field-input mt-3 sm:w-56">
                    @foreach ([5, 15, 30, 60] as $minutes)
                        <option value="{{ $minutes }}" @selected((int) old('finance_minutes', $financeMinutes) === $minutes)>{{ $minutes }} minutos</option>
                    @endforeach
                </select>
                @error('finance_minutes') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="btn btn-primary"><x-icon name="check" class="size-4" /> Salvar configurações</button>
        </form>

        <aside class="space-y-4">
            <div class="card p-5">
                <h2 class="font-semibold text-white">Minha conta</h2>
                <p class="mt-1 text-sm text-zinc-400">Nome, e-mail e senha de acesso.</p>
                <a href="{{ route('members.edit', auth()->user()) }}" class="btn btn-secondary mt-4"><x-icon name="lock" class="size-4" /> Alterar meus dados e senha</a>
            </div>
            <div class="card p-5">
                <h2 class="font-semibold text-white">Usuários e atividades</h2>
                <p class="mt-1 text-sm text-zinc-400">Quem acessa o painel e o registro do que cada um fez.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('members.index') }}" class="btn btn-secondary"><x-icon name="users" class="size-4" /> Usuários</a>
                    <a href="{{ route('activities.index') }}" class="btn btn-secondary"><x-icon name="clock" class="size-4" /> Atividades</a>
                </div>
            </div>
        </aside>
    </div>
</x-layouts.app>
