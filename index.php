<?php
  $libraryName = "";

  if (isset($_FILES['file-input'])){
    if ($handle = fopen($_FILES['file-input']['tmp_name'], 'r')) {
      while ($line = fgets($handle)) {
        if (trim($line) !== "") {
          $flag = flag(trim($line));
          if (gettype($flag) === 'string') {
            $flagged[] = $line;
            $taglines[$flag][] = $line;
          } else {
            if ($flag[0] % 2 === 0) $flagged[] = $line;
            else $unflagged[] = $line;

            if ($flag[0] >= 2) {
              $AtLibrary[] = $line;
              $meta[$line]['url'] = $flag['url'];
            }

            if ($flag == -1) {
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
      if (isset($AtLibrary)) $output .= "\tStill Registered At $libraryName: " . count($AtLibrary) . " OCLCs\n" . implode($AtLibrary) . "\n";
      if (isset($taglines)) {
        $output .= "-----------------------Errors-----------------------\n\n";
        foreach ($taglines as $tag => $arr) $output .= "\t$tag: " . count($arr) . " OCLCs\n" . implode($arr) . "\n";
      }

      $liAtLibrary = '';
      if (isset($AtLibrary)) {
        foreach ($AtLibrary as $oclc)
          $liAtLibrary .= "<li><a href=" . $meta[$oclc]['url'] . " target='_blank'>$oclc</a></li>";
      }

      // Return Values:
      //    $simpleOutput - String => Flagged OCLCs
      //    $output - String => More detailed output including errors & additional ibase_db_info
      //    $liAtLibrary - String => HTML formatted list of li's linking to books still listed as at Library
      echo "<?xml version='1.0'?>";
      echo "<output>";
      echo "  <simpleOutput><![CDATA[$simpleOutput]]></simpleOutput>";
      echo "  <detailedOutput><![CDATA[$output]]></detailedOutput>";
      echo "  <liAtLibrary><![CDATA[$liAtLibrary]]></liAtLibrary>";
      echo "</output>";
    } else return -1;
    exit;
  }

  # Function flag
  #
  # @param $oclc - OCLC number for a catalog entry
  # Return:
  #     String - String (usually error) to associate with that OCLC
  #     -1 - Error connecting to worldcat
  #     [0, ...] - No copy at Library, No copy found elsewhere in IL
  #     [1, ...] - No copy at Library, Copy found elsewhere in IL
  #     [2, ...] - Copy at Library, No copy found elsewhere in IL
  #     [3, ...] - Copy at Library, Copy found elsewhere in IL
  function flag($oclc) {
    if ($json = file_get_contents("http://www.worldcat.org/webservices/catalog/content/libraries/$oclc?servicelevel=full&format=json&location=$state&frbrGrouping=off&maximumLibraries=100&startLibrary=1&wskey=$wskey")) {
      if ($json = json_decode($json)) {
        if (!property_exists($json, 'library')) {
          if ($oclc !== $json->OCLCnumber) return flag($json->OCLCnumber);
          else return "Error: '" . $json->diagnostics->diagnostic->message . "'";
        }
        $returnValue[] = 0;
        foreach ( $json->library as $library) {
          if (!property_exists($library, 'institutionName')) return "Error: '" . $library->diagnostic->message . "'";

          if ($library->institutionName === $GLOBALS['libraryName'] && $returnValue[0] < 2) {
            $returnValue[0] += 2;
            $returnValue['url'] = $library->opacUrl;
          } else if ($returnValue[0] % 2 === 0) $returnValue[0]++;
        }
        return $returnValue;
      } else return "Error: Could not decode response from server";
    } else return -1;
  }

?>
<!DOCTYPE html>

<html>
<head>
  <style>
    body {margin:0; padding:0; }
    h1 { text-align: center; }
    #container { height: 100%; width: 32em;  margin: auto; background: url("Assets/bg-stripes.png") repeat #fff;  border: 1px #AAF solid; overflow: hidden; }
    #content { position: relative; padding: 1em; }
    #input { position: relative; }
    #loadingIcon { position: absolute; height: 100%; left: 0; right: 0; margin: auto; top: 0; }
    .dNone { display: none; }
    #output { position: relative; }
    .download { width: 40%; height: 40px; }
    #AtLibrary { background: #fcfcfc; border: 2px black solid; border-radius: 10px; padding-top: 10px; padding-bottom: 10px; }
  </style>
  <script>
    var simpleOutput;
    var detailedOutput;

    function init() {
    }

    function checkFile(file) {
      //filename = 'testDoc';
      var xmlhttp = new XMLHttpRequest();
      var xmlhttp = new XMLHttpRequest();
      xmlhttp.onreadystatechange = function() {
        if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
          document.getElementById('loadingIcon').classList.add('dNone');
          if (xmlhttp.responseText == '-1') {
          } else if (xmlhttp.responseText == "Connection_Error") {
            alert("There were issues either parsing the file or connecting to www.worldcat.org");
          }else {
            document.getElementById('input').classList.add('dNone');
            document.getElementById('output').classList.remove('dNone');
            var parser = new DOMParser();
            var xml = parser.parseFromString(xmlhttp.responseText, "text/xml");
            simpleOutput = xml.getElementsByTagName('simpleOutput')[0].childNodes[0].nodeValue;
            detailedOutput = xml.getElementsByTagName('detailedOutput')[0].childNodes[0].nodeValue;
            document.getElementById('input').classList.add('dNone');
            if (xml.getElementsByTagName('liAtLibrary')[0].childNodes[0].nodeValue != '') {
              document.getElementById('AtLibrary').parentNode.classList.remove('dNone');
              document.getElementById('AtLibrary').innerHTML = xml.getElementsByTagName('liAtLibrary')[0].childNodes[0].nodeValue;
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

    function downloadSimple() {
      document.location = 'data:application/octet-stream,' + encodeURIComponent(simpleOutput);
    }

    function downloadDetailed() {
      document.location = 'data:application/octet-stream,' + encodeURIComponent(detailedOutput);
    }

  </script>
</head>
<body onload='init()'>
  <h1>Last Copy in Illinois Checker</h1>
  <div id='container'>
    <div id='content'>
      <div id='input'>
        <label for='upload'>Upload Batch OCLC File:</label><br>
        <input type='file' id='upload' onchange="checkFile((this.files[0]));"></input>
        <img src='Assets/ajax-loader.gif' id='loadingIcon' class='dNone'>
      </div>
      <div id='output' class='dNone'>
        <h2>OCLC Number Responses</h2>
        <button type='button' id='downloadSimple' onclick='downloadSimple()' class='download'>Download Simple</button>
        <button type='button' id='downloadDetailed' onclick='downloadDetailed()' class='download'>Download Detailed</button>
        <div  class='dNone'>
          <h2>Entries Listed as 'At <?php print $libraryName; ?>'</h2>
          <ol id='AtLibrary'></ol>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
