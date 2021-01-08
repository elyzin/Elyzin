<?php

// Return url or path chunk ensuring directory separator at both end
return function (string $pathpart = '', $file = false) {
    if (empty($pathpart)) {
        return '/';
    }
    $file = $file ? '' : '/';
    return str_replace('\\', '/', trim($pathpart, '/\\')) . $file;
};
