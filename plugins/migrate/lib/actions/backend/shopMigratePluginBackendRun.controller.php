<?php

/**
 * Class shopMigratePluginBackendRunController
 * @property shopMigrateTransport[string] $data['transport']
 */
class shopMigratePluginBackendRunController extends waLongActionController
{
    /**
     * @var shopMigrateTransport
     */
    private $transport;

    protected function preExecute()
    {
        $this->getResponse()->addHeader('Content-type', 'application/json');
        $this->getResponse()->sendHeaders();
    }

    protected function init()
    {
        try {
            $options = waRequest::post();
            if ($transport = waRequest::post('transport')) {
                unset($options['transport']);
            }
            $this->data['transport'] = shopMigrateTransport::getTransport($transport, $options, $this->processId);
            $this->transport = $this->data['transport'];
            $this->transport->init();
            $this->data['timestamp'] = time();
            $this->data['count'] = $this->transport->count();
            $stages = array_keys($this->data['count']);
            $this->data['current'] = array_fill_keys($stages, 0);
            $this->data['processed_count'] = array_fill_keys($stages, 0);
            $this->data['stage'] = reset($stages);
            $this->data['error'] = null;
            $this->data['stage_name'] = $this->transport->getStageName($this->data['stage']);
            $this->data['memory'] = memory_get_peak_usage();
            $this->data['memory_avg'] = memory_get_usage();
        } catch (waException $ex) {
            echo json_encode(array('error' => $ex->getMessage(),));
            exit;
        }
    }

    public function execute()
    {
        try {
            parent::execute();
        } catch (waException $ex) {
            if ($ex->getCode() == '302') {
                echo json_encode(array('warning' => $ex->getMessage()));
            } else {
                echo json_encode(array('error' => $ex->getMessage()));
            }
        }
    }

    protected function isDone()
    {
        $done = true;
        foreach ($this->data['current'] as $stage => $current) {
            if ($current < $this->data['count'][$stage]) {
                $done = false;
                $this->data['stage'] = $stage;
                break;
            }
        }
        if ($this->transport) {
            $this->data['stage_name'] = $this->transport->getStageName($this->data['stage']);
        }

        return $done;
    }

    private function isBreak()
    {
        static $runtime;
        static $limit;
        $time = time();
        if (!$runtime) {
            $runtime = $time;
            $limit = $this->max_exec_time ? min(20, max(5, $this->max_exec_time / 2)) : 20;
        }

        return ($time - $runtime) > $limit;
    }

    protected function step()
    {
        $step = $this->transport->step($this->data['current'], $this->data['count'], $this->data['processed_count'], $this->data['stage'], $this->data['error']);
        $this->data['memory'] = memory_get_peak_usage();
        $this->data['memory_avg'] = memory_get_usage();

        return !$this->isDone() && $step && !$this->isBreak();
    }

    protected function save()
    {
        $this->getStorage()->close();
    }

    protected function finish($filename)
    {
        $this->info();
        $result = false;
        if ($this->getRequest()->post('cleanup')) {
            $result = true;
            $class = null;
            if ($this->transport) {
                $this->transport->finish();
                $class = get_class($this->transport);
            } elseif ($this->data['transport']) {

                $transport = $this->data['transport'];
                /**
                 * @var shopMigrateTransport $transport
                 */
                $transport->finish();
                $class = get_class($transport);
            }

            if ($class) {
                $params = array(
                    'type' => preg_replace('@^shopMigrate(\w+)Transport$@', '$1', $class),
                );
                $this->logAction('catalog_import', $params);
            }

        }

        return $result;
    }

    protected function report()
    {
        $report = '<div class="successmsg">';
        $report .= sprintf('<i class="icon16 yes"></i>%s ', _wp('Successfully imported'));
        $chunks = array();
        foreach ($this->data['current'] as $stage => $current) {
            if ($current) {
                if (!empty($this->data['transport'])) {
                    $transport = $this->data['transport'];
                    /**
                     * @var shopMigrateTransport $transport
                     */
                    if ($data = $transport->getStageReport($stage, $this->data['processed_count'])) {
                        $chunks[] = htmlentities($data, ENT_QUOTES, 'utf-8');
                    }
                }
            }
        }
        $report .= implode(', ', $chunks);
        if (!empty($this->data['timestamp'])) {
            $interval = time() - $this->data['timestamp'];
            $interval = sprintf(
                _wp('%02d hr %02d min %02d sec'),
                floor($interval / 3600),
                floor($interval / 60) % 60,
                $interval % 60
            );
            $report .= ' '.sprintf(_wp('(total time: %s)'), $interval);
        }
        $report .= '</div>';

        return $report;
    }

    protected function info()
    {
        $interval = 0;
        if (!empty($this->data['timestamp'])) {
            $interval = time() - $this->data['timestamp'];
        }
        $response = array(
            'time'       => sprintf(
                '%d:%02d:%02d',
                floor($interval / 3600),
                floor($interval / 60) % 60,
                $interval % 60
            ),
            'processId'  => $this->processId,
            'stage'      => false,
            'progress'   => 0.0,
            'ready'      => $this->isDone(),
            'count'      => empty($this->data['count']) ? false : $this->data['count'],
            'memory'     => sprintf('%0.2fMByte', $this->data['memory'] / 1048576),
            'memory_avg' => sprintf('%0.2fMByte', $this->data['memory_avg'] / 1048576),
        );

        $stage_num = 0;
        $stage_count = count($this->data['current']);

        foreach ($this->data['current'] as $stage => $current) {
            if ($current < $this->data['count'][$stage]) {
                $response['stage'] = $stage;
                $response['progress'] = sprintf(
                    '%0.3f%%',
                    100.0 * (1.0 * $current / $this->data['count'][$stage] + $stage_num) / $stage_count
                );
                break;
            }
            ++$stage_num;
        }
        $response['stage_name'] = $this->data['stage_name'];
        $response['stage_num'] = $stage_num;
        $response['stage_count'] = $stage_count;
        $response['current_count'] = $this->data['current'];
        $response['processed_count'] = $this->data['processed_count'];
        if ($response['ready']) {
            $response['report'] = $this->report();
        }
        echo json_encode($response);
    }

    protected function restore()
    {
        $this->transport = $this->data['transport'];
        $this->transport->restore();
    }
}
