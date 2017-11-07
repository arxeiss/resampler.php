<?php

spl_autoload_register(function ($className) {

    $prefix = 'Resampler\\';
    if (strpos($className, $prefix) !== 0) {
        return;
    }

    $file = substr($className, strlen($prefix));
    $file = __DIR__.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR
        .str_replace('\\', DIRECTORY_SEPARATOR, $file).'.php';
    
    if (!is_readable($file)) {
        return;
    }

    require $file;
});
