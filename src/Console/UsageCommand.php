<?php

namespace Simonet85\LaravelDeepL\Console;

use Illuminate\Console\Command;
use Simonet85\LaravelDeepL\Exceptions\DeepLException;
use Simonet85\LaravelDeepL\Translator;

class UsageCommand extends Command
{
    protected $signature = 'deepl:usage';

    protected $description = 'Affiche la consommation de caractères du compte DeepL';

    public function handle(Translator $translator): int
    {
        try {
            $usage = $translator->usage();
        } catch (DeepLException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            '%s / %s caractères utilisés (%s restants).',
            number_format($usage['used'], 0, ',', ' '),
            number_format($usage['limit'], 0, ',', ' '),
            number_format($usage['remaining'], 0, ',', ' '),
        ));

        return self::SUCCESS;
    }
}
