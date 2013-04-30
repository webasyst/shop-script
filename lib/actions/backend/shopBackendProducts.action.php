<?php
class shopBackendProductsAction extends waViewAction
{
    public function execute()
    {

        $this->setLayout(new shopBackendLayout());

        $this->getResponse()->setTitle(_w('Products'));

        $category_model = new shopCategoryModel();
        $categories = $category_model->getFullTree();

        foreach ($categories as &$item) {
            if (!isset($item['children_count'])) {
                $item['children_count'] = 0;
            }
            if (isset($categories[$item['parent_id']])) {
                $parent = &$categories[$item['parent_id']];
                if (!isset($parent['children_count'])) {
                    $parent['children_count'] = 0;
                }
                ++$parent['children_count'];
                unset($parent);
            }
        }
        unset($item);

        $this->view->assign('categories', $categories);

        $tag_model = new shopTagModel();
        $this->view->assign('cloud', $tag_model->getCloud());

        $set_model = new shopSetModel();
        $this->view->assign('sets', $set_model->getAll());

        $type_model = new shopTypeModel();
        $this->view->assign('types', $type_model->getTypes());

        $product_model = new shopProductModel();
        $this->view->assign('count_all', $product_model->countAll());

        $review_model = new shopProductReviewsModel();
        $this->view->assign('count_reviews', array(
            'all' => $review_model->count(null, false),
            'new' => $review_model->countNew(true)
        ));

        $product_services = new shopServiceModel();
        $this->view->assign('count_services', $product_services->countAll());

        $config = $this->getConfig();
        $this->view->assign('default_view', $config->getOption('products_default_view'));

        /*
         * @event backend_products
         * @return array[string]array $return[%plugin_id%] array of html output
         * @return array[string][string]string $return[%plugin_id%]['sidebar_top_li'] html output
         * @return array[string][string]string $return[%plugin_id%]['sidebar_section'] html output
         */
        $this->view->assign('backend_products', wa()->event('backend_products'));
    }
}
