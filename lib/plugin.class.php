<?php

define("PLUGIN_ID",               "id");
define("PLUGIN_IDENTIFIER",       "identifier");
define("PLUGIN_HOST",             "host");
define("PLUGIN_NAME",             "name");
define("PLUGIN_VERSION",          "version");
define("PLUGIN_DISPLAY_VERSION",  "displayVersion");
define("PLUGIN_DESCRIPTION",      "description");
define("PLUGIN_AUTHOR",           "author");
define("PLUGIN_CATEGORIES",       "categories");
define("PLUGIN_SECRET",           "secret");
define("PLUGIN_LEVEL",            "level");
define("PLUGIN_HOST_VERSION",     "hostVersion");
define("PLUGIN_SYSTEM_VERSION",   "systemVersion");
define("PLUGIN_MOD_DATE",         "modDate");
define("PLUGIN_DOWNLOAD_COUNT",   "downloads");
define("PLUGIN_SECRET_IDS",       "secretIDs");

define("LEVEL_NORMAL",  0);
define("LEVEL_PRE",     1);
define("LEVEL_DEV",     2);

define("PLUGIN_TABLE", "plugins");

define("GITHUB_BASE_URL", "http://cloud.github.com/downloads/quicksilver/Quicksilver/");
define("SYSTEM_DOWNLOAD_URL", "http://qs0.qsapp.com/plugins/download.php");

class Plugin {
  static private $plugins = null;

  private $dict = null;

  private $versions = null;

  private $latestVersion = null;

  private $dirtyProperties = null;

  private static function parseCriterias($criterias) {
    /* Default to hiding secret plugins
     * Also causes the case below to be executed (for secret ids) */
    if (!@$criterias[PLUGIN_SECRET])
      $criterias[PLUGIN_SECRET] = false;

    $where = array();
    foreach ($criterias as $criteria => $value) {
      switch ($criteria) {
        case PLUGIN_SECRET:
          $secret_strings = array();
          /* Handle that here because it gets ORed with secret */
          if ($criterias[PLUGIN_SECRET_IDS]) {
            foreach ($criterias[PLUGIN_SECRET_IDS] as $secret_id) {
              $secret_strings[] = sprintf("identifier = \"%s\"", $secret_id);
            }
          }
          $where[$criteria] = "(secret = " . ($value ? "1" : "0") . (count($secret_strings) != 0 ? " OR " . implode(" OR ", $secret_strings) : "") . ")";
          break;
        case PLUGIN_SECRET_IDS; /* Handled above */ break;
        case PLUGIN_LEVEL:
          $where[$criteria] = "level <= $value";
          break;
        case PLUGIN_HOST_VERSION:
          $where[$criteria]= "(minHostVersion <= \"$value\" OR ISNULL(minHostVersion)) AND (maxHostVersion > \"$value\" OR ISNULL(maxHostVersion))";
          break;
        case PLUGIN_SYSTEM_VERSION:
          $where[$criteria]= "(minSystemVersion <= \"$value\" OR ISNULL(minSystemVersion)) AND (maxSystemVersion > \"$value\" OR ISNULL(maxSystemVersion))";
          break;
        case PLUGIN_MOD_DATE:
          $where[$criteria]= "(modDate > \"$value\" OR ISNULL(modDate))";
          break;
        case PLUGIN_ID:
          $where[$criteria] = "$criteria = $value";
          break;
        default:
          $where[$criteria] = "$criteria = \"$value\"";
          break;
      }
    }
    $where = implode(" AND ", array_values($where));
    return $where;
  }

  static function all($secret = false, $level = LEVEL_NORMAL, $order_by = null) {
    return self::query(array(PLUGIN_SECRET => $secret, PLUGIN_LEVEL => $level, PLUGIN_HOST => QS_ID), $order_by);
  }

  static function query($criterias, $order_by = null) {
    $where = self::parseCriterias($criterias);
    if (!$order_by)
      $order_by = "name asc";
    $sub_sql = "SELECT identifier AS id, MAX(version) AS maxversion FROM " . PLUGIN_TABLE . " WHERE $where GROUP BY id";
    $sql = "SELECT identifier, version FROM " . PLUGIN_TABLE . " INNER JOIN ($sub_sql) AS sub ON sub.id = identifier AND maxversion = version ORDER BY $order_by";
    $recs = fetch_db($sql);
    if (!$recs)
      return array();

    $plugins = array();
    foreach ($recs as $rec) {
      $plugin = Plugin::get(PLUGIN_IDENTIFIER, $rec['identifier'], $criterias);
      $plugins[] = $plugin;
    }
    return $plugins;
  }

  /* Fetching with criterias can prevent the versions to load completely */
  static function fetch($type, $value, $criterias = null) {
    if (!$criterias)
      $criterias = array();
    $criterias[$type] = $value;
    $fetch_version = null;
    /* We will fetch the correct version ourselves
     * because doing it via SQL prevents the load of the other plugins
     */
    if (@$criterias[PLUGIN_VERSION]) {
      $fetch_version = $criterias[PLUGIN_VERSION];
      unset($criterias[PLUGIN_VERSION]);
    }
    $where = self::parseCriterias($criterias);
    /* Fetch the latest item first, so it can be the first one reconstructed */
    $sql = "SELECT * FROM " . PLUGIN_TABLE . " WHERE $where ORDER BY version DESC;";
    $recs = fetch_db($sql);
    if ($recs === false)
      return null;
    $plugin_rec = array_shift($recs);
    $versions = array();
    $plugin = new Plugin($plugin_rec, true);
    foreach ($recs as $rec) {
      $version = new Plugin($rec, true);
      $version->setLatestVersion($plugin);
      $versions[] = $version;
    }
    $plugin->setVersions($versions);
    return $fetch_version ? $plugin->version($fetch_version) : $plugin;
  }

  static function get($type, $value, $criterias = null) {
    $plugin = null;
    if ($type == PLUGIN_IDENTIFIER && $criterias == null)
      $plugin = @self::$plugins[$value];
    if (!$plugin) {
      $plugin = self::fetch($type, $value, $criterias);
      self::$plugins[$plugin->identifier] = $plugin;
    }
    return $plugin;
  }

  function __construct($dict, $saved = false) {
    if (!@$dict['displayVersion'])
      $dict['displayVersion'] = null;
    elseif ($saved)
      $dict['displayVersion'] = utf8_encode($dict['displayVersion']);
    $this->dict = $dict;
    $this->latestVersion = $this;
    $this->versions = array();
    $this->versions[$this->version] = $this;
    $this->dirtyProperties = $saved ? array() : array_keys($dict);
  }

  function __tostring() {
    $version_str = " version: " . $this->displayVersion();
    $host_str = $this->host ? " (host: $this->host)" : "";
    return "$this->name ($this->identifier)$version_str$host_str";
  }

  function __get($key) {
    if ($key == 'versions')
      return $this->versions;
    if ($key == 'latestVersion')
      return $this->latestVersion;
    return @$this->dict[$key];
  }

  function __set($key, $value) {
    if ($this->dict[$key] !== $value) {
      $this->dict[$key] = $value;
      $this->dirtyProperties[] = $key;
    }
  }

  function file_name($ext = "qspkg", $add_version = true) {
    return "{$this->identifier}" . ($add_version ? "__{$this->version}" : "") . ".$ext";
  }

  function image_file($ext = "*") {
    $file_name = $this->file_name($ext, false);
    /* Images *must be* alone in their directory because of the default $ext pattern */
    $image_file = glob_file("../files/images/", $file_name, $ext == "*", __FILE__);
    if (!$image_file)
      $image_file = glob_file("../files/images/", "default.png", false, __FILE__);
    return $image_file;
  }

  function plugin_file($ext = "qspkg", $glob = true) {
    $file_name = $this->file_name($ext, true);
    return glob_file('../files/', $file_name, $glob, __FILE__);
  }

  function application_url($ext = "dmg") {
    $url = null;

    if ($this->level == LEVEL_NORMAL) {

      /* Use github */
      // Drop the beta symbol so that we can prepend a plain B
      $version = "B" . str_replace(utf8_decode("ß"), "", $this->displayVersion);

      $dmg_name = "Quicksilver $version.$ext";
      $url = GITHUB_BASE_URL . $dmg_name;
    } else {
      /* Use local copy */
      $url = $this->plugin_url($ext);
    }
    debug("Plugin#application_url: $url");
    return $url;
  }

  function download_url() {
    $url = (is_localhost() ? web_root("../download.php", __FILE__) : SYSTEM_DOWNLOAD_URL);

    $url .= "?id=" . $this->identifier . "&version=" . $this->version;
    return $url;
  }

  function plist_file($glob = true) {
    return $this->plugin_file("qsinfo", $glob);
  }

  function image_url() {
    return web_root($this->image_file(), __FILE__);
  }

  function plugin_url($ext = "qspkg") {
    return web_root($this->plugin_file($ext), __FILE__);
  }

  function plist_url() {
    return web_root($this->plist_file(), __FILE__);
  }

  function versions() {
    return $this->versions;
  }

  function _setVersions($versions) {
    $this->versions = $versions;
  }

  function setVersions($versions) {
    $versions_dict = array();
    $this->versions = array();
    $versions_dict[$this->version] = $this;

    foreach ($versions as $version) {
      $versions_dict[$version->version] = $version;
      $version->_setVersions($versions_dict);
    }
    $this->_setVersions($versions_dict);
  }

  function version($version) {
    return $this->versions[$version];
  }

  function setLatestVersion($latest) {
    $this->latestVersion = $latest;
  }

  /** This function returns the plugin version for display purposes
   * 
   * no displayVersion set => "$bundle_version"
   * displayVersion set, matches /$major.$minor(.$bugfix)/ => "v$major.$minor(.$bugfix) ($bundle_version)"
   * displayVersion set, doesn't match => "$displayVersion ($bundle_version)"
   */
  function displayVersion() {
    if ($this->displayVersion) {
      $prefix = preg_match("/\d\.\d(\.\d)?/", $this->displayVersion) ? "v" : "";
      return sprintf("%s%s (%s)", $prefix, $this->displayVersion, $this->version);
    }
    return "$this->version";
  }

  /* Serialize the plugin to a PHP array
   * This is useful to transform the plugin data into some other format.
   */
  function to_array() {
    $array = array(
      PLUGIN_IDENTIFIER => $this->identifier,
      PLUGIN_NAME => $this->name,
      PLUGIN_VERSION => $this->version,
      PLUGIN_MOD_DATE => new DateTime($this->modDate, new DateTimeZone('UTC')),
    );
    if ($this->displayVersion)
      $array[PLUGIN_DISPLAY_VERSION] = $this->displayVersion;
    return $array;
  }

  function create($options = array()) {
    debug("Plugin#create: new Plugin => \"$this\": " . dump_str($this->dict));
    debug("Plugin#create: options: " . dump_str($options));

    $archive_file = $options['archive_file'];
    $info_file = $options['info_file'];
    $image_file = $options['image_file'];
    $image_ext = $options['image_ext'];

    $keys = array("identifier", "host", "version", "name", "displayVersion", "modDate", "level");
    $keys = array_unique(array_merge($keys, $this->dirtyProperties));

    foreach ($keys as $key) {
      $val = null;
      if ($key == "modDate" && !$this->$key)
        $val = "NOW()";
      else
        $val = quote_db($this->$key);
      $values[] = $val;
    }
    $keys = implode(", ", $keys);
    $values = implode(", ", $values);

    $sql = "INSERT INTO " . PLUGIN_TABLE . " ($keys) VALUES ($values);";
    debug("Plugin#create: Inserting plugin: $sql");
    if (!query_db($sql)) {
      error("Failed executing SQL: \"$sql\"");
      return false;
    }
    $this->dirtyProperties = array();

    /* Move our uploaded files to their final location */
    $plugin_path = $this->plugin_file($this->host == null ? "dmg" : "qspkg", false);
    $info_plist_path = $this->plist_file(false);
    $image_path = $this->image_file($image_ext);

    debug("Plugin#create: \"$this\" => moving files: \"$plugin_path\", \"$info_plist_path\", \"$image_path\"");

    if (!move_uploaded_file($archive_file, $plugin_path)) {
      error("Can't move \"$archive_file\" to \"$plugin_path\"");
      $this->delete();
      return false;
    }

    if (!move_uploaded_file($info_file, $info_plist_path)) {
      error("Can't move \"$info_file\" to \"$info_plist_path\"");
      $this->delete();
      return false;
    }

    if ($image_file && !move_uploaded_file($image_file, $image_path)) {
      error("Can't move \"$image_file\" to \"$image_path\"");
      $this->delete();
      return false;
    }

    return true;
  }

  function save() {
    if (count($this->dirtyProperties)) {
      $props = array();
      foreach ($this->dirtyProperties as $key) {
        $props[] = $key . " = " . quote_db($this->$key);
      }
      /* Only bump modDate if it isn't already specified */
      if (!in_array("modDate", $this->dirtyProperties))
        $props[] = "modDate = NOW()";
      $props = implode(", ", $props);

      $id = quote_db($this->identifier);
      $version = quote_db($this->version);
      $sql = "UPDATE " . PLUGIN_TABLE . " SET $props WHERE identifier = $id AND version = $version;";
      if (!query_db($sql)) {
        error("Failed executing SQL: \"$sql\"");
        return false;
      }

      /* Reset dirty properties for the next set of changes */
      $this->dirtyProperties = array();
      return true;
    }
    /* Nothing to save */
    return true;
  }

  function delete() {
    $id = quote_db($this->identifier);
    $version = quote_db($this->version);
    $sql = "DELETE FROM " . PLUGIN_TABLE . " WHERE identifier = $id AND version = $version;";
    if (!query_db($sql)) {
      error("Failed deletion of \"$this\", SQL: \"$sql\"");
      return false;
    }

    /* Delete files */
    $plugin_path = $this->plugin_file($this->host == null ? "dmg" : "qspkg", false);
    $info_plist_path = $this->plugin_file("qsinfo", false);
    $image_path = $this->image_file(false);

    if (file_exists($plugin_path))
      unlink($plugin_path);

    if (file_exists($info_plist_path))
      unlink($info_plist_path);

    if (file_exists($image_path))
      unlink($image_path);

    return true;
  }

  function displayName() {
    return $this->name . " " . ($this->displayVersion ? "{$this->displayVersion}" : "($this->version)");
  }

  function download() {
    debug("Plugin#download: \"$this\"");
    $file = null;
    if ($this->host == null) {
      $file = $this->application_url("dmg");
    } else {
      $file = $this->plugin_url("qspkg", false);
    }

    if (!$file) {
      error("Plugin archive for \"$this\" not found");
      return false;
    }

    $id = quote_db($this->identifier);
    $version = quote_db($this->version);
    $sql = "UPDATE LOW_PRIORITY " . PLUGIN_TABLE . " SET downloads = downloads + 1 WHERE identifier = $id AND version = $version";
    if (!query_db($sql)) {
      error("Failed updating download count for \"$this\"");
      return false;
    }

    if (!send_file($file, $this->displayName() . "." . ($this->host == null ? "dmg" : "qspkg"))) {
      error("Failed sending file \"$file\"");
      return false;
    }
    return true;
  }
}

?>