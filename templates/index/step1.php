<?php
/**
 * HTML for configuration step
 */
// TODO: move to different directory? Not technically a template
?>

<body>
<div class="container">

    <div class="header">
        <h1 class="text-center"><?php echo $title ?></h1>
    </div>

    <div class="well">
        <form class="form-vertical" action="index.php" method="post"  role="form" id="config-form">
            <div class="form-group">
                <label for="state-select">State:</label>
                <select class="form-control" required id="state-select" name="state">
                <?php
                foreach(STATES as $key => $value) {
                    echo "<option value='$key'>$value</option>";
                }
                ?>
                </select>
            </div>
            <?php // TODO: add placeholders ?>
            <div class="form-group">
                <label for="institution-input">Institution name:</label>
                <input class="form-control" type="text" required id="institution-input" name="institution">
            </div>
            <div class="form-group">
                <label for="wskey-input">WorldCat API Key:</label>
                <input class="form-control" type="text" required id="wskey-input" name="wskey">
            </div>
            <div class="form-group">
                <br>
                <input type="hidden" value="2" name="step">
                <input class="btn btn-primary" type="submit" id="config-submit" value="Submit">
            </div>
        </form>
    </div>

</div>

</body>