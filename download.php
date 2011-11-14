<?php
/*
 * File that get accessed when QS request an update (either plugin or main application)
 * 
 * When updating the app, we'll recieve :
 *   /download.php?id=com.blacktree.Quicksilver&type=dmg&new=yes
 * When updating a plugin, we'll recieve :
 *   /download.php?qsversion=%d&id=%@
 */

include("lib/functions.php");

$id = @$_GET['id'];
$version = @$_GET['version'];
$qsversion = @$_GET['qsversion'];

/* Provide Quicksilver download if nothing is requested */
if (!$id)
  $id = QS_ID;

$plugin = null;
if ($id == QS_ID) {
  /* Reconstruct level */
  if (@$_GET['dev'])
    $level = LEVEL_DEV;
  else if (@$_GET['pre'])
    $level = LEVEL_PRE;
  else
    $level = LEVEL_NORMAL;

  $criteria[PLUGIN_LEVEL] = $level;

  /* Sniff the requested version */
  $qsversion = intval($qsversion);
  if($qsversion)
    $criteria[PLUGIN_VERSION] = $qsversion;

  debug("Request for application download \"$id\" with level $level");
  $plugin = Plugin::get(PLUGIN_IDENTIFIER, $id, $criteria);
  if (!$plugin)
    http_error(404, "Application not found");

  /* Start the download ! */
  if (!$plugin->download())
    http_error(404, "Update file not found");

} elseif ($id != null) {
  $criteria = array();
  $criteria[PLUGIN_HOST] = QS_ID;
  $criteria[PLUGIN_IDENTIFIER] = $id;

  if ($version)
    $criteria[PLUGIN_VERSION] = intval($version);

  if ($qsversion)
    $criteria[PLUGIN_HOST_VERSION] = $qsversion;

  debug("Request for plugin download \"$id\" with criterias " . dump_str($criteria));
  $plugin = Plugin::get(PLUGIN_IDENTIFIER, $id, $criteria);
  if (!$plugin)
    http_error(404, "Plugin not found");

  /* Start the download ! */
  if (!$plugin->download())
    http_error(404, "Update file not found");
} else {
  http_error(400, "Unknown update requested");
}
http_error(200, "OK");
?>