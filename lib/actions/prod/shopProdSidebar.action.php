<?php
/**
 * Sidebar for product editor
 */
class shopProdSidebarAction extends waViewAction
{
    /**
     * shopProdSidebarAction constructor.
     * @param null|array $params
     *      int $params['product_id']
     *      array $params['backend_prod_event'] - result of throwing 'backend_prod' event
     */
    public function __construct($params = null)
    {
        if (!is_array($params)) {
            $params = [];
        }

        $params['id'] = isset($params['id']) && is_scalar($params['id']) ? intval($params['id']) : 0;
        $params['backend_prod_event'] = isset($params['backend_prod_event']) && is_array($params['backend_prod_event']) ? $params['backend_prod_event'] : [];

        parent::__construct($params);
    }

    public function execute()
    {
        $product_id = waRequest::param('id', '', 'int');
        $this->view->assign([
            'id' => $product_id,
            'backend_prod_event' => $this->params['backend_prod_event']
        ]);
    }
}
