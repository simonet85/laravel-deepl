<?php

namespace Simonet85\LaravelDeepL\Tests\Feature;

use Illuminate\Support\Facades\File;
use Simonet85\LaravelDeepL\Console\TranslateCommand;
use Simonet85\LaravelDeepL\Tests\TestCase;

class TranslateCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/deepl-'.uniqid();
        File::ensureDirectoryExists($this->dir.'/src');
        File::ensureDirectoryExists($this->dir.'/lang');
        $this->app['config']->set('deepl.lang_path', $this->dir.'/lang');
        $this->app['config']->set('deepl.scan_paths', [$this->dir.'/src']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_extracts_literal_keys_from_php_vue_and_ts(): void
    {
        $keys = TranslateCommand::extractKeys(<<<'CODE'
            {{ __('Mes événements') }} {{ __("Renvoyer l'email") }} :label="__('Menu')"
            trans('auth.failed') __('Il y a :count places') @lang('Profil') __(variable)
            CODE);

        $this->assertSame(['Mes événements', "Renvoyer l'email", 'Menu', 'Il y a :count places', 'Profil'], $keys);
    }

    public function test_translates_only_missing_keys_and_keeps_existing_translations(): void
    {
        File::put($this->dir.'/src/page.vue', "<p>{{ __('Bonjour') }} {{ __('Merci') }}</p>");
        File::put($this->dir.'/lang/en.json', json_encode(['Bonjour' => 'Hello (relu)']));
        $client = $this->fakeClient();

        $this->artisan('deepl:translate', ['locale' => 'en'])->assertSuccessful();

        $this->assertSame([['Merci']], array_map(fn ($c) => $c['texts'], $client->calls));
        $this->assertSame(
            ['Bonjour' => 'Hello (relu)', 'Merci' => '[en-GB] Merci'],
            json_decode(File::get($this->dir.'/lang/en.json'), true),
        );
    }

    public function test_reads_keys_from_the_source_language_json(): void
    {
        File::put($this->dir.'/lang/fr.json', json_encode(['Clé existante' => 'Clé existante']));
        $client = $this->fakeClient();

        $this->artisan('deepl:translate', ['locale' => 'es'])->assertSuccessful();

        $this->assertSame(['Clé existante'], $client->sentTexts());
        $this->assertFileExists($this->dir.'/lang/es.json');
    }

    public function test_force_retranslates_existing_keys(): void
    {
        File::put($this->dir.'/src/a.php', "<?php __('Bonjour');");
        File::put($this->dir.'/lang/en.json', json_encode(['Bonjour' => 'Old']));
        $this->fakeClient();

        $this->artisan('deepl:translate', ['locale' => 'en', '--force' => true])->assertSuccessful();

        $this->assertSame('[en-GB] Bonjour', json_decode(File::get($this->dir.'/lang/en.json'), true)['Bonjour']);
    }

    public function test_dry_run_calls_nothing_and_writes_nothing(): void
    {
        File::put($this->dir.'/src/a.php', "<?php __('Bonjour');");
        $client = $this->fakeClient();

        $this->artisan('deepl:translate', ['locale' => 'en', '--dry-run' => true])
            ->expectsOutputToContain('Bonjour')
            ->assertSuccessful();

        $this->assertSame([], $client->calls);
        $this->assertFileDoesNotExist($this->dir.'/lang/en.json');
    }

    public function test_reports_already_up_to_date(): void
    {
        File::put($this->dir.'/src/a.php', "<?php __('Bonjour');");
        File::put($this->dir.'/lang/en.json', json_encode(['Bonjour' => 'Hello']));
        $client = $this->fakeClient();

        $this->artisan('deepl:translate', ['locale' => 'en'])->expectsOutputToContain('déjà à jour')->assertSuccessful();

        $this->assertSame([], $client->calls);
    }

    public function test_usage_command_prints_consumption(): void
    {
        $this->fakeClient();

        $this->artisan('deepl:usage')->expectsOutputToContain('1 500')->assertSuccessful();
    }

    public function test_command_fails_gracefully_without_api_key(): void
    {
        File::put($this->dir.'/src/a.php', "<?php __('Bonjour');");
        $this->app['config']->set('deepl.api_key', null);

        $this->artisan('deepl:translate', ['locale' => 'en'])
            ->expectsOutputToContain('DEEPL_API_KEY')
            ->assertFailed();
    }
}
