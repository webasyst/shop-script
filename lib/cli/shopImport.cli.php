<?php

class shopImportCli extends waCliController
{
    public function execute()
    {
        $file = waRequest::param(0);
        if (!file_exists($file)) {
            throw new waException("File not found");
        }
        $xml = simplexml_load_file($file);
        $shop = $xml->shop;
        //$this->importCategories($shop);
        $this->importProducts($shop);
    }

    protected function importCategories(SimpleXMLElement $shop)
    {
        $category_model = new shopCategoryModel();
        $data = array();
        foreach ($shop->categories->children() as $c) {
            if (!$c['parentId']) {
                $category_model->add(array(
                    'id' => $c['id'],
                    'name' => (string)$c,
                    'url' => str_replace(' ', '_', waLocale::transliterate((string)$c, 'ru_RU'))
                ));
            } else {
                $data[] = $c;
            }
        }
        $data = array_reverse($data);
        foreach ($data as $c) {
            if ($c['parentId']) {
                $category_model->add(array(
                    'id' => $c['id'],
                    'name' => (string)$c,
                    'url' => str_replace(' ', '_', waLocale::transliterate((string)$c, 'ru_RU'))
                ), $c['parentId']);
            }
        }
    }


    protected function importProducts(SimpleXMLElement $shop)
    {
        $category_products_model = new shopCategoryProductsModel();
        foreach ($shop->offers->children() as $o) {
            $data = array(
                'name' => (string)$o->name,
                'description' => trim((string)$o->description),
                'price' => (string)$o->price,
                'url' => preg_replace("/^.*?product_slug=([^&]+).*?$/ui", "$1", (string)$o->url)
            );
            $product = new shopProduct();
            if ($product->save($data) && (int)$o->categoryId) {
                $category_products_model->add($product->getId(), (int)$o->categoryId);
            }
        }
    }
}