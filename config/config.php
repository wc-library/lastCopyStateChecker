<?php
/**
 * Processes configuration from init.ini
 */

// Config file path
define('CONFIG_PATH', $_SERVER['DOCUMENT_ROOT'].'/config/init.ini');

// Throw exception if config file doesn't exist
if(!file_exists(CONFIG_PATH)) {
    throw new Exception('Config file does not exist.');
}

define('CONFIG', parse_ini_file(CONFIG_PATH));