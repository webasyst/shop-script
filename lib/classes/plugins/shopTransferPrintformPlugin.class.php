<?php

/**
 * Class shopTransferPrintformPlugin
 */
abstract class shopTransferPrintformPlugin extends shopPlugin implements shopPrintformInterface
{
    /**
     * @var shopPrintformTemplate
     */
    private $template;

    public function __construct($info)
    {
        parent::__construct($info);

        $this->template = new shopPrintformTemplate(
            $this->path.'/templates/actions/printform/template.html',
            wa()->getDataPath('plugins/'.$this->id.'/template.html')
        );
    }

    /**
     * @return string
     */
    public function getTemplatePath()
    {
        return $this->template->getPath();
    }

    /**
     * @return string
     */
    public function getTemplate()
    {
        return $this->template->getContent();
    }

    /**
     * @return bool
     */
    public function isTemplateChanged()
    {
        return $this->template->isChanged();
    }

    /**
     * @return mixed
     */
    public function resetTemplate()
    {
        return $this->template->reset();
    }

    /**
     * @param string $html
     * @return bool
     */
    public function saveTemplate($html)
    {
        return $this->template->save($html);
    }

    /**
     * @param mixed $data
     * @return string
     */
    public function renderPrintform($data)
    {
        if (is_numeric($data)) {
            $transfer = $this->getTransfer((int) $data);
        } elseif (is_array($data)) {
            $transfer = $data;
        } else {
            $transfer = $this->getTransfer(0);
        }

        $skus = array();
        $products = array();

        if ($transfer['skus']) {
            $sku_ids = array_keys($transfer['skus']);
            $sku_model = new shopProductSkusModel();
            $skus = array();
            $product_ids = array();
            foreach ($sku_model->getById($sku_ids) as $sku) {
                $skus[$sku['id']] = $sku;
                $product_ids[] = $sku['product_id'];
            }
            $product_ids = array_unique($product_ids);
            $product_model = new shopProductModel();
            $products = $product_model->getById($product_ids);
        }

        $from_stock = null;
        $to_stock = null;
        $stock_model = new shopStockModel();
        if ($transfer['stock_id_from']) {
            $from_stock = $stock_model->getById($transfer['stock_id_from']);
        }
        $to_stock = $stock_model->getById($transfer['stock_id_to']);

        $view = $this->template->getView();

        $primary_currency = wa('shop')->getConfig()->getCurrency();

        $data = array(
            'transfer' => $transfer,
            'settings' => $this->getSettings(),
            'skus' => $skus,
            'products' => $products,
            'from_stock' => $from_stock,
            'to_stock' => $to_stock,
            'primary_currency' => $primary_currency,
            'primary_currency_info' => waCurrency::getInfo($primary_currency)
        );

        $data = $this->preparePrintform($data, $view);
        $view->assign($data);

        return $this->template->display();
    }

    /**
     * @param $data
     * @param waView $view
     * @return mixed
     */
    public function preparePrintform($data, waView $view)
    {
        return $data;
    }

    private function getTransfer($transfer_id)
    {
        $tm = new shopTransferModel();
        $transfer = $tm->getById($transfer_id);
        if (!$transfer) {
            $transfer = $tm->getEmptyRow();
        }
        $tpm = new shopTransferProductsModel();
        $skus = $tpm->getByTransfer($transfer['id']);
        $transfer['skus'] = $skus;
        return $transfer;
    }
}
