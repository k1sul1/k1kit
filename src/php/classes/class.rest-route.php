<?php
namespace k1;

abstract class RestRoute extends \WP_REST_Controller {
  protected $ns;
  protected $route;
  public $routes = [];

  /**
   * @param string $ns API namespace. Example: wp/v1
   * @param string $route API route name. Example: 'posts'
   */
  public function __construct(string $ns, string $route) {
    $this->setNamespace($ns);
    $this->setRoute($route);
  }

  /**
   * Setter for namespace
   * @access private
   * @param string $ns
   */
  private function setNamespace(string $ns) {
    $this->namespace = $ns;
  }

  /**
   * Setter for route
   * @access private
   * @param string $route
   */
  private function setRoute(string $route) {
    $this->route = $route;
  }

  /**
   * Create API endpoint under the current namespace and route
   *
   * @param string $path Endpoint path. Example: '/issue/(?P<identifier>[a-zA-Z0-9-_]+)'
   * @param array $params Parameters to register_rest_route
   * @param array $transientify Parameters to \k1\Transientify. Leave empty to disable.
   * @throws \Exception
   */
  public function registerEndpoint(string $path, array $params = [], array $transientify = []) {
    if (empty($params)) {
      throw new \Exception('Parameter error: no parameters provided');
    } else if (isset($params['get_callback'])) {
      throw new \Exception('Parameter error: get_callback is unsupported, use callback instead');
    }

    /**
     * If $transientify isn't empty, proceed with transient magic
     */
    if (!empty($transientify)) {
      $cb = $params['callback']; // Save for later use
      $route = $this->route;
      $ns = $this->namespace;

      /**
       * Overwrite the provided callback, injecting a "middleware".
       * As this is PHP, I can't bind the callback $request parameter to the Transientify instance,
       * so I'm doing it this way.
       * 
       * $cb & $transientify are passed by reference to use less memory.
       */
      $params['callback'] = function($request) use (&$cb, $route, $ns, $path, $transientify) {
        $reqParams = $request->get_params();


        // Note: $path is empty in "top-level" endpoints
        $key = "{$ns}_{$route}_{$path}_" . md5(json_encode($reqParams));

        if (strlen($key) > 172) { // set_transient maximum key length
          \trigger_error("Transient key $key exceeds the maximum length of set_transient, and will be truncated", E_USER_WARNING);
        }

        $transientifier = new Transientify($key, $transientify);

        $missReason = null;
        $data = $transientifier
          ->get(function($transientify) use (&$cb, &$request) {
            // If the transient query comes up empty, run the original API callback
            $response = $cb($request);

            if (!is_wp_error($response)) {
              // And if it didn't result in an error, save the result
              return $transientify->set($response);
            }
            else {
              return $response;
            }
          }, $missReason);

        // Turn $data into a WP_REST_Response, but only if it isn't a WP_REST_Response already
        $response = rest_ensure_response($data);

        if (!is_wp_error($response)) {
          $response->header('X-Transientify', $missReason ?? 'Hit');
        }

        return $response;
      };
    }

    $this->routes[$path] = $params;
  }

  /**
   * Register all all created endpoints
   */
  public function registerRoutes() {
    foreach ($this->routes as $path => $params) {
      register_rest_route($this->namespace, $this->route . $path, $params);
    }
  }
}