<?php

class shopMigratePluginBackendOzonCleanupController extends waJsonController
{
    public function execute()
    {
        if (!wa()->getConfig()->isDebug()) {
            throw new waException('Access denied', 403);
        }
        $tables = waRequest::post('tables', array(), waRequest::TYPE_ARRAY);
        $tables = array_map('strval', (array) $tables);
        $tables = array_map('trim', $tables);
        $tables = array_unique($tables);

        $definitions = shopMigratePluginOzonHelper::getTablesMeta();
        $allowed = array_keys($definitions);
        $targets = array_values(array_intersect($allowed, $tables));

        if (!$targets) {
            $this->response = array(
                'cleared' => 0,
            );
            return;
        }

        $model = new waModel();
        foreach ($targets as $table) {
            $model->exec('TRUNCATE TABLE `'.$table.'`');
        }

        $this->response = array(
            'cleared' => count($targets),
        );
    }
}
