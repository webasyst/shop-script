<?php
class shopSettingsFeaturesFeatureValuesAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }
        $model = new shopFeatureModel();
        if (($id = waRequest::get('id', waRequest::TYPE_INT)) && ($feature = $model->getById($id))) {
            $feature['values'] = $model->getFeatureValues($feature);
            $this->view->assign('feature', $feature);
        } else {
            throw new waException(_w('Product feature not found'), 404);
        }
    }
}
