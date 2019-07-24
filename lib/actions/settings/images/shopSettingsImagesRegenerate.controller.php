<?php

class shopSettingsImagesRegenerateController extends waLongActionController
{
    protected $classes = ['shopImagesRegenerateProduct', 'shopImagesRegenerateReview'];

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
        $this->data['timestamp'] = time();
        $this->data['image_total_count'] = 0;
        $this->data['offset'] = 0;

        $this->setInstances();
        $this->setImageTotalCount();
    }

    protected function isDone()
    {
        return $this->data['offset'] >= $this->data['image_total_count'];
    }

    /**
     * @return bool|void
     */
    protected function step()
    {
        $chunk = $this->getChunk();

        foreach ($this->data['instances'] as $instance) {
            /** @var shopImagesRegenerateInterface $instance */
            $instance->setChunk($chunk);
            $images = $instance->regenerate();
            $this->setOffset(count($images));

            // If the rendered images are less than necessary, then the rest should be transferred to the next type.
            if (count($images) < $chunk) {
                $chunk = $chunk - count($images);
            } else {
                break;
            }
        }

        return true;
    }

    protected function info()
    {
        $interval = 0;
        if (!empty($this->data['timestamp'])) {
            $interval = time() - $this->data['timestamp'];
        }
        $response = array(
            'time'      => sprintf('%d:%02d:%02d', floor($interval / 3600), floor($interval / 60) % 60, $interval % 60),
            'processId' => $this->processId,
            'progress'  => 0.0,
            'ready'     => $this->isDone(),
            'offset'    => $this->data['offset'],
        );
        $response['progress'] = $this->getProgress();
        if ($this->getRequest()->post('cleanup')) {
            $response['report'] = $this->report();
        }

        echo json_encode($response);
    }

    protected function report()
    {
        $report = '<div class="successmsg"><i class="icon16 yes"></i> ';
        $report .= $this->getReports();

        if (!empty($this->data['timestamp'])) {
            $interval = time() - $this->data['timestamp'];
            $interval = sprintf(_w('%02d hr %02d min %02d sec'), floor($interval / 3600), floor($interval / 60) % 60, $interval % 60);
            $report .= ' '.sprintf(_w('(total time: %s)'), $interval);
        }

        $report .= '&nbsp;<a class="close" href="javascript:void(0);">'._w('close').'</a></div>';

        return $report;
    }

    /**
     * Creates class instances that generate images
     *
     * TODO You can add an event here.
     */
    protected function setInstances()
    {
        foreach ($this->classes as $class) {
            $instance = new $class();
            if ($instance instanceof shopImagesRegenerateInterface) {
                $this->data['instances'][$class] = $instance;
            }
        }
    }

    /**
     * Sets the number of images. Need to calculate interest
     */
    protected function setImageTotalCount()
    {
        foreach ($this->data['instances'] as $instance) {
            /** @var shopImagesRegenerateInterface $instance */
            $this->data['image_total_count'] += $instance->getImageTotalCount();
        }
    }

    /**
     * Updates the processed images counter
     * @param $count
     */
    protected function setOffset($count)
    {
        $this->data['offset'] += $count;
    }

    /**
     * Returns the number of images to be processed per step.
     * @return int
     */
    protected function getChunk()
    {
        $create_thumbnails = waRequest::post('create_thumbnails');
        $chunk_size = 50;
        if ($create_thumbnails) {
            $chunk_size = 10;
        }

        return $chunk_size;
    }

    /**
     * Returns the amount of interest
     * @return float|int|string
     */
    protected function getProgress()
    {
        if (empty($this->data['image_total_count'])) {
            $progress = 100;
        } else {
            $progress = ($this->data['offset'] / $this->data['image_total_count']) * 100;
        }
        $progress = sprintf('%0.3f%%', $progress);

        return $progress;
    }

    /**
     * Collects report from classes
     * @return string
     */
    protected function getReports()
    {
        $instances = $this->data['instances'];
        $report = '';

        /** @var shopImagesRegenerateInterface $instance */
        foreach ($instances as $instance) {
            $report .= $instance->getReport()."\n";
        }

        return $report;
    }
}
