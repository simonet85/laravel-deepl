<?php

namespace Simonet85\LaravelDeepL\Tests;

/**
 * Double du client DeepL : « traduit » en préfixant [cible] et en conservant le XML reçu.
 */
class FakeClient
{
    /** @var list<array{texts: array, source: ?string, target: string, options: array}> */
    public array $calls = [];

    public function translateText(array|string $texts, ?string $source, string $target, array $options = []): array
    {
        $this->calls[] = compact('texts', 'source', 'target', 'options');

        return array_map(fn (string $t) => (object) ['text' => "[{$target}] {$t}"], (array) $texts);
    }

    public function getUsage(): object
    {
        return (object) ['character' => (object) ['count' => 1500, 'limit' => 500000]];
    }

    public function sentTexts(): array
    {
        return array_merge(...array_map(fn ($c) => (array) $c['texts'], $this->calls));
    }
}
