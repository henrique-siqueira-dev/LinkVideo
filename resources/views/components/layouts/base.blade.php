{{-- script: entrada do Vite (a tela pública do filme usa uma bem menor que a do painel). --}}
@props(['title' => null, 'script' => 'resources/js/app.js', 'asyncFonts' => false])

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    @if (str_starts_with((string) config('app.url'), 'https://'))
        {{-- O servidor não redireciona http -> https; o navegador faz isso. --}}
        <script>if (location.protocol === 'http:') location.replace('https:' + location.href.slice(5));</script>
    @endif
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    @if ($asyncFonts)
        {{-- Não segura a primeira pintura esperando a fonte. --}}
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    @else
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    @endif
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%23e11d48'/%3E%3Cpath d='M12 9.5v13l10-6.5z' fill='white'/%3E%3C/svg%3E">

    {{ $head ?? '' }}

    @vite(['resources/css/app.css', $script])
</head>
<body class="min-h-screen font-sans">
    {{ $slot }}
</body>
</html>
