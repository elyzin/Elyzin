<?php

namespace Elyzin\Model;

use Config;
use File;

class Dbase extends \Buki\Pdox
{
    private $common_tables = ['session', 'user', 'organization', 'project'];

    public function __construct()
    {
        $this->config = File::read(Config::path('dbconnect', false))[trim(PRT, '/')];
        $this->config['cachedir'] = Config::path('dbcache');
        parent::__construct($this->config);
    }

    /**
     * Table existance checker and auto builder before calling parent's table() function. JOIN not covered in this method's scope.
     */
    public function table($table)
    {
        $exists = (Config::path('dbcache')) . 'existing_tables.php';
        if(!file_exists($exists)){
            File::write($this->list_tables(), $exists);
        }
        $existing_tables = File::read($exists);
        
        if (is_string($table)) {
            if (strpos($table, ',') > 0) {
                $tables = explode(',', $table);
            } else {
                $tables[] = $table;
            }
        } else {
            $tables = $table;
        }

        $flag = false;
        foreach($tables as $index => $table){
            $orig_table = $table = explode(" ", trim($table))[0]; // Preserve original table name in case required for fetching structure
            
            if(!in_array($table, $this->common_tables)){
                if(!isset($_SESSION['project_code'])) {
                    die("Project not defined"); // Throw exception
                } else {
                    $table = $_SESSION['project_code'] . '_' . $table;
                    $tables[$index] = $_SESSION['project_code'] . '_' . trim($tables[$index]); // Manipulate main table name
                }
            }

            $pref_table = $this->prefix . $table;
            if(!in_array($pref_table, $existing_tables)){
                $flag = true;
                $struct = File::read(Config::path('presets') . 'table.' . $orig_table . '.php');
                
                $this->new_table($table, $struct, true);
            }
        }
        // Check if new table is created
        if($flag){
            File::write($this->list_tables(), $exists);
        }

        return parent::table($tables);
    }

    public function new_table(string $table_name, array $column_array = [], bool $mode = false, string $engine = 'InnoDB', string $charset = 'utf8')
    {
        $mode = $mode ? ' IF NOT EXISTS' : ''; // Soft mode
        if (empty($table_name)) {
            throw new \Exception("Table name is unavailable");
        } else if (is_array($table_name)) {
            throw new \Exception("Invalid table name is provided.");
        } else {
            $table_name = $this->prefix . $table_name;
        }
        // Initial values
        $auto_increment = '';
        $colfacts = array();
        $keyfacts = array();
        $setprimary = 1;

        // Process each row entries
        foreach ($column_array as $entry) {

            // Assign keys to key-less declarations
            if (array_keys($entry) === range(0, count($entry) - 1)) {
                $entry = array_combine(array_slice(['column', 'type', 'character', 'null', 'auto', 'default', 'key', 'key_visibility'], 0, count($entry)), $entry);
            }

            $entity = '`' . $entry['column'] . '` ' . $entry['type'];
            $entity .= $entry['type'] == 'text' ? '' : '(' . (int) $entry['character'] . ')';
            $entity .= $entry['null'] ? '' : ' NOT NULL';

            if ($entry['auto']) {
                $entity .= ' auto_increment';
                $auto_increment = ' AUTO_INCREMENT=1';
            } else {
                if (!$entry['null']) {
                    if (isset($entry['default'])) {
                        if (empty($entry['default'])) {
                            $entry['default'] = '0';
                        }

                        $entity .= ' default \'' . $entry['default'] . '\'';
                    }
                } else {
                    $entity .= ' default NULL';
                }
            }

            // Append column values in Column array
            $colfacts[] = $entity;

            // Append Key values in Key array
            if (isset($entry['key']) && ($entry['type'] != 'text')) // Ommited text types being key (CHECK CRITERIA OF KEY)
            {
                if ($entry['key'] == 'p' && (isset($setprimary))) // This is primary key
                {
                    $keyfacts[] = 'PRIMARY KEY  (`' . $entry['column'] . '`)';
                    unset($setprimary); // Destroy primary indicator to accept only first one as primary (if in case :p)
                } else // Other standard key
                {
                    $entry['key_visibility'] = (isset($entry['key_visibility'])) ? ',`visible`' : '';
                    $keyfacts[] = 'KEY `' . $entry['column'] . '` (`' . $entry['column'] . '`' . $entry['key_visibility'] . ')';
                }
            }
        }
        $column_array = array_merge($colfacts, $keyfacts);
        $column_array = implode(', ', $column_array);
        $this->pdo->exec('CREATE TABLE' . $mode . ' `' . $table_name . '` (' . $column_array . ') ENGINE=' . $engine . $auto_increment . ' DEFAULT CHARSET=' . $charset); // Error reporting ???
        $this->queryCount++;
    }

    public function list_tables(string $part = "", bool $exact = false)
    {
        $like = strlen(trim($part)) ? ($exact ? ' LIKE \'' . $this->config['prefix'] . $part . '\'' : ' LIKE \'%' . $part . '%\'') : '';
        $query = $this->pdo->query('SHOW TABLES' . $like);
        $this->queryCount++;
        return str_replace($this->config['prefix'], '', $query->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function has_table($table)
    {
        if (!empty($table) && !empty($this->list_tables($table, true))) {
            return true;
        }
        return false;
    }
}
