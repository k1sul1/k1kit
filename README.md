# k1 kit

The next Jetpack, but with less cancer. Works as a toolkit in WordPress development. "Designed" for REST API driven sites, but handy in traditional WordPress development as well.

New features are subject to be added, but it's up to you to decide if you use them.

**As this plugin is in pretty early development, backwards compatibility might break at any given time.** After reaching 1.0, the plugin will follow semantic versioning, and breaking changes will always come with a major version number increment: 1.2.3 => 2.0.0

## Features
- Custom Gutenberg block support
  - Sample blocks available at [k1sul1/wordpress-theme-base](https://github.com/k1sul1/wordpress-theme-base)
- Support for multiple languages using Polylang
  - ACF Options page for each language
  - Falling back to core i18n functions if Polylang isn't available
- Reusable & combinable data-driven templates
  - Since there's no JSX support for PHP *yet*, they're admittedly a bit ugly
  - If you don't like the style, plugging Twig or some other solution should be possible
  - Sample templates available at [k1sul1/wordpress-theme-base](https://github.com/k1sul1/wordpress-theme-base)
- URL resolver
  - Maintains an index of each resource in wp_posts (filterable)
  - Resources can be requested from the REST API with an URL. Handy for single page applications following WordPress permalink structure.
- Transient overhaul
  - Built with the assumption of Redis availability
  - List of transients & UI for managing*
  - Full usage depends on Redis!
- Compact API for REST route generation
  - Automagical caching
<!-- Not sure if this is worth implementing, as I recommend talking to the API via a middle man like NodeJS
- Cacheproxy API endpoint*
  - For storing "3rd" party or native API requests
-->

*: WIP, you might want to hide the feature in production.

## Install
Installs like any other plugin, drop the plugin folder into wp-content/plugins, or if you're sophisticated:

```
composer require k1sul1/k1kit
```

## Quickstart
Install the plugin. You can activate it too, but that isn't necessary, if you ensure that this plugin is loaded, which you might want to do.

```php
require_once WP_PLUGIN_DIR . '/k1kit/src/php/init.php';
```

Doing that also ensures that the plugin is always active.

I recommend that you create a new function, which you use to access the App class. You'll be using it a lot.

```php
/**
 * $options is doesn't do anything on subsequent runs
 */
function app($options = []) {
  return \k1\App::init($options);
}
```

The App class is a singleton (it was either that or global variables) so you can call `app()` as many times as you want, the "expensive" part is only done once. That also means that the location of the first call matters, so I recommend that you do so in `functions.php` or early in your plugin.

There are a few options for you to decide on:
```php
$options = [
  'blocks' => [],
  'templates' => [],
  'languageSlugs' => ['en'],
  'generateOptionsPages' => true,
  'manifests' => [
    'client' => __DIR__ . '/dist/client-manifest.json',
    'admin' => __DIR__ . '/dist/admin-manifest.json'
  ],
]
```

`$options['blocks']` should contain an array of paths to files that contain blocks. Leave empty to "disable" block feature.
`$options['templates']` should contain an array of paths to files that contain the templates. Leave empty and you'll load no templates.
`$options['languageSlugs']` should contain an array of language slugs the site will be using.
`$options['generateOptionsPages']` determines whether or not to create ACF options pages for your languages.
`$options['manifests']` should contain a key-value array of paths to manifest files. Set to false to disable manifest feature.

See https://github.com/k1sul1/wordpress-theme-base/blob/master/functions.php for an example on how to properly do it in a theme that builds asset manifests.

The App class itself doesn't contain many public methods:

```php
$app = app();

$app->getOption('key'); // Get value from an ACF options field, using the current language unless other specified
$app->getBlock('Hero'); // Get a custom block instance. Useful if you want to render a block manually.
```

Some of the functionality is broken up to "subclasses": AssetManifest and i18n. They're instantiated for you, and placed somewhere nice. Any public methods in them are game.

```php
$app = app();

$app->i18n->getText('Something that should be translated');
$app->i18n->getLanguage();
$app->i18n->getLanguages();

// A different AssetManifest instance if created for each manifest
$app->manifests['client']->enqueue('client.js');
$app->manifests['client']->getAssetFilename('something.svg', false) // Set second parameter to false to return a filesystem path instead of an URL
```

### That's it?
Kinda. There's also a few files worth of helpers under `src/php/lib` that you may find useful. My personal favourites are k1\params, k1\withTransient, k1\dotty, k1\Media\svg & k1\Media\image. `svg` depends on the svg being present in the `client` manifest. I suggest that you skim the files and see if there's anything you like.

### But wait, there's more!
Behind the scenes, there are classes Block, Resolver, RestRoute, TransientList and Transientify.

`Block` is used as an ACF blocks base class. `class SomeBlock extends \k1\Block`
`Resolver` creates a new database table and builds a permalink index in it for a *fast* url_to_postid equivalent. You can configure what will be indexed with filters.
`RestRoute` is used as the base class for building REST API routes & endpoints. Light abstraction that provides caching functionality and a compact API.
`TransientList` is the hackiest thing you'll see in a while. If a suitable object cache (Redis) exists, refs to transients created by Transientify are kept in a list, stored as a transient. The purpose of it is to allow finetuned cache control, but you still need to know what you're doing. **Ironically, if you run into performance problems, this might be the reason. Unlikely, but still possible.**
`Transientify` is a fancy API for *_transient functions. An earlier version of it is keeping one of the biggest tech magazines in Finland standing. It can be a bit daunting at first, look where it's used if you're lost. `\k1\withTransient` can be your friend.

### There's even more!
Some functionality exposes REST API endpoints: (note that this list may be out of date, best to check `src/php/api`)

- k1/v1/transientlist
- k1/v1/transientlist/delete
- k1/v1/resolver/url
- k1/v1/resolver/index
- k1/v1/resolver/index/build
- k1/v1/resolver/index/continue

`Resolver` is bound into class `Kit`, if you need access to the PHP api of `Resolver`, you can do `\k1\kit()->resolver`. `TransientList` is not bound anywhere, but it also isn't initialized at any point, it simply serves as a static class. If you want to try your hand on managing transients, simply access it directly: `\k1\TransientList::read()` & `\k1\TransientList::delete()`.

This repository by itself is a bit raw, be sure to see [k1sul1/wordpress-theme-base](https://github.com/k1sul1/wordpress-theme-base) on how I'm using it in client projects, and [k1sul1/kisu.li](https://github.com/k1sul1/kisu.li) in my personal portfolio. (WIP, not public _yet_)

## Warnings
### Resolver
The resolver is on by default. You can turn it off with `add_filter('k1kit/use-resolver', '__return_false')`. If you don't use it, you should turn it off.

When you activate the plugin, it will build the resolver index. The index will contain all post types that are marked as public, but you can change that with a filter. As you add, modify or delete posts, the index will be updated automatically.

**If you change the site domain** (`wp search-replace`), the index will be corrupted, as the index table indexes are sha1 hashes of the URLs. You can easily fix this by rebuilding the index at any time from the admin. The old index will remain functional until the new version has been generated.

The database table is very lightweight:
```
MariaDB [wordpress]> DESCRIBE wp_k1_resolver;
+---------------+---------------+------+-----+---------+-------+
| Field         | Type          | Null | Key | Default | Extra |
+---------------+---------------+------+-----+---------+-------+
| object_id     | bigint(20)    | NO   | PRI | NULL    |       |
| permalink     | varchar(2048) | NO   |     | NULL    |       |
| permalink_sha | char(40)      | NO   | UNI | NULL    |       |
+---------------+---------------+------+-----+---------+-------+
3 rows in set (0.002 sec)
```

### Transients
Overhauling the transients kinda requires overhauling the software behind them. You _can_ use WP DB as the transient store, but you shouldn't.

If theres's no object-cache.php present, data compression will be turned off, and there won't be a TransientList.

This is tested with Redis, with the most popular plugins: [WP Redis](https://wordpress.org/plugins/wp-redis/) and [Redis Object Cache](https://wordpress.org/plugins/redis-cache/).

As long as WP is using an actual key-value store for set_transient & get_transient, this plugin should work just fine.

#### Memcached
Memcached will work for small sites, but it has a hardcoded maximum value size of 1MB, which makes it impossible to store the TransientList in memcached if it gets bigger than that. After that it will fail.

## #GOTTAGOFAST
The transients could be a bit faster if the abstraction of the object cache was removed. That would mean ditching WordPress *_transient functions, and using something like Predis, but the cost of loading WordPress makes that pretty much useless, and this plugin wouldn't be object cache agnostic anymore.

The keys generated *can* be predictable, so it's possible to store something like REST API responses, and serve them to users directly from the object cache, and only loading WordPress if a transient doesn't exist, but that's out of the scope of this project.
