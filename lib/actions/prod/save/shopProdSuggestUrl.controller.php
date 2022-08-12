<?php
/**
 * Transliterate human-readable product name and suggest a reasonable URL for the product.
 */
class shopProdSuggestUrlController extends waJsonController
{
    public function execute()
    {
        $product_name = waRequest::request('name', '', 'string');

        $url = shopHelper::transliterate($product_name);

        $max_num_of_tries = 20;
        for ($i = 0; $i < 20; $i++) {
            $try_url = $url . ($i > 0 ? ('-' . $i) : '');
            $in_use = shopHelper::isProductUrlInUse(array('url' => $try_url, 'id' => 0));
            if (!$in_use) {
                break;
            }
        }

        $this->response = array(
            'url' => $in_use ? $url : $try_url,
            'in_use' => (bool) $in_use,
            'url_base' => $url,
        );
    }
}
