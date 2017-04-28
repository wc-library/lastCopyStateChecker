/**
 * TODO: document
 */


var simpleOutput;
var detailedOutput;

function checkFile(file)
{
    // TODO: use jQuery Ajax for clarity

    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function()
    {
        if (xmlhttp.readyState == 4 && xmlhttp.status == 200)
        {
            document.getElementById('loadingIcon').classList.add('dNone');
            if (xmlhttp.responseText == '-1') {
                // TODO: is this necessary?
            }
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

// TODO: copy stuff from biblevel-checker JavaScript, changing things as necessary


