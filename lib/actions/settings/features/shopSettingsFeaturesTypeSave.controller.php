<?php

class shopSettingsFeaturesTypeSaveController extends waJsonController
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }
        $model = new shopTypeModel();

        $data = array();
        $data['id'] = waRequest::post('id', 0, waRequest::TYPE_INT);
        switch (waRequest::post('source', 'custom')) {
            case 'custom':
                $data['name'] = waRequest::post('name');
                if (trim($data['name']) === '') {
                    $this->errors[] = [
                        'name' => 'name',
                        'value' => _w('This field is required.')
                    ];
                    return;
                }
                $data['icon'] = waRequest::post('icon_url', false, waRequest::TYPE_STRING_TRIM);
                if (empty($data['icon'])) {
                    $data['icon'] = waRequest::post('icon', 'icon.box', waRequest::TYPE_STRING_TRIM);
                }

                if (!empty($data['id'])) {
                    $model->updateById($data['id'], $data);
                } else {
                    $data['sort'] = $model->select('MAX(sort)+1 as max_sort')->fetchField('max_sort');
                    $data['id'] = $model->insert($data);
                }
                $routing_path = $this->getConfig()->getPath('config', 'routing');
                if (file_exists($routing_path)) {
                    $routes     = include($routing_path);
                    $new_routes = $this->addTypeToRoutes(
                        waRequest::post('storefronts', [], waRequest::TYPE_ARRAY),
                        intval($data['id']),
                        array_map('intval', array_column((new shopTypeModel())->getAll(), 'id')),
                        $routes
                    );
                    // Only ever touch config if something changed
                    if ($routes != $new_routes) {
                        waUtils::varExportToFile($new_routes, $routing_path);
                    }
                }
                break;
            case 'template':
                $data = $model->insertTemplate(waRequest::post('template'), true);
                break;
        }

        if ($data) {
            $data['icon_html'] = shopHelper::getIcon($data['icon'], 'icon.box');
            $data['name_html'] = '<span class="js-type-icon">'.$data['icon_html'].'</span>
                    <span class="js-type-name">'.htmlspecialchars($data['name'], ENT_QUOTES, 'utf-8').'</span>';
        }
        $this->response = $data;
    }

    /**
     * @param array $storefronts
     * @param string $type_id
     * @param array $all_types
     * @param array $routes
     * @return array
     */
    private function addTypeToRoutes($storefronts, $type_id, $all_types, $routes)
    {
        foreach ($routes as $site => $site_routes) {
            if (!is_array($site_routes)) {
                continue;
            }
            foreach ($site_routes as $route_id => $param) {
                if (ifset($param, 'app', null) !== 'shop' || !isset($param['url'])) {
                    continue;
                }
                $param['type_id'] = ifset($param, 'type_id', null);
                $enable = isset($storefronts[$site]) && in_array($param['url'], $storefronts[$site]);
                try {
                    $routes[$site][$route_id]['type_id'] = $this->getNewRouteTypeId(ifset($param, 'type_id', null), $type_id, $enable, $all_types);
                } catch (waException $e) {
                    $this->errors[] = [
                        'name'  => 'storefronts['.$site.']['.$param['url'].']',
                        'value' => _w('The current product type is the only one selected for this storefront.')
                    ];
                }
            }
        }
        return $routes;
    }

    /**
     * Given the old 'type_id' route parameter, enable or disable given type_id
     * and return new 'type_id' for the route.
     * Throws waException when trying to disable the last type on this storefront.
     *
     * Example:
     * $this->getNewRouteTypeId([1, 3, 4], 2, true,  [1, 2, 3, 4]) ->> [1, 2, 3, 4]
     * $this->getNewRouteTypeId([1, 3, 4], 3, false, [1, 2, 3, 4]) ->> [1, 4]
     * $this->getNewRouteTypeId([2],       2, false, [1, 2, 3, 4]) ->> waException
     * $this->getNewRouteTypeId('',        2, false, [1, 2, 3, 4]) ->> [1, 3, 4]
     * $this->getNewRouteTypeId('',        2, false, [2])          ->> waException
     *
     * @param mixed $old_type_id
     * @param int $type_id
     * @param bool $enable
     * @param array $all_types
     * @return mixed
     * @throws waException
     */
    private function getNewRouteTypeId($old_type_ids, $type_id, $enable, $all_types)
    {
        $ALL_INCLUDED = [null, [], false, '', '0', 0];

        // Enable a type on a storefront?
        if ($enable) {
            // Nothing to do if all types are already included
            if (in_array($old_type_ids, $ALL_INCLUDED, true)) {
                return $old_type_ids;
            }

            // Nothing to do if current selection already contains $type_id
            if (!is_array($old_type_ids)) {
                if ($old_type_ids == $type_id) {
                    return $old_type_ids;
                }
            } else {
                if (in_array($type_id, $old_type_ids)) {
                    return $old_type_ids;
                }
            }

            // Not all types are included, and current selection does not contain $type_id.
            // Add $type_id to list of types.
            $new_type_ids = $old_type_ids;
            if (!is_array($new_type_ids)) {
                $new_type_ids = [intval($old_type_ids)];
            }
            $new_type_ids[] = $type_id;
            return $new_type_ids;
        }

        //
        // Otherwise, disable type on a storefront.
        //

        $new_type_ids = $old_type_ids;

        // When all types are enabled, convert them to explicit list of types
        if (in_array($new_type_ids, $ALL_INCLUDED, true)) {
            $new_type_ids = $all_types;
        }

        if (!is_array($new_type_ids) || count($new_type_ids) <= 1) {
            if ($new_type_ids == [$type_id] || $new_type_ids == $type_id) {
                // Can not remove last type_id from a storefront
                throw new waException('Can not remove last type_id from a storefront');
            } else {
                // $type_id not in list, nothing to remove
                return $old_type_ids;
            }
        }

        // Remove a single type from list of types, if it is there
        if (!in_array($type_id, $new_type_ids)) {
            return $old_type_ids;
        }
        $new_type_ids = array_diff($new_type_ids, [$type_id]);
        return array_values($new_type_ids);
    }
}
