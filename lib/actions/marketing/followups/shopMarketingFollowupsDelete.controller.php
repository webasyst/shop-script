<?php

class shopMarketingFollowupsDeleteController extends shopMarketingSettingsJsonController
{
    public function execute()
    {
        $fm = new shopFollowupModel();
        $id = waRequest::post('id', null, waRequest::TYPE_INT);

        $f = $fm->getById($id);
        if ($f) {
            /**
             * @event followup_delete
             *
             * Notify plugins about deleted followup
             *
             * @param array[string]int $params['id'] followup_id
             * @return void
             */
            wa('shop')->event('followup_delete', $f);
            $fm->deleteById($id);

            $followup_sources_model = new shopFollowupSourcesModel();
            $followup_sources_model->deleteByField('followup_id', $id);
        }
    }
}