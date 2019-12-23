<?php
namespace k1\Routes;

class Transients extends \k1\RestRoute {
  public function __construct() {
    parent::__construct('k1/v1', 'transients');

    $this->registerEndpoint(
      '/',
      [
        'methods' => 'GET',
        'callback' => [$this, 'status'],
        'permission_callback' => function() {
          return current_user_can('manage_options');
        },
      ],
      []
    );

    $this->registerEndpoint(
      '/disable',
      [
        'methods' => 'POST',
        'callback' => [$this, 'disable'],
        'permission_callback' => function() {
          return current_user_can('manage_options');
        },
      ],
      []
    );

    $this->registerEndpoint(
      '/enable',
      [
        'methods' => 'POST',
        'callback' => [$this, 'enable'],
        'permission_callback' => function() {
          return current_user_can('manage_options');
        },
      ],
      []
    );
  }

  public function status() {
    return new \WP_REST_Response([
      'enabled' => get_option('k1kit_transients_enabled', false)
    ]);
  }

  public function enable() {
    update_option('k1kit_transients_enabled', true, true);

    return new \WP_REST_Response([
      'success' => 'Transients enabled'
    ]);
  }

  public function disable() {
    update_option('k1kit_transients_enabled', false, true);

    return new \WP_REST_Response([
      'success' => 'Transients disabled'
    ]);
  }
}
