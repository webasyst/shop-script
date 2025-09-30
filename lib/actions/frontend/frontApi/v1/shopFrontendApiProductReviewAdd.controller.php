<?php
/**
 * /product/<id>/reviews/add/
 */
class shopFrontendApiProductReviewAddController extends shopFrontApiJsonController
{
    public function post()
    {
        $product_id = waRequest::param('id', 0, waRequest::TYPE_INT);
        $parent_id = waRequest::post('parent_id', 0, waRequest::TYPE_INT);
        $rate = waRequest::post('rate', null, waRequest::TYPE_STRING_TRIM);
        $title = waRequest::post('title', null, waRequest::TYPE_STRING_TRIM);
        $text = waRequest::post('text', '', waRequest::TYPE_STRING_TRIM);
        $name = waRequest::post('name', '', waRequest::TYPE_STRING_TRIM);
        $email = waRequest::post('email', '', waRequest::TYPE_STRING_TRIM);
        $site = waRequest::post('site', '', waRequest::TYPE_STRING_TRIM);

        if (wa()->getSetting('headless_api_antispam_enabled', false, 'shop')) {
            $this->checkAntispamHash(function($antispam_api_key, $antispam_cart_key, $customer_token) use ($title, $text) {
                return $antispam_api_key.$antispam_cart_key.$title.$text;
            });
        }

        $product_model = new shopProductModel();
        $product = $product_model->getById(waRequest::param('id', 0, waRequest::TYPE_INT));

        if (!$product) {
            throw new waAPIException('not_found', _w('Product not found.'), 404);
        }

        $status = (wa('shop')->getSetting('moderation_reviews') ? shopProductReviewsModel::STATUS_MODERATION : shopProductReviewsModel::STATUS_PUBLISHED);
        $data = [
            'parent_id'    => 0,
            'product_id'   => $product_id,
            'datetime'     => date('Y-m-d H:i:s'),
            'status'       => $status,
            'title'        => $title,
            'text'         => $text,
            'rate'         => $rate,
            'contact_id'   => 0,
            'name'         => $name,
            'email'        => $email,
            'images_count' => 0,
            'site'         => $site,
            'auth_provider' => 'guest',
            'is_front_api' => true,
        ];

        $reviews_model = new shopProductReviewsModel();
        if ($errors = $reviews_model->validate($data)) {
            throw new waAPIException('data_invalid', implode(', ', $errors), 400);
        }

        $id = $reviews_model->add($data, $parent_id);
        if ($id) {
            wa('webasyst');
            $service_agreement = wa('shop')->getSetting('review_service_agreement');
            webasystHelper::logAgreementAcceptance(
                'service_agreement',
                wa('shop')->getSetting('review_service_agreement_hint'),
                ifempty($service_agreement, 'notice'),
                null,
                'review'
            );
        } else {
            throw new waAPIException('error_adding', _w('Review adding error.'), 400);
        }

        $this->response = ['review_id' => $id];
    }
}
