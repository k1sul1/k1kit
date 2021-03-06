<?php
namespace k1;

function env() {
  if (defined('WP_ENV')) {
    return WP_ENV;
  } else {
    define('WP_ENV', getenv('WP_ENV') ?? 'production');
  }
  return WP_ENV;
}

function isProd() {
  return env() === 'production';
}

function isStaging() {
  return env() === 'staging';
}

function isDev() {
  return env() === 'development';
}

/**
 * Return the current, full URL, excluding URL parameters.
 *
 * @return string
 */
function currentUrl() {
  $protocol = (isset($_SERVER['HTTPS']) ? "https" : "http");

  return "$protocol://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
}

/**
 * Return string in slugish format.
 *
 * @param string $string
 * @return string
 */
function slugify(string $string = '') {
  $string = str_replace(' ', '-', $string);
  $string = strtolower($string);
  return preg_replace('/[^A-Za-z0-9\-]/', '', $string);
}

/**
 * Dives into deep arrays using dot notation and returns a single value, or the default
 * if no value was found. Great for providing fallback or default values.
 *
 * dotty($authorData, "name", "John Doe")
 *
 * @param array $data
 * @param string $key
 * @param mixed $default
 */
function dotty($data = [], $key = "", $default = false) {
  if (!empty($data)) {
    if (strpos($key, ".") > -1) {
      $levels = explode(".", $key);
      $value = $data;

      for ($level = 0; $level < count($levels); $level++) {
        $value = $value[$levels[$level]] ?? $default;
      }

      if (is_array($value) && empty($value)) {
        return $default;
      }

      return $value;
    }

    return $data[$key] ?? $default;
  }

  return $default;
}

/**
 * Combines default parameters with provided parameters
 *
 * @param array $defaults
 * @param array $provided
 * @return array
 */
function params($defaults = [], $provided = []) {
  if (!is_array($defaults) || !is_array($provided)) {
    throw new \Exception('Invalid data provided to params, both parameters must be arrays!');
  }

  return array_replace_recursive($defaults, array_filter($provided, function ($value) {
    if (is_bool($value)) {
      return true; // empty() fails on booleans
    }

    return !empty($value);
  }));
}

/**
 * For component class names. Produces more readable code and end result.
 *
 */
function className() {
  $args = func_get_args();
  $classes = \esc_attr(PHP_EOL . join(PHP_EOL, $args));

  return "class=\"$classes\"";
}

function title($title = null) {
  if (!$title) {
    $title = get_the_title();
  }

  return \esc_html(apply_filters("the_title", $title));
}

function content($content = null) {
  if (is_null($content)) {
    $content = get_the_content();
  }

  // Nope. https://github.com/k1sul1/k1kit/issues/2
  // return apply_filters("the_content", $content);

  // $content = \do_blocks($content); // Causes infinite loops that are FUCKING NIGHTMARE to debug.
  $content = \wptexturize($content);
  $content = \convert_smilies($content);
  $content = \wpautop($content);
  $content = \shortcode_unautop($content);
  $content = \prepend_attachment($content);
  $content = \wp_filter_content_tags($content);

  return $content;
}

/**
 * the_content alternative, wraps content in a div for uniform styles, and a little extra.
 * If page breaks (<!-- nextpage -->) are used, you can either load the next page with JS,
 * or use wp_link_pages to generate a proper pagination.
 */
function gutenbergContent($paginationOpts = null) {
  global $numpages, $page, $multipage, $post;

  $id = \esc_attr($post->ID);
  $attrs = "data-id='$id'";
  if ($multipage) {
    $p = \esc_attr($page);
    $pages = \esc_attr($numpages);

    $attrs = "$attrs data-page='$p' data-pages='$pages'";
  }

  echo "<div class='k1-gutenberg' $attrs>";
  \the_content();

  if ($paginationOpts !== null) {
    \wp_link_pages($paginationOpts);
  }

  echo "</div>";
}

function wrapper($wrappable, $options = []) {
  $options = params([
    "element" => "div",
    "className" => "wrapper",
  ], $options);

  $tag = $options["element"];
  $class = $options["className"];

  return "<$tag class='$class'>$wrappable</$tag>";
}

/**
 * Capture function *output*. Useful when you need to have a template inside a variable.
 */
function capture($fn, ...$params) {
  \ob_start();
  $fn(...$params);
  return \ob_get_clean();
}

function withTransient($data, $opts = [], &$missReason = null) {
  if (!class_exists('\k1\Transientify')) {
    return $data;
  }

  $options = params([
    'key' => null,
    'options' => [
      'expires' => \HOUR_IN_SECONDS,
      'type' => 'general',
      'bypassPermissions' => ['edit_posts'],
    ]
  ], $opts);

  if (!$options['key']) {
    throw new \Exception('Unable to create transient without key');
  }

  $transient = new Transientify($options['key'], $options['options']);
  $missReason = null;

  return $transient->get(function($transientify) use (&$data) {
    return $transientify->set($data);
  }, $missReason);
}

function transientResult($missReason = null) {
  if (\is_null($missReason)) {
    return 'Hit';
  }

  return $missReason;
}
