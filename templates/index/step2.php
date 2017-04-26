<?php
/**
 * HTML for configuration step 2
 */
// TODO: move to different directory? Not technically a template
?>

<body>
<div class="container">

    <div class="header">
        <h1 class="text-center"><?php echo $title ?></h1>
    </div>

    <div class="well">
        <?php // TODO: re-style, make ids more helpful ?>
        <form class="text-center" action="index.php" method="post" id="continue-form">
            <h2>Your Library's Information Has Been Saved.</h2>
            <input type="submit" value="Continue" class="btn btn-primary">
        </form>
    </div>
</div>
</body>