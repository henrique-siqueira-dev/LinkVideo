<?php

/*
 * linkVideo: index.php da pasta pública do servidor.
 *
 * Instalação (uma pasta só, enviada para www/sistemas/):
 *   www/sistemas/linkvideo/                    -> .htaccess, build/, index.php, robots.txt
 *   www/sistemas/linkvideo/__PASTA_SISTEMA__/  -> o sistema (app, vendor, storage, .env...)
 *
 * O nome da subpasta do sistema é aleatório para que ninguém consiga adivinhar
 * o caminho dos arquivos internos. Não renomeie.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

// Não mostra avisos do PHP (com caminhos internos) para o visitante.
ini_set('display_errors', '0');

define('LARAVEL_START', microtime(true));

$appPath = __DIR__.'/__PASTA_SISTEMA__';

if (! is_file($appPath.'/vendor/autoload.php')) {
    http_response_code(500);
    exit('linkVideo: pasta do sistema não encontrada ou envio incompleto.');
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $appPath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $appPath.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $appPath.'/bootstrap/app.php';

// A pasta pública é ESTA (build/ fica aqui)
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
