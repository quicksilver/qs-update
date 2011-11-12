<?php

include("lib/functions.php");

/*
* URL: plugininfo.php?asOfDate=date&updateVersion=updateVersion&qsversion=qsversion&sids=ids
* asOfDate is the date of the last fetched date
* updateversion and qsversion are both the currently installed version, as a hexString
*/

$shouldSendFullIndex = false;
$updated = false;
$version = null;

$updateVersion = @$_GET['updateVersion'];
if ($updateVersion) {
  $version = $updateVersion;
  $updated = true; /* Means QS was just updated */
} else {
  $version = @$_GET['qsversion'];
}

$asOfDate = @$_GET['asOfDate'];
if ($asOfDate && !$updated) {
  $date = array();
  if(preg_match_all("/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/", $asOfDate, $date)) {
    $asOfDate = mktime(	$date[4][0], $date[5][0], $date[6][0],
      $date[2][0], $date[3][0], $date[1][0]);
    $asOfDate = strftime("%Y-%m-%d %H:%M:%S", $asOfDate);
  } else {
    $asOfDate = null;
  }
}
if (!$asOfDate)
  $shouldSendFullIndex = true;

/* Convert the hexString back in an integer */
$version = hexstring_to_int($version);

$sids = @$_GET['sids'];
if ($sids) {
  $sids = explode(",", $sids);
}

$os_version = null;
if (preg_match_all("/.*OSX\/(\d{1,})\.(\d{1,})\.(\d{1,}).*/", $_SERVER['HTTP_USER_AGENT'], $version_parts)) {
  $os_version = $version_parts[1][0] . "." . $version_parts[2][0] . "." . $version_parts[3][0];
  //debug("OS X Version: " . $os_version);
  /* TODO: Use this */
}

debug(__FILE__ . ": query: " . $_SERVER['QUERY_STRING']);
debug(__FILE__ . ": asOfDate: $asOfDate, version: $version, updated: " . ($updated ? "yes" : "no") . ", full index: " . ($shouldSendFullIndex ? "yes" : "no") . ", sids: " . dump_str($sids));
debug(__FILE__ . ": Agent: " . $_SERVER['HTTP_USER_AGENT'] . ", OS: $os_version");

$criteria = array();
$criteria[PLUGIN_HOST] = QS_ID;

if ($version)
  $criteria[PLUGIN_HOST_VERSION] = $version;

if ($asOfDate) /* Provide a full list after an update */
  $criteria[PLUGIN_MOD_DATE] = $asOfDate;

/* FIXME: sids */

/* TODO: Send plugins categories along with the list ? */

$query = Plugin::query($criteria);

$structure = array('plugins' => array());
if ($shouldSendFullIndex)
  $structure['fullIndex'] = $shouldSendFullIndex;

foreach ($query as $plugin) {
  $plugin_array = $plugin->to_array();
  $plugin_structure = array();
  $plugin_structure['CFBundleIdentifier'] = $plugin_array[PLUGIN_IDENTIFIER];
  $plugin_structure['CFBundleName'] = $plugin_array[PLUGIN_NAME];
  $plugin_structure['CFBundleVersion'] = new CFString(int_to_hexstring($plugin_array[PLUGIN_VERSION]));

  if (isset($plugin_array[PLUGIN_DISPLAY_VERSION]))
    $plugin_structure['CFBundleShortVersionString'] = new CFString($plugin_array[PLUGIN_DISPLAY_VERSION]);

  $plugin_structure['QSModifiedDate'] = $plugin_array[PLUGIN_MOD_DATE]->format("Y-m-d h:m:s O"); // 2005-04-27 12:05:01 -0800

  /* Fetch Info.plist parts */
  $info_plist = $plugin->plugin_file("qsinfo");
  if ($info_plist) {
    $info_struct = null;
    try {
      $info_struct = new CFPropertyList($info_plist);
    }
    catch (DOMException $e) {
      http_error(500, "Failed reading plist for \"$plugin\"");
    }

    $info_dict = $info_struct->getValue(true);
    if ($info_dict->get('QSPlugIn'))
      $plugin_structure['QSPlugIn'] = $info_dict->get('QSPlugIn');
    if ($info_dict->get('QSRequirements'))
      $plugin_structure['QSRequirements'] = $info_dict->get('QSRequirements');
  }
  $structure['plugins'][] = $plugin_structure;
}

$td = new CFTypeDetector();
$guessedStructure = $td->toCFType( $structure );

$plist = new CFPropertyList();
$plist->add( $guessedStructure );

echo $plist->toXML(true);
?>