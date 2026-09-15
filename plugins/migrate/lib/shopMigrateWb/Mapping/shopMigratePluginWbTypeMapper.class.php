<?php

class shopMigratePluginWbTypeMapper
{
    const MODE_AUTO = 'auto';
    const MODE_MANUAL = 'manual';

    private $repository;
    private $type_model;
    private $map_model;
    private $map = array();
    private $snapshot_id = 0;

    public function __construct(shopMigratePluginWbSnapshotRepository $repository)
    {
        $this->repository = $repository;
        $this->type_model = new shopTypeModel();
        $this->map_model = $repository->getTypeMapModel();
    }

    public function warmup($snapshot_id)
    {
        $snapshot_id = $this->requirePositiveId($snapshot_id, _wp('Invalid Wildberries snapshot ID.'));
        $this->map = array();
        foreach ((array) $this->map_model->getMap($snapshot_id) as $row) {
            if (!is_array($row) || empty($row['subject_id'])) {
                continue;
            }
            $this->map[(int) $row['subject_id']] = $row;
        }
        $this->snapshot_id = $snapshot_id;
    }

    public function resolve($snapshot_id, $parent_id, $subject_id, $mode = self::MODE_AUTO)
    {
        $snapshot_id = $this->requirePositiveId($snapshot_id, _wp('Invalid Wildberries snapshot ID.'));
        $parent_id = $this->requirePositiveId($parent_id, _wp('Invalid Wildberries parent category ID.'));
        $subject_id = $this->requirePositiveId($subject_id, _wp('Invalid Wildberries subject ID.'));
        $this->ensureWarm($snapshot_id);

        $mode = $this->normalizeMode($mode);
        $mapping = isset($this->map[$subject_id]) ? $this->map[$subject_id] : array();

        if ($mode === self::MODE_MANUAL && $this->isManualMapping($mapping)) {
            $legacy_action = strtolower(trim((string) ifset($mapping['action'], 'auto')));
            $action = $this->getAction($mapping);
            if ($action === 'skip') {
                return null;
            }
            if ($legacy_action !== 'create' && !empty($mapping['shop_type_id'])) {
                return $this->requireMappedType($mapping['shop_type_id']);
            }
            if ($action !== 'auto') {
                throw new waException(_wp('Select a Shop-Script product type for the manual Wildberries mapping.'));
            }
        }

        if ($this->isAutoMapping($mapping) && !empty($mapping['shop_type_id'])) {
            $type = $this->type_model->getById((int) $mapping['shop_type_id']);
            if ($type) {
                return (int) $type['id'];
            }
        }

        $source = $this->getSourceSubject($snapshot_id, $parent_id, $subject_id);
        $name = $this->buildTypeName($source['parent_name'], $source['name']);
        $type = $this->findExactType($name);
        if (!$type) {
            $type_id = $this->createType($name);
            $type = $this->type_model->getById($type_id);
        }

        $this->saveMapping($snapshot_id, $parent_id, $subject_id, array(
            'mode'           => self::MODE_AUTO,
            'action'         => 'auto',
            'shop_type_id'   => (int) $type['id'],
            'shop_type_name' => (string) $type['name'],
        ));

        return (int) $type['id'];
    }

    /**
     * Resolves legacy manual "create" mappings through the automatic strategy.
     */
    public function createForSubject($snapshot_id, $parent_id, $subject_id)
    {
        return $this->resolve($snapshot_id, $parent_id, $subject_id, self::MODE_AUTO);
    }

    /**
     * Reuses a type with the same normalized name or creates it once.
     */
    public function createType($name)
    {
        $name = $this->cleanName($name);
        if ($name === '') {
            throw new waException(_wp('Enter a Shop-Script product type name.'));
        }

        $type = $this->findExactType($name);
        if ($type) {
            return (int) $type['id'];
        }

        $lock = new shopMigratePluginWbOperationLock($this->type_model);
        $lock_key = 'type_name_'.substr(sha1($this->normalizeName($name)), 0, 32);
        if (!$lock->acquire($lock_key)) {
            throw new waException(_wp('Another product type with this name is being created. Try again in a moment.'));
        }
        try {
            // Recheck after acquiring the name-specific lock: another PHP
            // worker may have created the type between the first lookup and
            // lock acquisition.
            $type = $this->findExactType($name);
            if ($type) {
                return (int) $type['id'];
            }

            $sort = (int) $this->type_model->select('MAX(sort)')->fetchField();
            $type_id = (int) $this->type_model->insert(array(
                'name' => $name,
                'icon' => '',
                'sort' => $sort + 1,
            ));
            if ($type_id <= 0) {
                throw new waException(_wp('Unable to create a Shop-Script product type.'));
            }

            return $type_id;
        } finally {
            $lock->release($lock_key);
        }
    }

    private function getSourceSubject($snapshot_id, $parent_id, $subject_id)
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
        if ($parent_name === '' && method_exists($this->repository, 'getParentsModel')) {
            $parent = $this->repository->getParentsModel()->getByField(array(
                'snapshot_id' => (int) $snapshot_id,
                'parent_id'   => (int) $parent_id,
            ));
            if ($parent) {
                $parent_name = $this->cleanName(ifset($parent['name'], ''));
            }
        }
        $subject_name = $this->cleanName(ifset($subject['name'], ''));
        if ($parent_name === '' || $subject_name === '') {
            throw new waException(_wp('Wildberries category names are missing from the current snapshot.'));
        }

        return array(
            'parent_name' => $parent_name,
            'name'        => $subject_name,
        );
    }

    private function buildTypeName($parent_name, $subject_name)
    {
        return $this->cleanName($parent_name).' / '.$this->cleanName($subject_name);
    }

    private function findExactType($name)
    {
        $normalized = $this->normalizeName($name);
        foreach ((array) $this->type_model->getAll() as $type) {
            if ($this->normalizeName(ifset($type['name'], '')) === $normalized) {
                return $type;
            }
        }
        return null;
    }

    private function requireMappedType($type_id)
    {
        $type = $this->type_model->getById((int) $type_id);
        if (!$type) {
            throw new waException(_wp('The mapped Shop-Script product type no longer exists.'));
        }
        return (int) $type['id'];
    }

    private function saveMapping($snapshot_id, $parent_id, $subject_id, array $data)
    {
        $this->map_model->saveMapping($snapshot_id, $parent_id, $subject_id, $data);
        $this->map[(int) $subject_id] = array_merge(array(
            'snapshot_id' => (int) $snapshot_id,
            'parent_id'   => (int) $parent_id,
            'subject_id'  => (int) $subject_id,
        ), $data);
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
        if ($action === 'create') {
            // Compatibility with legacy mappings: forced creation is no
            // longer supported because Shop-Script type names must be unique.
            return 'auto';
        }
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
