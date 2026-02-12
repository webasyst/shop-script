<?php

/**
 * Creates Ozon migration tables defined in lib/config/db.php.
 */

$schema_path = dirname(__DIR__).'/config/db.php';
if (!file_exists($schema_path)) {
    return;
}

$schemas = include $schema_path;
if (!is_array($schemas) || !$schemas) {
    return;
}

$model = new waModel();

foreach ($schemas as $table => $definition) {
    if (!is_array($definition)) {
        continue;
    }
    try {
        $model->createSchema(array($table => $definition));
    } catch (waDbException $e) {
        // Table already exists or cannot be created – ignore to keep update idempotent.
    }
}
