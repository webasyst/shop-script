<?php

class shopMigratePluginOzonCategoriesModel extends shopMigratePluginOzonModel
{
    protected $table = 'shop_migrate_ozon_categories';

    public function addBatch($snapshot_id, array $categories)
    {
        if (!$categories) {
            return;
        }
        $rows = array();
        foreach ($categories as $category) {
            $rows[] = array(
                'snapshot_id'             => (int) $snapshot_id,
                'description_category_id' => (int) ifset($category['description_category_id']),
                'parent_id'               => isset($category['parent_id']) ? (int) $category['parent_id'] : null,
                'name'                    => (string) ifset($category['name'], ''),
                'path'                    => (string) ifset($category['path'], ''),
                'level'                   => (int) ifset($category['level'], 0),
            );
        }

        $this->multipleInsert($rows, array('parent_id', 'name', 'path', 'level'));
    }

    public function getAllBySnapshot($snapshot_id)
    {
        return $this->select('*')
            ->where('snapshot_id = ?', (int) $snapshot_id)
            ->order('path ASC')
            ->fetchAll('description_category_id');
    }

    public function getPathMap($snapshot_id)
    {
        return $this->select('description_category_id, path')
            ->where('snapshot_id = ?', (int) $snapshot_id)
            ->fetchAll('description_category_id', true);
    }
}
