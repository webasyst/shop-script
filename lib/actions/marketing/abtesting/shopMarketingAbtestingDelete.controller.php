<?php

class shopMarketingAbtestingDeleteController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('id', null, waRequest::TYPE_INT);

        $abtest_variants_model = new shopAbtestVariantsModel();
        $abtest_model = new shopAbtestModel();

        $abtest_model->deleteById($id);
        $abtest_variants_model->deleteByField('abtest_id', $id);
    }
}