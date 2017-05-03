<?php

// TODO: remove checks for config file, handle through Ajax
try {
    include_once 'config/config.php';
}
// if config file hasn't yet been created, include config form
catch (ConfigFileExeption $e) {
    // TODO: get rid of step system
    $step = isset($_POST['step']) ? $_POST['step'] : 1;
    $abb = DEFAULT_STATE;
}


// TODO: exception is caught by this point if config file doesn't exist
// If config file exists, parse it and go to $step 3
if(file_exists(CONFIG_PATH)) {
    $data = parse_ini_file(CONFIG_PATH);
    $keys = array_keys($data);
    $step = 3;

    $abb = $data[$keys[0]];
    $libraryName = $data[$keys[1]];
    $wskey = $data[$keys[2]];
}


// TODO: move to handler
if (isset($_FILES['file-input']))
{
    if ($handle = fopen($_FILES['file-input']['tmp_name'], 'r'))
    {
        while ($line = fgets($handle))
        {
            if (trim($line) !== "")
            {
                $flag = flag(trim($line),$abb,$wskey);

                if (gettype($flag) === 'string')
                {
                    $flagged[] = $line;
                    $taglines[$flag][] = $line;
                }
                else
                {
                    // 0 or 2 (not elsewhere)
                    if ($flag[0] % 2 === 0)
                        $flagged[] = $line;
                    else
                        $unflagged[] = $line;
                    // 2 or 3 (here)
                    if ($flag[0] >= 2)
                    {
                        $atLibrary[] = $line;
                        $meta[$line]['url'] = $flag['url'];
                    }
                    if ($flag == -1)
                    {
                        echo "Connection_Error";
                        exit;
                    }
                }
            }
        }
        fclose($handle);

        $simpleOutput = '';
        if (isset($flagged)) $simpleOutput = implode($flagged);
        $output = '';
        if (isset($flagged)) $output .= "\tFlagged: " . count($flagged) . " OCLCs\n" . implode($flagged) . "\n";
        if (isset($unflagged)) $output .= "\tUnflagged: " . count($unflagged) . " OCLCs\n" . implode($unflagged) . "\n";
        if (isset($atLibrary)) $output .= "\tStill Registered At $libraryName: " . count($atLibrary) . " OCLCs\n" . implode($atLibrary) . "\n";
        if (isset($taglines))
        {
            $output .= "-----------------------Errors-----------------------\n\n";
            foreach ($taglines as $tag => $arr) $output .= "\t$tag: " . count($arr) . " OCLCs\n" . implode($arr) . "\n";
        }
        $liatLibrary = '';
        if (isset($atLibrary))
        {
            foreach ($atLibrary as $oclc)
                $liatLibrary .= "<li><a href=" . $meta[$oclc]['url'] . " target='_blank'>$oclc</a></li>";
        }
        echo "<?xml version='1.0'?>";
        echo "<output>";
        echo "  <simpleOutput><![CDATA[$simpleOutput]]></simpleOutput>";
        echo "  <detailedOutput><![CDATA[$output]]></detailedOutput>";
        echo "  <liatLibrary><![CDATA[$liatLibrary]]></liatLibrary>";
        echo "</output>";
    }
    else return -1;
    exit;
}

/*
 * FROM EARLIER REVISION:
 +  # Function flag
 +  #
 +  # @param $oclc - OCLC number for a catalog entry
 +  # Return:
 +  #     String - String (usually error) to associate with that OCLC
 +  #     -1 - Error connecting to worldcat
 +  #     [0, ...] - No copy at Library, No copy found elsewhere in IL
 +  #     [1, ...] - No copy at Library, Copy found elsewhere in IL
 +  #     [2, ...] - Copy at Library, No copy found elsewhere in IL
 +  #     [3, ...] - Copy at Library, Copy found elsewhere in IL
 */
function flag($oclc,$stateabb,$worldcatkey)
{
    // TODO: use CURL instead
    if ($json = file_get_contents("http://www.worldcat.org/webservices/catalog/content/libraries/$oclc?servicelevel=full&format=json&location=$stateabb&frbrGrouping=off&maximumLibraries=100&startLibrary=1&wskey=$worldcatkey"))
    {
        if ($json = json_decode($json))
        {
            if (!property_exists($json, 'library'))
            {
                if ($oclc !== $json->OCLCnumber)
                    return flag($json->OCLCnumber,$stateabb,$worldcatkey);
                else
                    return "Error: '" . $json->diagnostics->diagnostic->message . "'";
            }

            // Initialize to 0 (not in library, not elsewhere in state)
            $returnValue[] = 0;
            foreach ( $json->library as $library)
            {
                if (!property_exists($library, 'institutionName'))
                    return "Error: '" . $library->diagnostic->message . "'";

                // If one of the libraries is this institution and we're at 0 or 1 (not in library)
                if ($library->institutionName === $GLOBALS['libraryName'] && $returnValue[0] < 2)
                {
                    // Add 2 to mark that the copy was found at this library
                    $returnValue[0] += 2;
                    $returnValue['url'] = $library->opacUrl;
                }
                // If we found it at another institution at the state
                else if ($returnValue[0] % 2 === 0)
                    // Add 1 to mark that it's been found elsewhere
                    $returnValue[0]++;
            }
            return $returnValue;
        }
        else return "Error: Could not decode response from server";
    }
    else return -1;
}
?>

<!DOCTYPE html>
<html lang = "en">
<?php
// Set $state_title to currently selected state name if applicable
$state_title = (array_key_exists($abb, STATES)) ?
    STATES[$abb] : DEFAULT_STATE;
$title = sprintf(TITLE_FORMAT_STRING, $state_title);

include_once 'templates/header.php';
?>

<?php
switch($step) {
    // Step 1: Config file hasn't been created
    case 1:
        include 'templates/forms/config_form.php';
        break;
    // Step 2: After config form has been submitted
    // TODO: use Ajax instead. Bugs can arise if we reach case 2 without setting variables (e.g. fill out form in case 1, delete ini file, refresh page on step 2)
    case 2:
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

        include 'templates/forms/step2.php';
        break;
    // Step 3: After config file has been created
    case 3:
        include 'templates/forms/lastcopystate_form.php';
        break;
}	?>
</html>