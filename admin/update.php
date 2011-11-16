<?php

include('../lib/functions.php');

$submit = @$_GET['submit'];
if (!$submit)
  $submit = @$_POST['submit'];

$id = @$_GET['id'];
if (!$id)
  $id = @$_POST['id'];

$name = @$_GET['name'];
if (!$name)
  $name = @$_POST['name'];

$version = @$_GET['version'];
if (!$version)
  $version = @$_POST['version'];

$error = "";

$plugin = Plugin::get(PLUGIN_IDENTIFIER, $id);
if (!$plugin && $id)
  $error = "Cannot find plugin with ID \"$id\"…";

/* Select correct version */
if ($plugin && $version) {
  if ($plugin->version !== $version) {
    $good_plugin = $plugin->versions[$version];
    if ($good_plugin) {
      $plugin = $good_plugin;
    } else {
      $error = "Cannot find plugin version \"$version\" for ID \"$id\": " . dump_str($plugin) . ".";
      $plugin = null; /* Because we don't want to modify another plugin if this is a submit */
    }
  }
}
if ($plugin) {
  switch ($submit) {
    case "Update":
      $displayVersion = @$_POST['displayVersion'];
      $desc = @$_POST['description'];
      $level = @$_POST['level'];
      $minHostVersion = @$_POST['minHostVersion'];
      $maxHostVersion = @$_POST['maxHostVersion'];
      $secret = @$_POST['is_secret'];

      debug(__FILE__ . ": updating \"$id\", \"$version\" with " . dump_str($_POST));
      debug("updating plugin: " . dump_str($plugin));

      $plugin->name = $name;
      $plugin->description = $desc;
      if ($displayVersion)
        $plugin->displayVersion = $displayVersion;

      $plugin->secret = $secret;
      if ($level != null)
        $plugin->level = $level;

      if ($minHostVersion != null)
        $plugin->minHostVersion = $minHostVersion;

      if ($maxHostVersion != null)
        $plugin->maxHostVersion = $maxHostVersion;

      if ($minSystemVersion != null)
        $plugin->minSystemVersion = $minSystemVersion;

      if ($maxSystemVersion != null)
        $plugin->maxSystemVersion = $maxSystemVersion;

      // Update the plugin mod date to be now (p_j_r edit)
      /* FIXME: This should be done by Plugin if there's any difference in values (search dirtyProperties) */
      $plugin->modDate = date('Y-m-d h:m:s O');

      if (!$plugin->save())
        $error = "Plugin save failed.";
    break;
    case "Cancel":
      header("Location: index.php");
      die();
    break;
    case "Delete":
      header("Location: delete.php?id=" . $plugin->identifier . "&version=" . $plugin->version);
      die();
    break;
  }
}

?><!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
  <title>Editing <?= $plugin->name ?></title>
  <link href="site.css" rel="stylesheet" type="text/css" />
  <script type="text/javascript">
  <!--
  function askDelete(pluginName) {
    var res = confirm("You're about to delete plugin \"" + pluginName + "\". Are you sure you want to continue ?");
    return res;
  }
  -->
  </script>
</head>
<body>
  <div id="page">
    <h1>Editing <?= $plugin->name ?></h1>
    <?php
    if ($error)
      echo "<p class=\"error\">" . $error . "</p>";
      if ($plugin) {
    ?>
    <form name="plugin_edit" method="post">
      <label>Available version: </label>
      <?php
      if (count($plugin->versions)) {
        foreach ($plugin->versions as $version) {
          $url = "?id=" . $plugin->identifier . "&version=" . $version->version;
          echo "<a class=\"version\" href=\"$url\">" . $version->displayVersion() . "</a> ";
        }
      }
      ?><br />
      <input type="hidden" id="id" name="id" value="<?php echo $plugin->identifier; ?>" />
      <input type="hidden" id="version" name="version" value="<?php echo $plugin->version; ?>" />
      <label for="id">ID :</label> <?php echo $plugin->identifier; ?><br />
      <label for="version">Version :</label> <?php echo $plugin->version; ?><br />
      <label for="name">Name :</label>
      <input id="name" name="name" value="<?php echo $plugin->name; ?>" size="48"/><br />
      <label for="displayVersion">Display Version :</label>
      <input id="displayVersion" name="displayVersion" value="<?php echo $plugin->displayVersion; ?>" size="6"/><br />
      <label for="description">Description :</label><br />
      <textarea id="description" name="description" rows="6" cols="65"><?php echo $plugin->description; ?></textarea><br />
      <label for="level">Level :</label>
      <input type="radio" id="level_nor" name="level" value="0"<?= $plugin->level == 0 ? " checked" : "" ?>><label for="level_nor" class="no_strong">Normal</label>&nbsp;&nbsp;
      <input type="radio" id="level_pre" name="level" value="1"<?= $plugin->level == 1 ? " checked" : "" ?>><label for="level_pre" class="no_strong">Pre-release</label>&nbsp;&nbsp;
      <input type="radio" id="level_dev" name="level" value="2"<?= $plugin->level == 2 ? " checked" : "" ?>><label for="level_dev" class="no_strong">Developer</label>&nbsp;&nbsp;
      <input type="checkbox" id="is_secret" name="is_secret"<?= $plugin->secret ? " checked" : "" ?> value="1"/> <label for="is_secret" class="no_strong">Plugin is secret</label><br />
      <label for="minHostVersion">Host Version – Min:</label><input id="minHostVersion" name="minHostVersion" value="<?php echo $plugin->minHostVersion; ?>" size="6" />
      <label for="maxHostVersion">– Max:</label><input id="maxHostVersion" name="maxHostVersion" value="<?php echo $plugin->maxHostVersion; ?>" size="6" /><br />
      <label for="minSystemVersion">System Version – Min:</label><input id="minSystemVersion" name="minSystemVersion" value="<?php echo $plugin->minSystemVersion; ?>" size="6" />
      <label for="maxSystemVersion">– Max:</label><input id="maxSystemVersion" name="maxSystemVersion" value="<?php echo $plugin->maxSystemVersion; ?>" size="6" /><br />
      <label>Download count: </label><?= $plugin->downloads ?> |
      <label>Last updated at: </label><?= $plugin->modDate ?><br />
      <hr />
      <input class="left" type="submit" name="submit" value="Delete" onclick="return askDelete('<?= $plugin->displayName() ?>');" />
      <input class="right" type="submit" name="submit" value="Update" />
      <input class="right" type="submit" name="submit" value="Cancel" />
    </form>
    <?php
    } else {
      ?><a href="index.php">Return to Updates index page</a><?php
    }
    ?>
  </div>
</body>
</html>