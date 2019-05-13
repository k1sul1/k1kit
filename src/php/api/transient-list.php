<?php
namespace k1\Routes;

class TransientList extends \k1\RestRoute {
  public function __construct() {
    parent::__construct('k1/v1', 'transientlist');

    if (\k1\Transientify::$useList) {
      $this->registerEndpoint(
        '/',
        [
          'methods' => 'GET',
          'callback' => [$this, 'listTransients'],
          'permission_callback' => function() {
            return current_user_can('manage_options');
          },
        ],
        []
      );

      $this->registerEndpoint(
        '/delete',
        [
          'methods' => 'POST',
          'callback' => [$this, 'deleteTransient'],
          'permission_callback' => function() {
            return current_user_can('manage_options');
          },
        ]
      );
    } else {
      $this->registerEndpoint(
        '/',
        [
          'methods' => 'GET',
          'callback' => [$this, 'notEnabled'],
        ],
        []
      );
    }
  }

  public function notEnabled() {
    return new \WP_REST_Response([
      'error' => 'TransientList is not enabled'
    ], 503);
  }

  public function listTransients($request) {
    return new \WP_REST_Response(\k1\TransientList::read());
  }

  public function deleteTransient($request) {
    $params = $request->get_params();
    $transientKey = $params['transientKey'] ?? null;

    if (!$transientKey) {
      return new \WP_REST_Response([
        'error' => "Parameter transientKey missing",
      ], 400);
    }

    $removed = \k1\TransientList::delete($transientKey);

    if ($removed) {
      return new \WP_REST_Response([
        'success' => "Transient $transientKey deleted",
      ]);
    }

    return new \WP_REST_Response([
      'error' => "Transient $transientKey was not deleted",
    ], 500);
  }
}
