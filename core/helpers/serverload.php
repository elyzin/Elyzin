<?php

/**
 * Displays the variable (string or array) in a formatted way
 *
 * @param void
 * @return int
 */
return function () {
    $load = '';
    if (stristr(PHP_OS, 'win')) {
        $cmd = 'wmic cpu get loadpercentage /all';
        @exec($cmd, $output);
        if ($output) {
            foreach($output as $line) {
                if ($line && preg_match('/^[0-9]+$/', $line)) {
                    $load = $line;
                    break;
                }
            }
        }

    } else {
        $sys_load = sys_getloadavg();
        $load = $sys_load[0];
    }
    return $load;
};
