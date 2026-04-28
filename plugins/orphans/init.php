<?php
require_once( '../plugins/orphans/orphans_conf.php' );

eval( FileUtil::getPluginConf( $plugin["name"] ) );

$conf = rOrphanConf::load();
$jResult .= $conf->get();
$jResult .= "plugin.maxFiles=" . intval($orphans_max_files) . ";";
$jResult .= "plugin.debug="    . ($orphans_debug ? 1 : 0) . ";";

$theSettings->registerPlugin($plugin["name"], $pInfo["perms"]);
