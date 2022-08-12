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
}
