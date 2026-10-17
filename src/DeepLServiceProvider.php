<?php

namespace Simonet85\LaravelDeepL;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\ServiceProvider;
use Simonet85\LaravelDeepL\Console\TranslateCommand;
use Simonet85\LaravelDeepL\Console\UsageCommand;

class DeepLServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/deepl.php', 'deepl');

        $this->app->singleton(Translator::class, function ($app) {
            $config = $app['config']->get('deepl', []);
            $cache = ($config['cache']['enabled'] ?? false)
                ? $app->make(CacheFactory::class)->store($config['cache']['store'] ?? null)
                : null;

            return new Translator($config, $cache);
        });

        $this->app->alias(Translator::class, 'deepl');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/deepl.php' => config_path('deepl.php')], 'deepl-config');

            $this->commands([TranslateCommand::class, UsageCommand::class]);
        }
    }
}
