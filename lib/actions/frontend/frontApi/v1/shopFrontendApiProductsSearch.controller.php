<?php
/**
 * Serves API requests for list of products: by category, by tag, related products, test search, etc.
 */
class shopFrontendApiProductsSearchController extends shopFrontApiJsonController
{
    public function post()
    {
        return $this->get();
    }

    protected function getCollectionHash()
    {
        $category_id = waRequest::param('category_id', null, 'int');
        if ($category_id) {
            return 'category/'.$category_id;
        }

        $set_id = waRequest::param('set_id', null, 'string_trim');
        if ($set_id) {
            return 'set/'.$set_id;
        }

        $tag_id = waRequest::param('tag_id', null, 'string_trim');
        if ($tag_id) {
            return 'tag/'.$tag_id;
        }

        $product_ids = waRequest::param('product_ids', null, 'string_trim');
        if ($product_ids) {
            $product_ids = array_filter(array_map('intval', array_map('trim', explode(',', $product_ids))));
            if (!$product_ids) {
                throw new waAPIException('invalid_param', sprintf_wp('Incorrect value of the “%s” parameter.', 'product_ids'));
            }
            return 'id/'.join(',', $product_ids);
        }

        $product_id = waRequest::param('product_id_cross_selling', null, 'int');
        if ($product_id) {
            return 'related/cross_selling/'.$product_id;
        }

        $product_id = waRequest::param('product_id_upselling', null, 'int');
        if ($product_id) {
            return 'related/upselling/'.$product_id;
        }

        $search_term = waRequest::request('query', null, 'string_trim');
        if ($search_term) {
            return 'search/query='.str_replace('&', '\&', $search_term);
        }

        throw new waAPIException('invalid_param', sprintf_wp('Missing required parameter: %s.', 'query'));
    }

    protected function getFilters()
    {
        return waRequest::request('filters', null, 'array');
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
        $filters = $this->getFilters();
        list($offset, $limit) = $this->getOffsetLimit();
        $hash = $this->getCollectionHash();

        $collection = new shopProductsCollection($hash, [
            'overwrite_product_prices' => true,
            'frontend' => true,
        ]);
        if ($filters) {
            $collection->filters($filters);
        }

        $formatter = new shopFrontApiProductFormatter([
            'fields' => $this->getCollectionFields(),
        ]);
        $products = $collection->getProducts($formatter->getCollectionFields(), $offset, $limit, false);

        $this->response['count'] = $collection->count();
        $this->response['offset'] = $offset;
        $this->response['limit'] = $limit;
        $this->response['products'] = array_values($formatter->format($products));
    }

    protected function getCollectionFields()
    {
        $fields = array('*' => 1);
        $additional_fields = waRequest::request('fields');
        if (!$additional_fields) {
            $additional_fields = 'skus_image,sku_filtered';
        }
        if ($additional_fields) {
            if (is_string($additional_fields)) {
                $additional_fields = explode(',', $additional_fields);
            }
            if (!is_array($additional_fields)) {
                throw new waAPIException('invalid_param', _w('The “fields” parameter must contain either a string of values separated by comma or an array of strings.'));
            }
            foreach ($additional_fields as $f) {
                $f = trim($f);
                $fields[$f] = 1;
            }
        }
        return array_keys($fields);
    }
}
