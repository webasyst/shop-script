<?php

class shopServiceMoveController extends waJsonController
{
    public function execute() {
        $service_id = waRequest::get('service_id', null, waRequest::TYPE_INT);
        if (!$service_id) {
            $this->moveServices();
        } else {
            $this->moveVariants($service_id);
        }
    }
    
    public function moveServices()
    {
        $id = waRequest::post('id', null, waRequest::TYPE_INT);
        $before_id = waRequest::post('before_id', null, waRequest::TYPE_INT);
        $service_model = new shopServiceModel();
        $service_model->move($id, $before_id);
    }
    
    public function moveVariants($service_id)
    {
        $id = waRequest::post('id', null, waRequest::TYPE_INT);
        $before_id = waRequest::post('before_id', null, waRequest::TYPE_INT);
        $model = new shopServiceVariantsModel();
        $model->move($service_id, $id, $before_id);
    }
        
}