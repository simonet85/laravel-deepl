<?php

namespace Simonet85\LaravelDeepL\Tests\Feature;

use Simonet85\LaravelDeepL\Exceptions\DeepLException;
use Simonet85\LaravelDeepL\Facades\DeepL;
use Simonet85\LaravelDeepL\Tests\TestCase;
use Simonet85\LaravelDeepL\Translator;

class TranslatorTest extends TestCase
{
    public function test_translates_a_single_string_with_configured_source_and_mapped_target(): void
    {
        $client = $this->fakeClient();

        $this->assertSame('[en-GB] Bonjour', DeepL::translate('Bonjour', 'en'));
        $this->assertSame('fr', $client->calls[0]['source']);
        $this->assertSame('en-GB', $client->calls[0]['target']);
    }

    public function test_keeps_array_keys_and_order(): void
    {
        $this->fakeClient();

        $result = DeepL::translate(['b' => 'Deux', 'a' => 'Un'], 'de');

        $this->assertSame(['b' => '[de] Deux', 'a' => '[de] Un'], $result);
    }

    public function test_protects_laravel_placeholders_and_escapes_xml_characters(): void
    {
        $client = $this->fakeClient();

        $result = DeepL::translate('Il reste :count places & <b>vite</b> pour :name', 'en');

        $this->assertSame(
            'Il reste <x>:count</x> places &amp; &lt;b&gt;vite&lt;/b&gt; pour <x>:name</x>',
            $client->sentTexts()[0],
        );
        $this->assertSame('[en-GB] Il reste :count places & <b>vite</b> pour :name', $result);
        $this->assertSame('xml', $client->calls[0]['options']['tag_handling']);
        $this->assertSame(['x'], $client->calls[0]['options']['ignore_tags']);
    }

    public function test_does_not_treat_times_as_placeholders(): void
    {
        $client = $this->fakeClient();

        DeepL::translate('Rendez-vous à 15:30', 'en');

        $this->assertSame('Rendez-vous à 15:30', $client->sentTexts()[0]);
    }

    public function test_skips_blank_strings_without_calling_the_api(): void
    {
        $client = $this->fakeClient();

        $this->assertSame(['a' => '', 'b' => '  '], DeepL::translate(['a' => '', 'b' => '  '], 'en'));
        $this->assertSame([], $client->calls);
    }

    public function test_caches_translations_so_repeated_texts_are_not_billed_twice(): void
    {
        $client = $this->fakeClient(['cache' => ['enabled' => true, 'ttl' => 60, 'prefix' => 'deepl']]);

        DeepL::translate('Bonjour', 'en');
        DeepL::translate(['x' => 'Bonjour', 'y' => 'Merci'], 'en');

        $this->assertSame(['Bonjour', 'Merci'], array_map(fn ($t) => $t, array_merge(...array_map(fn ($c) => $c['texts'], $client->calls))));
        $this->assertCount(2, $client->calls);
        $this->assertSame(['Merci'], $client->calls[1]['texts']);
    }

    public function test_splits_requests_into_batches(): void
    {
        $client = $this->fakeClient(['batch_size' => 2, 'cache' => ['enabled' => false]]);

        DeepL::translate(['a' => '1a', 'b' => '2b', 'c' => '3c'], 'en');

        $this->assertCount(2, $client->calls);
    }

    public function test_passes_formality_and_context_options(): void
    {
        $client = $this->fakeClient(['formality' => 'more', 'context' => 'Site d\'événements', 'cache' => ['enabled' => false]]);

        DeepL::translate('Merci', 'de');

        $this->assertSame('more', $client->calls[0]['options']['formality']);
        $this->assertSame('Site d\'événements', $client->calls[0]['options']['context']);
    }

    public function test_reports_usage(): void
    {
        $this->fakeClient();

        $this->assertSame(['used' => 1500, 'limit' => 500000, 'remaining' => 498500], DeepL::usage());
    }

    public function test_fails_clearly_without_api_key(): void
    {
        $this->app['config']->set('deepl.api_key', null);
        $this->app->forgetInstance(Translator::class);

        $this->expectException(DeepLException::class);
        $this->expectExceptionMessage('DEEPL_API_KEY');

        $this->app->make(Translator::class)->translate('Bonjour', 'en');
    }
}
