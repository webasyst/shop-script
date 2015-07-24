<?php
/** Save after user modified a single cell in products table inline editor. */
class shopProductsInlineSaveController extends shopProductListAction
{
    public function __construct($params = null)
    {
        // We don't care for anything parent's constructor does
    }

    public function execute()
    {
        // Check parameters and access rights
        $product = new shopProduct(waRequest::post('product_id', 0, 'int'));
        if (!$product->id || $product->checkRights() < 2) {
            echo json_encode(array(
                'status' => 'error',
                'data' => null,
            ));
            exit;
        }

        // Modify $product altering SKU price
        $price = waRequest::post('price');
        if ($price) {
            $price = str_replace(',', '.', $price);
            if (is_numeric($price)) {
                $skus = $product->skus;
                foreach($skus as &$s) {
                    $s['price'] = $price;
                }
                unset($s);
                $product->skus = $skus;
            }
        }

        // Modify product altering SKU stocks
        $stock = waRequest::post('stock');
        if ($stock !== null) {
            $skus = $product->skus;
            foreach($skus as &$s) {
                if (is_array($stock)) {
                    $s['count'] = null;
                    $s['stock'] = $stock;
                } else {
                    $s['count'] = $stock === '' ? null : $stock;
                    $s['stock'] = null;
                }
            }
            unset($s);
            $product->skus = $skus;
        }

        // Save product
        $product->save();

        // Emulate a JSON controller returning order list
        $this->collection = new shopProductsCollection('id/'.$product->id);
        $products = $this->collection->getProducts('*,image,'.join(',', self::getEnabledColumns()), 0, 1);
        $this->workupProducts($products);
        echo json_encode(array(
            'status' => 'ok',
            'data' => array_values($products),
        ));
        exit;
    }
}

