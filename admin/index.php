<?php

require("../lib/functions.php");

$plugins = Plugin::all(false, LEVEL_DEV);

$secrets = Plugin::all(true, LEVEL_DEV);

$app = Plugin::get(PLUGIN_IDENTIFIER, QS_ID);

$plugins = array_merge($plugins, $secrets);

?><!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
  <title>Update administration page</title>
  <link href="site.css" rel="stylesheet" type="text/css" />
</head>
<body>
  <div id="page">
    <h1>Update administration page</h1>
    <p><a href="add.php"><strong>Add update/new plugin</strong></a></p>
    <hr />
    <div id="app_updates">
      <h2>Application Updates <span id="app_count"><?= count($app->versions) ?> updates</span></h2>
      <table>
        <tr>
          <th>Version</th>
          <th>Level</th>
          <th>Downloads</th>
        </tr>
        <?php
        $i = 0;
        foreach ($app->versions as $version) {
          $url = "update.php?id=" . $version->identifier . "&amp;version=" . $version->version;
          $odd = ($i % 2 == 1);
          ?><tr<?= $odd ? ' class="odd"' : "" ?> onclick="javascript:document.location = '<?= $url ?>';">
          <td><a href="<?= $url ?>">v<?= substr($version->displayVersion(),2) ?></a></td>
          <td><a href="<?= $url ?>"><?= $version->level ?></a></td>
          <td><a href="<?= $url ?>"><?= $version->downloads ?></a></td>
        </tr><?php
        $i++;
        }
        ?>
      </table>
  </div>
  <hr />
  <div id="plugin_updates">
    <h2>Plugins <span id="plugin_count"><?= count($plugins) ?> plugins</span></h2>
    <table>
      <tr>
        <th>Name</th>
        <th>Version</th>
        <th>Downloads</th>
      </tr>
      <?php
      $i = 0;
      foreach ($plugins as $plugin) {
        $url = "update.php?id=" . $plugin->identifier . "&amp;version=" . $plugin->version;
        $odd = ($i % 2 == 1);
      ?>
      <tr<?= $odd ? ' class="odd"' : "" ?> onclick="javascript:document.location = '<?= $url ?>';">
        <td><a href="<?= $url ?>"><?= $plugin->name ?></a></td>
        <td><a href="<?= $url ?>"><?= $plugin->displayVersion() ?></a></td>
        <td><a href="<?= $url ?>"><?= $plugin->downloads ?></a></td>
      </tr><?php
        $i++;
      }
    ?>
    </table>
  </div>
</div>
</body>
</html>