<?php
class shopDebugPlugin extends shopPlugin
{
    /**
     *
     * @param array $params
     * @param array[string]array $params['data'] raw product entry data
     * @param array[string]shopProduct $params['instance'] product entry instance
     */
    public function productSaveHandler($params)
    {
        waLog::log(var_export($params['data'], true), __FUNCTION__.'.log');
    }
    /**
     *
     * @param array $ids
     */
    public function productDeleteHandler($ids)
    {
        waLog::log(var_export($ids, true), __FUNCTION__.'.log');
    }
}
