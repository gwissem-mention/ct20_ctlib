<?php

spl_autoload_register(function($class) {
    if (0 === strpos($class, 'CTLib\\')) {
        $file = __DIR__ . '/ctlib/src/' . str_replace('\\', '/', $class) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
});
