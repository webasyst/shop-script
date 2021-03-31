<?php
/**
 * Separate layout for products section.
 */
class shopBackendProductsLayout extends waLayout
{

    public function execute()
    {
        // no magic here, just a basic layout with simple plugin hook
        // for all the wizardry how different parts of layout are hidden, see ProductsEditSection layout
        // tl;dr: code in this execute() only runs when full layout render is required,
        // with <!DOCTYPE> and all.

        /**
         * @event backend_prod_layout
         * @param shopProduct $product
         * @return $return['head'] inside <head>
         * @return $return['top'] above app content
         * @return $return['bottom'] below app content
         */
        $backend_prod_layout_event = wa('shop')->event('backend_prod_layout', ref([
            'product' => $this->getProduct(),
        ]));

        $this->view->assign([
            'backend_prod_layout_event' => $backend_prod_layout_event,
        ]);
    }

    public function getProduct()
    {
        return new shopProduct(); // overriden in subclasses
    }
}
