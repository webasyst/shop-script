<?php
class shopSettingsFeaturesFeatureControlController extends waJsonController
{
    public function execute()
    {
        $values = waRequest::post('values');
        if (!$values || !is_array($values)) {
            $values = array();
            $values[] = array('name' => 'test', 'value' => 0);
        }
        $type = null;
        $value_type = waRequest::post('value_type', waRequest::TYPE_STRING, shopFeatureModel::TYPE_VARCHAR);
        if (strpos($value_type, '.')) {
            list($value_type, $type) = explode('.', $value_type, 2);
        }
        foreach ($values as $data) {
            switch ($value_type) {
                case shopFeatureModel::TYPE_DIMENSION:
                    $data['type'] = $type;
                    $this->response[] = shopDimension::getControl($data['name'], $data);
                    break;
                default:
                    $this->response[] = waHtmlControl::getControl(waHtmlControl::INPUT, $data['name'], $data);
                    break;
            }
        }
    }
}
