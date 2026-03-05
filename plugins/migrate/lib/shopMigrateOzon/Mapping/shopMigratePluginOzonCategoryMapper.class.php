<?php

class shopMigratePluginOzonCategoryMapper
{
    private $repository;
    private $settings;
    private $category_model;
    private $map_model;
    private $map = array();
    private $paths = array();
    private $category_tree_checked = false;

    public function __construct(shopMigratePluginOzonSnapshotRepository $repository, shopMigratePluginOzonSettings $settings)
    {
        $this->repository = $repository;
        $this->settings = $settings;
        $this->category_model = new shopCategoryModel();
        $this->map_model = $repository->getCategoryMapModel();
    }

    public function warmup($snapshot_id)
    {
        $this->map = $this->map_model->getMap($snapshot_id);
        $this->paths = $this->repository->getCategoriesModel()->getPathMap($snapshot_id);
    }

    public function resolve($snapshot_id, $description_category_id)
    {
        $mode = $this->settings->getOperationMode();
        if ($mode === shopMigratePluginOzonSettings::MODE_MANUAL && isset($this->map[$description_category_id])) {
            $option = $this->map[$description_category_id];
            if (isset($option['action']) && $option['action'] === 'skip') {
                return null;
            }
            if (!empty($option['shop_category_id'])) {
                return (int) $option['shop_category_id'];
            }
        }

        if (!empty($this->map[$description_category_id]['shop_category_id']) && $this->map[$description_category_id]['mode'] === shopMigratePluginOzonSettings::MODE_AUTO) {
            return (int) $this->map[$description_category_id]['shop_category_id'];
        }

        $category_id = $this->ensureCategoryExists($description_category_id);
        if ($category_id) {
            $this->map_model->saveAuto($snapshot_id, $description_category_id, array(
                'mode'             => shopMigratePluginOzonSettings::MODE_AUTO,
                'shop_category_id' => $category_id,
                'action'           => 'auto',
            ));
            $this->map[$description_category_id] = array(
                'shop_category_id' => $category_id,
                'mode'             => shopMigratePluginOzonSettings::MODE_AUTO,
            );
        }

        return $category_id;
    }

    private function ensureCategoryExists($description_category_id)
    {
        $this->ensureCategoryTreeIntegrity();
        $path = ifset($this->paths[$description_category_id]);
        if (!$path) {
            return null;
        }
        $segments = array_filter(array_map('trim', explode('/', str_replace('\\', '/', $path))));
        $parent_id = 0;
        foreach ($segments as $segment) {
            $existing = $this->category_model->getByField(array(
                'parent_id' => $parent_id,
                'name'      => $segment,
            ));
            if ($existing) {
                $parent_id = $existing['id'];
                continue;
            }
            $url = $this->generateUrl($segment, $parent_id);
            $now = date('Y-m-d H:i:s');
            $new_id = $this->category_model->add(array(
                'parent_id'       => $parent_id,
                'name'            => $segment,
                'url'             => $url,
                'type'            => shopCategoryModel::TYPE_STATIC,
                'status'          => 1,
                'create_datetime' => $now,
                'edit_datetime'   => $now,
            ), $parent_id ?: null);
            $parent_id = $new_id;
        }

        return $parent_id ?: null;
    }

    private function generateUrl($name, $parent_id)
    {
        $base = strtolower(waLocale::transliterate($name));
        $base = preg_replace('/[^a-z0-9\-]+/', '-', $base);
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'category';
        }
        $url = $base;
        $suffix = 1;
        while ($this->category_model->getByField(array(
            'parent_id' => $parent_id,
            'url'       => $url,
        ))) {
            $url = $base.'-'.$suffix++;
        }
        return $url;
    }

    private function ensureCategoryTreeIntegrity()
    {
        if ($this->category_tree_checked) {
            return;
        }
        $table = $this->category_model->getTableName();
        $has_broken_rows = $this->category_model->query("SELECT 1 FROM {$table} WHERE left_key IS NULL OR right_key IS NULL LIMIT 1")->fetchAssoc();
        if ($has_broken_rows) {
            $this->category_model->repair();
        }
        $this->category_tree_checked = true;
    }
}
