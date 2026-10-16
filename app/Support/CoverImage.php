<?php

namespace App\Support;

use GdImage;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Versão leve da capa (JPEG de até 1080 px de largura) para a tela do link e as listas
 * abrirem rápido: uma capa de celular pode ter vários MB. Gerada na primeira vez e
 * guardada em covers/otimizadas/. Sem GD (ou se algo falhar) entrega a capa original.
 */
class CoverImage
{
    private const MAX_WIDTH = 1080;

    /** Abaixo disso a capa já é leve: não vale a pena gerar outra. */
    private const LIGHT_BYTES = 250 * 1024;

    /** Imagens maiores que isso (em pixels) não são abertas, para não estourar a memória do PHP. */
    private const MAX_PIXELS = 16_000_000;

    private const DIRECTORY = 'covers/otimizadas';

    public static function path(string $coverPath): string
    {
        $disk = Storage::disk('local');
        $optimized = static::optimizedPath($coverPath);

        if ($disk->exists($optimized)) {
            return $optimized;
        }

        if (! function_exists('imagecreatefromstring') || ! $disk->exists($coverPath)) {
            return $coverPath;
        }

        try {
            $image = static::resized($disk->path($coverPath));
            if (! $image) {
                return $coverPath;
            }
            $disk->makeDirectory(self::DIRECTORY);
            imagejpeg($image, $disk->path($optimized), 80);

            return $optimized;
        } catch (Throwable $e) {
            report($e);

            return $coverPath;
        }
    }

    /** Apaga a versão leve (capa trocada ou vídeo excluído). */
    public static function forget(?string $coverPath): void
    {
        if ($coverPath) {
            Storage::disk('local')->delete(static::optimizedPath($coverPath));
        }
    }

    private static function optimizedPath(string $coverPath): string
    {
        return self::DIRECTORY.'/'.pathinfo($coverPath, PATHINFO_FILENAME).'.jpg';
    }

    private static function resized(string $file): ?GdImage
    {
        $info = @getimagesize($file);
        if (! $info || $info[0] * $info[1] > self::MAX_PIXELS) {
            return null;
        }
        [$width, $height] = $info;
        $isJpeg = $info[2] === IMAGETYPE_JPEG;

        if ($width <= self::MAX_WIDTH && filesize($file) <= self::LIGHT_BYTES) {
            return null;
        }

        // Foto de celular vem "deitada" com a rotação no EXIF; sem como ler, mantém a original.
        $orientation = 1;
        if ($isJpeg) {
            if (! function_exists('exif_read_data')) {
                return null;
            }
            $orientation = (int) (@exif_read_data($file)['Orientation'] ?? 1);
        }

        $source = @imagecreatefromstring((string) file_get_contents($file));
        if (! $source) {
            return null;
        }

        $source = match ($orientation) {
            3 => imagerotate($source, 180, 0),
            6 => imagerotate($source, -90, 0),
            8 => imagerotate($source, 90, 0),
            default => $source,
        };
        $width = imagesx($source);
        $height = imagesy($source);

        $targetWidth = min($width, self::MAX_WIDTH);
        $targetHeight = (int) round($height * $targetWidth / $width);

        // Fundo escuro para capas PNG/WEBP com transparência.
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($target, 0, 0, imagecolorallocate($target, 11, 11, 16));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $target;
    }
}
