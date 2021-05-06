<?php
error_reporting(0);
@ini_set('display_errors', 0);

$phpver = '';
$wp_version = '';
$plugin_version = '';
$success = true;

preg_match("#^\d+(\.\d+)*#", defined('PHP_VERSION') ? PHP_VERSION : phpversion(), $phpver);
$web = explode(" ", $_SERVER['SERVER_SOFTWARE']);

if (file_exists("../../../wp-includes/version.php")) {
    include_once "../../../wp-includes/version.php";
}

if (file_exists('readme.txt')) {
    $versionLine = '';
    $fh = fopen('readme.txt', 'r');
    while ($line = fgets($fh)) {
        if (strpos($line, 'Stable tag:') > -1) {
            $versionLine .= $line;
        }
    }
    fclose($fh);
    $plugin_version = str_replace('Stable tag: ', '', $versionLine);
    $plugin_version = preg_replace("/\r|\n/", "", $plugin_version);
}

$data = array("success" => $success,
    "systemInfo" => array(
        "platform" => "woocommerce",
        "platformVersion" => (string) $wp_version,
        "phpVersion" => (string) $phpver[0],
        "pluginVersion" => (string) $plugin_version,
        "webserver" => (string) $web[0],
    ));
header('Content-Type: application/json');
echo json_encode($data);
