<?php
$states = array("AL" => "Alabama", "AK" => "Alaska", "AZ" => "Arizona", "AR" => "Arkansas",
"CA" => "California", "CO" => "Colorado", "CT" => "Connecticut", "DE" => "Delaware", "FL" => "Florida", 
"GA" => "Georgia", "HI" => "Hawaii", "ID" => "Idaho", "IL" => "Illinois", "IN" => "Indiana", "IA" => "Iowa",
"KS" => "Kansas", "KY" => "Kentucky", "LA" => "Louisiana", "ME" => "Maine", "MD" => "Maryland", "MA" => "Massachusetts",
"MI" => "Michigan", "MN" => "Minnesota", "MS" => "Mississippi", "MO" => "Missouri", "MT" => "Montana", "NE" => "Nebraska",
"NV" => "Nevada", "NH" => "New Hampshire", "NJ" => "New Jersey", "NM" => "New Mexico", "NY" => "New York", "NC" => "North Carolina",
"ND" => "North Dakota", "OH" => "Ohio", "OK" => "Oklahoma", "OR" => "Oregon", "PA" => "Pennsylvania", "RI" => "Rhode Island",
"SC" => "South Carolina", "SD" => "South Dakota", "TN" => "Tennessee", "TX" => "Texas", "UT" => "Utah", "VT" => "Vermont",
"VA" => "Virginia", "WA" => "Washington", "WV" => "West Virginia", "WI" => "Wisconsin", "WY" => "Wyoming", "State" => "State");

$path = "init.ini";

	if(file_exists($path))
		{
		$data = parse_ini_file($path);
		$keys = array_keys($data);
		$step = 3;
	
		$abb = $data[$keys[0]];
		$libraryName = $data[$keys[1]];
		$wskey = $data[$keys[2]];
		}
	else
		{
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
<head>
	<title>Last Copy in <?php print $states[$abb]; ?> Checker</title>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">	
	<link rel="stylesheet" href="http://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.2/jquery.min.js"></script>
	<script src="http://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>
	<style>
	body {background: linear-gradient(to right,#A3B58E 0%,#C6D6AE 50%,#A3B58E 100%);}
	h1 { text-align: center; }
	#striped_Box {padding: 25px; margin: auto; background: url("Assets/bg-stripes.png") repeat #fff;  border: 1px #AAF solid; overflow: hidden; box-shadow: 2px 2px 8px #888; border-radius: 8px;}
	#content { position: relative; padding: 25px; }
	#input { position: relative; }
	#loadingIcon { position: absolute; height: 100%; left: 0; right: 0; margin: auto; top: 0; }
	.dNone { display: none; }
	#output { position: relative; }
	#atLibrary { background: #fcfcfc; border: 2px black solid; border-radius: 10px; padding-top: 10px; padding-bottom: 10px; }
	</style>
	<script>
    var simpleOutput;
    var detailedOutput;

    function checkFile(file) 
		{
		var xmlhttp = new XMLHttpRequest();
		var xmlhttp = new XMLHttpRequest();
		xmlhttp.onreadystatechange = function()
			{
			if (xmlhttp.readyState == 4 && xmlhttp.status == 200)
				{
				document.getElementById('loadingIcon').classList.add('dNone');
				if (xmlhttp.responseText == '-1')
					{}
				else if (xmlhttp.responseText == "Connection_Error")
					{
					alert("There were issues either parsing the file or connecting to www.worldcat.org");
					}
				else
					{
					document.getElementById('input').classList.add('dNone');
					document.getElementById('output').classList.remove('dNone');
					var parser = new DOMParser();
					var xml = parser.parseFromString(xmlhttp.responseText, "text/xml");
					simpleOutput = xml.getElementsByTagName('simpleOutput')[0].childNodes[0].nodeValue;
					detailedOutput = xml.getElementsByTagName('detailedOutput')[0].childNodes[0].nodeValue;
					document.getElementById('input').classList.add('dNone');
					if(xml.getElementsByTagName('liatLibrary')[0].childNodes[0].nodeValue != '')
						{
						document.getElementById('atLibrary').parentNode.classList.remove('dNone');
						document.getElementById('atLibrary').innerHTML = xml.getElementsByTagName('liatLibrary')[0].childNodes[0].nodeValue;
						}
					}
				}
			};
		xmlhttp.open("POST", document.location.href, true);
		var formData = new FormData();
		formData.append("file-input", file);
		xmlhttp.send(formData);
		document.getElementById('loadingIcon').classList.remove('dNone');
		}

    function downloadSimple()
		{
		document.location = 'data:application/octet-stream,' + encodeURIComponent(simpleOutput);
		}

	function downloadDetailed()
		{
		document.location = 'data:application/octet-stream,' + encodeURIComponent(detailedOutput);
		}
	</script>
</head>
	
<?php
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
			
					foreach(array_keys($states) as $state)
						{
						print "<option value = \"$state\" >";
						print $states[$state];
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
						<h1>Your Library's Information Has Been Saved</h1>
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
						<h1>Last Copy in <?php print $states[$abb];?> Checker</h1>
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