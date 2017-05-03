<?php
/**
 * Functions for checking the last copy state
 */

// Include configuration data (exception thrown if file doesn't exist)
include_once $_SERVER['DOCUMENT_ROOT'] . '/lastCopyStateChecker/config/config.php';
// Throw exception if config file doesn't exist
if(!file_exists(CONFIG_PATH)) {
    throw new ConfigFileExeption('Config file does not exist.');
}


/* Classes */

/**
 * Exception class to throw if get_library_locations() fails to retrieve locations
 */
class LibraryLocationException extends Exception {}


/* Functions */

/**
 * Remove any non-numeric characters from the string to ensure the OCLC number format is valid
 * @param string $oclc The OCLC number to fix
 * @return string The fixed OCLC number
 */
function fix_oclc($oclc) {
    // Remove all non-numeric characters (e.g. whitespace, letters, punctuation, etc)
    return preg_replace("/[^0-9]/", "", $oclc);
}


/**
 * Formats the WorldCat Search API URL
 * @param string|int $oclc The OCLC number of the record
 * @return string Formatted WorldCat Search API URL (includes the API key)
 */
function format_api_url($oclc) {
    $api_key = CONFIG['wskey'];
    $state = CONFIG['state'];

    $base_url = "http://www.worldcat.org/webservices/catalog/content/libraries/$oclc";
    $get_params = "servicelevel=full&format=json&location=$state&frbrGrouping=off&maximumLibraries=100&startLibrary=1&wskey=$api_key";
    return "$base_url?$get_params";
}


/**
 * Retrieves JSON data for library locations query using curl
 * @param string|int $oclc The OCLC number
 * @return mixed Associative array of libraries or false if query failed
 * @throws LibraryLocationException
 */
function get_library_locations($oclc) {
    $url = format_api_url($oclc);

    // Retrieve JSON data
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    $json = curl_exec($ch);

    // Get results as associative array
    $results = json_decode($json, true);
    // If $results is an array and has the key 'library', retrieve $results['library']
    $library_locations =
        (is_array($results) && array_key_exists('library', $results)) ?
        $results['library'] : false;

    // If $library_locations is false, pass the error message on in an exception
    if ($library_locations === false) {
        $error_message = array_key_exists('diagnostics', $results) ?
            $error_message =$results['diagnostics']['diagnostic']['message'] :
            'Could not decode response from server';
        throw new LibraryLocationException($error_message);
    }

    return $library_locations;
}


/**
 * Determine if item is at $library and/or elsewhere in the state
 * @param array $library_locations Results of get_library_locations()
 * @return array Results of check where
 * ['at-library'] = true if item is at this institution
 * ['in-state'] = true if item is elsewhere in the state
 * ['url'] = the URL for this institution's catalog entry or null if not found
 * @throws LibraryLocationException
 */
function check_library_locations($library_locations) {
    $institution = CONFIG['institution'];

    // Initialize results array
    $results = [
        'at-library' => false,
        'in-state' => false,
        'url' => null
    ];

    // Iterate through library locations
    foreach ($library_locations as $library) {
        // Handle case where $library doesn't have key 'institutionName'
        // This generally occurs when $library_locations is a single-item array with diagnostic information
        if (!array_key_exists('institutionName', $library)) {
            // Get error message or set a generic one if no diagnostic information is provided
            $error_message = array_key_exists('diagnostic', $library) ?
                $library['diagnostic']['message'] :
                'Institution name not found and no diagnostic message provided';
            throw new LibraryLocationException($error_message);
        }

        // If it's at this library and we haven't marked it as such already
        if ($library['institutionName'] === $institution && !$results['at-library']) {
            // Set $results['at-library'] to true and ['url'] to URL for item in institution's local catalog
            $results['at-library'] = true;
            $results['url'] = $library['opacUrl'];
        }
        // Else if it's at another institution in the state and we haven't marked it as such already
        else if (!$results['in-state']) {
            // Set 'in-state' flag to true
            $results['in-state'] = true;
        }
    }

    return $results;
}