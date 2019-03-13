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