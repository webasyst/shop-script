<?php
/**
 * Dialog
 */
class shopProdCategorySortDialogAction extends waViewAction
{
    public function execute()
    {
        $category_id = waRequest::request("category_id", 0, waRequest::TYPE_INT);
        if (!$category_id) {
            $this->errors = [
                "id" => "category_not_found",
                "text" => _w('Category not found.')
            ];
            return false;
        }

        $collection = new shopProductsCollection("category/".$category_id);
        $category = $collection->getInfo();
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
            "category" => $category,
            "products" => $products,
            "render_products" => (bool)$render_products
        ]);

        $this->setTemplate("templates/actions/prod/main/dialogs/categories.sort_products.html");
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
