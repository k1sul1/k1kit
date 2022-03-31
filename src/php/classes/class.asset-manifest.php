<?php

namespace k1;

/**
 * AssetManifest is for webpack style manifests.
 */
class AssetManifest {
  public $assets = [];

  // handle prefix
  public $hp = "k1";
  public $buildDir = "dist";

  public function __construct(string $path, string $name) {
    $this->assets = (array) json_decode(file_get_contents($path));
    $this->name = $name;
  }

  public function isJS(string $assetName) {
    $js = strpos($assetName, '.js') !== false;
    $ts = strpos($assetName, '.js') !== false;

    if (strpos($assetName, '.js') !== false) {
      return true;
    } else if (strpos($assetName, '.ts') !== false) {
      return true;
    } else {
      return false;
    }
  }

  public function isCSS(string $assetName) {
    return strpos($assetName, '.css') !== false;
  }

  public function enqueueJS(string $filename, $dependencies = [], $inFooter = true) {
    $handle = basename($filename);
    $handle = "{$this->hp}-$handle";

    wp_enqueue_script(
      $handle,
      $filename,
      $dependencies,
      null,
      $inFooter
    );

    return $handle;
  }

  public function enqueueCSS(string $filename, $dependencies = []) {
    $handle = basename($filename);
    $handle = "{$this->hp}-$handle";

    wp_enqueue_style(
      $handle,
      $filename,
      $dependencies,
      null,
    );

    return $handle;
  }

  public function enqueue(string $assetName, $dependencies = []) {
    $isJS = $this->isJS($assetName);
    $isCSS = !$isJS && $this->isCSS($assetName);
    $filename = $this->getAssetFilename($assetName);

    if (!$filename) {
      $message = "Unable to enqueue asset $assetName. It wasn't present in the {$this->name} manifest. ";

      throw new \Exception($message);
    }

    if ($isJS) {
      return $this->enqueueJS($filename, $dependencies, true);
    } else if ($isCSS) {
      return $this->enqueueCSS($filename);
    }

    throw new \Exception("Unable to enqueue asset $assetName ($filename) due to type being unsupported");
  }

  public function getAsset(string $assetName) {
    $asset = $this->assets[$assetName];

    return !empty($asset) ? $asset : false;
  }

  /**
   * The asset manifest doesn't know of WP, it assumes the files are available in webroot.
   */
  public function withBuildDirectory(string $filename, $forBrowser = true) {
    return ($forBrowser ? \get_stylesheet_directory_uri() : \get_stylesheet_directory()) . "/{$this->buildDir}/$filename";
  }

  public function getAssetFilename(string $assetName, $forBrowser = true) {
    $asset = $this->getAsset($assetName);

    if ($asset) {
      $filename = $this->assets[$assetName];

      return $this->withBuildDirectory($filename, $forBrowser);
    }

    return false;
  }

}

class ViteBuild extends AssetManifest {
  public $buildDir = "vitebuild";
  public $serverUrl = "http://localhost:8888";
  public $serverPort = 8888;
  protected $dev = false;

  public function __construct(string $path, string $name) {
    parent::__construct($path, $name);

    if ($this->devServerExists()) {
      $this->dev = true;

      add_action('wp_enqueue_scripts', function() {
        // This doesn't use the internal enqueue so it can set the script handle freely.
        wp_enqueue_script("vite/client", $this->serverUrl . "/@vite/client", [], null, false);
      }, 1);
    }
  }

  public function isDev() {
    return $this->dev;
  }

  public function getAssetFilename(string $assetName, $forBrowser = true) {
    $asset = $this->getAsset($assetName);

    if ($asset) {
        $filename = $asset->file;

        return $this->withBuildDirectory($filename);
      }

      return false;
  }

  public function enqueueJS(string $filename, $dependencies = [], $inFooter = true) {
    $handle = parent::enqueueJS($filename, $dependencies, $inFooter);

    add_filter('script_loader_tag', function($tag, $h, $src) use ($handle) {
      if ($handle !== $h) {
        return $tag;
      }

      $tag = '<script id="' . esc_attr($handle) . '" type="module" src="' . esc_url($src) . '"></script>';
      return $tag;
    } , 10, 3);

    return $handle;
  }

  public function enqueue(string $assetName, $dependencies = [], $options = []) {
    $useCSS = $options['useCSS'] ?? false;
    $isJS = $this->isJS($assetName);

    if (!$this->isDev()) {
      $filename = $this->getAssetFilename($assetName);
    } else {
      $filename = $this->serverUrl . '/' . $assetName;
    }

    if (!$filename) {
      $message = "Unable to enqueue asset $assetName. It wasn't present in the {$this->name} manifest. ";

      throw new \Exception($message);
    }

    if ($useCSS) {
      $asset = $this->getAsset($assetName);
      $files = $asset->css;

      $handles = [];

      foreach ($files as $i => $css) {
        $filename = $this->withBuildDirectory($css);
        $handles = $this->enqueueCSS($filename);
      }

      return $handles;
    } else if ($isJS) {
      return $this->enqueueJS($filename, $dependencies, true);
    }

    throw new \Exception("Unable to enqueue asset $assetName ($filename) due to type being unsupported");
  }

  /**
   * This is the best way I found to check for the existence.
   *
   * fopen throws warnings if it can't find whatever it's looking for. That's why I used the STFU operator,
   * because those warnings are irrelevant. They're expected in prod.
   */
  private function devServerExists(){
    $file = @fopen($this->serverUrl . "/@vite/client", "r");

    if (!$file) {
      // If WP is running inside Docker but Vite is running on the host, retrying with this should work.
      // This should work on Mac & Linux. It does not work from the client!
      $file = @fopen("http://host.docker.internal:{$this->serverPort}/@vite/client", "r");

      if (!$file) {
        return false;
      }
    }

    fclose($file);
    return true;
  }
}
