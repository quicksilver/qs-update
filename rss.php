<?php

include("lib/functions.php");

$plugins = Plugin::all(false, LEVEL_NORMAL, PLUGIN_MOD_DATE . " DESC");

echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>Quicksilver Plugins Repository</title>
    <link>http://qsapp.com/plugins</link>
    <atom:link href="http://qsapp.com/plugins/rss.xml" rel="self" type="application/rss+xml" />
    <description>A list of the most up to date plugins for Quicksilver.</description><?php
    foreach ($plugins as $plugin) {
      $moddate = strftime("%Y-%m-%d", strtotime($plugin->modDate));
      $version = $plugin->displayVersion ? $plugin->displayVersion : int_to_hexstring($plugin->version);
      ?><item>
        <title><?= $plugin->name ?></title>
        <link>http://qsapp.com/plugins.php</link>
        <description><?= $plugin->name ?> updated to <?= $version ?> on <?= $moddate ?><?php // TODO: Provide changelog ?></description>
        <guid isPermaLink="false"><?= $plugin->plugin_url(); ?></guid>
      </item>
      <?php
    }
  ?></channel>
</rss>