<?php

namespace App\Http\Requests;

use App\Enums\AgeRating;
use App\Enums\Genre;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isMaster();
    }

    public function rules(): array
    {
        $creating = $this->routeIs('videos.store');

        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'genre' => ['required', Rule::enum(Genre::class)],
            'age_rating' => ['required', Rule::enum(AgeRating::class)],
            'cover' => [
                $creating ? 'required' : 'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.config('linkvideo.uploads.cover_max_kb'),
            ],
            'video_token' => [$creating ? 'required' : 'nullable', 'uuid'],
            'duration' => ['nullable', 'integer', 'min:0', 'max:604800'],
            'video_width' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'video_height' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'price' => ['nullable', 'string', 'max:20', 'regex:/^(R\$\s*)?[\d.]+(,\d{1,2})?$/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'título',
            'description' => 'descrição',
            'genre' => 'tipo',
            'age_rating' => 'classificação',
            'cover' => 'capa',
            'video_token' => 'vídeo',
            'duration' => 'duração',
            'price' => 'valor',
        ];
    }

    public function messages(): array
    {
        return [
            'video_token.required' => 'Selecione o arquivo de vídeo e aguarde o envio terminar.',
            'price.regex' => 'Informe o valor em reais (ex.: 49,90).',
        ];
    }
}
