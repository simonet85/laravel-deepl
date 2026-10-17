<?php

namespace Simonet85\LaravelDeepL\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Simonet85\LaravelDeepL\DeepLServiceProvider;
use Simonet85\LaravelDeepL\Translator;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [DeepLServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('deepl.api_key', 'test-key:fx');
        $app['config']->set('deepl.source_lang', 'fr');
        $app['config']->set('deepl.scan_paths', []);
    }

    /**
     * Remplace le client SDK par un faux « traducteur » : préfixe chaque texte par la langue cible.
     *
     * @return FakeClient
     */
    protected function fakeClient(array $config = []): FakeClient
    {
        $client = new FakeClient;
        $this->app->singleton(Translator::class, function ($app) use ($client, $config) {
            return new Translator(
                array_merge($app['config']->get('deepl'), $config),
                $app['cache']->store(),
                fn () => $client,
            );
        });

        return $client;
    }
}
