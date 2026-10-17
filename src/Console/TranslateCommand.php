<?php

namespace Simonet85\LaravelDeepL\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Simonet85\LaravelDeepL\Exceptions\DeepLException;
use Simonet85\LaravelDeepL\Translator;

class TranslateCommand extends Command
{
    protected $signature = 'deepl:translate
        {locale : Locale cible (ex. en, es, de)}
        {--source= : Langue source (défaut : deepl.source_lang)}
        {--scan=* : Dossiers à analyser (défaut : deepl.scan_paths)}
        {--force : Retraduit aussi les clés déjà présentes}
        {--dry-run : Liste les clés à traduire sans appeler DeepL}';

    protected $description = 'Complète lang/{locale}.json en traduisant avec DeepL les clés trouvées dans le code';

    public function handle(Translator $translator): int
    {
        $locale = (string) $this->argument('locale');
        $source = $this->option('source') ?: config('deepl.source_lang');
        $directory = config('deepl.lang_path') ?: lang_path();
        $targetFile = "{$directory}/{$locale}.json";

        $keys = $this->collectKeys($directory, $source);
        $existing = $this->readJson($targetFile);

        $todo = $this->option('force')
            ? $keys
            : array_values(array_filter($keys, fn (string $key) => ! array_key_exists($key, $existing)));

        if ($todo === []) {
            $this->components->info("{$locale}.json est déjà à jour (".count($keys).' clés).');

            return self::SUCCESS;
        }

        $characters = array_sum(array_map('mb_strlen', $todo));
        $this->components->info(count($todo)." clé(s) à traduire vers « {$locale} » (~{$characters} caractères).");

        if ($this->option('dry-run')) {
            foreach ($todo as $key) {
                $this->line("  - {$key}");
            }

            return self::SUCCESS;
        }

        try {
            $translated = $translator->translate(array_combine($todo, $todo), $locale, $source);
        } catch (DeepLException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $result = array_merge($existing, $translated);
        File::ensureDirectoryExists($directory);
        File::put($targetFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

        $this->components->info(count($translated)." clé(s) écrites dans {$targetFile}.");

        return self::SUCCESS;
    }

    /**
     * Clés = clés de tous les lang/*.json existants + littéraux trouvés dans le code.
     *
     * @return list<string>
     */
    private function collectKeys(string $directory, ?string $source): array
    {
        // Clés déjà connues : celles de tous les lang/*.json (langue source, mais aussi les autres
        // locales), ce qui couvre les clés construites dynamiquement, invisibles pour le scanner.
        $keys = [];
        foreach (glob("{$directory}/*.json") ?: [] as $file) {
            array_push($keys, ...array_keys($this->readJson($file)));
        }
        if ($source) {
            array_push($keys, ...array_keys($this->readJson("{$directory}/{$source}.json")));
        }

        $paths = $this->option('scan') ?: config('deepl.scan_paths', []);
        foreach ($paths as $path) {
            $absolute = str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) ? $path : base_path($path);
            if (! is_dir($absolute)) {
                continue;
            }
            foreach (File::allFiles($absolute) as $file) {
                if (! in_array($file->getExtension(), ['php', 'vue', 'ts', 'js'], true)) {
                    continue;
                }
                foreach (self::extractKeys($file->getContents()) as $key) {
                    $keys[] = $key;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Littéraux passés à __(), trans(), trans_choice() ou @lang() (guillemets simples ou doubles).
     * Les clés « fichier.clef » de lang/*.php (minuscules séparées par des points) sont ignorées : elles ne sont pas des phrases.
     *
     * @return list<string>
     */
    public static function extractKeys(string $code): array
    {
        $pattern = '/(?:__|trans|trans_choice|@lang)\(\s*(?:\'((?:\\\\.|[^\'\\\\])*)\'|"((?:\\\\.|[^"\\\\])*)")/';
        preg_match_all($pattern, $code, $matches, PREG_SET_ORDER);

        $keys = [];
        foreach ($matches as $m) {
            $single = ($m[1] ?? '') !== '';
            $raw = $single ? $m[1] : ($m[2] ?? '');
            $key = stripcslashes($raw);

            if ($key !== '' && ! preg_match('/^[a-z0-9_\-]+(\.[a-z0-9_\-]+)+$/', $key)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /** @return array<string, string> */
    private function readJson(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : [];
    }
}
