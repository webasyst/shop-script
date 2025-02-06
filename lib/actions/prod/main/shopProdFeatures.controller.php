<?php
/**
 * Data for all (or some) features for filters in product list.
 */
class shopProdFeaturesController extends waJsonController
{
    public function execute()
    {
        $ids = waRequest::request('id', null);
        $this->response = shopFilter::getFeatureTypes($ids);
    }
}
