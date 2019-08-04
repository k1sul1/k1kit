<?php

namespace k1;

class Resolver {
  public function __construct() {
    global $wpdb;
    $this->db = $wpdb;

    // Append the site id to the prefix if necessary
    if (defined('MULTISITE') && MULTISITE) {
      $this->prefix = $this->db->prefix . get_current_blog_id() . "_";
    } else {
      $this->prefix = $this->db->prefix;
    }

    $dbCreated = get_option('k1kit_resolver_db_created', false);

    if (!$dbCreated) {
      $this->createDatabase();
    }
  }

  private function createDatabase() {
    $collate = apply_filters('k1kit/resolver/dbCollate', !empty(\DB_COLLATE) ? \DB_COLLATE : 'utf8mb4_swedish_ci');
    $prefix = $this->prefix;

    $this->db->query("DROP TABLE IF EXISTS `{$prefix}k1_resolver`;");
    $this->db->query("
      CREATE TABLE `{$prefix}k1_resolver` (
        `object_id` bigint(20) NOT NULL COMMENT 'JOINable with {$prefix}posts ID',
        `permalink` varchar(2048) NOT NULL COMMENT 'Maximum url length',
        `permalink_sha` char(40) NOT NULL COMMENT 'Used for the index',
        PRIMARY KEY (`object_id`),
        UNIQUE KEY (`permalink_sha`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE={$collate};
    ");

    update_option('k1kit_resolver_db_created', true);
  }

  /**
   * @todo Make this actually available. I can't call it in the plugin deactivation hook as that
   * file has to be god-knows-what-php-version compatible for w.org
   *
   * uninstall.php?
   */
  private function destroyDatabase() {
    $prefix = $this->prefix;
    $this->db->query("DROP TABLE IF EXISTS `{$prefix}resolver`;");
    $this->db->query("DROP TABLE IF EXISTS `{$prefix}resolver_temp`;");

    delete_option('k1kit_resolver_db_created');
    delete_option('k1kit_resolver_index_status');
  }

  public function getIndexablePostTypes() {
    $types = apply_filters(
      'k1kit/resolver/index/indexableTypes',
      get_post_types(apply_filters('k1kit/resolver/index/queryArgs', [
        'public' => true,
      ]))
    );

    return $types;
  }

  public function resolve($url) {
    $url = sanitize_text_field($url ?? '');
    $url = str_replace(
      apply_filters('k1kit/resolver/resolve/urlReplace/search', [
        '&preview=true',
      ]),
      apply_filters('k1kit/resolver/resolve/urlReplace/replace', [
        ''
      ]),
      $url
    );

    // Sometimes saved URLs contain trailing slashes, sometimes they don't. It depends.
    // It's also nicer for the user.
    $slashed = strpos($url, "?") === false ? trailingslashit($url) : $url;
    $prefix = $this->prefix;

    $id = $this->db->get_var($this->db->prepare(
      "SELECT object_id FROM `{$prefix}k1_resolver`
      WHERE permalink_sha = SHA1(%s) OR permalink_sha = SHA1(%s)
      LIMIT 1",
      $url,
      $slashed
    ));

    if ($id !== null) {
      $post = get_post($id);

      return $post;
    }

    return false;
  }

  public function getIndexingStatus() {
    return get_option("k1kit_resolver_index_status", [
      "indexing" => false,
      "chunkCount" => 0,
      "chunks" => [],
    ]);
  }

  public function getIndexStatus() {
    $prefix = $this->prefix;
    $status = $this->getIndexingStatus();

    $types = $this->getIndexablePostTypes();
    foreach ($types as $k => $v) {
      $types[$k] = "'$v'";
    }
    $types = join(', ', $types);

    $total = $this->db->get_var("
      SELECT COUNT(*) FROM `{$prefix}posts`
      WHERE post_status NOT IN ('trash', 'auto-draft')
      AND post_type IN ($types) ORDER BY ID
    ");
    if ($status['indexing']) {
      $indexed = $this->db->get_var("SELECT COUNT(*) FROM `{$prefix}k1_resolver_temp`");

      return [
        "indexing" => true,
        "indexed" => $indexed,
        "total" => $total,
        "percentage" => $indexed / $total * 100,
      ];
    } else {
      $indexed = $this->db->get_var("SELECT COUNT(*) FROM `{$prefix}k1_resolver`");

      return [
        "indexing" => false,
        "indexed" => $indexed,
        "total" => $total,
        "percentage" => 100,
      ];
    }
  }

  public function updateLinkToIndex($pID) {
    if (!wp_is_post_revision($pID)) {
      $prefix = $this->prefix;
      $types = $this->getIndexablePostTypes();
      $permalink = get_permalink($pID);
      $post = get_post($pID);

      if (!in_array($post->post_type, $types)) {
        return false;
      }

      $this->db->query(
        $this->db->prepare("
            INSERT INTO `{$prefix}k1_resolver`
            (object_id, permalink, permalink_sha)
            VALUES(%d, %s, SHA1(%s))
            ON DUPLICATE KEY
            UPDATE permalink = %s, permalink_sha = SHA1(%s)
          ",
          $pID, $permalink, $permalink, $permalink, $permalink
        )
      );

      if ($this->db->last_error) {
        error_log("DB error while updating index: {$this->db->last_error}");
      }
    }
  }

  /**
   * Start the indexing process by creating a temporary table.
   */
  public function startIndexing() {
    if ($this->getIndexingStatus()['indexing']) {
      return $this->continueIndexing();
    }

    $prefix = $this->prefix;
    $this->db->query("CREATE TABLE IF NOT EXISTS `{$prefix}k1_resolver_temp` LIKE `{$prefix}k1_resolver`");
    $this->db->query("TRUNCATE TABLE `{$prefix}k1_resolver_temp`");

    $types = $this->getIndexablePostTypes();
    foreach ($types as $k => $v) {
      $types[$k] = "'$v'";
    }
    $types = join(', ', $types);

    $all = $this->db->get_results("
      SELECT ID FROM `{$prefix}posts`
      WHERE post_status NOT IN ('trash', 'auto-draft')
      AND post_type IN ($types) ORDER BY ID
    ");
    $chunks = array_chunk($all, 250);

    update_option("k1kit_resolver_index_status", [
      "indexing" => true,
      "chunkCount" => count($chunks),
      "chunks" => $chunks,
    ]);

    $this->continueIndexing();
    return true;
  }

  /**
   * Get the next chunk and process it
   */
  public function continueIndexing() {
    $status = $this->getIndexingStatus();
    $prefix = $this->prefix;
    if ($status['indexing'] === false) {
      return false;
    }

    $chunk = array_shift($status['chunks']);

    foreach ($chunk as $obj) {
      $object_id = $obj->ID;
      $link = get_permalink($object_id);
      $post = [
        'object_id' => $object_id,
        'permalink' => $link,
        'permalink_sha' => sha1($link),
      ];

      $this->db->insert("{$prefix}k1_resolver_temp", $post, ['%d', '%s', '%s']);
    }

    if (count($status['chunks']) === 0) {
      $this->endIndexing();
    } else {
      update_option("k1kit_resolver_index_status", [
        "indexing" => true,
        "chunkCount" => $status['chunkCount'],
        "chunks" => $status['chunks'],
      ]);

      wp_remote_post(get_site_url() . '/wp-json/k1/v1/resolver/index/continue', [
        "blocking" => false,
        "sslverify" => !isProd(),
      ]);
    }

    return true;
  }

  /**
   * Switch the active and temporary table and delete the old table
   */
  public function endIndexing() {
    if ($this->getIndexingStatus()['indexing'] === false) {
      return false;
    }

    $prefix = $this->prefix;
    $this->db->query("RENAME TABLE `{$prefix}k1_resolver` TO `{$prefix}k1_resolver_old`, `{$prefix}k1_resolver_temp` TO `{$prefix}k1_resolver`");
    $this->db->query("DROP TABLE `{$prefix}k1_resolver_old`");

    update_option("k1_resolver_index_status", [
      "indexing" => false,
      "chunkCount" => 0,
      "chunks" => [],
    ]);

    return true;
  }

  public function deleteLinkFromIndex($pID) {
    if (!is_int($pID)) {
      return false;
    }

    $prefix = $this->prefix;
    $this->db->query(
      $this->db->prepare(
        "DELETE FROM `{$prefix}k1_resolver` WHERE object_id = %d",
        $pID
      )
    );
  }
}
