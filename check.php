<?php
/*
 * File that get accessed when QS wants to check for updates
 * 
 * URL: check.php?type=rel&current=3841
 * type is either rel, pre, dev (Release, Pre-Release, Development)
 * current is the current QS version
 */

include("lib/functions.php");

$id = QS_ID;
$type = @$_GET['type'];
$current = @$_GET['current'];

$level = null;
switch ($type) {
  case "dev":
    $level = LEVEL_DEV;
  break;
  case "pre":
    $level = LEVEL_PRE;
  break;
  case "rel":
  default:
    $level = LEVEL_NORMAL;
  break;
}
$criterias = array(PLUGIN_LEVEL => $level);

$plugin = Plugin::get(PLUGIN_IDENTIFIER, $id, $criterias);
if (!$plugin)
  http_error(500, "Plugin id \"$id\" not found");

/* FIXME: Check the weird hexString thingy. I guess QS will expect that... */
echo $plugin->version;
?>