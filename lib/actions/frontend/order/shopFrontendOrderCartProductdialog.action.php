<?php
/**
 * /order/cart/productdialog/ in frontend: dialog to change product SKU or add new product to cart.
 */
class shopFrontendOrderCartProductdialogAction extends shopFrontendProductAction
{
    public function execute()
    {
        $product_id = waRequest::request('id', null, 'int');
        if ($product_id) {
            $product = new shopProduct($product_id, true);
            $product_id = $product['id'];
        }
        if (!$product_id) {
            throw new waException(_w('Product not found.'), 404);
        }

        $selected_sku_id = waRequest::request('sku_id', null, 'int');

        $this->prepareProduct($product, $selected_sku_id);
        $this->view->assign('product', $product);
        $this->assignFeaturesSelectable($product);

        list($services, $skus_services) = $this->getServiceVars($product);

        // Mark selected service variants if came in request
        $selected_services = waRequest::request('service', [], 'array_int');
        foreach($selected_services as $service_id => $variant_id) {
            if (isset($services[$service_id])) {
                $services[$service_id]['is_selected'] = true;
                if (isset($services[$service_id]['variants'][$variant_id])) {
                    $services[$service_id]['variants'][$variant_id]['is_selected'] = true;
                }
            }
        }

        $this->view->assign('sku_services', $skus_services);
        $this->view->assign('services', $services);
        $this->view->assign('currency_info', $this->getCurrencyInfo());
        $this->view->assign('stocks', shopHelper::getStocks(true));

        /**
         * @event frontend_cart_productdialog
         * @param shopProduct $product
         */
        $this->view->assign('frontend_cart_productdialog', wa()->event('frontend_cart_productdialog', $product));

        // Never use layout for this action
        $this->setLayout(null);

        // Use template from theme if exists, or from core app otherwise
        $theme_template_path = $this->getTheme()->path.'/order.product.edit.html';
        if (file_exists($theme_template_path)) {
            $this->setThemeTemplate('order.product.edit.html');
        } else {
            $this->setTemplate('frontend/order/cart/dialog/product.edit.html', true);
        }
    }
}

