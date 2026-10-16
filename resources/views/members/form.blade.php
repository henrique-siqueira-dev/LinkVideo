@php
    $editing = $member->exists;
    $isMe = $editing && $member->is(auth()->user());
    $title = ! $editing ? 'Novo usuário' : ($isMe ? 'Minha conta' : 'Editar usuário');
@endphp

<x-layouts.app :title="$title">
    <a href="{{ route('members.index') }}" class="inline-flex items-center gap-1.5 text-sm text-zinc-400 transition hover:text-white">
        <x-icon name="arrow-left" class="size-4" /> Voltar para usuários
    </a>
    <h1 class="mt-3 text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $title }}</h1>
    <p class="mt-1 text-sm text-zinc-400">
        {{ $editing ? 'Deixe a senha em branco para manter a atual.' : 'O novo usuário entra como master: acessa e gerencia tudo no painel.' }}
    </p>

    <form method="POST" action="{{ $editing ? route('members.update', $member) : route('members.store') }}" class="card mt-6 max-w-xl space-y-5 p-5 sm:p-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div>
            <label for="name" class="field-label">Nome</label>
            <input id="name" name="name" value="{{ old('name', $member->name) }}" required maxlength="120" class="field-input" autocomplete="name">
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="field-label">E-mail (usado para entrar)</label>
            <input id="email" name="email" type="email" value="{{ old('email', $member->email) }}" required maxlength="150" class="field-input" autocomplete="email">
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-5 sm:grid-cols-2" x-data="{ show: false }">
            <div>
                <label for="password" class="field-label">{{ $editing ? 'Nova senha' : 'Senha' }}</label>
                <input id="password" name="password" :type="show ? 'text' : 'password'" type="password" {{ $editing ? '' : 'required' }} minlength="8" class="field-input" autocomplete="new-password" placeholder="mínimo 8 caracteres">
                @error('password') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password_confirmation" class="field-label">Repita a senha</label>
                <input id="password_confirmation" name="password_confirmation" :type="show ? 'text' : 'password'" type="password" {{ $editing ? '' : 'required' }} minlength="8" class="field-input" autocomplete="new-password">
            </div>
            <label class="flex items-center gap-2 text-xs text-zinc-400 sm:col-span-2">
                <input type="checkbox" x-model="show" class="size-4 accent-brand-600"> Mostrar senha
            </label>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn btn-primary"><x-icon name="check" class="size-4" /> {{ $editing ? 'Salvar' : 'Cadastrar usuário' }}</button>
            <a href="{{ route('members.index') }}" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</x-layouts.app>
