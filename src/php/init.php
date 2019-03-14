<?php

namespace k1;

if (!defined("ABSPATH")) {
  die("You're not supposed to be here.");
}

require 'classes/class.transientify.php';
require 'classes/class.transient-list.php';
require 'classes/class.resolver.php';
require 'classes/class.rest-route.php';

$resolver = new Resolver();

add_action('admin_menu', function() {
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
});

add_action('admin_enqueue_scripts', function() {
  $assets = json_decode(file_get_contents(__DIR__ . '/../gui/build/asset-manifest.json'));

  wp_enqueue_style('k1kit-css', $assets->{'main.css'});
  wp_enqueue_script('k1kit-mainjs', $assets->{'main.js'}, [], false, true);
  wp_localize_script('k1kit-mainjs', 'k1kit', [
    'nonce' => wp_create_nonce('wp_rest'),
  ]);
});

add_action('save_post', [$resolver, 'updateLinkToIndex']);
add_action('delete_post', [$resolver, 'deleteLinkFromIndex']);

add_action('plugins_loaded', function() {

}, 666);

add_action('rest_api_init', function() use (&$resolver) {
  require 'api/resolver.php';
  require 'api/transient-list.php';

  (new Routes\Resolver($resolver))->registerRoutes();
  (new Routes\TransientList())->registerRoutes();
});
