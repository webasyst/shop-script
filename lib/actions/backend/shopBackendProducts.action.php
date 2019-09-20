<?php
class shopBackendProductsAction extends waViewAction
{
    /**
     * Tag limit for cloud output
     */
    const MAX_TAGS = 100;

    public function execute()
    {
        $this->setLayout(new shopBackendLayout());

        $this->getResponse()->setTitle(_w('Products'));

        $this->view->assign('categories', new shopCategories());

        $tag_model = new shopTagModel();
        $tags_count = $tag_model->countAll();

        if ($tags_count < self::MAX_TAGS) {
            $tags = $tag_model->getCloud();
        } elseif ($tags_count > 1) {
            $tags = 'search';  //view autocomplite field
        } else{
            $tags = null;
        }

        $this->view->assign('max_tags', self::MAX_TAGS);
        $this->view->assign('cloud', $tags);

        $set_model = new shopSetModel();
        $this->view->assign('sets', $set_model->getAll());
        $collapse_types = wa()->getUser()->getSettings('shop', 'collapse_types');
        if (empty($collapse_types)) {
            $type_model = new shopTypeModel();
            $this->view->assign('types', $type_model->getAll('id'));
        } else {
            $this->view->assign('types', false);
        }

        $this->view->assign('products_rights', $this->getUser()->isAdmin('shop') || $this->getUser()->getRights('shop', 'type.%'));

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

        $this->view->assign('sidebar_width', $config->getSidebarWidth());

        $this->view->assign('lang', substr(wa()->getLocale(), 0, 2));
    }
}
