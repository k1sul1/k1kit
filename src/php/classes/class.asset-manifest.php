<?php

namespace k1;

class AssetManifest {
  private $assets = [];

  public function __construct($path, $name) {
    $this->assets = (array) json_decode(file_get_contents($path));
    $this->name = $name;
  }

  public function enqueue(string $assetName, $dependencies = []) {
    $isJS = strpos($assetName, '.js') !== false;
    $isCSS = !$isJS && strpos($assetName, '.css') !== false;
    $filename = $this->getAssetFilename($assetName);

    if (!$filename) {
      $message = "Unable to enqueue asset $assetName. It wasn't present in the {$this->name} manifest. ";

      throw new \Exception($message);
    }

    $basename = basename($filename);

    if ($isJS) {
      wp_enqueue_script(
        "k1-$basename",
        $filename,
        $dependencies,
        \k1\isDev() ? date('U') : null,
        true
      );
    } else if ($isCSS) {
      wp_enqueue_style(
        "k1-$basename",
        $filename,
        $dependencies,
        \k1\isDev() ? date('U') : null,
      );
    } else {
      throw new \Exception("Unable to enqueue asset $assetName ($filename) due to type being unsupported");
    }

    // Return the handle for use in wp_localize_script
    return "k1-$basename";
  }

  public function getAssetFilename(string $assetName, $forBrowser = true) {
    if (isset($this->assets[$assetName])) {
      $filename = $this->assets[$assetName];

      return ($forBrowser ? \get_stylesheet_directory_uri() : \get_stylesheet_directory()) . "/dist/$filename";
    }

    return false;
  }

}
