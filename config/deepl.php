<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Clé d'API DeepL
    |--------------------------------------------------------------------------
    | Une clé « Free » se termine par ":fx" et utilise automatiquement l'API gratuite.
    | Ne la commitez jamais : placez-la dans .env.
    */
    'api_key' => env('DEEPL_API_KEY'),

    /*
    | Serveur personnalisé (proxy, tests). Laissez vide pour détecter Free/Pro d'après la clé.
    */
    'server_url' => env('DEEPL_SERVER_URL'),

    /*
    |--------------------------------------------------------------------------
    | Langues
    |--------------------------------------------------------------------------
    | `source_lang` : langue des textes sources (aussi celle de vos clés si vous
    | utilisez le français comme clé). `null` = détection automatique par DeepL.
    | `target_languages` : correspondance entre vos codes de locale Laravel et les codes
    | exigés par DeepL pour certaines cibles (en => en-GB, pt => pt-BR…).
    */
    'source_lang' => env('DEEPL_SOURCE_LANG', 'fr'),

    'target_languages' => [
        'en' => 'en-GB',
        'pt' => 'pt-PT',
    ],

    /*
    | Formalité : default, more, less, prefer_more, prefer_less. `null` = valeur DeepL.
    */
    'formality' => env('DEEPL_FORMALITY'),

    /*
    | Contexte libre (non facturé) transmis à DeepL pour de meilleures traductions.
    */
    'context' => env('DEEPL_CONTEXT'),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    | Évite de re-facturer un texte déjà traduit. `store` null = magasin par défaut.
    */
    'cache' => [
        'enabled' => env('DEEPL_CACHE', true),
        'store' => env('DEEPL_CACHE_STORE'),
        'ttl' => (int) env('DEEPL_CACHE_TTL', 60 * 60 * 24 * 30),
        'prefix' => 'deepl',
    ],

    /*
    | Nombre maximal de textes par requête (DeepL accepte 50 textes au plus).
    */
    'batch_size' => (int) env('DEEPL_BATCH_SIZE', 50),

    /*
    |--------------------------------------------------------------------------
    | Commande `deepl:translate`
    |--------------------------------------------------------------------------
    | `scan_paths` : dossiers analysés pour trouver les clés __('…'), trans('…'), @lang('…').
    | `lang_path` : dossier des fichiers JSON (null = lang_path() de l'application).
    */
    'scan_paths' => [
        'app',
        'resources/js',
        'resources/views',
    ],

    'lang_path' => null,
];
