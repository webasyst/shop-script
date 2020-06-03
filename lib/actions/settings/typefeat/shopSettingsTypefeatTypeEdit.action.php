<?php
/**
 * Type editor dialog HTML.
 */
class shopSettingsTypefeatTypeEditAction extends waViewAction
{
    public function execute()
    {
        $type_id = waRequest::request('type', '', waRequest::TYPE_STRING);

        $type_model = new shopTypeModel();
        $type_templates = [];
        if ($type_id) {
            $type = $type_model->getById($type_id);
            if (!$type) {
                throw new waException('Not found', 404);
            }
        } else {
            $type = $type_model->getEmptyRow();

            // New type can be created from a template
            $type_templates = (array)shopTypeModel::getTemplates();
        }

        $icons = (array)$this->getConfig()->getOption('type_icons');

        $type['icon_url'] = '';
        $type['icon_class'] = $type['icon'];
        if (false !== strpos($type['icon'], '/')) {
            $type['icon_url'] = $type['icon'];
            $type['icon_class'] = '';
        }

        if ($type['icon_class'] && !in_array($type['icon_class'], $icons)) {
            $icons[] = $type['icon_class'];
        } else if (empty($type['id'])) {
            $type['icon_class'] = reset($icons);
        }

        $storefronts = [];
        $count_storefronts = 0;
        $shop_routes = wa()->getRouting()->getByApp('shop');
        $count_all_storefronts = count(shopStorefrontList::getAllStorefronts());

        foreach ($shop_routes as $domain => $stores) {
            foreach ($stores as $store_id => $param) {
                if (is_array($param['type_id']) && count($param['type_id']) > 0) {
                    $is_checked = in_array($type_id, $param['type_id']);
                } else {
                    $is_checked = in_array($param['type_id'], [null, [], false, '', '0', 0], true);
                }
                $storefronts[] = [
                    'url'        => $param['url'],
                    'domain'     => $domain,
                    'is_checked' => $is_checked
                ];
                $count_storefronts = ($is_checked ? ++$count_storefronts : $count_storefronts);
            }
        }

        $this->view->assign([
            'all_storefronts_is_checked' => ($count_storefronts === $count_all_storefronts),
            'type_templates' => $type_templates,
            'storefronts' => $storefronts,
            'icons' => $icons,
            'type' => $type
        ]);
    }
}
