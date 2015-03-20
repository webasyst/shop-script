<?php

class shopSettingsSearchReindexController extends waLongActionController
{
    protected $product_model;
    /**
     * @var shopIndexSearch
     */
    protected $search_index;

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

    protected function finish($filename)
    {
        $this->info();
        if ($this->getRequest()->post('cleanup')) {
            return true;
        }
        return false;
    }
    
    protected function init()
    {
        $this->product_model = new shopProductModel();
        $this->data['total_count'] = $this->product_model->countAll();
        $this->data['offset'] = 0;
        $this->data['timestamp'] = time();
        $this->search_index = new shopIndexSearch();
    }

    protected function restore()
    {
        $this->product_model = new shopProductModel();
        $this->search_index = new shopIndexSearch();
    }


    protected function isDone()
    {
        return $this->data['offset'] >= $this->data['total_count'];
    }
    
    protected function step()
    {
        $limit = 10;
        
        $products = $this->product_model->select('id')->order('id')->limit("{$this->data['offset']}, $limit")->fetchAll();
        foreach ($products as $p) {
            $this->data['offset'] += 1;
            try {
                // DO something with product $p
                $this->search_index->indexProduct($p['id']);
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
        }
        sleep(1);
    }


    protected function info()
    {
        $interval = 0;
        if (!empty($this->data['timestamp'])) {
            $interval = time() - $this->data['timestamp'];
        }
        $response = array(
            'time'       => sprintf('%d:%02d:%02d', floor($interval / 3600), floor($interval / 60) % 60, $interval % 60),
            'processId'  => $this->processId,
            'progress'   => 0.0,
            'ready'      => $this->isDone(),
            'offset' => $this->data['offset'],
        );
        $response['progress'] = ($this->data['offset'] / $this->data['total_count']) * 100;
        $response['progress'] = sprintf('%0.3f%%', $response['progress']);
        
        if ($this->getRequest()->post('cleanup')) {
            $response['report'] = $this->report();
        }
        
        echo json_encode($response);
    }
    
    protected function report()
    {
        $report = '<div class="successmsg"><i class="icon16 yes"></i> '.
            _w('Successfully re-indexed %d product', 'Successfully re-indexed %d products', $this->data['total_count']);

        if (!empty($this->data['timestamp'])) {
            $interval = time() - $this->data['timestamp'];
            $interval = sprintf(_w('%02d hr %02d min %02d sec'), floor($interval / 3600), floor($interval / 60) % 60, $interval % 60);
            $report .= ' '.sprintf(_w('(total time: %s)'), $interval);
        }
        
        $report .= '&nbsp;<a class="close" href="javascript:void(0);">'._w('Close').'</a></div>';
        
        return $report;
    }
    
    private function error($message)
    {
        $path = wa()->getConfig()->getPath('log');
        waFiles::create($path.'/shop/search_reindex.log');
        waLog::log($message, 'shop/search_reindex.log');
    }    
}