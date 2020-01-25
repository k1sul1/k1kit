<?php
namespace k1\Media;

/**
 * Get SVG from the theme dist folder
 */
function svg(string $filename, array $data = []) {
  $data = \k1\params([
    'className' => ['k1-svg'],
    'manifest' => 'client',
  ], $data);
  $wrapper = function($svgEl) use ($data) {
    $class = \k1\className(...$data['className']);

    return "<div $class>$svgEl</div>";
  };

  $manifests = \k1\app()->manifests;
  $manifest = $manifests[$data['manifest']] ?? false;

  if (!$manifest) {
    throw new \Exception("Tried to use an svg from a non-existent manifest: $data[manifest].");
  }

  return $wrapper(file_get_contents(
    $manifest->getAssetFilename('img/' . $filename, false)
  ));
}

/**
 * Get an image from WordPress. Caption and srcset support.
 */
function image($image = null, array $data = []) {
  $data = \k1\params([
    'size' => 'medium',
    'className' => ['k1-image'],
    'responsive' => true,
    'sizes' => null,
    'allowCaption' => false,
  ], $data);

  $image = getImageData($image, $data['size']);
  $class = \k1\className(...$data['className']);

  if (!$image) {
    return false;
  }

  $tag = "<img src='$image[src]' $class alt='$image[alt]'";

  if ($data['responsive']) {
    $data['sizes'] = empty($data['sizes']) ? getImageSizesAttribute($image['srcset']) : $data['sizes'];

    $tag .= " srcset='$image[srcset]' sizes='$data[sizes]'";
  }

  if ($image['title']) {
    $tag .= " title='$image[title]'";
  }

  $tag .= '>';

  if ($data['allowCaption'] && $image['caption']) {
    return "
      <figure class='k1-figure'>
        $tag

        <figcaption class='k1-figure__caption'>
          $image[caption]
        </figcaption>
      </figure>
    ";
  }

  return $tag;
}

/**
 * Generate sizes attribute value from srcset
 */
function getImageSizesAttribute($rawSrcSet) {
  $sets = explode(', ', $rawSrcSet);
  $sets = array_filter(array_map(function($set) {
    if (empty($set)) {
      return null;
    }

    [$url, $size] = explode(' ', $set);

    return [
      'url' => $url,
      'size' => $size,
    ];
  }, $sets));
  $sizes = '';

  $prevPxSize = null;
  foreach ($sets as $set) {
    $url = $set['url'];
    $size = $set['size'];
    $pxSize = str_replace('w', 'px', $size);

    if (!$prevPxSize) {
      $sizes .= "(min-width: $pxSize) $size, \n";

    } else if ($prevPxSize) {
      $sizes .= "(max-width: $pxSize) and (min-width: $prevPxSize) $size,\n";
    }

    $prevPxSize = $pxSize;
  }

  $sizes .= "100vw";


  return $sizes;
}

/**
 * Get image data from WordPress.
 */
function getImageData($image = null, string $size = 'medium') {
  if (is_array($image)) {
    $id = $image['ID'];
  } else if (is_numeric($image)) {
    $id = absint($image);
  } else {
    return false;
  }

  $x = get_post($id);
  $data = [
    'src' => wp_get_attachment_image_url($id, $size),
    'srcset' => wp_get_attachment_image_srcset($id, $size),
    'description' => \esc_html($x->post_content),
    'title' => \esc_attr($x->post_title),
    'alt' => get_post_meta($id, '_wp_attachment_image_alt', true),
    'caption' => \esc_html($x->post_excerpt),
  ];

  return $data;
}
