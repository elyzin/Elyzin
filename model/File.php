<?php

namespace Elyzin\Model;

class File
{
    use \Elyzin\Core\Traits\Writer;

    /**
     * Returns human readable file size
     *
     * @param integer $size
     * @param integer $precision
     * @return string
     */
    public function size(int $size, $precision = 2)
    {
        $i = floor(log($size, 1024));
        return round($size / pow(1024, $i), $precision) . ' ' . ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'][$i];
    }

    /**
     * Reads a file from given path and returns associative array
     *
     * @param string $filepath
     * @return array
     */
    public static function read(string $filepath)
    {
        $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

        switch ($ext) {
            case 'ini':
                return parse_ini_file($filepath, true, INI_SCANNER_TYPED);
                break;

            case 'php':
                return require $filepath;
                break;

            case 'json':
                return json_decode(\file_get_contents($filepath), true);
                break;

            default:
                throw new \Exception("Unsupported file extension \"{$ext}\".");
                break;
        }
    }

    /**
     * Writes array values to the file in given path detecting format as per file extension
     *
     * @param array $content
     * @param string $filepath
     * @return void
     */
    public static function write(array $content, string $filepath, bool $append = false)
    {
        $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'ini':
                $content = self::iniMake($content);
                break;

            case 'php':
                $content = self::arrMake($content);
                break;

            case 'json':
                $content = json_encode($content);
                break;

            default:
                throw new \Exception("Unsupported file extension \"{$ext}\".");
                break;
        }
        self::penSafe($content, $filepath, $append);
    }

    /**
     * Creates php file of given array for later retrieval
     *
     * @param array $array
     * @return string
     */
    public static function arrMake(array $array)
    {
        $content = var_export($array, true);

        $date = new \DateTime();
        $date = $date->format('d-m-Y, H:i:s') . ' (' . date_default_timezone_get() . ')';
        $content = <<<EOD
<?php
//auto generated on $date

return $content;
EOD;
        return $content;
    }

    /**
     * Creates ini file content from associative array
     *
     * @param array $array
     * @return string
     */
    public static function iniMake(array $array)
    {
        $out = $sectionless = '';
        foreach ($array as $rootkey => $rootvalue) {
            if (is_array($rootvalue)) {
                // find out if the root-level item is an indexed or associative array
                $indexed_root = array_keys($rootvalue) == range(0, count($rootvalue) - 1);
                // associative arrays at the root level have a section heading
                if (!$indexed_root) {
                    $out .= PHP_EOL . "[$rootkey]" . PHP_EOL;
                }

                // loop through items under a section heading
                foreach ($rootvalue as $key => $value) {
                    if (is_array($value)) {
                        // indexed arrays under a section heading will have their key omitted
                        $indexed_item = array_keys($value) == range(0, count($value) - 1);
                        foreach ($value as $subkey => $subvalue) {
                            // omit subkey for indexed arrays
                            if ($indexed_item) {
                                $subkey = "";
                            }

                            // add this line under the section heading
                            $out .= "{$key}[$subkey] = " . self::iniQuote($subvalue) . PHP_EOL;
                        }
                    } else {
                        if ($indexed_root) {
                            // root level indexed array becomes sectionless
                            $sectionless .= "{$rootkey}[] = " . self::iniQuote($value) . PHP_EOL;
                        } else {
                            // plain values within root level sections
                            $out .= "$key = " . self::iniQuote($value) . PHP_EOL;
                        }
                    }
                }
            } else {
                // root level sectionless values
                $sectionless .= "$rootkey = " . self::iniQuote($rootvalue) . PHP_EOL;
            }
        }
        return $sectionless . $out;
    }

    /**
     * Checks the input value and if not numeric or boolean then puts quotes around
     *
     * @param mixed $val
     * @return string
     */
    private static function iniQuote($val)
    {
        //return (is_numeric($val) || is_bool($val)) ? $val : "\"" . $val . "\"";
        if(is_bool($val)) return $val ? 'true' : 'false';
        if(is_numeric($val)) return $val;
        return "\"" . $val . "\"";
    }
}
