# k1 kit

The next Jetpack, but with less cancer. Most useful with the WordPress REST API, but provides features for traditional WordPress development as well. Heavily inspired by the "closed-source" stuff I wrote for a year, but more generic.

I'm a fan of the Unix philosophy but this is not going to follow that. New features are subject to be added, but they'll always be optional.

Very work in progress, except for the name. That's perfect.

## Features
- URL resolver
  - Maintains an index of each resource in wp_posts (filterable)
  - Resources can be requested from the REST API with an URL. Handy for single page applications following WordPress permalink structure.
- Transient overhaul
  - Built with the assumption of Redis availability
  - List of transients & UI for managing*
- Compact API for REST route generation
  - Automagical caching
- Cacheproxy API endpoint*
  - For storing "3rd" party or native API requests

Features marked with * are WIP, or might not exist at all.

## Word of warning
Overhauling the transients kinda requires overhauling the software behind them. You _can_ use WP DB as the transient store, but you shouldn't. 

If theres's no object-cache.php present, data compression will be turned off, and there won't be a TransientList. 

This is tested with Redis, with the most popular plugins: [WP Redis](https://wordpress.org/plugins/wp-redis/) and [Redis Object Cache](https://wordpress.org/plugins/redis-cache/). 

As long as WP is using an actual key-value store for set_transient & get_transient, this plugin should work just fine.

### Memcached
Memcached will work for small sites, but it has a hardcoded maximum value size of 1MB, which makes it impossible to store the TransientList in memcached if it gets bigger than that. After that it will fail.

## #GOTTAGOFAST
The transients could be a bit faster if the abstraction of the object cache was removed. That would mean ditching WordPress *_transient functions, and using something like Predis, but the cost of loading WordPress makes that pretty much useless, and this plugin wouldn't be object cache agnostic anymore.

The keys generated *can* be predictable, so it's possible to store something like REST API responses, and serve them to users directly from the object cache, and only loading WordPress if a transient doesn't exist, but that's out of the scope of this project.