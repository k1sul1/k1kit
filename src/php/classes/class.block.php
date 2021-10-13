<?php
namespace k1;

/**
 * Boilerplate behind the magic
 */
abstract class Block {
  protected $name = null;

  /**
   * If you define your own constructor, be sure to call
   * parent::__construct in it.
   */
  public function __construct() {
    $this->register($this->getSettings());
  }

  public function register($data) {
    if (!function_exists('acf_register_block')) {
      throw new \Exception('ACF Pro is required to register blocks. If you don\'t have ACF Pro, remove all blocks from the /blocks folder.');
    }

    acf_register_block($data);
  }

  /**
   * In order to have less boilerplate, we extract the class name from the resulting class.
   */
  public function getName() {
    if (!$this->name) {
      $this->name = (new \ReflectionClass($this))->getShortName();
    }

    return $this->name;
  }

  /**
   * Chances are your block doesn't require changing anything, and you're good with the defaults.
   * If not, just redefine this function in your block.
   */
  public function getSettings() {
    return [
      'title' => $this->getName(),
      'name' => strtolower($this->getName()),
      'render_callback' => [$this, 'print'],
      'mode' => 'preview',
      'category' => 'layout',
      'supports' => [
        'align' => true,
        'mode' => true,
        'multiple' => true,
        'jsx' => true,
      ],
    ];
  }

  /**
   * Change how block will be cached by redefining this method.
   *
   * Return false to disable cache.
   */
  public function getTransientSettings($block, $postId) {
    $blockSettings = $this->getSettings();
    $blockId = $block['id'];
    $paged = get_query_var('paged', 1);
    $key = "$blockSettings[name]_{$postId}_{$blockId}_$paged";

    return [
      'key' => $key,
      'options' => [
        'expires' => \MINUTE_IN_SECONDS * 5,
        'type' => 'acf-block',
        'bypassPermissions' => ['edit_posts'],
      ]
    ];
  }

  public function renderPreviewNotice($block, $postId) {
    // No longer necessary in recent versions of Gutenberg
    // $app = \k1\app();
    // echo "<div class='k1-block__preview-notice'><p>";
    // echo $app->i18n->getText('Block: Preview helper');
    // echo "</p></div>";
  }

  public function render($fields, $isPreview, $postId) {
    echo "\n\n\n<!-- Block" . $this->getName() . "is missing the render method -->\n\n\n";

    return null;
  }

  /**
   * Output the block. This is the render_callback of \register_block_type.
   *
   * @param $block Raw block data, not very useful in block render.
   * @param $content Has no use (in custom blocks), as far as I know.
   */
  public function print($block, $content = '', $isPreview = false, $postId) {
    $fields = \get_fields() ?: []; // Get ALL fields for block
    $transientSettings = !$isPreview ? $this->getTransientSettings($block, $postId) : false;

    if ($isPreview) {
      $this->renderPreviewNotice($block, $postId);
    }

    if (!empty($transientSettings) && \class_exists('\k1\Transientify')) {
      $transient = new Transientify($transientSettings['key'], $transientSettings['options']);
      $missReason = null;

      echo $transient->get(function($transientify) use (&$fields, &$isPreview, &$postId) {
        $html = capture([$this, 'render'], $fields, $isPreview, $postId);

        return $transientify->set($html);
      }, $missReason);

      echo "\n\n\n<!-- Block " . $this->getName() . " cache: " . transientResult($missReason) . " -->";
    } else {
      $this->render($fields, $isPreview, $postId);
    }
  }
}
