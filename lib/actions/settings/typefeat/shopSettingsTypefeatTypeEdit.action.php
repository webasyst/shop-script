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

        $this->view->assign([
            'type_templates' => $type_templates,
            'icons' => $icons,
            'type' => $type,
        ]);
    }
}
