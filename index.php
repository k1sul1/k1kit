<?php
/**
 * Plugin name: k1 kit
 * Plugin URI: https://github.com/k1sul1/k1kit
 * Description: WordPress development toolkit
 * Version: 1.0.0
 * Author: @k1sul1
 * Author URI: https://github.com/k1sul1/
 * License: MIT
 * Text Domain: k1kit
 *
 */

if (!defined("ABSPATH")) {
  die("You're not supposed to be here.");
}

define('K1_KIT_DIR', plugin_dir_url(__FILE__));

function k1kit_has_version_troubles($isNetwork = null) {
  $php_version = phpversion();
  $wp_version = $GLOBALS['wp_version'];
  $php_over_7 = version_compare($php_version, 7.0, '>=');
  $wp_ok = version_compare($wp_version, 5.8, '>=');
  $message = "";

  if (!$php_over_7) {
    $message .= "Minimum PHP version required is 7.0. Yours is {$php_version}. ";
  } elseif (!$wp_ok) {
    $message .= "Minimum WP version required is 5.8. Yours is {$wp_version}. ";
  }

  if ($isNetwork) {
    $message .= "k1kit must be activated on each site separately.";
  }

  if (empty($message)) {
    return false;
  }

  return $message;
}

function k1kit_on_activate() {
  $version_troubles = k1kit_has_version_troubles();

  if ($version_troubles) {
    deactivate_plugins(basename(__FILE__));
    wp_die($version_troubles);
  }

  add_action("shutdown", 'flush_rewrite_rules');
}

function k1_k1kit_on_deactivate() {
  flush_rewrite_rules();
}

register_activation_hook(__FILE__, 'k1kit_on_activate');
register_deactivation_hook(__FILE__, 'k1_k1kit_on_deactivate');

$version_troubles = k1kit_has_version_troubles();
if ($version_troubles) {
  deactivate_plugins(basename(__FILE__));
  wp_die($version_troubles);
} else {
  require_once 'src/php/init.php';
}
