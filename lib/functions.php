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

function quote_db($obj)
{
  if ($obj == null)
    return "NULL";
  if (is_string($obj)) {
    if ($obj == "NULL")
      return $obj;
    if ($obj == "")
      return "\"\"";
    connect_db();
    return mysql_real_escape_string($obj);
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

define("LGLVL_DEBUG", 0);
define("LGLVL_ERROR", 5);

$current_level = LGLVL_DEBUG;

function log_level() {
  global $current_level;
  return $current_level;
}

function set_log_level($level) {
  global $current_level;
  $current_level = $level;
}

function qslog($level, $str = null) {
  if ($level <= log_level()) {
    puts($str);
    if ($level == LGLVL_ERROR)
      debug_print_backtrace();
  }
}

function debug($str = null) {
  qslog(LGLVL_DEBUG, $str);
}

function error($str = null) {
  qslog(LGLVL_ERROR, $str);
}

function http_error($code, $msg) {
  header("HTTP $code $msg");
  echo $msg;
  die();
}

function dump($obj) {
  ob_start();
  var_dump($obj);
  $str = ob_get_clean();
  debug($str);
}

/** Utilities */

/** This function does its best to provide the correct URLs relative
 * to the HTTP server's DocRoot. This allow a transparent use when
 * developing on the local Apache server.
 *
 * example:
 * web_root("images/my_image.png") => "/mysite/images/my_image.png"
 */
function web_root($file) {
  if ($file == null)
    return $file;
  $doc_root = $_SERVER['DOCUMENT_ROOT'];
  $self_parts = explode("/", dirname($_SERVER['PHP_SELF']));
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
function file_root($file) {
  return $_SERVER['DOCUMENT_ROOT'] . web_root($file);
}

function glob_file($dir, $glob, $call_glob = true) {
  $file_path = file_root($dir) . (strrpos($dir, "/") != strlen($dir) ? '/' : '') . $glob;
  if (!$call_glob)
    return $file_path;
  $globs = glob($file_path, GLOB_BRACE);
  if (!$globs || count($globs) != 1)
    return null;
  return $globs[0];
}

function mime_type($file) {
  $finfo = finfo_open();
  $type = finfo_file($finfo, $file, FILEINFO_MIME);
  finfo_close($finfo);
  return $type;
}

function send_file($file, $name = null, $redirect = true) {
  if ($redirect) {
    header('Location:' . $file);
    die("You are being redirected...");
  } else {
    $file = file_root($file);
    header("Content-Type: " . mime_type($file));
    echo file_get_contents($file);
  }
}

function outputPlugins()
{

  connect_db();

  $ordervar = @$_GET["order"] ? quote_db(@$_GET["order"]) : "moddate";
  $asc_desc = @$_GET["sort"] ? quote_db(@$_GET["sort"]) : "DESC";

  $result = query_db("SELECT * FROM plugins ORDER BY $ordervar $asc_desc");
  $now = time();
  $i = 0;
  while($row = mysql_fetch_array($result))
  {
    $moddate_unix = strtotime($row['moddate']);
    $odd = $i % 2 == 1;

    $image_file = file_exists($row['image']) ? $row['image'] : "images/noicon.png";

    echo '<div class="box name' . ($odd ? ' odd' : '') . '" >';
    echo '  <img src="' . $image_file . '" alt="plugin icon" />';
    echo '  <a href="https://github.com/downloads/quicksilver/Plugins-Mirror/' . $row['fullpath'] . '">' . $row['name'] . '</a>';
    if($now - $moddate_unix <= 30412800)
      echo '  <sup><span style="color:#ff0000;" >new!</span></sup>';
    echo '</div>';
    echo '<div class="box version' . ($odd ? ' odd' : '') . '" >' . $row['version'] . ' </div>';
    echo '<div class="box updated' . ($odd ? ' odd' : '') . '" >' . $row['moddate'] . ' </div>';
    //	<div class="box" id="dl"><a href="'.$row['fullpath'].'"><img src="images/download.gif" /></a></div>';
    $i++;
  }
  echo '<p>&nbsp;</p>';

  close_db();

}
