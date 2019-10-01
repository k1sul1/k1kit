<?php

namespace k1;

class Transientify {
  const DEFAULT_EXPIRY = 360; // 5 minutes

  public $expires = 0;
  public $bypass = false;

  // Static defaults. Assumes no object-cache,
  // if object-cache is present, these values will be changed
  // in the plugin init process.
  public static $compress = false;
  public static $useList = false;

  /**
   * @param string $key Key to save the transient with.
   * @param array $options Options to control the transient.
   * @throws \Exception
   */
  public function __construct(string $key = null, $transientOptions = []) {
    if (is_null($key)) {
      throw new \Exception('No key provided');
    } else if (strpos($key, '|') > -1) {
      throw new \Exception("The key can't contain |");
    }

    $transientOptions = apply_filters(
      'k1kit/transientify/transient/options',
      array_merge([
        "expires" => self::DEFAULT_EXPIRY,
        // "bypassPermissions" => ['edit_posts'],
        "bypassPermissions" => [],
        "bypassKey" => "FORCE_FRESH",
        "type" => "general",
      ], $transientOptions),
      $key
    );
    $customKeyPrefix = apply_filters('k1kit/transientify/customKeyPrefix', false, $key, $transientOptions);
    $separateByRole = apply_filters('k1kiy/transientify/separateByRole', true, $key, $transientOptions);

    if ($customKeyPrefix) {
      $this->key = $customKeyPrefix;
    } else {
      $type = $transientOptions['type'];
      $this->key = "k1t|$type|";
    }

    if ($separateByRole) {
      $user = wp_get_current_user();

      if ($user && $user->ID) {
        $data = get_userdata($user->ID);

        // I have no fucking clue how it's possible that an user without ANY roles exists, but
        // according to logs it is. Sometimes I hate this POS they call WordPress.
        $primaryRole = $data->roles[0] ?? 'visitor';

        $this->key = $this->key . "$primaryRole|";
      } else {
        // No user, use visitor as role
        $this->key = $this->key . "visitor|";
      }
    }

    $this->key = $this->key . $key;

    $bypassParamPresentAndCorrect = ($_GET['bypass'] ?? false) === $transientOptions['bypassKey'];
    $bypassPermissionPresent = (bool) count(array_filter($transientOptions['bypassPermissions'], 'current_user_can'));

    $this->bypass = $bypassParamPresentAndCorrect || $bypassPermissionPresent;
    $this->expires = $transientOptions['expires'];
  }

  public static function objectCacheExists() {
    return wp_using_ext_object_cache();
  }

  /**
   * Delete a transient
   */
  public function delete() {
    if (self::$useList) {
      // Let the list handle deletion
      return TransientList::delete($this->key);
    }

    return delete_transient($this->key);
  }

  /**
   * @param callable $dataCb Function that populates transient if no value is found
   * @param string &$missReason Mutate parameter to contain transient miss reason. If empty, a transient was found.
   */
  public function get(callable $dataCb, &$missReason = '') {
    $missReason = null;

    if (!$this->bypass) {
      $transient = get_transient($this->key);

      if ($transient) {
        if (self::$compress) {
          $transient = gzuncompress($transient);
        }

        $transient = unserialize($transient);

        if (!$transient) {
          error_log("Transient {$this->key} is corrupted");
        }
      } else {
        $transient = false;
      }

      if ($transient !== false) {
        return $transient;
      }

      $missReason = 'Miss';
    }
    else {
      $missReason = 'Bypass';
    }

    return $dataCb($this);
  }

  /**
   * Set the transient value
   *
   * @param $data
   * @return mixed
   */
  public function set($data) {

    if (!$this->bypass) {
      if (self::$useList) {
        TransientList::add($this->key, [
          'expirySeconds' => $this->expires,
          'storedData' => $data, // Used for meta generation
        ]);
      }

      $copy = serialize($data);

      // Checking return value of set_transient is pointless; it returns false if the value being saved is identical with the previous one
      set_transient(
        $this->key,
        self::$compress ? gzcompress($copy) : $copy,
        $this->expires
      );
    }

    return $data;
  }
}
