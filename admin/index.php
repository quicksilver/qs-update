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
<h1>Update administration page</h1>
<p><a href="add.php"><strong>Add update/new plugin</strong></a></p>
<hr />
<h2>Application Updates</h2>

<p><?= count($app->versions) ?> updates</p>

<table>
  <tr>
    <th>Version</th>
    <th>Level</th>
    <th>Downloads</th>
    <th colspan="2">Actions</th>
  </tr>
  <?php
  foreach ($app->versions as $version) {
    ?><tr>
      <td>v<?= substr($version->displayVersion(),2) ?></td>
      <td><?= $version->level ?></td>
      <td><?= $version->downloads ?></td>
      <td><a href="update.php?id=<?= $version->identifier ?>&amp;version=<?= $version->version ?>">Edit</a></td>
      <td><a href="delete.php?id=<?= $version->identifier ?>&amp;version=<?= $version->version ?>">Delete</a></td>
    </tr><?php
  }
  ?>
</table>
<hr />
<h2>Plugins</h2>
<p><?= count($plugins) ?> plugins</p>

<table>
  <tr>
    <th>Name</th>
    <th>Version</th>
    <th>Downloads</th>
    <th colspan="2">Actions</th>
  </tr>
  <?php
  foreach ($plugins as $plugin) {
    ?><tr>
      <td><?= $plugin->name ?></td>
      <td><?= $plugin->displayVersion() ?></td>
      <td><?= $plugin->downloads ?></td>
      <td><a href="update.php?id=<?= $plugin->identifier ?>&amp;version=<?= $plugin->version ?>">Edit</a></td>
      <td><a href="delete.php?id=<?= $plugin->identifier ?>&amp;version=<?= $plugin->version ?>">Delete</a></td>
    </tr><?php
  }
  ?>
</table>
</body>
</html>