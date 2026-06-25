<?php

class shopInstaller
{
    /**
     * @param string|array[] string $table
     */
    public function createTable($table)
    {
        $tables = array_map('strval', (array)$table);
        if (empty($tables)) {
            return;
        }

        $db_path = wa()->getAppPath('lib/config/db.php', 'shop');

        if (!file_exists($db_path)) {
            return;
        }

        $db = include($db_path);

        $db_partial = array();
        foreach ($tables as $table) {
            if (isset($db[$table])) {
                $db_partial[$table] = $db[$table];
            }
        }

        if (empty($db_partial)) {
            return;
        }

        $m = new waModel();
        $m->createSchema($db_partial);
    }

    // Should really only be used to add single columns.
    // Adds them in multiple queries one by one because
    // of the limitations of waModel / waDbAdapter :(
    // Does nothing when columns already exist (does not check if schema matches).
    // Does nothing when table or column is missing from db.php.
    public function addColumns($table, $columns)
    {
        $db_path = wa()->getAppPath('lib/config/db.php', 'shop');
        if (!file_exists($db_path)) {
            return;
        }

        $db = include($db_path);
        if (!isset($db[$table])) {
            return;
        }

        $m = new waModel();
        $columns = (array) $columns;

        $prev_column = null;
        foreach ($db[$table] as $column => $column_schema) {
            if (in_array($column, $columns)) {
                $m->addColumn($column, $db, $prev_column, $table);
            }
            $prev_column = $column;
        }
    }

    /**
     * Ensure .htaccess and thumb.php files are in place inside wa-data/public/shop
     * 
     * @param string|null $type products|promos|categories|all; null means all
     * @return bool true if all files exist or were successfully created; false if unable to write at least one of the files.
     */
    public function ensureThumbPhp(?string $type=null): bool
    {
        $app_id = 'shop';
        $source_path = wa()->getAppPath('lib/config/data/', $app_id);
        $php_file_contents = '<?php
$file = dirname(__FILE__)."/../../../../"."/wa-apps/'.$app_id.'/lib/config/data/%s";
if (file_exists($file)) {
    include($file);
} else {
    header("HTTP/1.0 404 Not Found");
}';
        $result = true;

        $copyHtaccess = function($target_path) use ($source_path) {
            $target = $target_path.'.htaccess';
            if (!file_exists($target)) {
                try {
                    waFiles::copy($source_path.'.htaccess', $target);
                } catch (Throwable $e) {
                    return false;
                }
            }
            return true;
        };

        $writePhpScript = function($target_path, $source_filename) use ($php_file_contents) {
            $target = $target_path.'thumb.php';
            if (!file_exists($target)) {
                $written_bytes = waFiles::write($target, sprintf($php_file_contents, $source_filename));
                return $written_bytes > 0;
            }
            return true;
        };

        if (!$type || $type === 'products' || $type === 'all') {
            // generate product thumb via php on demand
            $target_path = wa()->getDataPath('products/', true, $app_id);
            $result = $writePhpScript($target_path, 'thumb.php') && $result;
            $result = $copyHtaccess($target_path) && $result;
        }

        if (!$type || $type === 'promos' || $type === 'all') {
            $target_path = wa()->getDataPath('promos/', true, $app_id);
            $result = $writePhpScript($target_path, 'promos.thumb.php') && $result;
            $result = $copyHtaccess($target_path) && $result;
        }

        if (!$type || $type === 'categories' || $type === 'all') {
            $target_path = wa()->getDataPath('categories/', true, $app_id);
            $result = $writePhpScript($target_path, 'categories.thumb.php') && $result;
            $result = $copyHtaccess($target_path) && $result;
        }

        return $result;
    }
}
