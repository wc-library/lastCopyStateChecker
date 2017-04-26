<?php
// Array mapping state abbreviations to their full names
define('STATES', array("AL" => "Alabama", "AK" => "Alaska", "AZ" => "Arizona", "AR" => "Arkansas",
    "CA" => "California", "CO" => "Colorado", "CT" => "Connecticut", "DE" => "Delaware", "FL" => "Florida",
    "GA" => "Georgia", "HI" => "Hawaii", "ID" => "Idaho", "IL" => "Illinois", "IN" => "Indiana", "IA" => "Iowa",
    "KS" => "Kansas", "KY" => "Kentucky", "LA" => "Louisiana", "ME" => "Maine", "MD" => "Maryland", "MA" => "Massachusetts",
    "MI" => "Michigan", "MN" => "Minnesota", "MS" => "Mississippi", "MO" => "Missouri", "MT" => "Montana", "NE" => "Nebraska",
    "NV" => "Nevada", "NH" => "New Hampshire", "NJ" => "New Jersey", "NM" => "New Mexico", "NY" => "New York", "NC" => "North Carolina",
    "ND" => "North Dakota", "OH" => "Ohio", "OK" => "Oklahoma", "OR" => "Oregon", "PA" => "Pennsylvania", "RI" => "Rhode Island",
    "SC" => "South Carolina", "SD" => "South Dakota", "TN" => "Tennessee", "TX" => "Texas", "UT" => "Utah", "VT" => "Vermont",
    "VA" => "Virginia", "WA" => "Washington", "WV" => "West Virginia", "WI" => "Wisconsin", "WY" => "Wyoming", "State" => "State"));

// TODO: move to config/, add .htaccess to ensure API key isn't publically accessible, use .php file instead so can't leak to browser?
// TODO: make this a constant? (it's never written to)
$path = "init.ini";

// If config file exists, parse it and go to $step 3
if(file_exists($path)) {
    $data = parse_ini_file($path);
    $keys = array_keys($data);
    $step = 3;

    $abb = $data[$keys[0]];
    $libraryName = $data[$keys[1]];
    $wskey = $data[$keys[2]];
}
// Else set $step to 1 (or the post value for 'step', if set)
else {
    $step = isset($_POST['step']) ? $_POST['step'] : 1;
    $abb = "State";
}


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
                    if ($flag[0] % 2 === 0) $flagged[] = $line;
                    else $unflagged[] = $line;
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

function flag($oclc,$stateabb,$worldcatkey)
{
    if ($json = file_get_contents("http://www.worldcat.org/webservices/catalog/content/libraries/$oclc?servicelevel=full&format=json&location=$stateabb&frbrGrouping=off&maximumLibraries=100&startLibrary=1&wskey=$worldcatkey"))
    {
        if ($json = json_decode($json))
        {
            if (!property_exists($json, 'library'))
            {
                if ($oclc !== $json->OCLCnumber) return flag($json->OCLCnumber,$stateabb,$worldcatkey);
                else return "Error: '" . $json->diagnostics->diagnostic->message . "'";
            }
            $returnValue[] = 0;
            foreach ( $json->library as $library)
            {
                if (!property_exists($library, 'institutionName')) return "Error: '" . $library->diagnostic->message . "'";

                if ($library->institutionName === $GLOBALS['libraryName'] && $returnValue[0] < 2)
                {
                    $returnValue[0] += 2;
                    $returnValue['url'] = $library->opacUrl;
                }
                else if ($returnValue[0] % 2 === 0) $returnValue[0]++;
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
    <?php include_once 'templates/header.php' ?>

<?php
// TODO: extract <body> markup in each case to a template file
switch($step)
{
    case 1:?>

        <body>
        <div class = "container">
            <div class = "row">
                <div class="col-sm-6 col-sm-offset-3">
                    <form action = "index.php" method = "post"  role="form" id = 'striped_Box'>
                        <div class = "col-sm-12">
                            <label>Please Enter State Name:</label>
                            <?php
                            print "<select name = \"state\" class=\"form-control\">";

                            foreach(array_keys(STATES) as $state)
                            {
                                print "<option value = \"$state\" >";
                                print STATES[$state];
                                print "</option>";
                            }
                            print "</select>";
                            ?>
                        </div>
                        <div class = "col-sm-12">
                            <label>Please Enter the Institution's Name:</label>
                            <input type = "text" name = "institution" class = "form-control">
                        </div>
                        <div class = "col-sm-12">
                            <label>Please Enter the Institution's WorldCat API Key:</label>
                            <input type = "text" name = "wskey" class = "form-control">
                        </div>
                        <div class = "col-sm-12">
                            <br/>
                            <input type = "hidden" value = '2' name = "step">
                            <input type = "submit" value = "Submit" class = "btn btn-default">
                        </div>
                    </form>
                </div>
            </div>
        </div>

        </body>
        </html>
        <?php
        break;

    case 2:

        $state = $_POST['state'];
        $libraryName = $_POST['institution'];
        $wskey = $_POST['wskey'];
        ?>
        <body>
        <div class = "container">
            <div class = "row">
                <div class="col-sm-6 col-sm-offset-3">
                    <form action = "index.php" method = "post" id = "striped_Box" style = "text-align: center;">
                        <h1 class="text-center">Your Library's Information Has Been Saved</h1>
                        <input type = "submit" value = "Continue" class = "btn btn-default">
                    </form>
                </div>
            </div>
        </div>
        </body>
        </html>

        <?php
        $iniData = [];
        $iniData["settings"] =
            [
                "state" => $state,
                "institution" => $libraryName,
                "wskey" => $wskey
            ];

        $file = fopen($path, 'w');
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

    case 3:
        ?>

        <body>
        <div class = "container">
            <div class = "row">
                <div class = "col-sm-6 col-sm-offset-3">
                    <div>
                        <h1 class="text-center">Last Copy in <?php print STATES[$abb];?> Checker</h1>
                    </div>
                    <div id = "striped_Box">
                        <div id = 'input' class = "col-sm-12">
                            <label for='upload'>Upload Batch OCLC File:</label>
                            <input type='file' id='upload' onchange="checkFile((this.files[0]));">
                            <img src='Assets/ajax-loader.gif' id='loadingIcon' class='dNone'>
                        </div>
                        <div id='output' class='dNone' style = "text-align: center;">
                            <div class = "col-sm-12">
                                <h2>OCLC Number Responses</h2>
                                <button type='button' id='downloadSimple' onclick='downloadSimple()' class = "btn btn-default">Download Simple</button>
                                <button type='button' id='downloadDetailed' onclick='downloadDetailed()' class = "btn btn-default">Download Detailed</button>
                            </div>
                            <div class='dNone'>
                                <h2>Entries Listed as 'At <?php print $libraryName; ?>'</h2>
                                <ol id='atLibrary'></ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </body>
        </html>
        <?php
        break;
}	?>