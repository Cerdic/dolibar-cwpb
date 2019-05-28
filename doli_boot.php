<?php

$dir = __DIR__;
while($dir && $dir!=='/' && !file_exists($dir . "/main.inc.php")) {
	$dir = dirname($dir);
}
if (!file_exists($dir . "/main.inc.php")) {
	die ("Impossible de trouver dolibarr");
}
chdir($dir);


define('NOLOGIN', true);

// Include the conf.php and functions.lib.php
require_once 'filefunc.inc.php';
// Init the 5 global objects, this include will make the new and set properties for: $conf, $db, $langs, $user, $mysoc
require_once 'master.inc.php';

//require './main.inc.php';
require __DIR__ . '/inc/exporter_csv.php';
