<?php
/**
 * HTML for configuration step 3
 */
// TODO: move to different directory? Not technically a template
?>

<body>

<?php // TODO: re-style using Bootstrap ?>

<div class = "container">
    <div class = "row">
        <div class = "col-sm-6 col-sm-offset-3">
            <div>
                <h1 class="text-center"><?php echo $title ?></h1>
            </div>
            <?php // TODO: copy form from biblevel-checker? ?>
            <div id = "striped_Box">
                <div id = 'input' class = "col-sm-12">
                    <label for='upload'>Upload Batch OCLC File:</label>
                    <input type='file' id='upload' onchange="checkFile((this.files[0]));">
                    <?php // TODO: use CSS loader ?>
                    <img src='Assets/ajax-loader.gif' id='loadingIcon' class='dNone'>
                </div>
                <div id='output' class='dNone' style = "text-align: center;">
                    <div class = "col-sm-12">
                        <h2>OCLC Number Responses</h2>
                        <button type='button' id='downloadSimple' onclick='downloadSimple()' class = "btn btn-default">Download Simple</button>
                        <button type='button' id='downloadDetailed' onclick='downloadDetailed()' class = "btn btn-default">Download Detailed</button>
                    </div>
                    <div class='dNone'>
                        <h2>Entries Listed as 'At <?php echo $libraryName; ?>'</h2>
                        <ol id='atLibrary'></ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
