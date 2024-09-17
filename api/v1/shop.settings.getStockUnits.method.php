<?php
/** @since 11.0.0 */
class shopSettingsGetStockUnitsMethod extends shopApiMethod
{
    protected $courier_allowed = true;

    public function execute()
    {
        $active_only = waRequest::request('active_only', true);

        $unit_model = new shopUnitModel();
        $this->response = $unit_model->getAll();
        if ($active_only) {
            $this->response = array_filter($this->response, function($u) {
                return $u['status'] > 0;
            });
        }
        $this->response = array_values($this->response);
    }
}
