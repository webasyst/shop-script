<?php

class shopDemoDataTable extends waModel
{
    public function __construct($table_name, $type = null, $writable = false)
    {
        $this->table = $table_name;
        parent::__construct($type, $writable);
    }
}
