<?php
/**
 * Markup for config form
 */
// Include constants (for state select form)
include_once $_SERVER['DOCUMENT_ROOT'] . '/lastCopyStateChecker/config/constants.php';
?>

    <div class="well hidden" id="config-form-container">
        <form class="form-vertical" role="form" id="config-form">
            <div class="form-group">
                <label for="state-select">State:</label>
                <select class="form-control" required id="state-select" name="state">
                    <option value="" disabled selected>Select a state</option>
                <?php
                foreach(STATES as $key => $value) {
                    echo "<option value='$key'>$value</option>";
                }
                ?>
                </select>
            </div>
            <div class="form-group">
                <label for="institution-input">Institution name:</label>
                <input class="form-control" type="text" required
                       id="institution-input" name="institution" placeholder="Name of the library/institution">
            </div>
            <div class="form-group">
                <label for="wskey-input">WorldCat API Key:</label>
                <input class="form-control" type="text" required
                       id="wskey-input" name="wskey" placeholder="WorldCat API key">
            </div>
            <div class="form-group">
                <br>
                <input type="hidden" value="2" name="step">
                <input class="btn btn-primary" type="submit"  disabled
                       id="config-form-submit" value="Submit">
            </div>
        </form>
    </div>