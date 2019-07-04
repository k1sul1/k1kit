<?php

namespace k1;

if (!defined("ABSPATH")) {
  die("You're not supposed to be here.");
}

class Kit {
  public $resolver;
  public static $instance;

  public static function init(...$params) {
    if (self::$instance) {
      return self::$instance;
    }

    self::$instance = new Kit(...$params);

    return self::$instance;
  }

  private function __construct() {
    foreach (glob(dirname(__FILE__) . "/lib/*.php") as $filename) {
      require_once($filename);
    }

    foreach (glob(dirname(__FILE__) . "/classes/class.*.php") as $filename) {
      require_once($filename);
    }

    if (Transientify::objectCacheExists()) {
      // Change defaults if object cache is present
      Transientify::$useList = true;
      Transientify::$compress = true;
    }

    if (apply_filters('k1kit/use-resolver', true)) {
      $resolver = new Resolver();
    } else {
      $resolver = null;
    }

    if ($resolver) {
      // Update resolver index when posts are deleted, added or modified
      add_action('save_post', [$resolver, 'updateLinkToIndex']);
      add_action('delete_post', [$resolver, 'deleteLinkFromIndex']);
    }

    // Initialize API routes if request is a REST request
    add_action('rest_api_init', function() use (&$resolver) {
      require 'api/resolver.php';
      require 'api/transient-list.php';

      if ($resolver) {
        (new Routes\Resolver($resolver))->registerRoutes();
      }

      (new Routes\TransientList())->registerRoutes();
    });

    // Maybe hook into other plugins at some point in future
    add_action('plugins_loaded', function() {

    }, 666);

    add_action('admin_menu', [$this, 'addMenuPage']);
    add_action('admin_enqueue_script', [$this, 'maybeEnqueueScripts']);

    $this->resolver = $resolver;
  }

  public function addMenuPage() {
    add_menu_page(
      __( 'k1 kit', 'k1kit' ),
      'k1 kit',
      apply_filters('k1_settings_capability', 'manage_options'),
      'k1kit',
      function() {
        echo "<div id='k1kit-gui'>Loading...</div>";
      },
      null,
      999
    );
  }

  public function maybeEnqueueScripts() {
    global $pagenow;
    $whitelist = ['admin.php'];

    if (!in_array($pagenow, $whitelist)) {
      return false;
    }

    $assets = json_decode(file_get_contents(__DIR__ . '/../gui/build/asset-manifest.json'));

    wp_enqueue_style('k1kit-css', $assets->{'main.css'});
    wp_enqueue_script('k1kit-mainjs', $assets->{'main.js'}, [], false, true);
    wp_localize_script('k1kit-mainjs', 'k1kit', [
      'nonce' => wp_create_nonce('wp_rest'),
    ]);
  }
}

function kit(...$params) {
  return Kit::init(...$params);
}

// Can be called anywhere.
kit();
