<?php

class shopMigratePluginBackendWbCreateCategoryController extends waJsonController
{
    public function execute()
    {
        try {
            if (!$this->getUser()->getRights('shop', 'importexport')) {
                throw new waRightsException(_wp('Access denied.'));
            }
            if (waRequest::method() !== 'post') {
                throw new waRightsException(_wp('Access denied.'));
            }
            $snapshot_id = $this->validateSnapshot();
            if (!$this->getUser()->getRights('shop', 'setscategories')) {
                throw new waRightsException(_wp('Access denied.'));
            }
            $name = waRequest::post('name', '', waRequest::TYPE_STRING_TRIM);
            $entity_type = waRequest::post('entity_type', 'subject', waRequest::TYPE_STRING_TRIM);
            $wb_id = waRequest::post('wb_id', 0, waRequest::TYPE_INT);
            $wb_parent_id = waRequest::post('wb_parent_id', 0, waRequest::TYPE_INT);
            $shop_parent_id = waRequest::post('shop_parent_id', 0, waRequest::TYPE_INT);
            if ($name === '' || $wb_id <= 0 || !in_array($entity_type, array('parent', 'subject'), true)) {
                if ($name === '') {
                    throw new waException(_wp('Enter a category name.'));
                }
                throw new waException(_wp('Invalid Wildberries category creation parameters.'));
            }
            if ($shop_parent_id < 0) {
                throw new waException(_wp('Invalid Shop-Script parent category.'));
            }

            $repository = new shopMigratePluginWbSnapshotRepository();
            $mapper = new shopMigratePluginWbCategoryMapper($repository);
            $map_model = $repository->getCategoryMapModel();
            $lock = new shopMigratePluginWbOperationLock();
            // Subject rows may create the same missing WB parent concurrently,
            // so serialize category creation for the whole snapshot.
            $lock_key = 'category_snapshot_'.$snapshot_id;
            if (!$lock->acquire($lock_key)) {
                throw new waException(_wp('This category is being created in another request. Try again in a moment.'));
            }
            try {
                $category_created = true;
                if ($entity_type === 'parent') {
                    $source = $repository->getParentsModel()->getByField(array(
                        'snapshot_id' => $snapshot_id,
                        'parent_id'   => $wb_id,
                    ));
                    if (!$source) {
                        throw new waException(_wp('Wildberries parent category was not found in the current snapshot.'));
                    }
                    if ($shop_parent_id > 0) {
                        $this->requireStaticCategory($shop_parent_id);
                    }
                    $category = $this->findReusableMappedCategory(
                        $snapshot_id,
                        'parent',
                        $wb_id,
                        $name,
                        $shop_parent_id,
                        true,
                        $map_model
                    );
                    if ($category) {
                        $category_id = (int) $category['id'];
                        $category_created = false;
                    } else {
                        $category_id = $mapper->createCategory($name, $shop_parent_id);
                    }
                    $map_model->saveMapping($snapshot_id, 'parent', $wb_id, 0, array(
                        'mode'             => shopMigratePluginWbSettings::MODE_MANUAL,
                        'action'           => 'map',
                        'shop_category_id' => $category_id,
                    ));
                } else {
                    $subject = $repository->getSubjectsModel()->getByField(array(
                        'snapshot_id' => $snapshot_id,
                        'subject_id'  => $wb_id,
                    ));
                    if (!$subject) {
                        throw new waException(_wp('Wildberries subject was not found in the current snapshot.'));
                    }
                    if ($wb_parent_id <= 0) {
                        $wb_parent_id = (int) $subject['parent_id'];
                    }
                    if ((int) $subject['parent_id'] !== $wb_parent_id) {
                        throw new waException(_wp('Wildberries subject does not belong to the selected parent category.'));
                    }
                    $name = $this->normalizeSubjectName($name, $subject);
                    if ($shop_parent_id <= 0) {
                        $this->assertParentMappingAllowsCreation($snapshot_id, $wb_parent_id, $map_model);
                    }
                    $category = $this->findReusableMappedCategory(
                        $snapshot_id,
                        'subject',
                        $wb_id,
                        $name,
                        $shop_parent_id,
                        $shop_parent_id > 0,
                        $map_model
                    );
                    if ($category) {
                        $category_id = (int) $category['id'];
                        $shop_parent_id = (int) $category['parent_id'];
                        $category_created = false;
                    } else {
                        if ($shop_parent_id <= 0) {
                            $shop_parent_id = $this->resolveOrCreateShopParent(
                                $snapshot_id,
                                $wb_parent_id,
                                $subject,
                                $repository,
                                $mapper
                            );
                        } else {
                            $this->requireStaticCategory($shop_parent_id);
                            $map_model->saveMapping($snapshot_id, 'parent', $wb_parent_id, 0, array(
                                'mode'             => shopMigratePluginWbSettings::MODE_MANUAL,
                                'action'           => 'map',
                                'shop_category_id' => $shop_parent_id,
                            ));
                        }
                        $category_id = $mapper->createCategory($name, $shop_parent_id);
                    }
                    $map_model->saveMapping($snapshot_id, 'subject', $wb_id, $wb_parent_id, array(
                        'mode'             => shopMigratePluginWbSettings::MODE_MANUAL,
                        'action'           => 'map',
                        'shop_category_id' => $category_id,
                    ));
                }

                $model = new shopCategoryModel();
                $category = $model->getById($category_id);
                if (!$category) {
                    throw new waException(_wp('The newly created Shop-Script category was not found.'));
                }
                $parent_category = !empty($shop_parent_id) ? $model->getById((int) $shop_parent_id) : null;
                $this->response = array(
                    'status'           => 'ok',
                    'category'         => $category,
                    'category_id'      => (int) $category['id'],
                    'shop_category_id' => (int) $category['id'],
                    'shop_parent_id'   => (int) $shop_parent_id,
                    'shop_parent_name' => $parent_category ? (string) $parent_category['name'] : '',
                    'parent_category'  => $parent_category ?: null,
                    'created'          => $category_created,
                    'reused'           => !$category_created,
                );
            } finally {
                $lock->release($lock_key);
            }
        } catch (Throwable $e) {
            $this->setError($e->getMessage());
        }
    }

    private function resolveOrCreateShopParent(
        $snapshot_id,
        $wb_parent_id,
        array $subject,
        shopMigratePluginWbSnapshotRepository $repository,
        shopMigratePluginWbCategoryMapper $mapper
    ) {
        $map_model = $repository->getCategoryMapModel();
        $mapping = $map_model->getByField(array(
            'snapshot_id' => $snapshot_id,
            'entity_type' => 'parent',
            'wb_id'       => $wb_parent_id,
        ));
        if (!empty($mapping)
            && strtolower(trim((string) ifset($mapping['action'], 'auto'))) === 'skip'
        ) {
            throw new waException(_wp('The Wildberries parent category is skipped. Change its mapping before creating a subcategory.'));
        }
        if (!empty($mapping['shop_category_id'])) {
            $category = (new shopCategoryModel())->getById((int) $mapping['shop_category_id']);
            if ($category && (int) $category['type'] === shopCategoryModel::TYPE_STATIC) {
                $map_model->saveMapping($snapshot_id, 'parent', $wb_parent_id, 0, array(
                    'mode'             => shopMigratePluginWbSettings::MODE_MANUAL,
                    'action'           => 'map',
                    'shop_category_id' => (int) $category['id'],
                ));
                return (int) $category['id'];
            }
        }

        $source = $repository->getParentsModel()->getByField(array(
            'snapshot_id' => $snapshot_id,
            'parent_id'   => $wb_parent_id,
        ));
        $parent_name = trim((string) ifset($source['name'], ifset($subject['parent_name'], '')));
        if ($parent_name === '') {
            throw new waException(_wp('Wildberries parent category name is missing from the current snapshot.'));
        }
        $shop_parent_id = $mapper->createCategory($parent_name, 0);
        $map_model->saveMapping($snapshot_id, 'parent', $wb_parent_id, 0, array(
            'mode'             => shopMigratePluginWbSettings::MODE_MANUAL,
            'action'           => 'map',
            'shop_category_id' => $shop_parent_id,
        ));
        return $shop_parent_id;
    }

    private function assertParentMappingAllowsCreation($snapshot_id, $wb_parent_id, $map_model)
    {
        $mapping = $map_model->getByField(array(
            'snapshot_id' => (int) $snapshot_id,
            'entity_type' => 'parent',
            'wb_id'       => (int) $wb_parent_id,
        ));
        if (!empty($mapping)
            && strtolower(trim((string) ifset($mapping['action'], 'auto'))) === 'skip'
        ) {
            throw new waException(_wp('The Wildberries parent category is skipped. Change its mapping before creating a subcategory.'));
        }
    }

    private function findReusableMappedCategory(
        $snapshot_id,
        $entity_type,
        $wb_id,
        $name,
        $shop_parent_id,
        $match_parent,
        $map_model
    ) {
        $mapping = $map_model->getByField(array(
            'snapshot_id' => (int) $snapshot_id,
            'entity_type' => (string) $entity_type,
            'wb_id'       => (int) $wb_id,
        ));
        if (empty($mapping['shop_category_id'])) {
            return null;
        }
        $category = (new shopCategoryModel())->getById((int) $mapping['shop_category_id']);
        if (!$category || (int) $category['type'] !== shopCategoryModel::TYPE_STATIC) {
            return null;
        }
        if ($this->normalizeName(ifset($category['name'], '')) !== $this->normalizeName($name)) {
            return null;
        }
        if ($match_parent && (int) $category['parent_id'] !== (int) $shop_parent_id) {
            return null;
        }
        return $category;
    }

    private function requireStaticCategory($category_id)
    {
        $category = (new shopCategoryModel())->getById((int) $category_id);
        if (!$category || (int) $category['type'] !== shopCategoryModel::TYPE_STATIC) {
            throw new waException(_wp('Select a static Shop-Script parent category.'));
        }
        return $category;
    }

    private function normalizeSubjectName($name, array $subject)
    {
        $name = trim((string) $name);
        $parent_name = trim((string) ifset($subject['parent_name'], ''));
        $subject_name = trim((string) ifset($subject['name'], ''));
        $default_path = trim($parent_name.' / '.$subject_name, ' /');
        if ($default_path !== '' && $this->normalizeName($name) === $this->normalizeName($default_path)) {
            return $subject_name;
        }
        return $name;
    }

    private function normalizeName($name)
    {
        $name = preg_replace('/\s+/u', ' ', trim((string) $name));
        $name = $name === null ? '' : $name;
        return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    }

    private function validateSnapshot()
    {
        $posted_id = waRequest::post('snapshot_id', 0, waRequest::TYPE_INT);
        $current_id = (new shopMigratePluginWbSettings())->getCurrentSnapshotId();
        if ($posted_id <= 0 || $current_id <= 0 || $posted_id !== $current_id) {
            throw new waException(_wp('The Wildberries snapshot has changed. Reload the page and try again.'));
        }
        return $posted_id;
    }
}
