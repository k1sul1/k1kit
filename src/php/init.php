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
      require 'api/transients.php';
      require 'api/transient-list.php';

      /**
       * Get all REST enabled post types
       */
      $postTypes = array_filter(
        get_post_types([], 'objects'),
        function($type) {
          return $type->show_in_rest === true;
        }
      );

      $wp_version = $GLOBALS['wp_version'];
      $over59 = version_compare($wp_version, 5.9, '>=');

      if (defined('REST_REQUEST') && !$over59) {
        // This seems to have broken entirely in 5.9. Fuck it, I don't care for now.
        // It fails in the internal api request part.
        add_filter('acf/format_value', '\k1\REST\changeAcfValue', 10, 3);
      }

      foreach ($postTypes as $type) {
        if (apply_filters('k1kit/addAcfToAPI', true)) {
          register_rest_field($type->name, 'acf', [
            'get_callback' => '\k1\REST\getCustomFields',
          ]);
        }

        if (apply_filters('k1kit/addBlocksToAPI', true)) {
          $name = $over59 ? 'blockData' : 'blocks';

          register_rest_field($type->name, $name, [
            'get_callback' => '\k1\REST\getBlockData',
          ]);
        }


        if (apply_filters('k1kit/addSeoToAPI', true)) {
          register_rest_field($type->name, 'seo', [
            'get_callback' => '\k1\REST\getSeoData',
          ]);
        }
      }


      if ($resolver) {
        (new Routes\Resolver($resolver))->registerRoutes();
      }

      (new Routes\Transients())->registerRoutes();
      (new Routes\TransientList())->registerRoutes();
    });

    // Maybe hook into other plugins at some point in future
    add_action('plugins_loaded', function() {

    }, 666);

    add_action('admin_menu', [$this, 'addMenuPage']);
    add_action('admin_enqueue_scripts', [$this, 'maybeEnqueueScripts']);

    $this->resolver = $resolver;
  }

  public function addMenuPage() {
    add_menu_page(
      __( 'k1 kit', 'k1kit' ),
      'k1 kit',
      apply_filters('k1kit/capabilityRequiredForOptions', 'manage_options'),
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

    // When WordPress is installed into a subdir (Bedrock), home will differ from siteurl.
    // The difference is the folder WP is installed in.
    // When working with relative paths (like the asset manifest), this difference will break the enqueues.
    // Turning relative paths into absolutes should work in all cases.
    $home = get_option('home');
    // $siteurl = get_option('siteurl');



    $assets = json_decode(file_get_contents(__DIR__ . '/../gui/build/asset-manifest.json'));

    // var_dump($assets);

    wp_enqueue_style('k1kit-css', $home . $assets->{'main.css'});

    // CSS enqueue is fine, it's just JS that breaks Gutenberg.
    if (!in_array($pagenow, $whitelist)) {
      return false;
    }

    wp_enqueue_script('k1kit-mainjs', $home . $assets->{'main.js'}, [], false, true);
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
