<?php

namespace Simonet85\LaravelDeepL\Exceptions;

use RuntimeException;

class DeepLException extends RuntimeException
{
    public static function missingApiKey(): self
    {
        return new self('Clé DeepL absente : renseignez DEEPL_API_KEY dans votre fichier .env.');
    }

    public static function requestFailed(string $reason, ?\Throwable $previous = null): self
    {
        return new self("Échec de la requête DeepL : {$reason}", 0, $previous);
    }
}
