<?php

require_once __DIR__.'/constants.php';

spl_autoload_register(function (string $class_name) {
    require_once __DIR__."/src/$class_name.php";
});
