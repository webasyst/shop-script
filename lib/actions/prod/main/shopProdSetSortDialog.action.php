<?php
/**
 * Dialog
 */
class shopProdSetSortDialogAction extends waViewAction
{
    public function execute()
    {
        $set_id = waRequest::request("set_id", 0, waRequest::TYPE_STRING);
        if (!$set_id) {
            $this->errors = [
                "id" => "set_not_found",
                "text" => _w('Set not found.')
            ];
            return false;
        }

        $collection = new shopProductsCollection("set/".$set_id);
        $set = $collection->getInfo();
        $count = $collection->count();

        $render_products = waRequest::request("force", 0, waRequest::TYPE_INT);
        $fuse = 150;

        $products = [];
        if ($render_products || $count < $fuse) {
            $_products = $collection->getProducts('*,images', 0, $count);
            foreach($_products as $_product) {
                $products[] = $this->formatProduct($_product);
            }
            $render_products = 1;
        }

        $this->view->assign([
            "set" => $set,
            "products" => $products,
            "render_products" => (bool)$render_products
        ]);

        $this->setTemplate("templates/actions/prod/main/dialogs/sets.sort_products.html");
    }

    protected function formatProduct($product) {
        // Фотографии продукта
        $photo = null;

        if (!empty($product["images"])) {
            foreach ($product["images"] as $_image) {
                if (empty($photo) || $_image["id"] === $product["image_id"]) {
                    // Append file modification time to image URL
                    // in order to avoid browser caching issues
                    $last_modified = "";
                    $path = shopImage::getPath($_image);
                    if (file_exists($path)) {
                        $last_modified = "?".filemtime($path);
                    }

                    $photo = [
                        "id" => $_image["id"],
                        "url" => $_image["url_thumb"].$last_modified,
                        "description" => $_image["description"]
                    ];
                }
            }
        }

        return [
            "id" => $product["id"],
            "name" => $product["name"],
            "photo" => $photo,
            "price_html" => shop_currency_html($product["price"], true),
        ];
    }
}
