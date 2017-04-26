<?php
/**
 * HTML for configuration step
 */
// TODO: move to different directory? Not technically a template
?>

<body>
<div class="container">
    <div class="row">
        <div class="col-sm-6 col-sm-offset-3">
            <?php // TODO: form fields aren't required, can just click submit ?>
            <form action="index.php" method="post"  role="form" id="striped_Box">
                <div class="col-sm-12">
                    <label>Please Enter State Name:</label>
                    <?php
                    echo "<select name = \"state\" class=\"form-control\">";

                    foreach(array_keys(STATES) as $state)
                    {
                        echo "<option value = \"$state\" >";
                        echo STATES[$state];
                        echo "</option>";
                    }
                    echo "</select>";
                    ?>
                </div>
                <div class="col-sm-12">
                    <label>Please Enter the Institution's Name:</label>
                    <input type="text" name="institution" class="form-control">
                </div>
                <div class="col-sm-12">
                    <label>Please Enter the Institution's WorldCat API Key:</label>
                    <input type="text" name="wskey" class="form-control">
                </div>
                <div class="col-sm-12">
                    <br/>
                    <input type="hidden" value="2" name="step">
                    <input type="submit" value="Submit" class="btn btn-default">
                </div>
            </form>
        </div>
    </div>
</div>

</body>