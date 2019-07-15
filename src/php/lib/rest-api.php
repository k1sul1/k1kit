<?php

namespace k1\REST;

function getCustomFields($post) {
  $fields = get_fields($post['id']);

  if (empty($fields)) {
    return false;
  }

  /**
   * Allow all fields by default, change the function with a filter if you want to block fields.
   */
  $removeFields = function($field) {
    return true;
  };

  return array_filter($fields, apply_filters('k1kit/REST/allowedCustomFields', $removeFields, $fields, $post));
}

function getBlockData($post) {
  $data = has_blocks($post['content']['raw']) ? parse_blocks($post['content']['raw']) : false;

  if ($data) {
    foreach ($data as $i => $block) {
      if (strpos($block['blockName'], 'acf/') === 0) {
        acf_setup_meta($block['attrs']['data'], $block['attrs']['id'], true);
        $block['attrs']['data'] = \get_fields();
        acf_reset_meta($block['attrs']['id']);

        $data[$i] = $block;
      }
    }
  }

  return $data;
}

function getSeoData($post) {
  if (function_exists("the_seo_framework")) {
    $id = $post['id'];
    $seo = the_seo_framework();

    return [
      'title' => $seo->get_title($id),
      'meta' => [
        'description' => $seo->get_description($id),
        'og:description' => $seo->get_open_graph_description($id),
        'og:title' => $seo->get_open_graph_title($id),
        'og:image' => $seo->get_social_image_url_from_post_thumbnail($id),
      ],
      // 'canonical' => $seo->get_canonical_url(['id' => $id]), // ?: false,
    ];
  }

  return false;
}

function changeAcfValue($value, $postId, $field) {
  $getRestResponseWithId = function ($id) {
    // copied from resolver api

    $post = get_post($id);
    $type = $post->post_type;
    $id = $post->ID;
    $ptypeObject = get_post_type_object($type);
    $endpoint = !empty($ptypeObject->rest_base) ? $ptypeObject->rest_base : $type;

    global $wp_rest_server;
    $req = new \WP_REST_Request("GET", "/wp/v2/{$endpoint}/{$id}");

    $response = rest_do_request($req);

    $data = $wp_rest_server->response_to_data($response, true);
    $response->set_data($data);

    return $response->data;
  };

  switch ($field['type']) {
    case 'post_object':
      if (!empty($value)) {
        $value = $getRestResponseWithId((int) $value);
      }
    break;

    case 'relationship':
      foreach ($value as $k => $id) {
        $value[$k] = $getRestResponseWithId($id);
      }
    break;
  }
  return $value;
}
