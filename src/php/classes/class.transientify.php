<?php

namespace k1;

class Transientify {
  const DEFAULT_EXPIRY = 360; // 5 minutes

  public $expires = 0;
  public $bypass = false;
  public $compress = false;
  public $useList = false;

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

    $ocExists = self::objectCacheExists();
    $transientOptions = apply_filters(
      'k1_transientify_transient_options', 
      array_merge([
        "expires" => self::DEFAULT_EXPIRY,
        // "bypassPermissions" => ['edit_posts'],
        "bypassPermissions" => [],
        "bypassKey" => "FORCE_FRESH",
        "useCompression" => $ocExists, // DO NOT use compression if using WP DB as transient store
        "useList" => $ocExists,
        "type" => "general",
      ], $transientOptions),
      $key
    );
    $customKeyPrefix = apply_filters('k1_transientify_custom_key_prefix', false, $key, $transientOptions);
    $separateByRole = apply_filters('k1_transientify_separate_by_role', true, $key, $transientOptions);

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
        $primaryRole = $data->roles[0];

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
    $this->compress = $transientOptions['useCompression'];
    $this->useList = $transientOptions['useList'];
  }

  public static function objectCacheExists() {
    // This doesn't guarantee it's being used but...
    return class_exists('\WP_Object_Cache');
  }

  /**
   * Clear a transient
   */
  public static function clear(string $key = null) {
    if (is_null($key)) {
      throw new \Exception('No key provided');
    }

    // if ($this->useList) {
    //   TransientList::remove($key);
    // }

    return delete_transient($key);
  }

  /*i*
   * @param callable $dataCb Function that populates transient if no value is found
   * @param string &$missReason Mutate parameter to contain transient miss reason. If empty, a transient was found.
   */
  public function get(callable $dataCb, &$missReason = '') {
    $missReason = '';

    if (!$this->bypass) {
      $transient = get_transient($this->key);

      if ($transient) {
        if ($this->compress) {
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
      if ($this->useList) {
        TransientList::add($this->key, [
          'expirySeconds' => $this->expires,
          'storedData' => $data, // Used for meta generation
        ]);
      }

      $copy = serialize($data);

      // Checking return value of set_transient is pointless; it returns false if the value being saved is identical with the previous one
      set_transient($this->key, $this->compress ? gzcompress($copy) : $copy, $this->expires);
    }

    return $data;
  }
}