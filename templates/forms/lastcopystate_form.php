<?php
/**
 * Markup for main form to upload a list of OCLC numbers
 */
?>


<div class="well hidden" id="last-copy-state-form-container">
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
               id="last-copy-state-submit" name="last-copy-state-submit" value="Submit">
    </form>

</div>
