<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // O Coolify pode entregar o domínio sem esquema (SERVICE_FQDN_APP = "linkvideo.exemplo.com").
        // Sem "https://" o bloco abaixo não roda e os pedidos do upload saem como http:// (conteúdo misto).
        $appUrl = trim((string) config('app.url'));
        if ($appUrl !== '' && ! preg_match('#^[a-z][a-z0-9+.-]*://#i', $appUrl)) {
            config(['app.url' => 'https://'.ltrim($appUrl, '/')]);
        }

        // Na hospedagem o nginx atende em HTTPS e repassa ao PHP como HTTP.
        // Sem isto o Laravel gera links, CSS/JS e formulários com http:// e o
        // navegador bloqueia (conteúdo misto).
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
            $this->app['request']->server->set('HTTPS', 'on');
        }

        // Docker: o nginx entrega o arquivo (com Range) e o PHP fica livre na hora.
        // Os cabeçalhos são definidos aqui, nunca lidos do visitante.
        if ($mapping = config('linkvideo.x_accel_mapping')) {
            BinaryFileResponse::trustXSendfileTypeHeader();
            $this->app['request']->headers->set('X-Sendfile-Type', 'X-Accel-Redirect');
            $this->app['request']->headers->set('X-Accel-Mapping', $mapping);
        }
    }
}
