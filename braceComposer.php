<?php

$contents = file_get_contents(__DIR__ . "/vendor/composer/autoload_static.php");

$replaced = preg_replace("/(__DIR__ \.[^,]*)/", "($0)", $contents);

file_put_contents(__DIR__ . "/vendor/composer/autoload_static.php", $replaced);
