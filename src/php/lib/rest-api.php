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
  $data = false;

  if (isset($post['content']) && isset($post['content']['raw'])) {
    $data = has_blocks($post['content']['raw']) ? parse_blocks($post['content']['raw']) : false;
  }

  if ($data) {
    foreach ($data as $i => $block) {
      if (strpos($block['blockName'], 'acf/') === 0) {
        if (!empty($block['attrs']) && !empty($block['attrs']['data'])) {
          $bData = $block['attrs']['data'];
        } else {
          // I am so fucking fed up with shit BREAKING ALL THE FUCKING TIME!!!
          // THIS IS WHY I DO NOT WORK WITH WP ANYMORE, EVERY FUCKING VERSION UPGRADE BREAKS SOME CODE
          $bData = null;
        }


        if ($bData) {
          $id = $block['attrs']['id'];

          acf_setup_meta($bData, $id, false); // This makes get_fields work
          $fields = \get_fields($block['attrs']['id']);
          $block['attrs']['data'] = $fields; // Replacing the mess with nice data
          //  acf_reset_meta($block['attrs']['id']); // I don't this does anything meaningful
        }

        $data[$i] = $block;
      } else if ($block['blockName'] === 'core/shortcode') {
        $block['innerHTML'] = \do_shortcode($block['innerHTML']);

        $data[$i] = $block;
      } else {
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
        'og:image' => get_the_post_thumbnail_url($id, 'large'),
      ],
      'canonical' => $seo->create_canonical_url(['id' => $id, 'get_custom_field' => true]),
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
        if (is_numeric($value)) {
          $id = (int) $value;
        } else {
          $id = $value->ID;
        }

        $value = $getRestResponseWithId($id);
      }
    break;

    case 'relationship':
      if (is_array($value)) {
        foreach ($value as $k => $id) {
          $value[$k] = $getRestResponseWithId($id);
        }
      }
    break;
  }

  return $value;
}
