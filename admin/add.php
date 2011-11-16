<?php

include('../lib/functions.php');

$mod_date = @$_POST['mod_date'];
if ($mod_date)
  $mod_date = strftime("%Y-%m-%d %H:%M:%S", strtotime($mod_date));

$plist = null;

$is_app = @$_POST['is_app'];

if (@$_POST['submit'] == "New") {
  $archive_file = @$_FILES["plugin_archive_file"];
  $info_file = @$_FILES["info_plist_file"];
  $image_file = @$_FILES["image_file"];
  $image_ext = @$_POST['image_ext'];
  $level = @$_POST['app_level'] ? $_POST['app_level'] : 0; /* Plugins get level from plist */

  if ($archive_file["error"] !== 0)
    http_error(500, "Missing plugin_archive_file");
  if ($info_file["error"] !== 0)
    http_error(500, "Missing info_plist_file");

  /* FIXME: Check mime-types / extensions */
  /* Extension preferred, because mime-type is told by client,
  * and usually default to application/octet-stream, which
  * is useless
  */
  try {
    ob_start();
    $plist = new CFPropertyList();
    $plist_contents = null;

    $plist_contents = file_get_contents($info_file['tmp_name']);
    $plist->parse($plist_contents);
    ob_end_clean();
  }
  catch (DOMException $e) {
    /* Remove the likely offending Escape shortcut present in QS Info.plist and try again. */
    debug("Failed parsing once, retry...");
    $plist_contents = str_replace("", "", $plist_contents);
    try {
      ob_start();
      $plist->parse($plist_contents);
      ob_end_clean();
    }
    catch (DOMException $e) {
      error("Exception while parsing: $e");
      http_error(500, "Exception while parsing plist !");
    }
  }

  if (!$plist)
    http_error(500, "Failed to parse plist");

  $dict = $plist->getValue(true);

  if (!$is_app) {
    debug("Add plugin: $archive_file, $info_file, $image_file, $image_ext, " . dump_str($_POST));

    /* Build our plugin record */
    $record = array();
    $record[PLUGIN_IDENTIFIER] = $dict->get('CFBundleIdentifier')->getValue();
    $record[PLUGIN_HOST] = QS_ID;
    $record[PLUGIN_NAME] = $dict->get('CFBundleName')->getValue();
    $record[PLUGIN_VERSION] = hexstring_to_int($dict->get('CFBundleVersion')->getValue());
    if ($mod_date)
      $record[PLUGIN_MOD_DATE] = $mod_date;
    if ($dict->get('CFBundleShortVersionString'))
      $record[PLUGIN_DISPLAY_VERSION] = utf8_decode($dict->get('CFBundleShortVersionString')->getValue());

    $record[PLUGIN_LEVEL] = LEVEL_NORMAL;
    $record[PLUGIN_SECRET] = 0;
    $requirements = $dict->get('QSRequirements');
    if ($requirements) {
      $feature_level = $requirements->get('feature');
      if ($feature_level) {
        $record[PLUGIN_LEVEL] = $feature_level->getValue();
      }
      $minHostVersion = $requirements->get('minHostVersion');
      if ($minHostVersion) {
          $record[PLUGIN_MIN_HOST_VERSION] = $minHostVersion->getValue();
      }
      $maxHostVersion = $requirements->get('maxHostVersion');
      if ($maxHostVersion) {
          $record[PLUGIN_MAX_HOST_VERSION] = $maxHostVersion->getValue();
      }
      $minSystemtVersion = $requirements->get('minSystemVersion');
      if ($minSystemVersion) {
          $record[PLUGIN_MIN_SYSTEM_VERSION] = $minSystemVersion->getValue();
      }
      $maxSystemVersion = $requirements->get('maxSystemVersion');
      if ($maxSystemVersion) {
          $record[PLUGIN_MAX_SYSTEM_VERSION] = $maxSystemVersion->getValue();
      }
    }
    $plugin_info = $dict->get('QSPlugIn');
    if ($plugin_info) {
      $secret = $plugin_info->get('secret');
      if ($secret)
        $record[PLUGIN_SECRET] = $secret->getValue();

      $author = $plugin_info->get('author');
      if ($author)
        $record[PLUGIN_AUTHOR] = $author->getValue();

      $desc = $plugin_info->get('description');
      if ($desc)
        $record[PLUGIN_DESCRIPTION] = $desc->getValue();
    }

    debug("Plugin record generated: " . dump_str($record));

    /* Create the plugin */
    $plugin = new Plugin($record);
    $create_options = array(
      'archive_file' => $archive_file['tmp_name'],
      'info_file' => $info_file['tmp_name'],
      'image_file' => $image_file ? $image_file['tmp_name'] : null,
      'image_ext' => $image_ext,
    );

    if (!$plugin->create($create_options))
      http_error(500, "Failed creation of plugin");
    else
      http_error(200, "OK");
  } else {
    /* Build application update record */
    debug("Add application: $archive_file, $info_file, $image_file, $image_ext, " . dump_str($_POST));
    $record = array();
    $record[PLUGIN_IDENTIFIER] = $dict->get('CFBundleIdentifier')->getValue();
    $record[PLUGIN_HOST] = "";
    $record[PLUGIN_NAME] = $dict->get('CFBundleName')->getValue();
    $record[PLUGIN_VERSION] = hexstring_to_int($dict->get('CFBundleVersion')->getValue());
    $record[PLUGIN_LEVEL] = $level;
    if ($mod_date)
      $record[PLUGIN_MOD_DATE] = $mod_date;
    if ($dict->get('CFBundleShortVersionString'))
      $record[PLUGIN_DISPLAY_VERSION] = utf8_decode($dict->get('CFBundleShortVersionString')->getValue());

    debug("Application record generated: " . dump_str($record));

    /* Create the plugin */
    $plugin = new Plugin($record);
    $create_options = array(
      'archive_file' => $archive_file['tmp_name'],
      'info_file' => $info_file['tmp_name'],
    );

    if (!$plugin->create($create_options))
      http_error(500, "Failed creation of application update");
    else
      http_error(200, "OK");
  }
}
?><html>
  <head>
    <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
    <title>Add plugin</title>
    <link href="site.css" rel="stylesheet" type="text/css" />
  </head>
  <body>
    <form name="plugin_add" method="post" enctype="multipart/form-data">
      <label for="plugin_archive_file">Plugin ditto archive :</label>
      <input id="plugin_archive_file" name="plugin_archive_file" type="file"/><br />
      <label for="info_plist_file">Plugin Info.plist :</label>
      <input id="info_plist_file" name="info_plist_file" type="file"/><br />
      <label for="image_file">Plugin Icon :</label>
      <input id="image_file" name="image_file" type="file"/><br />

      <input id="is_app" name="is_app" type="checkbox"/><label for="is_app"> Application update</label><br />
      &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<label for="app_level">Update level :</label>
      <input type="radio" id="app_level_nor" name="app_level" value="0"><label for="app_level_nor"> Normal</label>&nbsp;&nbsp;
      <input type="radio" id="app_level_pre" name="app_level" value="1"><label for="app_level_pre"> Pre-release</label>&nbsp;&nbsp;
      <input type="radio" id="app_level_dev" name="app_level" value="2"><label for="app_level_dev"> Developer</label>
      </br >

      <label for="date">Update date (leave empty to use archive modification time)</label>
      <input id="mod_date" name="mod_date" type="datetime" size="32"/><br />
      <input type="submit" name="submit" value="New" />
    </form>
  </body>
</html>