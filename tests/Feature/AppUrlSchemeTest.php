<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Tests\TestCase;

class AppUrlSchemeTest extends TestCase
{
    public function test_domain_without_scheme_generates_https_urls(): void
    {
        // Coolify pode entregar só o domínio em APP_URL.
        config(['app.url' => 'linkvideo.example.test']);

        (new AppServiceProvider($this->app))->boot();

        $this->assertSame('https://linkvideo.example.test', config('app.url'));
        $this->assertStringStartsWith('https://', route('login'));
    }

    public function test_http_app_url_is_kept(): void
    {
        config(['app.url' => 'http://localhost']);

        (new AppServiceProvider($this->app))->boot();

        $this->assertSame('http://localhost', config('app.url'));
    }
}
