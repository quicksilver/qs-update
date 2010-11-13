<?php
/*
 * File that get accessed when QS request an update (either plugin or main application)
 * 
 * When updating the app, we'll recieve :
 *   /download.php?id=com.blacktree.Quicksilver&type=dmg&new=yes (yes, with no version)
 * When updating a plugin, we'll recieve :
 *   /download.php?qsversion=%d&id=%@
 */

include("lib/functions.php");

$type = null; /* either "app", "plugin" or null */
$id = @$_GET['id'];
$appType = null;
$dlType = @$_GET['type'];
$new = @$_GET['new'];
$qsversion = @$_GET['qsversion'];

$plugin = null;
if ($id == QS_ID) {
  $type = "app";
  if (@$_GET['pre'] == "1")
    $appType = "pre";
  if (@$_GET['dev'] == "1")
    $appType = "dev";
  if ($appType)
    $id .= ".$appType";

  $plugin = Plugin::get(PLUGIN_IDENTIFIER, $id);
} elseif ($id != null) {
  $type = "plugin";
  $criteria = array();
  $criteria[PLUGIN_HOST] = QS_ID;
  $criteria[PLUGIN_IDENTIFIER] = $id;

  if ($qsversion)
    $criteria[PLUGIN_HOST_VERSION] = $qsversion;

  $plugin = Plugin::get(PLUGIN_IDENTIFIER, $id, $criteria);
}

if ($type == "app") {
  switch ($dlType) {
    case "dmg":
      /* Build a path to the app dmg */
      $file = $plugin->plugin_url($dlType);
      if (!$file)
        http_error(500, "Non-existent file for plugin \"$id\"");
      send_file($file);
    break;
    default:
      http_error(500, "Unhandled type \"$dlType\". Use type=dmg");
    break;
  }
} else if ($type == "plugin") {
  /* Build a path to this plugin dmg */
  $file = $plugin->plugin_url();
  if (!$file)
    http_error(500, "File not found");
  send_file($file);
} else {

}
?>