<?php

class shopProdCategoriesRecountController extends waLongActionController
{
    /**
     * @var shopCategoryModel
     */
    protected $category_model;

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
        return true;
    }
    
    protected function init()
    {
        $this->category_model = new shopCategoryModel();
        $this->data['total_count'] = $this->category_model->select('COUNT(id) AS `dynamic_count`')->where('`type` = ' . shopCategoryModel::TYPE_DYNAMIC)->fetchField('dynamic_count');
        $this->data['offset'] = 0;
        $this->data['timestamp'] = time();
    }

    protected function restore()
    {
        $this->category_model = new shopCategoryModel();
    }

    protected function isDone()
    {
        return $this->data['offset'] >= $this->data['total_count'];
    }
    
    protected function step()
    {
        if (empty($this->data['static_recount'])) {
            $this->category_model->recount();
            $this->data['static_recount'] = true;
            $this->data['categories'] = $this->category_model->select('`id`, `count`')->order('id')->where('`type` = ' . shopCategoryModel::TYPE_STATIC)->fetchAll();
        }

        $limit = 10;
        $categories = $this->category_model->select('id')->order('id')->where('`type` = ' . shopCategoryModel::TYPE_DYNAMIC)->limit("{$this->data['offset']}, $limit")->fetchAll();
        foreach ($categories as $category) {
            $this->data['offset']++;
            try {
                if ($category['type'] = shopCategoryModel::TYPE_DYNAMIC) {
                    $product_collection = new shopProductsCollection('category/' . $category['id']);
                    $category_right_count = $product_collection->count();
                    if ($category_right_count != $category['count']) {
                        $category['count'] = $category_right_count;
                        $this->data['categories'] += [
                            'id' => $category['id'],
                            'count' => $category_right_count,
                        ];
                        $this->category_model->update($category['id'], ['count' => $category['count']]);
                    }
                }
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
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
            'ready'      => $this->isDone(),
            'offset' => $this->data['offset'],
        );
        $response['progress'] = ($this->data['offset'] / $this->data['total_count']) * 100;
        $response['progress'] = sprintf('%0.3f%%', $response['progress']);
        $response['categories'] = $this->data['categories'];

        echo json_encode($response);
    }
    
    private function error($message)
    {
        $path = wa()->getConfig()->getPath('log');
        waFiles::create($path.'/shop/category_recount.log');
        waLog::log($message, 'shop/category_recount.log');
    }    
}