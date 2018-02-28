<?php
/**
 * Handles Ajax queries for getting/setting config data
 */

include_once '../config/config.php';

// Check for old config file at root of application to prevent API key being readable client-sie
if (file_exists('../init.ini')) {
    // If config file exists in config/, just remove the old file
    if (file_exists(CONFIG_PATH))
        unlink('../init.ini');
    // If old config file exists but new one doesn't, move the old one to the proper directory
    else {
        rename('../init.ini', '../config/init.ini');
    }
}

// Get the function requested by ajax query
$function = $_POST['function'];
// Data to return
$data = [];

try {

    // Determine what function to perform based on query
    switch ($function) {
        // Get config values (default case)
        case 'get':
        default:
            // Throw exception if config file doesn't exist
            if(!file_exists(CONFIG_PATH)) {
                throw new ConfigFileExeption('Config file does not exist.');
            }

            // API key not sent client-side
            $data['config'] = [
                'state' => CONFIG['state'],
                'institution' => CONFIG['institution']
            ];
            // Format title string
            $data['title'] = sprintf(TITLE_FORMAT_STRING, STATES[CONFIG['state']]);
            break;

        // Set config values
        case 'set':
            // Get POST data and create config file
            $state = $_POST['state'];
            $libraryName = $_POST['institution'];
            $wskey = $_POST['wskey'];

            $iniData = [];
            $iniData["settings"] =
                [
                    "state" => $state,
                    "institution" => $libraryName,
                    "wskey" => $wskey
                ];

            $file = fopen(CONFIG_PATH, 'w');
            foreach ($iniData as $key => $value) {
                $dataToWrite[] = "[$key]";
                foreach ($value as $k => $v) {
                    $escaped_value = addcslashes($v, '"');
                    $dataToWrite[] = "$k = \"$escaped_value\"";
                }
                $dataToWrite[] = "";
            }
            fwrite($file, implode("\r\n",$dataToWrite));
            fclose($file);
            break;
    }
}
// If config file hasn't been created yet, set $data['config'] to false
catch (ConfigFileExeption $e) {
    $data['config'] = false;
    // Send generic title string
    $data['title'] = sprintf(TITLE_FORMAT_STRING, DEFAULT_STATE);
}
// If some other exception occurred, set $data['error'] and header response code
catch (Exception $e) {
    $data['error'] =  $e->getMessage();
    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
}



// Return JSON encoded data
echo json_encode($data);

