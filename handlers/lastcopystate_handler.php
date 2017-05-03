<?php
/**
 * Handles Ajax queries for lastCopyStateChecker
 */

// Data to return
$data = [];

try {
    include_once 'functions/lastcopystate.php';

    // Determine if data is a file or text
    $type = $_POST['type'];
    // Get array of OCLC numbers
    $oclc_list = ($type == 'file') ?
        file($_FILES['oclc-list']['tmp_name']) : explode(',', $_POST['oclc-list']);

    // TODO: determine how to store results and document
    $results = [];

    // Iterate through each number
    foreach ($oclc_list as $index => $oclc) {
        // reset timeout
        set_time_limit(30);
        // Remove any whitespace or non-numeric characters from the string
        $oclc = fix_oclc($oclc);
        // If $oclc is empty, skip this iteration
        if ($oclc == '')
            continue;

        try {
            $library_locations = get_library_locations($oclc);
            $flag_results = check_library_locations($library_locations);
        } catch (LibraryLocationException $e) {
            $error_message = $e->getMessage();
            $flag_results = null;
            $results[$index]['error'] = $error_message;
        }

        $results[$index]['oclc'] = $oclc;
        $results[$index]['flag'] = $flag_results;
    }

    $data['results'] = $results;

}
// Set $data['error'] if an exception was thrown
catch (Exception $e) {
    $data['error'] =  $e->getMessage();
    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
}

// Return JSON encoded data
echo json_encode($data);