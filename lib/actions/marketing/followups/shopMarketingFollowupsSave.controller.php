<?php

class shopMarketingFollowupsSaveController extends shopMarketingSettingsJsonController
{
    public function execute()
    {
        /**
         * @var shopConfig $config
         */
        $config = $this->getConfig();
        $transports = shopMarketingFollowupsAction::getTransports();
        $fm = new shopFollowupModel();

        $followup = waRequest::post('followup');
        $followup_sources = '';

        if ($followup && is_array($followup)) {
            if (empty($followup['all_sources']) && !isset($followup['selected_sources_all']) && isset($followup['selected_sources'])) {
                $followup_sources = $followup['selected_sources'];
            }
            $id = ifempty($followup, 'id', null);
            $empty_row = $fm->getEmptyRow();
            $followup = array_intersect_key($followup, $empty_row) + $empty_row;
            unset($followup['id']);
            $followup['delay'] = ((float)str_replace(array(' ', ','), array('', '.'), ifset($followup['delay'], '3'))) * 3600;
            if (empty($followup['name'])) {
                $followup['name'] = _w('<no name>');
            }

            $followup['from'] = $followup['from'] ? $followup['from'] : null;

            if ($followup['from'] === 'other') {
                $followup['from'] = waRequest::post('from');
            }

            // In restricted mail mode it's only allowed to use notifications
            // with default text. This is useful for demo and trial accounts.
            if ($config->getOption('restricted_mail')) {
                if (isset($transports[$followup['transport']]['template'])) {
                    $followup['body'] = $transports[$followup['transport']]['template'];
                } else {
                    throw new waRightsException();
                }
            }

            if ($id) {
                unset($followup['last_cron_time']);
                $f = $fm->getById($id);
                if ($f['status'] == 0 && $followup['status'] == 1) {
                    $followup['last_cron_time'] = date('Y-m-d H:i:s');
                }

                $fm->updateById($id, $followup);
                $just_created = false;
            } else {
                $followup['last_cron_time'] = date('Y-m-d H:i:s');
                $id = $fm->insert($followup);
                $just_created = true;
            }

            $followup_sources_model = new shopFollowupSourcesModel();
            $followup_sources_model->deleteByField('followup_id', $id);
            $insert_sources = array();
            if (empty($followup_sources)) {
                $insert_sources[] = array('followup_id' => $id, 'source' => 'all_sources');
            } else {
                foreach ($followup_sources as $source) {
                    $insert_sources[] = array('followup_id' => $id, 'source' => $source);
                }
            }
            $followup_sources_model->multipleInsert($insert_sources);

            $f = $fm->getById($id);
            if ($f) {
                $f['just_created'] = $just_created;

                /**
                 * Notify plugins about created or modified followup
                 * @event followup_save
                 * @param array[string]int $params['id'] followup_id
                 * @param array[string]bool $params['just_created']
                 * @return void
                 */
                wa('shop')->event('followup_save', $f);
            }

            if ($f) {
                return $this->response['id'] = $f['id'];
            }
        }

        return $this->errors[] = array(
            'id'   => 'id',
            'text' => _w('Saving error'),
        );
    }
}