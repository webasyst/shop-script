<?php

class shopContactsLinksHandler extends waEventHandler
{
    /**
     * @param array $params deleted contact_id
     * @return array|void
     */
    public function execute(&$params)
    {
        waLocale::loadByDomain('shop');
        // TODO: take a look to other models related with contacts

        $links = array();

        $product_reviews_model = new shopProductReviewsModel();
        $order_model = new shopOrderModel();
        foreach ($params as $contact_id) {
            $links[$contact_id] = array();
            if ($count = $product_reviews_model->countByField('contact_id', $contact_id)) {
                $links[$contact_id][] = array(
                    'role' => _wd('shop', 'Reviews author'),
                    'links_number' => $count,
                );
            }
            if ($count = $order_model->countByField('contact_id', $contact_id)) {
                $links[$contact_id][] = array(
                    'role' => _wd('shop', 'Order customer'),
                    'links_number' => $count
                );
            }
        }
        
        wa()->event(array('shop', 'contacts_links'), $links);

        return $links;
    }
}

