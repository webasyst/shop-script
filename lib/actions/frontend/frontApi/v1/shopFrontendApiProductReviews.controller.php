<?php
/**
 * /product/<id>/reviews
 */
class shopFrontendApiProductReviewsController extends shopFrontApiJsonController
{
    public function post()
    {
        return $this->get();
    }

    protected function getOffsetLimit()
    {
        $offset = waRequest::request('offset', 0, 'int');
        if ($offset < 0) {
            throw new waAPIException('invalid_param', _w('An “offset” parameter value must be greater than or equal to zero.'));
        }
        $limit = waRequest::request('limit', 100, 'int');
        if ($limit < 0) {
            throw new waAPIException('invalid_param', _w('A “limit” parameter value must be greater than or equal to zero.'));
        }
        if ($limit > 1000) {
            throw new waAPIException('invalid_param', sprintf_wp('A “limit” parameter value must not exceed %s.', 1000));
        }
        return [$offset, $limit];
    }

    public function get()
    {
        $product_id = waRequest::param('id', null, 'int');
        if (!$product_id) {
            throw new waAPIException('invalid_param', _w('An “id” parameter value is required.'));
        }

        $parent_review_id = waRequest::request('parent_review_id', null, 'int');
        $return_tree = waRequest::get('tree', null, 'int');
        $depth = waRequest::get('depth', null, 'int');

        $options = [];
        if ($parent_review_id) {
            $offset = 0;
            $limit = 1;
            $options = ['where_reviews' => ['id' => $parent_review_id]];
        } else {
            list($offset, $limit) = $this->getOffsetLimit();
        }

        $reviews_model = new shopProductReviewsModel();
        $reviews = $reviews_model->getFullTree($product_id, $offset, $limit, 'datetime DESC', $options);

        $formatter = new shopFrontApiProductReviewFormatter([
            'comments_as_tree' => (boolean) $return_tree,
            'max_depth' => $depth,
        ]);
        $reviews = array_map([$formatter, 'format'], $reviews);

        $this->response = [
            'count' => $parent_review_id ? 1 : (int) $reviews_model->countInFrontend($product_id),
            'offset' => $offset,
            'limit' => $limit,
            'reviews' => array_values($reviews),
        ];
    }
}
