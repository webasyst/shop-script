<?php
/**
 * Product code editor dialog HTML.
 */
class shopSettingsTypefeatCodeEditAction extends waViewAction
{
    public function execute()
    {
        $code_id = waRequest::request('id', '', waRequest::TYPE_STRING);

        $selected_type_id = null;
        if (empty($feature['id'])) {
            $selected_type_id = waRequest::request('type_id');
        }

        $code = $this->getCode($code_id);
        $types = $this->getTypes($code, $selected_type_id);

        $all_types_is_checked = !empty($code['types'][0]);
        if (empty($feature['id']) && $selected_type_id === 'all_existing') {
            $all_types_is_checked = true;
        }

        $code_plugin_enabled = false;
        if (!empty($code['plugin_id'])) {
            $shop_plugins = wa('shop')->getConfig()->getPlugins();
            $code_plugin_enabled = isset($shop_plugins[$code['plugin_id']]);
        }

        $this->view->assign([
            'all_types_is_checked' => $all_types_is_checked,
            'selected_type' => ifset($types, $selected_type_id, null),
            'code' => $code,
            'types' => $types,
            'code_plugin_enabled' => $code_plugin_enabled,
            'protected_code' => $code['protected'] && $code_plugin_enabled,
        ]);
    }

    protected function getCode($code_id)
    {
        $product_code_model = new shopProductCodeModel();
        $code = $product_code_model->getById($code_id);
        if ($code_id && !$code) {
            throw new waException('Not found', 404);
        }
        if ($code) {
            $type_codes_model = new shopTypeCodesModel();
            $code['types'] = $type_codes_model->getTypesByCode($code['id']);
        } else {
            $code = $product_code_model->getEmptyRow();
            $code['types'] = [];
        }

        return $code;
    }

    protected function getTypes($code, $selected_type_id)
    {
        $type_model = new shopTypeModel();
        $types = $type_model->getAll('id');
        foreach($types as &$t) {
            $t['is_checked'] = !empty($code['types'][$t['id']]) || !empty($code['types'][0]) || $selected_type_id == $t['id'];
        }
        unset($t);
        return $types;
    }
}
