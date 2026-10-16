<x-layouts.base title="Entrar">
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-12">
        <div class="pointer-events-none absolute -top-40 left-1/2 h-[480px] w-[780px] -translate-x-1/2 rounded-full bg-brand-600/20 blur-[120px]"></div>
        <div class="pointer-events-none absolute -bottom-40 -right-20 h-80 w-96 rounded-full bg-indigo-600/10 blur-[110px]"></div>

        <div class="relative w-full max-w-sm">
            <div class="mb-8 flex flex-col items-center text-center">
                <x-logo class="scale-110" />
                <p class="mt-5 text-sm text-zinc-400">Acesse seu repositório de vídeos</p>
            </div>

            <form method="POST" action="{{ route('login.store') }}" class="card space-y-5 p-6 shadow-2xl shadow-black/40 sm:p-7">
                @csrf

                <div>
                    <label for="email" class="field-label">E-mail</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                           class="field-input" placeholder="voce@email.com">
                    @error('email') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div x-data="{ show: false }">
                    <label for="password" class="field-label">Senha</label>
                    <div class="relative">
                        <input id="password" name="password" :type="show ? 'text' : 'password'" type="password" required autocomplete="current-password"
                               class="field-input pr-16" placeholder="••••••••">
                        <button type="button" @click="show = !show"
                                class="absolute inset-y-0 right-0 px-3.5 text-xs font-semibold text-zinc-400 hover:text-white"
                                x-text="show ? 'Ocultar' : 'Mostrar'">Mostrar</button>
                    </div>
                    @error('password') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-center gap-2.5 text-sm text-zinc-400">
                    <input type="checkbox" name="remember" value="1" class="size-4 rounded border-ink-600 bg-ink-850 accent-brand-600">
                    Manter conectado
                </label>

                <button type="submit" class="btn btn-primary w-full py-3">Entrar</button>
            </form>

            <p class="mt-6 text-center text-xs text-zinc-600">© {{ date('Y') }} {{ config('app.name') }}</p>
        </div>
    </div>
</x-layouts.base>
