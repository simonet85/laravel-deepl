<?php

namespace Simonet85\LaravelDeepL\Facades;

use Illuminate\Support\Facades\Facade;
use Simonet85\LaravelDeepL\Translator;

/**
 * @method static string|array translate(string|array $text, string $target, ?string $source = null, array $options = [])
 * @method static array usage()
 * @method static string targetCode(string $locale)
 *
 * @see Translator
 */
class DeepL extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Translator::class;
    }
}
