<?php

/**
 * Class shopMigrateBitrixTransport
 * @title Bitrix
 * @description migrate data via Bitrix REST API
 */
class shopMigrateBitrixTransport extends shopMigrateTransport
{

    protected function initOptions()
    {
        parent::initOptions();
    }

    public function validate($result, &$errors)
    {
        return parent::validate($result, $errors);
    }

    public function count()
    {
        $count = array();
        return $count;
    }

    public function step(&$current, &$count, &$processed, $stage, &$error)
    {
        $method_name = 'step'.ucfirst($stage);
        $result = false;
        try {
            if (method_exists($this, $method_name)) {
                $result = $this->$method_name($current[$stage], $count, $processed[$stage]);
                if ($result && ($processed[$stage] > 10) && ($current[$stage] == $count[$stage])) {
                    $result = false;
                }
            } else {
                $this->log(sprintf("Unsupported stage [%s]", $stage), self::LOG_ERROR);
                $current[$stage] = $count[$stage];
            }
        } catch (Exception $ex) {
            $this->stepException($current, $stage, $error, $ex);
        }

        return $result;
    }

    private function stepCategory(&$current_stage, &$count, &$processed)
    {

    }

    private function stepProduct(&$current_stage, &$count, &$processed)
    {
        static $products;
        if (!$products) {

        }
        if ($product = reset($products)) {
            ++$processed;
            ++$current_stage;
            array_shift($products);
        }
        return true;
    }

    private function query()
    {
    }
}
