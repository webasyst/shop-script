<?php

class shopCustomersMassActions extends waActions
{
    protected $contact_ids = [];

    public function preExecute()
    {
        parent::preExecute();
        $this->contact_ids = waRequest::request('ids', null, waRequest::TYPE_ARRAY_INT);
    }

    protected function exportDialogAction()
    {
        $this->display([
            'contact_ids' => $this->contact_ids,
        ], 'templates/actions/customers/dialog/ExportDialog.html');
    }

    protected function addIdCodeDialogAction()
    {
        $this->display([
            'contact_ids' => $this->contact_ids,
        ], 'templates/actions/customers/dialog/AddIdCodeDialog.html');
    }

    protected function addCategoryDialogAction()
    {
        $this->display([
            'is_add' => true,
            'contact_ids' => $this->contact_ids,
            'categories' => shopCustomer::getAllCategories(),
        ], 'templates/actions/customers/dialog/CategoryDialog.html');
    }

    protected function removeCategoryDialogAction()
    {
        $this->display([
            'is_add' => false,
            'contact_ids' => $this->contact_ids,
            'categories' => shopCustomer::getAllCategories(),
        ], 'templates/actions/customers/dialog/CategoryDialog.html');
    }

    protected function exportAction()
    {
        wa()->getStorage()->close();
        if ((int) ini_get('max_execution_time') < 280) {
            set_time_limit(280);
        }

        $fd = fopen('php://temp', 'r+');
        fwrite($fd, join(';', [
            _w('Contact ID'),
            _w('Loyalty card code'),
            _w('First name'),
            _w('Last name'),
            _w('Email'),
            _w('Phone'),
            _w('Total spent').' ('._w('LTV').')',
            _w('Affiliate bonus'),
            _w('Number of orders'),
            _w('Registered'),
        ])."\n");

        $processBatch = function() use (&$batch, $fd) {
            // make sure contact_ids in batch are unique
            $batch = array_keys(array_flip($batch));

            $collection = new shopCustomersCollection('id/'.join(',', $batch), [
                'transform_phone_prefix' => 'all_domains'
            ]);
            $rows = $collection->getCustomers('id,id_code,name,firstname,middlename,lastname,email,phone,total_spent,affiliate_bonus,number_of_orders,registered', 0, count($batch));
            $phone_field_obj = waContactFields::get('phone');
            foreach ($rows as $row) {

                $phone = ifset($row, 'phone', 0, '');
                if ($phone) {
                    $phone = $phone_field_obj->format($row['phone'][0], 'value');
                }

                fwrite($fd, join(';', [
                    $row['id'],
                    $row['id_code'],
                    $row['firstname'],
                    $row['lastname'],
                    ifset($row, 'email', 0, ''),
                    $phone,
                    ifempty($row['total_spent'], '0.0'),
                    ifempty($row['affiliate_bonus'], '0.0'),
                    ifempty($row['number_of_orders'], 0),
                    $row['registered'],
                ])."\n");
            }
            $batch = [];
        };

        foreach ($this->getContactIds() as $id) {
            $batch[] = $id;
            if (count($batch) >= 100) {
                $processBatch();
            }
        }
        if ($batch) {
            $processBatch();
        }

        $file_size = ftell($fd);

        $response = wa()->getResponse();
        $response->setStatus(200);
        $response->addHeader("Content-type", 'text/csv');
        $response->addHeader("Content-Disposition", 'attachment; filename="customers.csv"');
        $response->addHeader("Accept-Ranges", "bytes");
        $response->addHeader("Content-Length", $file_size);
        $response->addHeader("Expires", "0");
        $response->addHeader("Cache-Control", "no-cache, must-revalidate");
        $response->addHeader("Pragma", "public");
        $response->addHeader("Connection", "close");
        $response->sendHeaders();

        rewind($fd);
        fpassthru($fd);
        fclose($fd);
        exit;
    }

    protected function addIdCodeAction()
    {
        wa()->getStorage()->close();
        if ((int) ini_get('max_execution_time') < 280) {
            set_time_limit(280);
        }

        $shop_customer_model = new shopCustomerModel();

        $batch = [];
        $total_count = 0;
        $updated_count = 0;

        $processBatch = function() use ($shop_customer_model, &$batch, &$total_count, &$updated_count) {
            // make sure contact_ids in batch are unique
            $batch = array_keys(array_flip($batch));
            $total_count += count($batch);

            // make sure shop_customer records exist (if wa_contact exists)
            try {
                $shop_customer_model->createFromContacts($batch);

                // Generate codes
                $codes = $shop_customer_model->getAvailableIdCodeBatch($batch);

                // Write codes to DB (only update existing customers without code)
                foreach ($codes as $contact_id => $code) {
                    try {
                        $res = $shop_customer_model->updateByField([
                            'contact_id' => $contact_id,
                            'id_code' => null,
                        ], [
                            'id_code' => $code,
                        ], null, true);
                        $updated_count += $res->affectedRows();
                    } catch (Throwable $e) {
                    }
                }
            } catch (Throwable $e) {
            }
            $batch = [];
        };

        foreach ($this->getContactIds() as $id) {
            $batch[] = $id;
            if (count($batch) >= 50) {
                $processBatch();
            }
        }
        if ($batch) {
            $processBatch();
        }

        $this->displayJson([
            'contacts_processed' => $total_count,
            'contacts_updated' => $updated_count,
            'message' => _w('%d loyalty card issued.', '%d loyalty cards issued.', $updated_count),
        ]);
    }

    protected function addCategoryAction()
    {
        $this->actionCategory('add');
    }

    protected function removeCategoryAction()
    {
        $this->actionCategory('remove');
    }

    protected function actionCategory($action)
    {
        $category_ids = waRequest::request('category_id', [], waRequest::TYPE_ARRAY_INT);
        if (!$category_ids) {
            return $this->displayJson(null, [
                'error' => 'category_id_required',
                'error_description' => 'Category ID or list of IDs is required',
            ]);
        }

        wa()->getStorage()->close();
        if ((int) ini_get('max_execution_time') < 280) {
            set_time_limit(280);
        }

        $contact_category_model = new waContactCategoryModel();
        $categories = $contact_category_model->getById($category_ids);
        $category_ids = array_keys($categories);
        if (!$category_ids) {
            return $this->displayJson(null, [
                'error' => 'category_not_found',
                'error_description' => 'Unknown contact category',
            ]);
        }

        $contact_categories_model = new waContactCategoriesModel();

        $total_count = 0;
        $batch = [];
        foreach ($this->getContactIds() as $id) {
            $batch[] = $id;
            if (count($batch) >= 100) {
                $total_count += count($batch);
                if ($action === 'remove') {
                    $contact_categories_model->remove($batch, $category_ids);
                } else if ($action === 'add') {
                    $contact_categories_model->add($batch, $category_ids);
                }
                $batch = [];
            }
        }
        if ($batch) {
            $total_count += count($batch);
            if ($action === 'remove') {
                $contact_categories_model->remove($batch, $category_ids);
            } else if ($action === 'add') {
                $contact_categories_model->add($batch, $category_ids);
            }
        }

        $categories_count = [];
        foreach (shopCustomer::getAllCategories() as $c) {
            $categories_count[$c['id']] = $c['cnt'];
        }

        $this->displayJson([
            'contacts_processed' => $total_count,
            'categories_count' => $categories_count,
        ]);
    }

    protected function getContactIds(): iterable
    {
        if ($this->contact_ids !== null) {
            return (array) $this->contact_ids;
        }

        $collection_hash = waRequest::request('hash', null, waRequest::TYPE_STRING);
        if ($collection_hash === null) {
            // support same parameters as ?module=customers&action=list : filter_id, category, type, search, only_customers
            $list_action = new shopCustomersListAction();
            $collection_hash = $list_action->getHash();
            if ($collection_hash === 'all') {
                return []; // 'all' is only supported by explicitly providing hash=all
            }
        }

        return $this->getIdsFromCollection($collection_hash);
    }

    protected function getIdsFromCollection(string $collection_hash)
    {
        if ($collection_hash === 'all') {
            return (function() {
                try {
                    $rows = (new waModel())->query("SELECT id FROM wa_contact");
                    foreach ($rows as $r) {
                        yield (int) $r['id'];
                    }
                } finally {
                    unset($rows);
                }
            })();
        }

        $collection = new shopCustomersCollection($collection_hash, array(
            'transform_phone_prefix' => 'all_domains'
        ));

        $total = $collection->count();
        return array_map(function($r) {
            return (int) $r['id'];
        }, $collection->getCustomers('id', 0, $total));
    }
}
