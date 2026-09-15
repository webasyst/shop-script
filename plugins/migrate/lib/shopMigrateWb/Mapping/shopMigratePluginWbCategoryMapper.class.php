<?php

class shopMigratePluginWbCategoryMapper
{
    const MODE_AUTO = 'auto';
    const MODE_MANUAL = 'manual';
    const ENTITY_PARENT = 'parent';
    const ENTITY_SUBJECT = 'subject';

    private $repository;
    private $category_model;
    private $map_model;
    private $map = array();
    private $snapshot_id = 0;

    public function __construct(shopMigratePluginWbSnapshotRepository $repository)
    {
        $this->repository = $repository;
        $this->category_model = new shopCategoryModel();
        $this->map_model = $repository->getCategoryMapModel();
    }

    public function warmup($snapshot_id)
    {
        $snapshot_id = $this->requirePositiveId($snapshot_id, _wp('Invalid Wildberries snapshot ID.'));
        $this->map = array();
        foreach ((array) $this->map_model->getMap($snapshot_id) as $row) {
            if (!is_array($row) || empty($row['entity_type']) || empty($row['wb_id'])) {
                continue;
            }
            $this->map[$this->getMapKey($row['entity_type'], $row['wb_id'])] = $row;
        }
        $this->snapshot_id = $snapshot_id;
    }

    /**
     * Resolves the leaf Shop-Script category for a WB parent/subject pair.
     */
    public function resolve($snapshot_id, $parent_id, $subject_id, $mode = self::MODE_AUTO)
    {
        $snapshot_id = $this->requirePositiveId($snapshot_id, _wp('Invalid Wildberries snapshot ID.'));
        $parent_id = $this->requirePositiveId($parent_id, _wp('Invalid Wildberries parent category ID.'));
        $subject_id = $this->requirePositiveId($subject_id, _wp('Invalid Wildberries subject ID.'));
        $this->ensureWarm($snapshot_id);
        $mode = $this->normalizeMode($mode);

        $source = $this->getSourcePair($snapshot_id, $parent_id, $subject_id);
        $shop_parent_id = $this->resolveEntity(
            $snapshot_id,
            self::ENTITY_PARENT,
            $parent_id,
            0,
            $source['parent_name'],
            0,
            $mode
        );
        if (!$shop_parent_id) {
            return null;
        }

        return $this->resolveEntity(
            $snapshot_id,
            self::ENTITY_SUBJECT,
            $subject_id,
            $parent_id,
            $source['subject_name'],
            $shop_parent_id,
            $mode
        );
    }

    /**
     * Explicitly creates both hierarchy levels and stores manual "create" mappings.
     */
    public function createForSubject($snapshot_id, $parent_id, $subject_id, $shop_parent_id = null)
    {
        $snapshot_id = $this->requirePositiveId($snapshot_id, _wp('Invalid Wildberries snapshot ID.'));
        $parent_id = $this->requirePositiveId($parent_id, _wp('Invalid Wildberries parent category ID.'));
        $subject_id = $this->requirePositiveId($subject_id, _wp('Invalid Wildberries subject ID.'));
        $this->ensureWarm($snapshot_id);
        $source = $this->getSourcePair($snapshot_id, $parent_id, $subject_id);

        if ($shop_parent_id === null) {
            $shop_parent_id = $this->createCategory($source['parent_name'], 0);
            $this->saveMapping($snapshot_id, self::ENTITY_PARENT, $parent_id, 0, array(
                'mode'             => self::MODE_MANUAL,
                'action'           => 'create',
                'shop_category_id' => $shop_parent_id,
            ));
        } else {
            $shop_parent_id = $this->requireStaticCategory($shop_parent_id);
        }

        $shop_subject_id = $this->createCategory($source['subject_name'], $shop_parent_id);
        $this->saveMapping($snapshot_id, self::ENTITY_SUBJECT, $subject_id, $parent_id, array(
            'mode'             => self::MODE_MANUAL,
            'action'           => 'create',
            'shop_category_id' => $shop_subject_id,
        ));

        return $shop_subject_id;
    }

    /**
     * Explicitly creates a new static category, without reusing an equal name.
     */
    public function createCategory($name, $parent_id = 0)
    {
        $name = $this->cleanName($name);
        if ($name === '') {
            throw new waException(_wp('Enter a Shop-Script category name.'));
        }

        $parent_id = max(0, (int) $parent_id);
        if ($parent_id > 0) {
            $this->requireStaticCategory($parent_id);
        }

        $url = trim((string) shopHelper::transliterate($name));
        if ($url === '') {
            $url = 'category';
        }
        $url = $this->category_model->suggestUniqueUrl($url, null, $parent_id);
        $now = date('Y-m-d H:i:s');
        $result = $this->category_model->add(array(
            'parent_id'       => $parent_id,
            'name'            => $name,
            'url'             => $url,
            'type'            => shopCategoryModel::TYPE_STATIC,
            'status'          => 1,
            'create_datetime' => $now,
            'edit_datetime'   => $now,
        ), $parent_id ?: null);

        if (is_array($result)) {
            throw new waException(_wp('Unable to create a Shop-Script category.'));
        }
        $category_id = (int) $result;
        if ($category_id <= 0) {
            throw new waException(_wp('Unable to create a Shop-Script category.'));
        }

        $category = $this->category_model->getById($category_id);
        if ($category) {
            wa()->event('category_save', $category);
        }
        return $category_id;
    }

    private function resolveEntity($snapshot_id, $entity_type, $wb_id, $wb_parent_id, $name, $shop_parent_id, $mode)
    {
        $key = $this->getMapKey($entity_type, $wb_id);
        $mapping = isset($this->map[$key]) ? $this->map[$key] : array();

        if ($mode === self::MODE_MANUAL && $this->isManualMapping($mapping)) {
            $action = $this->getAction($mapping);
            if ($action === 'skip') {
                return null;
            }
            if (!empty($mapping['shop_category_id'])) {
                return $this->requireStaticCategory($mapping['shop_category_id']);
            }
            if ($action === 'create') {
                $category_id = $this->createCategory($name, $shop_parent_id);
                $this->saveMapping($snapshot_id, $entity_type, $wb_id, $wb_parent_id, array(
                    'mode'             => self::MODE_MANUAL,
                    'action'           => 'create',
                    'shop_category_id' => $category_id,
                ));
                return $category_id;
            }
            if ($action !== 'auto') {
                throw new waException(_wp('Select a Shop-Script category for the manual Wildberries mapping.'));
            }
        }

        if ($this->isAutoMapping($mapping) && !empty($mapping['shop_category_id'])) {
            $category = $this->category_model->getById((int) $mapping['shop_category_id']);
            if ($category && (int) $category['type'] === shopCategoryModel::TYPE_STATIC) {
                return (int) $category['id'];
            }
        }

        $category = $this->findExactCategory($name, $shop_parent_id);
        $category_id = $category ? (int) $category['id'] : $this->createCategory($name, $shop_parent_id);
        $this->saveMapping($snapshot_id, $entity_type, $wb_id, $wb_parent_id, array(
            'mode'             => self::MODE_AUTO,
            'action'           => 'auto',
            'shop_category_id' => $category_id,
        ));
        return $category_id;
    }

    private function getSourcePair($snapshot_id, $parent_id, $subject_id)
    {
        $subject = $this->repository->getSubjectsModel()->getByField(array(
            'snapshot_id' => (int) $snapshot_id,
            'subject_id'  => (int) $subject_id,
        ));
        if (!$subject) {
            throw new waException(_wp('Wildberries subject was not found in the current snapshot.'));
        }
        if ((int) $subject['parent_id'] !== (int) $parent_id) {
            throw new waException(_wp('Wildberries subject does not belong to the selected parent category.'));
        }

        $parent_name = $this->cleanName(ifset($subject['parent_name'], ''));
        if (method_exists($this->repository, 'getParentsModel')) {
            $parent = $this->repository->getParentsModel()->getByField(array(
                'snapshot_id' => (int) $snapshot_id,
                'parent_id'   => (int) $parent_id,
            ));
            if ($parent && $this->cleanName(ifset($parent['name'], '')) !== '') {
                $parent_name = $this->cleanName($parent['name']);
            }
        }
        $subject_name = $this->cleanName(ifset($subject['name'], ''));
        if ($parent_name === '' || $subject_name === '') {
            throw new waException(_wp('Wildberries category names are missing from the current snapshot.'));
        }

        return array(
            'parent_name'  => $parent_name,
            'subject_name' => $subject_name,
        );
    }

    private function findExactCategory($name, $parent_id)
    {
        $normalized = $this->normalizeName($name);
        $rows = $this->category_model->getByField(array(
            'parent_id' => (int) $parent_id,
            'type'      => shopCategoryModel::TYPE_STATIC,
        ), true);
        foreach ((array) $rows as $category) {
            if ($this->normalizeName(ifset($category['name'], '')) === $normalized) {
                return $category;
            }
        }
        return null;
    }

    private function requireStaticCategory($category_id)
    {
        $category = $this->category_model->getById((int) $category_id);
        if (!$category) {
            throw new waException(_wp('The mapped Shop-Script category no longer exists.'));
        }
        if ((int) $category['type'] !== shopCategoryModel::TYPE_STATIC) {
            throw new waException(_wp('Wildberries products can be mapped only to a static Shop-Script category.'));
        }
        return (int) $category['id'];
    }

    private function saveMapping($snapshot_id, $entity_type, $wb_id, $parent_id, array $data)
    {
        $this->map_model->saveMapping($snapshot_id, $entity_type, $wb_id, $parent_id, $data);
        $this->map[$this->getMapKey($entity_type, $wb_id)] = array_merge(array(
            'snapshot_id' => (int) $snapshot_id,
            'entity_type' => (string) $entity_type,
            'wb_id'       => (int) $wb_id,
            'parent_id'   => (int) $parent_id,
        ), $data);
    }

    private function getMapKey($entity_type, $wb_id)
    {
        return strtolower(trim((string) $entity_type)).':'.(int) $wb_id;
    }

    private function ensureWarm($snapshot_id)
    {
        if ((int) $this->snapshot_id !== (int) $snapshot_id) {
            $this->warmup($snapshot_id);
        }
    }

    private function isManualMapping(array $mapping)
    {
        return ifset($mapping['mode'], '') === self::MODE_MANUAL;
    }

    private function isAutoMapping(array $mapping)
    {
        return ifset($mapping['mode'], '') === self::MODE_AUTO;
    }

    private function getAction(array $mapping)
    {
        $action = strtolower(trim((string) ifset($mapping['action'], 'auto')));
        return $action === '' ? 'auto' : $action;
    }

    private function normalizeMode($mode)
    {
        return strtolower(trim((string) $mode)) === self::MODE_MANUAL ? self::MODE_MANUAL : self::MODE_AUTO;
    }

    private function requirePositiveId($value, $message)
    {
        $value = (int) $value;
        if ($value <= 0) {
            throw new waException($message);
        }
        return $value;
    }

    private function cleanName($name)
    {
        $name = trim((string) $name);
        $clean = preg_replace('/\s+/u', ' ', $name);
        return $clean === null ? $name : $clean;
    }

    private function normalizeName($name)
    {
        $name = $this->cleanName($name);
        return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    }
}
