<?php
require(__DIR__ . "/../../partials/nav.php");
?>
<h1>Home</h1>
<?php

if (is_logged_in(true)) {
    echo "Welcome home, " . get_username();
    //comment this out if you don't want to see the session variables
    //echo "<pre>" . var_export($_SESSION, true) . "</pre>";
}
?>
<?php
require(__DIR__ . "/../../public_html/Project/shooter.html");
?>
<?php
require(__DIR__ . "/../../partials/flash.php");
?>