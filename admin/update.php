<?php

include('../lib/functions.php');

$submit = @$_GET['submit'];
$id = @$_GET['id'];
if (!$id)
  $id = @$_POST['id'];
$name = @$_GET['name'];
if (!$name)
  $name = @$_POST['name'];
$version = @$_GET['version'];
if (!$version)
  $version = @$_POST['version'];
$plugin = null;

switch ($submit) {
  case "Search":
    $plugin = null;
    if ($id)
      $plugin = Plugin::get(PLUGIN_IDENTIFIER, $id);

    if (!$plugin && $name)
      $plugin = Plugin::get(PLUGIN_NAME, $name);
    
    if ($plugin && $version) {
      debug("version $version");
      if ($plugin->version !== $version)
        $plugin = $plugin->versions[$version];
    }

  break;
  case "Update":
    $plugin = Plugin::get(PLUGIN_IDENTIFIER, $id);

    if ($plugin && $version) {
      debug("version $version");
      if ($plugin->version !== $version)
        $plugin = $plugin->versions[$version];
    }

    $displayVersion = @$_GET['displayVersion'];
    $level = @$_GET['level'];
    $minHostVersion = @$_GET['minHostVersion'];
    $maxHostVersion = @$_GET['maxHostVersion'];
    $plugin->name = $name;
    if ($displayVersion)
      $plugin->displayVersion = $displayVersion;

    if ($level)
      $plugin->level = $level;
    
    if ($minHostVersion)
      $plugin->minHostVersion = $minHostVersion;
    
    if ($maxHostVersion)
      $plugin->maxHostVersion = $maxHostVersion;

    $plugin->save();
  break;
  case "New":

  break;
}

dump($plugin);

?>
<html>
<head></head>
<body>
  <form name="search_plugin">
    <label for="id">ID :</label>
    <input id="id" name="id" value="<?php echo $id; ?>" /><br />
    <label for="name">Name :</label>
    <input id="name" name="name" value="<?php echo $name; ?>" /><br />
    <input type="submit" name="submit" value="Search" />
  </form>
  <?php
  if (!$plugin)
    echo "No plugin found !";
  else {
  ?>
  <form name="plugin_edit" method="post">
    <input type="hidden" id="id" name="id" value="<?php echo $plugin->identifier; ?>" />
    <input type="hidden" id="version" name="version" value="<?php echo $plugin->version; ?>" />
    <label for="id">ID :</label> <?php echo $plugin->identifier; ?><br />
    <label for="version">Version :</label> <?php echo $plugin->version; ?><br />
    <label for="name">Name :</label>
    <input id="name" name="name" value="<?php echo $plugin->name; ?>" /><br />
    <label for="displayVersion">Display Version :</label>
    <input id="displayVersion" name="displayVersion" value="<?php echo $plugin->displayVersion; ?>" /><br />
    <!-- TODO: Change to radio buttons + checkbox for secret -->
    <label for="level">Level :</label>
    <input id="level" name="level" value="<?php echo $plugin->level; ?>" /><br />
    <label for="minHostVersion">Minimum Host Version :</label>
    <input id="minHostVersion" name="minHostVersion" value="<?php echo $plugin->minHostVersion; ?>" /><br />
    <label for="maxHostVersion">Maximum Host Version :</label>
    <input id="maxHostVersion" name="maxHostVersion" value="<?php echo $plugin->maxHostVersion; ?>" /><br />
    <input type="submit" name="submit" value="Update" />
    <?php
    if (count($plugin->versions)) {
      echo "Versions : ";
      foreach ($plugin->versions as $version) {
        $url = "?id=" . $plugin->identifier . "&version=" . $version->version . "&submit=Search";
        echo "<a href=\"" . $url . "\">" . $version->version . "</a> ";
      }
    }
    ?>
  </form>
  <?php
  }
  ?>
</body>
</html>