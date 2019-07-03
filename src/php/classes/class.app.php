<?php
namespace k1;

/**
 * Singleton class that configures the WordPress site
 */
class App {
  public $i18n;
  public $manifests;

  protected $blocks = [];
  protected static $instance;

  public static function init($options = []) {
    if (self::$instance) {
      return self::$instance;
    }

    self::$instance = new App($options);

    return self::$instance;
  }

  /**
   * Get option from ACF options page
   */
  public function getOption($x, $languageSlug = null) {
    $optionName = $this->i18n->getOptionName($x, $languageSlug);

    return \get_field($optionName, 'options');
  }

  public function getBlock($name) {
    return $this->blocks[$name];
  }

  /**
   * Forbid initialization by new by making the constructor private.
   * Use the \k1\app() function.
   */
  private function __construct($options = []) {
    $options = array_merge([
      'blocks' => [/* fill with file paths */],
      'templates' => [/* fill with file paths */],
      'languageSlugs' => ['en'],
      'generateOptionsPages' => true,
      // 'manifests' => [
      //   'client' => __DIR__ . '/dist/client-manifest.json',
      //   'admin' => __DIR__ . '/dist/admin-manifest.json'
      // ],
      'manifests' => false,
    ], $options);

    if ($options['manifests']) {
      foreach ($options['manifests'] as $name => $manifestPath) {
        $this->manifests[$name] = new AssetManifest($manifestPath, $name);
      }
    }

    $this->i18n = new i18n($options['languageSlugs']);

    /**
     * Load & initialize blocks and templates
     */

    foreach($options['templates'] as $template) {
      require_once $template;
    }

    add_action('acf/init', function() use ($options) {
      foreach ($options['blocks'] as $block) {
        require_once $block;

        $className = basename($block, '.php');
        $Class = "\\k1\Blocks\\$className";

        if (!class_exists($Class)) {
          throw new \Exception("Block $block is invalid");
        }

        $instance = new $Class($this);
        $this->blocks[$instance->getName()] = $instance;
      }
    });

    if ($options['generateOptionsPages']) {
      /**
       * Create options pages for all languages.
       * Please note that you have to create the field groups yourself,
       * and use the clone field with the prefix setting in it.
       */
      if (function_exists('acf_add_options_page')) {
        $languages = $this->i18n->getLanguages();
        $parent = acf_add_options_page([
          "page_title" => "Options Page",
          "menu_slug" => "acf-opts",
        ]);

        foreach ($languages as $lang) {
          $fields = [
            "page_title" => "Options $lang",
            "menu_title" => "Options $lang",
            "parent_slug" => $parent["menu_slug"],
          ];

          // Set first language as first
          if ($lang === $languages[0]) {
            $fields["menu_slug"] = "acf-options";
          }

          acf_add_options_sub_page($fields);
        }
      }
    }
  }
}
