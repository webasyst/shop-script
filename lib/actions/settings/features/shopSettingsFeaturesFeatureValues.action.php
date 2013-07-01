<?php
class shopSettingsFeaturesFeatureValuesAction extends waViewAction
{
    public function execute()
    {
        $model = new shopFeatureModel();
        if (($id = waRequest::get('id', waRequest::TYPE_INT)) && ($feature = $model->getById($id))) {
            $feature['values'] = $model->getFeatureValues($feature);
            $this->view->assign('feature', $feature);
        } else {
            throw new waException(_w('Product feature not found'), 404);
        }
    }
}
