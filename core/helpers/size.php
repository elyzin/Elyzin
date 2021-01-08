<?php

// Return human readable size
return function ($size, $precision = 2) {
    $i = floor(log($size, 1024));
    return round($size / pow(1024, $i), $precision) . ' ' . ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'][$i];
};