<?php
/**
 * Markup for main form to upload a list of OCLC numbers
 */
// TODO: move to different directory? Not technically a template
?>

<body>

<div class = "container">

    <div class="header">
        <h1 class="text-center"><?php echo $title ?></h1>
    </div>


    <?php // TODO: re-work encoding-level-form for last copy state checker: ?>
    <div class="well">
        <form class="form-vertical" id="lasty-copy-state-form">
            <ul class="nav nav-tabs">
                <li class="active">
                    <a data-toggle="tab" href="#list-text-pane" id="list-text-tab">
                        Text
                    </a>
                </li>
                <li>
                    <a data-toggle="tab" href="#file-select-pane" id="file-select-tab">
                        Upload File
                    </a>
                </li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade in active" id="list-text-pane">
                    <div class="form-group" id="list-text-group">
                        <label for="list-text-input">
                            Enter each OCLC number separated by a new line:
                        </label>
                        <textarea class="form-control list-text-area" rows="5" required id="list-text-input"
                                  placeholder="Put each OCLC number on its own line"></textarea>
                    </div>
                </div>
                <div class="tab-pane fade" id="file-select-pane">
                    <label for="file-select-input">
                        Select a .txt file with a list of OCLC numbers (each on its own line):
                    </label>
                    <div class="input-group form-group" id="file-select-group">
                        <label class="input-group-btn">
                            <span class="btn btn-default" id="file-select-btn">
                                Choose File
                                <input class="form-control-file" type="file" required disabled
                                       id="file-select-input" name="file-select-input" placeholder=""/>
                            </span>
                        </label>
                        <input class="form-control form-control-file" type="text" readonly
                               id="file-select-text" placeholder="Select a file." >
                    </div>
                </div>
            </div>

            <hr>

            <input class="btn btn-primary" type="submit" disabled
                   id="file-select-submit" name="file-select-submit" value="Submit">
        </form>

    </div>

    <!--TODO: uncomment below once old form is replaced-->
    <div id="output"></div>

    <!--<div class = "well">
        <?php /*// TODO: remove after merging w/ encoding level form */?>
        <div id = "input" class = "col-sm-12">
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
                <h2>Entries Listed as 'At <?php /*echo $libraryName; */?>'</h2>
                <ol id='atLibrary'></ol>
            </div>
        </div>
    </div>-->
</div>
</body>
