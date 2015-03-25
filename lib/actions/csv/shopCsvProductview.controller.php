<?php

class shopCsvProductviewController extends waJsonController
{
    /**
     * @var shopCsvReader
     */
    private $reader;
    /**
     * @var array
     */
    private static $collisions = array();

    private function init()
    {
        $path = wa()->getTempPath('csv/upload/');
        $name = basename(waRequest::post('file'));
        if ($name && file_exists($path.$name)) {
            $this->reader = shopCsvReader::snapshot($path.$name, self::$collisions);
        } else {
            throw new waException('CSV file not found');
        }
    }

    public static function columns($row, $key)
    {
        $data = false;
        if (!empty(self::$collisions)) {
            foreach (self::$collisions as $hash => $rows) {
                if (in_array($key, $rows)) {
                    $title = _w('Collision at rows #').implode(', ', $rows);
                    $data = json_encode($rows);
                    $value = '<i class="icon16 exclamation js-collision" title="'.$title.'"></i>';
                    if (preg_match('/^(c|p):u:(\d+)$/', $hash, $matches)) {
                        $href = '';
                        switch ($matches[1]) {
                            case 'c':
                                $href = '?action=products#/products/category_id='.$matches[2].'&view=table';
                                break;
                            case 'p':
                                $href = '?action=products#/product/'.$matches[2].'/';
                                break;
                        }
                        $value .= '<a href="'.$href.'" target="_blank"><i class="icon16 new-window" title="'.$title.'"></i></a>';
                    }
                    break;
                }
            }

        }
        if ($data) {

            return '<td data-rows="'.htmlentities($data, ENT_QUOTES, 'utf-8').'">'.$value.'</td>';
        } else {
            return '<td>&nbsp;</td>';
        }
    }


    public static function tableRowHandler($data)
    {
        switch (shopCsvProductrunController::getDataType($data)) {
            case shopCsvProductrunController::STAGE_CATEGORY:
                $td = '<i class="icon16 folder" title="'.htmlentities(_w('Will be imported as category'), ENT_QUOTES, 'utf-8').'"></i>';
                break;
            case shopCsvProductrunController::STAGE_PRODUCT:
            case shopCsvProductrunController::STAGE_SKU:
                $td = '<i class="icon16 box" title="'.htmlentities(_w('Will be imported as product'), ENT_QUOTES, 'utf-8').'"></i>';
                break;
            default:
                $td = '<i class="icon16 no"></i>';
                break;
        }
        return $td;
    }

    public function execute()
    {
        $this->init();
        $this->reader->seek(max(0, waRequest::request('row', 0, waRequest::TYPE_INT)));
        $limit = max(1, waRequest::request('limit', 50, waRequest::TYPE_INT));

        $this->reader->columns(array(
            array('shopCsvProductviewController', 'tableRowHandler'),
            array(__CLASS__, 'columns'),
        ));


        $n = 0;
        $this->response['tbody'] = '';
        while ((++$n <= $limit) && $this->reader->next()) {
            $this->response['tbody'] .= $this->reader->getTableRow();
        }
        $this->response['rows_count'] = $this->reader->count();
        $this->response['current'] = $this->reader->key();
    }
}
