<?php

namespace App\Services\Ai\Coach;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/** Checks shared by every place an image is sent to the model (chat photo, photo logging). */
final class ImageInput
{
    /** @throws ValidationException key `image` */
    public static function assertAcceptable(UploadedFile $image): void
    {
        $maxKb = (int) config('ai.images.max_kb', 4096);

        if (! $image->isValid() || ! in_array(self::mediaType($image), (array) config('ai.images.mimes', []), true)) {
            throw ValidationException::withMessages(['image' => __('coach.errors.image_invalid')]);
        }
        if ($image->getSize() === false || $image->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages(['image' => __('coach.errors.image_too_large', ['max' => (int) round($maxKb / 1024)])]);
        }
    }

    /** Detected from the file contents, not from the client-supplied name. */
    public static function mediaType(UploadedFile $image): string
    {
        return (string) ($image->getMimeType() ?: 'application/octet-stream');
    }

    /** @return array{type: string, source: array{type: string, media_type: string, data: string}} an API image block */
    public static function base64Block(UploadedFile $image): array
    {
        return [
            'type' => 'image',
            'source' => [
                'type' => 'base64',
                'media_type' => self::mediaType($image),
                'data' => base64_encode((string) file_get_contents($image->getRealPath())),
            ],
        ];
    }
}
