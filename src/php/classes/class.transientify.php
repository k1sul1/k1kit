<?php

namespace k1;

class Transientify {
  const DEFAULT_EXPIRY = 360; // 5 minutes

  public $expires = 0;
  public $bypass = false;
  public $compress = false;

  /**
   * @param string $key Key to save the transient with. 
   * @param array $options Options to control the transient.
   * @throws \Exception
   */
  public function __construct(string $key = null, $transientOptions = []) {
    if (is_null($key)) {
      throw new \Exception('No key provided');
    }
    $objectCacheExists = class_exists('\WP_Object_Cache'); // This doesn't guarantee it's being used but...
    $transientOptions = apply_filters(
      'k1_transientify_transient_options', 
      array_merge([
        "expires" => self::DEFAULT_EXPIRY,
        "bypassPermissions" => ['edit_posts'],
        "bypassKey" => "FORCE_FRESH",
        "useCompression" => $objectCacheExists, // DO NOT use compression if using WP DB as transient store
      ], $transientOptions),
      $key
    );
    $customKeyPrefix = apply_filters('k1_transientify_custom_key_prefix', false, $key, $transientOptions);
    $separateByRole = apply_filters('k1_transientify_separate_by_role', true, $key, $transientOptions);

    if ($customKeyPrefix) {
      $this->key = $customKeyPrefix;
    } else {
      $this->key = "k1_t_";
    }

    if ($separateByRole) {
      $user = wp_get_current_user();

      if ($user && $user->ID) {
        $data = get_userdata($user->ID);
        $primaryRole = $data->roles[0];

        $this->key = $this->key . "$primaryRole_";
      } else {
        // No user, use 0 as role
        $this->key = $this->key . "0_";
      }
    }

    $this->key = $this->key . $key;

    $bypassParamPresentAndCorrect = ($_GET['bypass'] ?? false) === $transientOptions['bypassKey'];
    $bypassPermissionPresent = (bool) count(array_filter($transientOptions['bypassPermissions'], 'current_user_can'));

    $this->bypass = $bypassParamPresentAndCorrect || $bypassPermissionPresent;
    $this->expires = $transientOptions['expires'];
    $this->compress = $transientOptions['useCompression'];
  }

  /**
   * Clear a transient
   */
  public static function clear(string $key = null) {
    if (is_null($key)) {
      throw new \Exception('No key provided');
    }

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
      $copy = serialize($data);

      // Checking return value of set_transient is pointless; it returns false if the value being saved is identical with the previous one
      set_transient($this->key, $this->compress ? gzcompress($copy) : $copy, $this->expires);
    }

    return $data;
  }
}