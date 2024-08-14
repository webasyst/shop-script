<?php

/**
 * Class shopProdPageSaveController
 *
 * Контроллер сохранения/редактирования Подстраниц в обновленном редакторе товаров
 */
class shopProdPageSaveController extends waJsonController
{
    /** @var shopProductPagesModel */
    private $pages_model;

    /** @var shopProductModel */
    private $product_model;

    /**
     * shopProdPageSaveController constructor.
     */
    public function __construct()
    {
        $this->pages_model   = new shopProductPagesModel();
        $this->product_model = new shopProductModel();
    }

    /**
     * @throws waException
     */
    public function execute()
    {
        $product_id = waRequest::post('product_id', null, waRequest::TYPE_INT);
        $page       = waRequest::post('page', [], waRequest::TYPE_ARRAY);
        $page       = $page + array_fill_keys(['id', 'product_id', 'status', 'url', 'name', 'title', 'content', 'description', 'keywords'], null);

        if (empty($product_id)) {
            /**
             * Случай если ID редактируемого товара не задан.
             * Если товар новый, то при переходе на вкладку 'Подстраницы'
             * товар сохраняется и $product_id становится известным.
             */
            $this->errors[] = _w('Unknown product');
            return;
        }

        $product = $this->product_model->getById($product_id);
        if (!$product) {
            $this->errors[] = [
                'id' => 'not_found',
                'text' => _w('Product not found.'),
            ];
            return;
        }
        if (!$this->product_model->checkRights($product)) {
            /** check rights */
            throw new waException(_w('Access denied'));
        }

        $page['product_id'] = $product_id;
        $page['status']     = empty($page['status']) ? 0 : 1;
        $page['url']        = trim($page['url'], "/ \n\r\t\v");
        if (empty($page['url']) && $page['url'] !== '0') {
            $this->errors[] = [
                'id'   => 'url_required',
                'text' => _w('The URL is a required field.')
            ];
            return;
        }

        $all_pages = $this->pages_model->getPages($product_id);
        $pages     = array_combine(array_column($all_pages, 'id'), array_column($all_pages, 'url'));

        if (empty($page['name'])) {
            /** дефолтное 'Название подстраницы', если не задано */
            $page['name'] = '('._ws('no-title').')';
        }

        if (empty($page['id'])) {
            /** случай добавления новой подстраницы (ID такой страницы не передается) */
            if (in_array($page['url'], $pages)) {
                /** проверка на уникальность сохраняемого URL */
                $this->errors[] = [
                    'id'   => 'url_required',
                    'text' => _w('The specified URL already exists.')
                ];
                return;
            }

            try {
                $page_id = $this->pages_model->add($page);
                if (empty($page_id)) {
                    $this->errors[] = _w('Error saving product page');
                    return;
                }
            } catch (waDbException $dbe) {
                if ($dbe->getCode() === 1366) {
                    $this->errors[] = ['id' => 'pages', 'text' => _w('Enable the emoji support in system settings.')];
                    return;
                } else {
                    throw $dbe;
                }
            }
        } else {
            /** случай обновления данных о подстранице */

            if (!empty($pages[$page['id']])) {
                unset($pages[$page['id']]);
            }
            if (in_array($page['url'], $pages)) {
                /** проверка на уникальность сохраняемого URL */
                $this->errors[] = [
                    'id'   => 'url_required',
                    'text' => _w('The specified URL already exists.')
                ];
                return;
            }

            try {
                if (!$this->pages_model->update($page['id'], $page)) {
                    $this->errors[] = _w('Error saving product page');
                    return;
                }
            } catch (waDbException $dbe) {
                if ($dbe->getCode() === 1366) {
                    $this->errors[] = ['id' => 'pages', 'text' => _w('Enable the emoji support in system settings.')];
                    return;
                } else {
                    throw $dbe;
                }
            }
            $page_id = $page['id'];
        }
        $this->logAction('product_edit', $product_id);

        unset($page);
        $page = $this->pages_model->getById($page_id);
        $page['name'] = htmlspecialchars($page['name']);

        $this->response = $page;
    }
}
