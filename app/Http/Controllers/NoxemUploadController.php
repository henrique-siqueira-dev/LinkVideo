<?php

namespace App\Http\Controllers;

use App\Support\Noxem\NoxemClient;
use App\Support\Noxem\NoxemUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Upload para a Noxem Cloud (VIDEO_STORAGE=noxem). Estas rotas só abrem, assinam e fecham
 * o envio; as partes do arquivo vão do navegador direto para o storage da Noxem.
 */
class NoxemUploadController extends Controller
{
    public function __construct()
    {
        abort_unless(NoxemClient::enabled(), 404);
    }

    public function start(Request $request, NoxemUploads $uploads): JsonResponse
    {
        $data = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'filesize' => ['required', 'integer', 'min:1'],
            'content_type' => ['nullable', 'string', 'max:120'],
        ]);

        return response()->json($uploads->start(
            userId: $request->user()->id,
            filename: $data['filename'],
            filesize: (int) $data['filesize'],
            contentType: $data['content_type'] ?? null,
        ));
    }

    public function sign(Request $request, string $token, NoxemUploads $uploads): JsonResponse
    {
        $data = $request->validate([
            'part_numbers' => ['required', 'array', 'min:1', 'max:100'],
            'part_numbers.*' => ['integer', 'min:1', 'max:10000'],
        ]);

        return response()->json($uploads->sign($token, $request->user()->id, $data['part_numbers']));
    }

    public function complete(Request $request, string $token, NoxemUploads $uploads): JsonResponse
    {
        return response()->json($uploads->complete($token, $request->user()->id));
    }

    public function abort(Request $request, string $token, NoxemUploads $uploads): JsonResponse
    {
        $uploads->abort($token, $request->user()->id);

        return response()->json(['aborted' => true]);
    }
}
