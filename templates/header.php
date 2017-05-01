<?php
/**
 * Template file for page headers
 */
// TODO: make generic

?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?php echo $title; ?></title>

    <?php // stylesheet includes Bootstrap + Bootswatch theme ?>
    <link rel="stylesheet" href="res/css/index.css">

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.2/jquery.min.js"></script>
    <script src="http://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>

    <script src="res/js/index.js"></script>
    <?php // TODO: remove once index-new.js is renamed to index.js ?>
    <script src="res/js/index-new.js"></script>
</head>