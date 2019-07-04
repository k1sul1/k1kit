<?php

namespace k1;

/**
 * List of all transients created by Transientify, implemented with transients.
 * DO NOT USE if you're not using Redis or equivalent in-memory object cache.
 */
class TransientList {
  public static $items = [];
  public static $listName = 'k1_transientlist';

  /**
   * Write the list to store.
   *
   * @return bool True if list was changed, false otherwise.
   */
  protected static function write() {
    return set_transient(self::$listName, self::$items, \YEAR_IN_SECONDS);
  }

  public static function read() {
    $list = get_transient(self::$listName);

    if (!empty($list)) {
      self::$items = $list;
    } else {
      self::$items = [];
    }

    return self::$items;
  }

  public static function delete(string $transientKey) {
    $transientKey = sanitize_text_field($transientKey);
    $removed = false;
    self::read();

    if (isset(self::$items[$transientKey])) {
      unset(self::$items[$transientKey]);
      $removed = delete_transient($transientKey);
    }

    if ($removed) {
      self::write();
    } else {
      error_log("Unable to remove $transientKey as it doesn't exist in the list");
    }

    return $removed;
  }

  public static function add(string $transientKey, $data = []) {
    if (!$transientKey) {
      return false;
    }

    $transientKey = sanitize_text_field($transientKey);

    $meta = array_merge([
      'type' => 'Unknown',
      'expirySeconds' => null,
      'timeSet' => date('U'),
      'storedData' => null,
    ], $data);

    if (!empty($meta['storedData'])) {
      $stored = $meta['storedData'];

      if (is_object($stored)) {
        $className = get_class($stored);
        switch ($className) {
          case "WP_REST_Response":
            $response = $stored->data;
            $meta = array_merge($meta, [
              'type' => 'WP_REST_Response',
              'route' => $stored->get_matched_route(),
            ]);
            break;
          default:
            $meta = apply_filters('k1kit/transientlist/addObjectMeta', array_merge($meta, [
              'type' => $className,
            ]), $data, $transientKey);
            break;
        }
      }
    }

    unset($meta['storedData']);
    $meta = apply_filters('k1kit/transientlist/addGeneralMeta', $meta, $data, $transientKey);

    self::read();
    self::$items[$transientKey] = $meta;
    return self::write();
  }
}
