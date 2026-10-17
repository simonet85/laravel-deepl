# Laravel DeepL

Package Laravel pour traduire avec [DeepL](https://www.deepl.com/docs-api) : une façade, un cache qui évite de payer deux fois le même texte, la protection des `:placeholders` de Laravel, et une commande Artisan qui remplit vos fichiers `lang/*.json`.

Il s'appuie sur le SDK officiel [`deeplcom/deepl-php`](https://github.com/DeepLcom/deepl-php).

## Installation

```bash
composer require simonet85/laravel-deepl
php artisan vendor:publish --tag=deepl-config   # optionnel
```

## Configuration (.env)

| Variable | Défaut | Rôle |
|---|---|---|
| `DEEPL_API_KEY` | – | Clé d'API. Une clé se terminant par `:fx` utilise l'API gratuite. **Ne la commitez jamais.** |
| `DEEPL_SOURCE_LANG` | `fr` | Langue source. Vide = détection automatique par DeepL. |
| `DEEPL_FORMALITY` | – | `default`, `more`, `less`, `prefer_more`, `prefer_less`. |
| `DEEPL_CONTEXT` | – | Contexte libre (non facturé) pour affiner les traductions. |
| `DEEPL_CACHE` | `true` | Met les traductions en cache. |
| `DEEPL_CACHE_STORE` | défaut Laravel | Magasin de cache à utiliser. |
| `DEEPL_CACHE_TTL` | 30 jours | Durée du cache, en secondes. |
| `DEEPL_BATCH_SIZE` | `50` | Textes par requête (maximum DeepL : 50). |
| `DEEPL_SERVER_URL` | auto | Serveur personnalisé (proxy). |

`target_languages` (dans `config/deepl.php`) associe vos locales aux codes exigés par DeepL, par exemple `en => en-GB`.

## Utilisation

```php
use Simonet85\LaravelDeepL\Facades\DeepL;

DeepL::translate('Bonjour', 'en');                       // "Hello"
DeepL::translate(['a' => 'Merci', 'b' => 'Au revoir'], 'de'); // clés conservées
DeepL::translate('Il reste :count places', 'en');        // :count est préservé
DeepL::usage();                                          // ['used' => …, 'limit' => …, 'remaining' => …]
```

Les textes vides ne sont pas envoyés, et les caractères `& < >` sont échappés puis restaurés.

## Commandes Artisan

```bash
php artisan deepl:translate en            # complète lang/en.json avec les clés manquantes
php artisan deepl:translate es --dry-run  # liste ce qui serait traduit, sans appeler DeepL
php artisan deepl:translate en --force    # retraduit aussi les clés existantes
php artisan deepl:translate de --scan=app --scan=resources/js
php artisan deepl:usage                   # caractères consommés ce mois-ci
```

`deepl:translate` collecte les clés dans `lang/{source}.json` (s'il existe) et dans les littéraux `__('…')`, `trans('…')`, `@lang('…')` des dossiers `scan_paths` (`app`, `resources/js`, `resources/views` par défaut). Les clés de type `auth.failed` sont ignorées. Les traductions déjà présentes sont conservées : relisez et corrigez-les à la main sans crainte de les perdre.

## Tests

```bash
composer install
vendor/bin/phpunit
```

Les tests utilisent un faux client : aucun appel réseau, aucun caractère consommé.

## Licence

MIT
