<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        // Na hospedagem o nginx atende em HTTPS e repassa ao PHP como HTTP.
        // Sem isto o Laravel gera links, CSS/JS e formulários com http:// e o
        // navegador bloqueia (conteúdo misto).
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
            $this->app['request']->server->set('HTTPS', 'on');
        }
    }
}
