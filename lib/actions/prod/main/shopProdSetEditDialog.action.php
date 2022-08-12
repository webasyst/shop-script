<?php
class shopProdSetEditDialogAction extends waViewAction
{
    public function execute()
    {
        $set = $this->getSet();

        $this->view->assign([
            "set" => $set
        ]);

        $this->setTemplate('templates/actions/prod/main/dialogs/sets.set.edit.html');
    }

    protected function getSet()
    {
        $set_id = waRequest::request("set_id", null, waRequest::TYPE_STRING);
        if ($set_id) {
            $set_model = new shopSetModel();
            $set = $set_model->getById($set_id);
            $set["json_params"] = (!empty($set["json_params"]) ? json_decode($set["json_params"], true) : null);

        // create empty set
        } else {
            $set = [
                "id" => null,
                "name" => "",
                "type" => "0",
                "rule" => "",
                "sort_products" => "",
                "count" => 8
            ];
        }

        return $set;
    }
}