<?php

class shopProdCategoryConditionEditDialogAction extends waViewAction
{
    public function execute()
    {
        $type = waRequest::post('type', null, waRequest::TYPE_STRING);
        $id = waRequest::post('id', null, waRequest::TYPE_STRING);

        $values = [];
        if ($type == 'product_param') {
            if ($id == 'type_id') {
                $type_model = new shopTypeModel();
                $values = $type_model->getAll();
            } elseif ($id == 'tag') {
                $tag_model = new shopTagModel();
                $values = $tag_model->getAll();
            }
        } elseif ($type == 'feature') {
            $feature_model = new shopFeatureModel();
            $feature = $feature_model->getFeatures('id', $id, 'id', true);
            if (isset($feature[$id]['values'])) {
                $values = $feature[$id]['values'];
            }
        }

        $this->view->assign([
            'values' => $this->formatValues($values),
        ]);

        $this->setTemplate("templates/actions/prod/main/dialogs/categories.category.condition.edit.html");
    }


    /**
     * @param array $values
     * @return array
     */
    private function formatValues($values) {
        $result = [];

        if (!empty($values)) {
            foreach($values as $key => $value) {
                // Кейс для цветастых значений
                if ($value instanceof shopColorValue) {
                    $value_data = [
                        "name" => (string)$value,
                        "value" => $key,
                        "code" => !empty($value["code"]) ? $value['hex'] : "#000000",
                    ];

                // Кейс для обычного массива (теги например)
                } else if (!empty($value["id"]) && !empty($value["name"])) {
                    $value_data = [
                        "name" => $value["name"],
                        "value" => $value["id"]
                    ];

                // Универсальный кейс
                } else {
                    $value_data = [
                        "name" => (string)$value,
                        "value" => $key
                    ];
                }

                $result[] = $value_data;
            }
        }

        return $result;
    }
}