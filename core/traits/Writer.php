<?php

namespace Elyzin\Core\Traits;

trait Writer
{
    // Securely create / write file
    public static function penSafe($dataToSave, $filename = '', bool $append = false)
    {
        self::makeDir(dirname($filename)); // Ensure directory exists else create
        if ($append && file_exists($filename)) {
            $pointer = 'a';
        } else {
            $pointer = 'w';
        }
        if ($fp = fopen($filename, $pointer)) {
            $startTime = microtime(true);
            do {
                $canWrite = flock($fp, LOCK_EX);
                // If lock not obtained sleep for 0 - 100 milliseconds, to avoid collision and CPU load
                if (!$canWrite) {
                    usleep(round(rand(0, 100) * 1000));
                }

            } while ((!$canWrite) and ((microtime(true) - $startTime) < 5));

            //file was locked so now we can store information
            if ($canWrite) {
                fwrite($fp, $dataToSave);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }
    }

    // Create recursive directory with permission
    public static function makeDir($path = '', $mode = 0777, $rec = true)
    {
        if (!is_dir($path)) {
            mkdir($path, $mode, $rec);
        }

        return (is_dir($path)) ? true : false;
    }

    // Delete recursive directory with all files in it
    public function wipeDir($dirname = '')
    {
        array_map('unlink', glob("$dirname/*.*"));
        rmdir($dirname);
    }
}