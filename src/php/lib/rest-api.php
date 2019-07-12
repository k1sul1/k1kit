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
