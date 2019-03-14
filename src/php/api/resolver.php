<?php
namespace k1\Routes;

class Resolver extends \k1\RestRoute {
  public function __construct(\k1\Resolver &$resolver) {
    parent::__construct('k1/v1', 'resolver');

    $this->resolver = $resolver;

    $this->registerEndpoint(
      '/url',
      [
        'methods' => 'GET',
        'callback' => [$this, 'resolveURL']
      ],
      [
        'expires' => \HOUR_IN_SECONDS
      ]
    );

    $this->registerEndpoint(
      '/index',
      [
        'methods' => 'GET',
        'callback' => [$this, 'getIndexStatus'],
        'permission_callback' => function() {
          return current_user_can('edit_posts');
        },
      ],
      []
    );

    $this->registerEndpoint(
      '/index/build',
      [
        'methods' => 'POST',
        'callback' => [$this, 'buildIndex'],
        'permission_callback' => function() {
          return current_user_can('edit_posts');
        },
      ],
    );

    $this->registerEndpoint(
      '/index/continue',
      [
        'methods' => 'POST',
        'callback' => [$this, 'continueIndexBuild']
      ],
    );
  }

  public function resolveURL($request) {
    $params = $request->get_params();
    $prefix = $this->resolver->prefix;

    $url = sanitize_text_field($params["url"] ?? '');
    $url = str_replace([
      "&preview=true",
    ], "", $url);
    $slashed = strpos($url, "?") === false ? trailingslashit($url) : $url;

    $id = $this->resolver->db->get_var($this->resolver->db->prepare(
      "SELECT object_id FROM `{$prefix}k1_resolver` WHERE permalink_sha = SHA1(%s) OR permalink_sha = SHA1(%s) LIMIT 1",
      $url,
      $slashed
    ));

    if ($id !== null) {
      $post = get_post($id);
      $type = $post->post_type;
      $ptypeObject = get_post_type_object($type);
      $endpoint = !empty($ptypeObject->rest_base) ? $ptypeObject->rest_base : $type;

      global $wp_rest_server;
      $req = new \WP_REST_Request("GET", "/wp/v2/{$endpoint}/{$id}");
      $req = apply_filters('k1_resolver_resolve_request', $req, $request);

      $response = rest_do_request($req);

      $data = $wp_rest_server->response_to_data($response, true);
      $response->set_data($data);
      $response = apply_filters('k1_resolver_resolve_response', $response, $request);

      return $response;
    } else {
      $response =  new \WP_REST_Response([
        "error" => "No post found.",
      ], 404);

      return $response;
    }
  }

  public function getIndexStatus($request) {
    $prefix = $this->resolver->prefix;
    $status = $this->resolver->getIndexingStatus();

    $types = $this->resolver->getIndexablePostTypes();
    foreach ($types as $k => $v) {
      $types[$k] = "'$v'";
    }
    $types = join(', ', $types);

    $total = $this->resolver->db->get_var("
      SELECT COUNT(*) FROM `{$prefix}posts` 
      WHERE post_status NOT IN ('trash', 'auto-draft') 
      AND post_type IN ($types) ORDER BY ID
    ");
    if ($status['indexing']) {
      $indexed = $this->resolver->db->get_var("SELECT COUNT(*) FROM `{$prefix}k1_resolver_temp`");

      return [
        "indexing" => true,
        "indexed" => $indexed,
        "total" => $total,
        "percentage" => $indexed / $total * 100,
      ];
    } else {
      $indexed = $this->resolver->db->get_var("SELECT COUNT(*) FROM `{$prefix}k1_resolver`");

      return [
        "indexing" => false,
        "indexed" => $indexed,
        "total" => $total,
        "percentage" => 100,
      ];
    }
  }

  public function buildIndex() {
    if ($this->resolver->startIndexing()) {
      return new \WP_REST_Response([
        "success" => "Started indexing",
      ]);
    } else {
      return new \WP_REST_Response([
        "error" => "Unable to start indexing"
      ], 500);
    }
  }

  public function continueIndexBuild() {
    ignore_user_abort(true);
    $status = $this->resolver->getIndexingStatus();

    if ($status['indexing'] === false) {
      return new \WP_REST_Response(["error" => "Not indexing"], 400);
    }

    $this->resolver->continueIndexing();
    return ["success" => "Continuing build of index"];
  }
}