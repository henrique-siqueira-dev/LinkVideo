<?php

return [

    /*
    | Usuários master criados pelo seeder. A senha inicial vem do .env
    | (MASTER_PASSWORD) para não ficar gravada no código.
    */
    'masters' => [
        ['name' => 'João Lucas', 'email' => 'joaolucasdev182@gmail.com'],
        ['name' => 'Higor Salbonfim', 'email' => 'euhigorsalbonfim@gmail.com'],
    ],

    'master_password' => env('MASTER_PASSWORD'),

    /*
    | No Docker o nginx entrega vídeos e capas (X-Accel-Redirect) depois que o PHP
    | confere o acesso. Formato "pasta-no-disco/=/local-interno/". Vazio: o PHP entrega.
    */
    'x_accel_mapping' => env('MEDIA_X_ACCEL_MAPPING'),

    /*
    | Onde ficam os vídeos novos: "local" (storage/app/private, entregue pelo
    | próprio servidor) ou "noxem" (Noxem Cloud: upload direto do navegador para
    | o storage e reprodução pela CDN deles). Vídeos já gravados continuam onde estão.
    */
    'video_storage' => env('VIDEO_STORAGE', 'local'),

    'uploads' => [
        // Tamanho máximo do vídeo, em MB.
        'video_max_mb' => (int) env('VIDEO_MAX_MB', 4096),

        // Fila de cadastro: soma máxima dos vídeos enviados de uma vez, em MB.
        'batch_max_mb' => (int) env('VIDEO_BATCH_MAX_MB', 4096),

        // Tamanho máximo da capa, em KB.
        'cover_max_kb' => (int) env('COVER_MAX_KB', 5120),

        // Tamanho desejado de cada pedaço do upload em partes, em MB.
        // É reduzido automaticamente se o upload_max_filesize do servidor for menor.
        'chunk_mb' => (int) env('VIDEO_CHUNK_MB', 8),

        'video_extensions' => ['mp4', 'm4v', 'webm', 'mov', 'ogv'],

        'video_mimes' => [
            'video/mp4',
            'video/x-m4v',
            'video/webm',
            'video/quicktime',
            'video/ogg',
            'application/octet-stream',
        ],
    ],

];
