<?php
/**
 * @since 10.0.0
 */
class shopCustomerSearchMethod extends shopApiMethod
{
    protected $method = ['GET', 'POST'];
    protected $courier_allowed = false;

    public function execute()
    {
        $this->validateRequest();

        $offset = waRequest::request('offset', 0, 'int');
        $limit = waRequest::request('limit', 100, 'int');
        list($sort_field, $sort_order) = $this->getSort();

        $this->response['offset'] = $offset;
        $this->response['limit'] = $limit;
        $this->response['sort'] = "{$sort_field} {$sort_order}";

        if (!wa()->getUser()->getRights('shop', 'customers')) {
            $this->response['customers'] = array();
            $this->response['count'] = 0;
            return;
        }

        $collection = $this->getCollection();
        $collection->orderBy($sort_field, $sort_order);

        $this->response['count'] = $collection->count();
        $customers = array_values($collection->getContacts('*,photo_url_40,photo_url_96', $offset, $limit));

        if ($customers) {

            $use_gravatar = wa('shop')->getConfig()->getGeneralSettings('use_gravatar');
            $gravatar_default = wa('shop')->getConfig()->getGeneralSettings('gravatar_default');

            $formatter = null;
            foreach ($customers as &$c) {
                $c['name'] = waContactNameField::formatName($c);

                if (isset($c['phone']) && is_array($c['phone'])) {
                    foreach($c['phone'] as &$row) {
                        if (isset($row['value'])) {
                            if (!isset($formatter)) {
                                $formatter = new waContactPhoneFormatter();
                            }
                            $row['formatted'] = $formatter->format($row['value']);
                        }
                    }
                    unset($row);
                }

                $email = ifset($c, 'email', 0, null);
                if ($use_gravatar && $email && !$c['photo']) {
                    $c['photo_url_40'] = shopHelper::getGravatar($email, 40, $gravatar_default, true);
                    $c['photo_url_96'] = shopHelper::getGravatar($email, 96, $gravatar_default, true);
                }
            }
            unset($c);
        }

        $this->response['customers'] = $customers;
    }

    protected function getCollection()
    {
        $hash = $this->get('hash');
        return new shopCustomersCollection($hash, [
            'transform_phone_prefix' => 'all_domains',
            'photo_url_2x' => true,
        ]);
    }

    protected function validateRequest()
    {
        $offset = waRequest::request('offset', 0, 'int');

        if ($offset < 0) {
            throw new waAPIException('invalid_param', 'Param offset must be greater than or equal to zero');
        }
        $limit = waRequest::request('limit', 100, 'int');
        if ($limit < 0) {
            throw new waAPIException('invalid_param', 'Param limit must be greater than or equal to zero');
        }
    }

    public function getSort()
    {
        $sort = waRequest::request('sort', null, 'string');
        if (!$sort) {
            $sort = 'name';
        }

        $sort = explode(' ', $sort, 2);

        $sort_order = (string)ifset($sort[1]);
        if ($sort_order != 'DESC') {
            $sort_order = 'ASC';
        }

        $m = new waContactModel();
        $sort_field = (string)ifset($sort[0]);
        if (!$m->fieldExists($sort_field)) {
            $sort_field = 'name';
        }

        return [$sort_field, $sort_order];
    }
}
