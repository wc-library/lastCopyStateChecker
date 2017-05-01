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


// Throw exception if config file doesn't exist
if(!file_exists(CONFIG_PATH)) {
    throw new ConfigFileExeption('Config file does not exist.');
}

// Associative array CONFIG
define('CONFIG', parse_ini_file(CONFIG_PATH));
// Config values from INI
// TODO: make variable names consistent with config file (or config keys consistent with variables)
// State abbreviation
$abb = CONFIG['state'];
// Library name
$libraryName = CONFIG['institution'];
// WorldCat API key
$api_key = CONFIG['wskey'];
