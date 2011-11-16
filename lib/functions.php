<?php

require_once "plugin.class.php";
require_once "CFPropertyList/CFPropertyList.php";

define("QS_ID", "com.blacktree.Quicksilver");

/** DB */
function connect_db() {
  static $db = null;
  if (!$db) {
    include('mysql.php');

    $db = mysql_connect ($host, $user, $pwd);
    if (!$db) {
      error('Could not connect');
      return false;
    }

    if (!mysql_select_db($database, $db)) {
      error('Could not select');
      return false;
    }
  }
  return $db;
}

function close_db() {
	return mysql_close(connect_db());
}

function quote_db($obj) {
  if ($obj === null)
    return "NULL";
  if (is_string($obj)) {
    if ($obj == "NULL")
      return $obj;
    if ($obj == "")
      return "\"\"";
    connect_db();
    return '"' . mysql_real_escape_string($obj) . '"';
  } else if (is_bool($obj)) {
    return $obj ? 1 : 0;
  } else {
    return $obj;
  }
}

function query_db($query) {
  connect_db();

  $res = mysql_query($query);
  if (!$res) {
    error('Could not execute: ' . mysql_error());
    return null;
  }
  return $res;
}

function fetch_db($query) {
  $res = query_db($query);
  if (!$res)
    return null;

  $recs = array();
  while($rec = mysql_fetch_assoc($res)) {
    $recs[] = $rec;
  }
  mysql_free_result($res);
  return $recs;
}

/** Logging */

function puts($str = null) {
  if ($str !== null)
    echo $str . "<br />\n";
}

define("LGLVL_DEBUG", E_USER_NOTICE);
define("LGLVL_WARN", E_USER_WARNING);
define("LGLVL_ERROR", E_USER_ERROR);

$current_level = LGLVL_WARN;
if (is_localhost())
  $current_level = LGLVL_DEBUG;

function log_level() {
  global $current_level;
  return $current_level;
}

function set_log_level($level) {
  global $current_level;
  $current_level = $level;
}

function qslog($level, $str) {
  if ($level <= log_level()) {
    error_log($str, 0);
  }
}

function debug($str) {
  qslog(LGLVL_DEBUG, $str);
}

function warn($str) {
  qslog(LGLVL_WARN, $str);
}

function error($str) {
  qslog(LGLVL_ERROR, $str);
}

function http_error($code, $msg) {
  error($msg);
  header("HTTP/ $code $msg");
  die("<h1>$code - $msg</h1>");
}

function dump_str($obj) {
  ob_start();
  var_dump($obj);
  $str = ob_get_clean();
  return $str;
}

function dump($obj) {
  debug(dump_str($obj));
}

/** Utilities */

/** This function returns true if we're on localhost, false otherwise */
function is_localhost() {
  return $_SERVER['HTTP_HOST'] == "localhost"
    || $_SERVER['REMOTE_ADDR'] == "::1"
    || $_SERVER['REMOTE_ADDR'] == "127.0.0.1";
}

/** This function does its best to provide the correct URLs relative
 * to the HTTP server's DocRoot. This allow a transparent use when
 * developing on the local Apache server.
 *
 * example:
 * web_root("images/my_image.png") => "/mysite/images/my_image.png"
 */
function web_root($file, $__file__ = null) {
  if ($file == null)
    return $file;

  if ($__file__ == null)
    $__file__ = $_SERVER['PHP_SELF'];

  $doc_root = $_SERVER['DOCUMENT_ROOT'];

  // Remove the doc root from the file
  if (strpos($__file__, $doc_root) === 0) {
    $__file__ = substr($__file__, strlen($doc_root), strlen($__file__));
  }

  $self_parts = explode("/", dirname($__file__));
  $file_parts = explode("/", $file);
  $parts = array(""); // Path must always start with a /

  if (strpos($file, $doc_root) === 0) {
    return substr($file, strlen($doc_root), strlen($file));
  }

  foreach ($self_parts as $part) {
    if ($part != $parts[count($parts) - 1])
      $parts[] = $part;
  }
  foreach ($file_parts as $part) {
    if ($part == "")
      continue;
    if ($part == "..") {
      array_pop($parts);
      continue;
    }
    if ($part != $parts[count($parts) - 1])
      $parts[] = $part;
  }
  return implode("/", $parts);
}

/** This function returns the real path to a given file.
 * Used to get a correct destination for move_uploaded_file()
 * regardless of where the root is located.
 *
 * example:
 * file_root("my_dir") => "/var/www/mysite/my_dir"
 *
 */
function file_root($file, $__file__ = null) {
  return $_SERVER['DOCUMENT_ROOT'] . web_root($file, $__file__);
}

function glob_file($dir, $glob, $call_glob = true, $__file__ = null) {
  $file_path = file_root($dir, $__file__) . (strrpos($dir, "/") != strlen($dir) ? '/' : '') . $glob;
  if (!$call_glob)
    return $file_path;
  $globs = glob($file_path, GLOB_BRACE);
  if (!$globs || count($globs) != 1)
    return null;
  return $globs[0];
}

function mime_type($file) {
  $type = "application/octet-stream";
  if (function_exists('finfo_open')) {
    $finfo = finfo_open();
    $type = finfo_file($finfo, $file, FILEINFO_MIME);
    finfo_close($finfo);
  } else {
    $last_dot = strrpos($file, ".");
    if ($last_dot) {
      $ext = substr($file, $last_dot, count($file) - $last_dot);
      switch ($ext) {
        case "dmg":
          $type = "application/x-apple-diskimage";
        break;

        case "qspkg":
          $type = "application/x-cpio";
        break;
      }
    }
  }
  return $type;
}

function send_file($file, $name = null) {
  if (strpos($file, "http") === 0) {
    /* HTTP link, redirect… */

    header('Location:' . $file);
    die("You are being redirected...");
  } else {
    $file_path = file_root($file) . $file;
    $type = mime_type($file_path);
    $size = filesize($file_path);

    debug("sending file \"$file\" ($file_path) with name \"$name\", type: $type,  size: $size");
    header("Content-Type: " . $type);
    header("Content-Length: " . $size);
    if ($name)
      header("Content-Disposition: attachment; filename=\"" . $name . "\"");
    $contents = file_get_contents($file_path);
    if (!$contents)
      return false;
    echo $contents;
    die();
  }
  return true;
}

function hexstring_to_int($string) {
  sscanf($string, "%X", $int);
  return $int;
}

function int_to_hexstring($int) {
  return sprintf("%X", $int);
}

?>