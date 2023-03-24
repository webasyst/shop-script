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
        $product_id = waRequest::param('id', '', waRequest::TYPE_STRING);
        $action = waRequest::param('action', '', waRequest::TYPE_STRING_TRIM);
        if (wa()->whichUI() == '1.3') {
            $url = wa()->getAppUrl() . shopHelper::getBackendEditorUrl($product_id, $action);
            $this->redirect($url);
            exit;
        }

        $can_edit = true;
        $prices_available = false;
        if (wa_is_int($product_id)) {
            $product_model = new shopProductModel();
            $can_edit = !!$product_model->checkRights($product_id);
        }

        $product = new shopProduct($product_id);
        if ($can_edit) {
            $prices_available = true;
        } else {
            if ($product["id"] && $product["status"] !== "-1") {
                $prices_available = true;
            }
        }

        $this->view->assign([
            'id'               => $product_id,
            'can_edit'         => $can_edit,
            'prices_available' => $prices_available,
            'backend_prod_event' => $this->params['backend_prod_event'],
            'not_found' => (($product_id !== "new") && !$product->getId()),
        ]);
    }
}
