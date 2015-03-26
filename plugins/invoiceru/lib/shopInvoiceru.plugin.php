<?php

class shopInvoiceruPlugin extends shopPlugin
{
    public function allowedCurrency()
    {
        return array('RUB', 'UAH');
    }

    /**
     * Safe for rights getting order data
     * @param $order_id
     * @throws waException
     * @return waOrder
     */
    public function getOrder($order_id)
    {
        $order = shopPayment::getOrderData($order_id, $this->allowedCurrency());
        switch (wa()->getEnv()) {
            case 'backend':
                if (!wa()->getUser()->getRights('shop', (!$order && !$order_id) ? 'settings' : 'orders')) {
                    throw new waException(_w("Access denied"));
                }
                if (!$order && !$order_id) {
                    $allowed_currency = $this->allowedCurrency();
                    $dummy_order = array(
                        'contact_id' => wa()->getUser()->getId(),
                        'id'         => 1,
                        'id_str'     => shopHelper::encodeOrderId(1),
                        'currency'   => reset($allowed_currency),
                    );
                    $order = waOrder::factory($dummy_order);
                } elseif (!$order) {
                    throw new waException('Order not found', 404);
                }
                break;
            default:
                //frontend
                if (!$order) {
                    throw new waException('Order not found', 404);
                }
                break;
        }
        return $order;
    }

    private function getTemplatePaths()
    {
        static $paths;
        if (!$paths) {
            $paths = array(
                'changed'  => wa()->getDataPath('plugins/'.$this->id.'/template.html', false, 'shop'),
                'original' => $this->path.'/templates/actions/printform/PrintformDisplay.html'
            );
        }
        return $paths;
    }

    public function getTemplatePath()
    {
        foreach ($this->getTemplatePaths() as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        return '';
    }

    public function getTemplate()
    {
        if ($path = $this->getTemplatePath()) {
            return file_get_contents($path);
        }
        return '';
    }

    public function isTemplateChanged()
    {
        $paths = $this->getTemplatePaths();
        return file_exists($paths['changed']);
    }

    public function resetTemplate()
    {
        $paths = $this->getTemplatePaths();
        $dir = dirname($paths['changed']);
        waFiles::delete($paths['changed']);
        waFiles::delete($dir.'/css/printform.css');
        waFiles::delete($dir.'/js/printform.js');
    }

    public function saveTemplate($data)
    {
        $paths = $this->getTemplatePaths();
        $changed = dirname($paths['changed']);
        $original = dirname($paths['original']);
        file_put_contents($paths['changed'], $data);
        waFiles::create($changed.'/css/');
        waFiles::create($changed.'/js/');
        file_put_contents(
            $changed.'/css/printform.css',
            file_get_contents($original.'/css/printform.css')
        );
        file_put_contents(
            $changed.'/js/printform.js',
            file_get_contents($original.'/js/printform.js')
        );
    }
}
