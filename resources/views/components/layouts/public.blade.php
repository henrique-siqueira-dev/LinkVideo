@props(['title' => null, 'footer' => null])

{{-- Layout da tela pública do link fixo: sem menu, sem login e sem o JavaScript do painel. --}}
<x-layouts.base :title="$title" script="resources/js/film.js" :async-fonts="true">
    <x-slot:head>
        <meta name="robots" content="noindex, nofollow">
        <meta name="referrer" content="no-referrer">
        <meta name="theme-color" content="#000000">
        {{ $head ?? '' }}
    </x-slot:head>

    <div class="flex min-h-screen flex-col">
        {{ $slot }}

        <footer class="px-4 pb-6 text-center text-xs text-zinc-600">{{ $footer ?? config('app.name') }}</footer>
    </div>
</x-layouts.base>
