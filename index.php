<!DOCTYPE html>
<html lang = "en">
<?php include_once 'templates/header.php' ?>
<body>

<div class="container">

    <?php // Page title set depending on what's currently being displayed ?>
    <h1 class="text-center" id="header-title">Last Copy in State Checker</h1>
    <?php // Container for the currently displayed form ?>
    <div id="form-container">
        <?php
        // Include forms (hidden by default)
        include_once 'templates/forms/config_form.php';
        include_once 'templates/forms/lastcopystate_form.php';
        ?>
    </div>
    <?php // Div used to display output ?>
    <div id="output"></div>

    <?php // Modal to display while loading ?>
    <div class="modal fade" id="loading-dialog" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle id="loader-circle" class="path" cx="50" cy="50" r="20" fill="none" stroke-width="3" stroke-miterlimit="10"/></svg></div>
                    <div class="text-center" id="loading-dialog-message">
                        Loading...
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

</body>
</html>