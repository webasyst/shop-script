<?php
/**
 * Shows a dialog after user clicks 'manage visibility' link
 * in products right sidebar; and processes submit from this dialog.
 */
class shopDialogVisibilityAction extends waViewAction
{
    public function execute()
    {
        if (waRequest::post()) {
            $result = $this->processPost();
        } else {
            $result = array(
                'successfull' => array(),
                'attempted' => array(),
                'denied' => array(),
            );
        }

        $this->view->assign(array(
            'status_change' => (int) !!waRequest::post('status'),
            'result' => $result,
        ));
    }

    protected function processPost()
    {
        $set_status = (int) !!waRequest::post('status');
        $update_sku_availability = !!waRequest::post('update_skus');

        $product_ids_denied = array();
        $product_ids_attempted = array();
        $product_ids_successfull = array();

        // We get either a collection hash or a list of product_ids
        $hash = waRequest::post('hash', '', 'string');
        if (!$hash) {
            $ids = waRequest::post('product_id', array(), waRequest::TYPE_ARRAY_INT);
            if (!$ids) {
                return array(
                    'successfull' => $product_ids_successfull,
                    'attempted' => $product_ids_attempted,
                    'denied' => $product_ids_denied,
                );
            } else {
                foreach($ids as $id) {
                    $product_ids_attempted[$id] = $id;
                }
            }
            $hash = 'id/'.join(',', $ids);
        }

        $product_model = new shopProductModel();
        $product_skus_model = new shopProductSkusModel();

        $is_admin = wa()->getUser()->isAdmin('shop'); // separate admin check to allow broken products with no type
        $types_allowed = shopHelper::getWritableTypes();

        $collection = new shopProductsCollection($hash);
        $total_count = $collection->count();
        $count = 100;
        $offset = 0;

        // Read and update products in batches
        while ($offset < $total_count) {

            $products = $collection->getProducts('id,type_id', $offset, $count);

            // Filter products user does not have access to
            $product_ids = array();
            foreach($products as $p) {
                if (empty($types_allowed[$p['type_id']]) && !$is_admin) {
                    $product_ids_denied[] = $p['id'];
                } else {
                    $product_ids[] = $p['id'];
                    $product_ids_successfull[] = $p['id'];
                    $product_ids_attempted[$p['id']] = $p['id'];
                }
            }

            // Update products and skus
            $product_model->updateById($product_ids, array('status' => $set_status));
            if ($update_sku_availability) {
                $product_skus_model->updateByField('product_id', $product_ids, array(
                    'available' => $set_status,
                ));
            }

            $offset += count($products);
            if (!$products) {
                break; // being paranoid
            }
        }

        return array(
            'denied' => $product_ids_denied,
            'successfull' => $product_ids_successfull,
            'attempted' => array_values($product_ids_attempted),
        );
    }
}

