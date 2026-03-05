<?php

class shopMigratePluginOzonTypeMapper
{
    private $repository;
    private $settings;
    private $type_model;
    private $map_model;
    private $map = array();
    private $paths = array();

    public function __construct(shopMigratePluginOzonSnapshotRepository $repository, shopMigratePluginOzonSettings $settings)
    {
        $this->repository = $repository;
        $this->settings = $settings;
        $this->type_model = new shopTypeModel();
        $this->map_model = $repository->getTypeMapModel();
    }

    public function warmup($snapshot_id)
    {
        $this->map = $this->map_model->getMap($snapshot_id);
        $this->paths = $this->repository->getCategoriesModel()->getPathMap($snapshot_id);
    }

    public function resolve($snapshot_id, $description_category_id, $ozon_type_id)
    {
        $key = sprintf('%d:%d', $description_category_id, $ozon_type_id);
        $mode = $this->settings->getOperationMode();
        if ($mode === shopMigratePluginOzonSettings::MODE_MANUAL && !empty($this->map[$key]) && !empty($this->map[$key]['shop_type_id'])) {
            return (int) $this->map[$key]['shop_type_id'];
        }

        if (!empty($this->map[$key]) && $this->map[$key]['mode'] === shopMigratePluginOzonSettings::MODE_AUTO && !empty($this->map[$key]['shop_type_id'])) {
            return (int) $this->map[$key]['shop_type_id'];
        }

        $type = $this->ensureTypeExists($description_category_id);
        $this->map_model->saveAuto($snapshot_id, $description_category_id, $ozon_type_id, array(
            'mode'           => shopMigratePluginOzonSettings::MODE_AUTO,
            'shop_type_id'   => $type['id'],
            'shop_type_name' => $type['name'],
        ));
        $this->map[$key] = array(
            'shop_type_id' => $type['id'],
            'mode'         => shopMigratePluginOzonSettings::MODE_AUTO,
        );

        return (int) $type['id'];
    }

    private function ensureTypeExists($description_category_id)
    {
        $name = ifset($this->paths[$description_category_id], 'Ozon '.$description_category_id);
        $existing = $this->type_model->getByField('name', $name);
        if ($existing) {
            return $existing;
        }
        $sort = (int) $this->type_model->select('MAX(sort) sort')->fetchField('sort');
        $data = array(
            'name' => $name,
            'icon' => '',
            'sort' => $sort + 1,
        );
        $id = $this->type_model->insert($data);
        return $this->type_model->getById($id);
    }
}

