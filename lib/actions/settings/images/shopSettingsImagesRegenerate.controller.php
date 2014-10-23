<?php

class shopSettingsImagesRegenerateController extends waLongActionController
{
    public function execute() {
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

    protected function finish($filename) {
        $this->info();
        if ($this->getRequest()->post('cleanup')) {
            return true;
        }
        return false;
    }

    protected function init() {
        $image_model = new shopProductImagesModel();
        
        $this->data['image_total_count'] = $image_model->countAvailableImages();
        $this->data['image_count'] = 0;
        $this->data['offset'] = 0;
        $this->data['product_id'] = null;
        $this->data['product_count'] = 0;
        $this->data['timestamp'] = time();
    }

    protected function isDone() {
        return $this->data['offset'] >= $this->data['image_total_count'];
    }
    
    protected function step()
    {
        $image_model = new shopProductImagesModel();
        $create_thumbnails = waRequest::post('create_thumbnails');
        $chunk_size = 50;
        if ($create_thumbnails) {
            $chunk_size = 10;
        }
        $sizes = wa('shop')->getConfig()->getImageSizes();
        
        $images = $image_model->getAvailableImages($this->data['offset'], $chunk_size);
        foreach ($images as $i) {
            if ($this->data['product_id'] != $i['product_id']) {
                sleep(0.2);
                $this->data['product_id'] = $i['product_id'];
                $this->data['product_count'] += 1;
            }
            try {
                $path = shopImage::getThumbsPath($i);
                if (!waFiles::delete($path)) {
                    throw new waException(sprintf(_w('Error when delete thumbnails for image %d'), $i['id']));
                }
                if ($create_thumbnails) {
                    shopImage::generateThumbs($i, $sizes);
                }
                
                $this->data['image_count'] += 1;    // image count - count of successful progessed images
                
            } catch (Exception $e) {
               $this->error($e->getMessage()); 
            }
            $this->data['offset'] += 1;
        }
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
        $response['progress'] = ($this->data['offset'] / $this->data['image_total_count']) * 100;
        $response['progress'] = sprintf('%0.3f%%', $response['progress']);
        
        if ($this->getRequest()->post('cleanup')) {
            $response['report'] = $this->report();
        }
        
        echo json_encode($response);
    }
    
    protected function report()
    {
        $report = '<div class="successmsg"><i class="icon16 yes"></i> '.
            _w('Updated %d product image.', 'Updated %d product images.', $this->data['image_count']).
            ' '.
            _w('%d product affected.', '%d products affected.', $this->data['product_count']);
        
        $interval = 0;
        if (!empty($this->data['timestamp'])) {
            $interval = time() - $this->data['timestamp'];
            $interval = sprintf(_w('%02d hr %02d min %02d sec'), floor($interval / 3600), floor($interval / 60) % 60, $interval % 60);
            $report .= ' '.sprintf(_w('(total time: %s)'), $interval);
        }
        
        $report .= '&nbsp;<a class="close" href="javascript:void(0);">'._w('close').'</a></div>';
        
        return $report;
    }
    
    private function error($message)
    {
        $path = wa()->getConfig()->getPath('log');
        waFiles::create($path.'/shop/images_regenerate.log');
        waLog::log($message, 'shop/images_regenerate.log');
    }
}