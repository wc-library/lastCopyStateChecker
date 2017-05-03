<?php
/**
 * Processes configuration from init.ini
 */

// Include general constants
include_once $_SERVER['DOCUMENT_ROOT'].'/lastCopyStateChecker/config/constants.php';

// Config file path
define('CONFIG_PATH', $_SERVER['DOCUMENT_ROOT'].'/lastCopyStateChecker/config/init.ini');


/**
 * Exception class to throw if config file has not yet been created.
 */
class ConfigFileExeption extends Exception {}

// Associative array CONFIG
define('CONFIG', parse_ini_file(CONFIG_PATH));
