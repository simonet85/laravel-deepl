<?php

namespace Simonet85\LaravelDeepL;

use Closure;
use DeepL\DeepLClient;
use DeepL\DeepLException as SdkException;
use Illuminate\Contracts\Cache\Repository;
use Simonet85\LaravelDeepL\Exceptions\DeepLException;

class Translator
{
    /** Délimiteur XML qui protège les :placeholders de Laravel de la traduction. */
    private const KEEP_TAG = 'x';

    private ?object $client = null;

    /**
     * @param  array<string, mixed>  $config
     * @param  Closure(): object|null  $clientFactory  Fabrique du client SDK (surchargée dans les tests).
     */
    public function __construct(
        private readonly array $config,
        private readonly ?Repository $cache = null,
        private readonly ?Closure $clientFactory = null,
    ) {}

    /**
     * Traduit un texte ou une liste de textes. Les clés du tableau sont conservées.
     *
     * @param  string|array<array-key, string>  $text
     * @param  array<string, mixed>  $options  Options DeepL supplémentaires (formality, context, glossary…).
     * @return ($text is string ? string : array<array-key, string>)
     */
    public function translate(string|array $text, string $target, ?string $source = null, array $options = []): string|array
    {
        $single = is_string($text);
        $items = $single ? [$text] : $text;

        $source ??= $this->config['source_lang'] ?? null;
        $targetCode = $this->targetCode($target);
        $options = $this->options($options);

        $result = [];
        $pending = [];

        foreach ($items as $key => $value) {
            if (trim((string) $value) === '') {
                $result[$key] = (string) $value;

                continue;
            }

            $cached = $this->cacheGet($source, $targetCode, $options, $value);
            if ($cached !== null) {
                $result[$key] = $cached;
            } else {
                $pending[$key] = $value;
            }
        }

        foreach (array_chunk($pending, max(1, (int) ($this->config['batch_size'] ?? 50)), true) as $chunk) {
            $translated = $this->request(array_map($this->protect(...), array_values($chunk)), $source, $targetCode, $options);

            foreach (array_keys($chunk) as $i => $key) {
                $text = $this->restore($translated[$i]);
                $result[$key] = $text;
                $this->cachePut($source, $targetCode, $options, $chunk[$key], $text);
            }
        }

        // Rétablit l'ordre d'origine.
        $ordered = [];
        foreach (array_keys($items) as $key) {
            $ordered[$key] = $result[$key];
        }

        return $single ? $ordered[array_key_first($ordered)] : $ordered;
    }

    /**
     * Consommation du compte : ['used' => int, 'limit' => int, 'remaining' => int].
     *
     * @return array{used: int, limit: int, remaining: int}
     */
    public function usage(): array
    {
        try {
            $character = $this->client()->getUsage()->character;
        } catch (SdkException $e) {
            throw DeepLException::requestFailed($e->getMessage(), $e);
        }

        $used = (int) $character->count;
        $limit = (int) $character->limit;

        return ['used' => $used, 'limit' => $limit, 'remaining' => max(0, $limit - $used)];
    }

    /** Code DeepL pour une locale Laravel (en → en-GB…). */
    public function targetCode(string $locale): string
    {
        $map = $this->config['target_languages'] ?? [];

        return $map[$locale] ?? $locale;
    }

    /**
     * @param  list<string>  $texts
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    private function request(array $texts, ?string $source, string $target, array $options): array
    {
        try {
            $results = $this->client()->translateText($texts, $source, $target, $options);
        } catch (SdkException $e) {
            throw DeepLException::requestFailed($e->getMessage(), $e);
        }

        return array_map(fn ($r) => $r->text, is_array($results) ? array_values($results) : [$results]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function options(array $extra): array
    {
        $options = [
            'tag_handling' => 'xml',
            'ignore_tags' => [self::KEEP_TAG],
        ];

        if (! empty($this->config['formality'])) {
            $options['formality'] = $this->config['formality'];
        }
        if (! empty($this->config['context'])) {
            $options['context'] = $this->config['context'];
        }

        return array_merge($options, $extra);
    }

    /** Échappe le XML puis entoure chaque :placeholder pour que DeepL ne le touche pas. */
    private function protect(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_XML1 | ENT_NOQUOTES, 'UTF-8');

        return preg_replace('/(?<![\w:]):[A-Za-z_][A-Za-z0-9_]*/', '<'.self::KEEP_TAG.'>$0</'.self::KEEP_TAG.'>', $escaped);
    }

    private function restore(string $text): string
    {
        $text = str_replace(['<'.self::KEEP_TAG.'>', '</'.self::KEEP_TAG.'>'], '', $text);

        return htmlspecialchars_decode($text, ENT_XML1 | ENT_NOQUOTES);
    }

    private function client(): object
    {
        if ($this->client !== null) {
            return $this->client;
        }

        if ($this->clientFactory !== null) {
            return $this->client = ($this->clientFactory)();
        }

        $key = $this->config['api_key'] ?? null;
        if (! is_string($key) || $key === '') {
            throw DeepLException::missingApiKey();
        }

        $sdkOptions = array_filter(['server_url' => $this->config['server_url'] ?? null]);

        return $this->client = new DeepLClient($key, $sdkOptions);
    }

    /** @param  array<string, mixed>  $options */
    private function cacheKey(?string $source, string $target, array $options, string $text): string
    {
        $prefix = $this->config['cache']['prefix'] ?? 'deepl';

        return $prefix.':'.hash('sha256', json_encode([$source, $target, $options, $text]));
    }

    /** @param  array<string, mixed>  $options */
    private function cacheGet(?string $source, string $target, array $options, string $text): ?string
    {
        if (! $this->cacheEnabled()) {
            return null;
        }

        $value = $this->cache->get($this->cacheKey($source, $target, $options, $text));

        return is_string($value) ? $value : null;
    }

    /** @param  array<string, mixed>  $options */
    private function cachePut(?string $source, string $target, array $options, string $text, string $translated): void
    {
        if ($this->cacheEnabled()) {
            $this->cache->put($this->cacheKey($source, $target, $options, $text), $translated, (int) ($this->config['cache']['ttl'] ?? 0) ?: null);
        }
    }

    private function cacheEnabled(): bool
    {
        return $this->cache !== null && (bool) ($this->config['cache']['enabled'] ?? false);
    }
}
