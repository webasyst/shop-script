<?php

/**
 * Cron job to
 * php cli.php shop yandexmarketPluginExport 18
 */
class shopYandexmarketPluginExportCli extends waCliController
{
    /** @var  int */
    private $profile_id;
    /** @var  array */
    private $result;

    protected function preExecute()
    {
        $this->profile_id = waRequest::param(0, 0, waRequest::TYPE_INT);
        if (empty($this->profile_id)) {
            throw new waException('Missed profile id');
        }
        parent::preExecute();

        wa()->setLocale('ru_RU');
        $params = array(
            'profile_id' => $this->profile_id,
        );
        waRequest::setParam($params);
    }

    public function execute()
    {
        $runner = new shopYandexmarketPluginRunController();
        $this->result = $runner->fastExecute($this->profile_id);
    }

    protected function afterExecute()
    {
        if (!empty($this->result['success'])) {
            print($this->result['success']);
        } elseif (!empty($this->result['error'])) {
            print($this->result['error']);
        } elseif (!empty($this->result['warning'])) {
            print($this->result['warning']);
        }
        print "\n";
        parent::afterExecute();
    }
}
