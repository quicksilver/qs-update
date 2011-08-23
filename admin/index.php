<?php

require("../lib/functions.php");

$plugins = Plugin::all(false, LEVEL_PRE);

$secrets = Plugin::all(true, LEVEL_PRE);

$plugins = array_merge($plugins, $secrets);

?><!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
  <title>Plugin administration page</title>
</head>
<body>
<h1>Plugin administration page</h1>

<p><?= count($plugins) ?> plugins</p>

<a href="add.php">Add plugin</a>

<table>
  <tr>
    <th>Name</th>
    <th>Version</th>
    <th colspan="2">Actions</th>
  </tr>
  <?php
  foreach ($plugins as $plugin) {
    ?><tr>
      <td><?= $plugin->name ?></td>
      <td><?= $plugin->displayVersion() ?></td>
      <td><a href="update.php?id=<?= $plugin->identifier ?>">Edit</a></td>
      <td><a href="delete.php?id=<?= $plugin->identifier ?>">Delete</a></td>
    </tr><?php
  }
  ?>
</table>
</body>
</html>