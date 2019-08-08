<?php
namespace k1\HTTP;

function buildUrl(array $parts) {
  return (isset($parts['scheme']) ? "{$parts['scheme']}:" : '') .
    ((isset($parts['user']) || isset($parts['host'])) ? '//' : '') .
    (isset($parts['user']) ? "{$parts['user']}" : '') .
    (isset($parts['pass']) ? ":{$parts['pass']}" : '') .
    (isset($parts['user']) ? '@' : '') .
    (isset($parts['host']) ? "{$parts['host']}" : '') .
    (isset($parts['port']) ? ":{$parts['port']}" : '') .
    (isset($parts['path']) ? "{$parts['path']}" : '') .
    (isset($parts['query']) ? "?{$parts['query']}" : '') .
    (isset($parts['fragment']) ? "#{$parts['fragment']}" : '');
}
