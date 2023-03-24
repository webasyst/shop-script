<?php

class shopOrderLogMethod extends shopApiMethod
{
    public function execute()
    {
        $id = $this->get('id', true);

        $workflow = new shopWorkflow();
        $log_model = new shopOrderLogModel();
        $log = $log_model->getLog($id);
        $root_url = rtrim(wa()->getRootUrl(true), '/');
        $host_url = wa()->getConfig()->getHostUrl();
        $use_gravatar = wa('shop')->getConfig()->getGeneralSettings('use_gravatar');
        $gravatar_default = wa('shop')->getConfig()->getGeneralSettings('gravatar_default');
        $contact_data = array();

        if ($use_gravatar) {
            $contact_ids = array();
            foreach ($log as $l) {
                if (!empty($l['contact_id'])) {
                    $contact_ids[] = $l['contact_id'];
                }
            }

            $wcc = new waContactsCollection('id/'.implode(',', $contact_ids));
            $contact_data = $wcc->getContacts('id,email', 0, count($contact_ids));
        }

        foreach ($log as &$l) {
            $l['log_record'] = $l['action_name'] = $l['action_id'];
            if ($l['action_id']) {
                try {
                    $action = $workflow->getActionById($l['action_id']);
                    if ($action) {
                        $l['action_name'] = $action->getName();
                        $l['log_record'] = $action->getOption('log_record');
                    }
                } catch (Exception $e) {
                }
            }

            $l['photo_url_40'] = $root_url.'/wa-content/img/userpic50@2x.jpg';

            if (!empty($l['contact_id'])) {
                $email = ifset($contact_data, $l['contact_id'], 'email', 0, null);
                if (empty($l['contact_photo']) && $use_gravatar && $email) {
                    $l['photo_url_40'] = shopHelper::getGravatar($email, 40, $gravatar_default, true);
                } elseif (!empty($l['contact_photo'])) {
                    $l['photo_url_40'] = $host_url.waContact::getPhotoUrl($l['contact_id'], $l['contact_photo'], 40, 40, 'person', 1);
                }
            }
        }

        $this->response = $log;
    }
}
