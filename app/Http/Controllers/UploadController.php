<?php

namespace App\Http\Controllers;

use App\Support\ChunkedUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function chunk(Request $request, ChunkedUpload $uploads): JsonResponse
    {
        $data = $request->validate([
            'upload_id' => ['required', 'uuid'],
            'index' => ['required', 'integer', 'min:0'],
            'total' => ['required', 'integer', 'min:1', 'max:100000'],
            'filename' => ['required', 'string', 'max:255'],
            'filesize' => ['required', 'integer', 'min:1'],
            'chunk' => ['required', 'file', 'max:'.(intdiv(ChunkedUpload::chunkSize(), 1024) + 1)],
        ]);

        $status = $uploads->receive(
            uploadId: $data['upload_id'],
            index: (int) $data['index'],
            total: (int) $data['total'],
            filename: $data['filename'],
            filesize: (int) $data['filesize'],
            chunk: $request->file('chunk'),
            userId: $request->user()->id,
        );

        return response()->json($status);
    }
}
