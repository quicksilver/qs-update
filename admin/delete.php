<?php

include("../lib/functions.php");

$id = $_GET['id'];

$plugin = Plugin::get(PLUGIN_IDENTIFIER, $id);

?>
<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
  <title>Deleting <?= $plugin->name ?></title>
</head>
<body>
  <h1>Deleting <?= $plugin->name ?></h1>
  <?php
  if ($plugin->delete())
    puts("Successfully deleted \"$plugin\"");
  else
    puts("Failed to delete \"$plugin\"");
  ?>
</body>
</html>