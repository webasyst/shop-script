<?php
class shopSettingsFeaturesFeatureValueAddController extends waJsonController
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }
        $model = new shopFeatureModel();
        $values = array(waRequest::post('value'));
        $code = waRequest::post('code');
        if ($values && $code && ($feature = $model->getByField('code', $code))) {
            $this->response = $model->setValues($feature, $values);
        } else {
            $this->setError(_w('Product feature not found'));
        }
    }
}
