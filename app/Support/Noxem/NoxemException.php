<?php

namespace App\Support\Noxem;

use Illuminate\Http\Client\Response;
use RuntimeException;

/** Falha numa chamada à API da Noxem Cloud. A mensagem pode ser mostrada ao usuário. */
class NoxemException extends RuntimeException
{
    public ?int $status = null;

    public static function fromResponse(Response $response, string $method, string $path): self
    {
        $apiMessage = $response->json('error.message') ?? $response->json('message') ?? $response->json('error');
        $apiMessage = is_string($apiMessage) ? $apiMessage : null;

        $message = match (true) {
            $response->status() === 401 => 'A API Key da Noxem Cloud é inválida ou foi revogada.',
            $response->status() === 403 => 'A Noxem Cloud recusou o pedido (contrato de streaming inativo ou chave sem permissão).',
            $response->status() === 404 => 'Vídeo não encontrado na Noxem Cloud.',
            $response->status() === 413 => 'O vídeo excede o limite do contrato de streaming da Noxem Cloud.',
            $response->status() === 429 => 'Muitos pedidos para a Noxem Cloud. Aguarde um pouco e tente de novo.',
            $response->serverError() => 'A Noxem Cloud está com instabilidade. Tente de novo em instantes.',
            default => $apiMessage ? "Noxem Cloud: {$apiMessage}" : 'A Noxem Cloud recusou o pedido.',
        };

        $exception = new self($message);
        $exception->status = $response->status();

        // Para o log: rota e resposta resumida (nunca a chave).
        report(new RuntimeException(sprintf(
            'Noxem %s %s respondeu %d: %s',
            strtoupper($method),
            $path,
            $response->status(),
            mb_substr((string) $response->body(), 0, 500),
        )));

        return $exception;
    }
}
