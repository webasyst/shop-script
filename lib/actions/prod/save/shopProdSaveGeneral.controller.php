<?php
/**
 * Accepts POST data from General tab in product editor.
 */
class shopProdSaveGeneralController extends waJsonController
{
    public function execute()
    {
        $product_raw_data = waRequest::post('product', null, waRequest::TYPE_ARRAY);
        $product_id = ifempty($product_raw_data, 'id', null);

        $product_data = $this->prepareProductData($product_raw_data);
        $this->checkRights($product_id, $product_data);

        if (!$this->errors) {
            /** @var shopProduct $product */
            $product = $this->saveProduct($product_id, $product_data);
        }

        if (!$this->errors) {
            $this->response = $this->prepareResponse($product);
        }
    }

    /**
     * Takes data from POST and prepares format suitable as input for for shopProduct class.
     * If something gets wrong, writes errors into $this->errors.
     *
     * @param array
     * @return array
     */
    protected function prepareProductData(array $product_raw_data)
    {
        $product_data = $product_raw_data;

        if (isset($product_data['status'])) {
            if ($product_data['status'] < 0) {
                $product_data['status'] = -1;
                switch ($product_data["params"]["redirect_type"]) {
                    case "404":
                        unset($product_data["params"]["redirect_code"]);
                        unset($product_data["params"]["redirect_url"]);
                        break;
                    case "home":
                        unset($product_data["params"]["redirect_url"]);
                        break;
                    case "category":
                        unset($product_data["params"]["redirect_url"]);
                        $product_data["params"]["redirect_category_id"] = $product_data["category_id"];
                        break;
                    case "url":
                        if (empty($product_data["params"]["redirect_url"])) {
                            $this->errors[] = [
                                'name' => 'product[params][redirect_url]',
                                'text' => _w('Redirect url is required'),
                            ];
                        }
                        break;
                    default:
                        break;
                }
            } else {
                $product_data['status'] = $product_data['status'] > 0 ? 1 : 0;
            }
        }
        unset($product_data["params"]["redirect_type"]);

        if (isset($product_data['category_id'])) {
            if (!isset($product_data['categories']) || !is_array($product_data['categories'])) {
                $product_data['categories'] = [];
            }
            array_unshift($product_data['categories'], $product_data['category_id']);
        } else {
            unset($product_data['categories']);
            unset($product_data['category_id']);
        }

        return $product_data;
    }

    /**
     * Takes product id (null for new products) and data prepared by $this->prepareProductData()
     * Saves to DB and returns shopProduct just saved.
     * If something goes wrong, writes errors into $this->errors and returns null.
     * @param int|null $product_id
     * @param array $product_data
     * @return shopProduct or null
     */
    protected function saveProduct($product_id, array $product_data)
    {
        $product = new shopProduct($product_id);
        $product->save($product_data, true, $errors);
        if ($errors) {
            // !!! TODO format errors properly, if any happened
            $this->errors[] = [
                'id' => "general",
                'text' => _w('Unable to save product').' '.wa_dump_helper($errors),
            ];
            return null;
        }

        return $product;
    }

    /**
     *
     * @param shopProduct $product
     * @return array
     */
    protected function prepareResponse(shopProduct $product)
    {

        $product_model = new shopProductModel();

        // Read all (cached) data from shopProduct, keep only what's stored in shop_product table
        $response = array_intersect_key($product->getData(), $product_model->getEmptyRow());

        $response['categories'] = $product['categories'];
        $response['tags'] = $product['tags'];

        return $response;
    }

    /**
     * @throws waRightsException
     */
    protected function checkRights($id, $data)
    {
        $product_model = new shopProductModel();
        if ($id) {
            if (!$product_model->checkRights($id)) {
                throw new waRightsException(_w("Access denied"));
            }
        } else {
            if (!$product_model->checkRights($data)) {
                throw new waRightsException(_w("Access denied"));
            }
        }
    }
}
