<?php

include('../lib/functions.php');

$host = @$_POST['host'];
if ($host === null)
  $host = QS_ID;

$mod_date = @$_POST['mod_date'];
if ($mod_date)
  $mod_date = strftime("%Y-%m-%d %H:%M:%S", strtotime($mod_date));

if (@$_POST['submit'] == "New") {
  $archive_file = @$_FILES["plugin_archive_file"];
  $info_file = @$_FILES["info_plist_file"];
  $image_file = @$_FILES["image_file"];
  $image_ext = @$_POST['image_ext'];

  if ($archive_file["error"] !== 0)
    http_error(400, "Missing plugin_archive_file");
  if ($info_file["error"] !== 0)
    http_error(400, "Missing info_plist_file");

  $plist = null;
  /* FIXME: Check mime-types / extensions */
  /* Extension preferred, because mime-type is told by client,
  * and usually default to application/octet-stream, which
  * is useless
  */
  try {
    ob_start();
    $plist_content = file_get_contents($info_file['tmp_name']);

    $plist = new CFPropertyList();
    $plist->parse($plist_content);
    ob_end_clean();
  }
  catch (DOMException $e) {
    error("Exception while parsing: $e");
    http_error(400, "Exception while parsing plist !");
  }

  if (!$plist)
    http_error(400, "Failed to parse plist");

  $dict = $plist->getValue(true);

  debug("Add: $archive_file, $info_file, $image_file, $image_ext, for $host, plist: " . dump_str($dict));

  /* Build our plugin record */
  $plugin_rec = array();
  $plugin_rec[PLUGIN_IDENTIFIER] = $dict->get('CFBundleIdentifier')->getValue();
  $plugin_rec[PLUGIN_HOST] = $host;
  $plugin_rec[PLUGIN_NAME] = $dict->get('CFBundleName')->getValue();
  $plugin_rec[PLUGIN_VERSION] = hexstring_to_int($dict->get('CFBundleVersion')->getValue());
  if ($mod_date)
    $plugin_rec[PLUGIN_MOD_DATE] = $mod_date;
  if ($dict->get('CFBundleShortVersionString'))
    $plugin_rec[PLUGIN_DISPLAY_VERSION] = utf8_decode($dict->get('CFBundleShortVersionString')->getValue());

  $plugin_rec[PLUGIN_LEVEL] = LEVEL_NORMAL;
  $plugin_rec[PLUGIN_SECRET] = 0;
  $requirements = $dict->get('QSRequirements');
  if ($requirements) {
    $feature_level = $requirements->get('feature');
    if ($feature_level)
      $plugin_rec[PLUGIN_LEVEL] = $feature_level->getValue();
  }
  $plugin_info = $dict->get('QSPlugIn');
  if ($plugin_info) {
    $secret = $plugin_info->get('secret');
    if ($secret)
      $plugin_rec[PLUGIN_SECRET] = $secret->getValue();

    $author = $plugin_info->get('author');
    if ($author)
      $plugin_rec[PLUGIN_AUTHOR] = $author->getValue();

    $desc = $plugin_info->get('description');
    if ($desc)
      $plugin_rec[PLUGIN_DESCRIPTION] = $desc->getValue();
  }

  debug("Plugin record generated: " . dump_str($plugin_rec));

  /* Create the plugin */
  $plugin = new Plugin($plugin_rec);
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
}
?><html>
  <head>
    <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
    <title>Add plugin</title>
  </head>
  <body>
    <form name="plugin_add" method="post" enctype="multipart/form-data">
      <input type="hidden" name="host" value="<?php echo QS_ID;?>" />
      <label for="plugin_archive_file">Plugin ditto archive :</label>
      <input id="plugin_archive_file" name="plugin_archive_file" type="file"/><br />

      <label for="info_plist_file">Plugin Info.plist :</label>
      <input id="info_plist_file" name="info_plist_file" type="file"/><br />

      <label for="image_file">Plugin Icon :</label>
      <input id="image_file" name="image_file" type="file"/><br />

      <label for="date">Update date (leave empty to use archive modification time) :</label>
      <input id="mod_date" name="mod_date" type="datetime" size="32"/><br />

      <input type="submit" name="submit" value="New" />
    </form>
  </body>
</html>