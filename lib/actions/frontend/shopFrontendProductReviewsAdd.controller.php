<?php

class shopFrontendProductReviewsAddController extends waJsonController
{
    protected $author = null;

    public function __construct()
    {
        $this->author = $this->getUser();
    }

    public function getProduct()
    {
        $product_model = new shopProductModel();
        $product = $product_model->getByField('url', waRequest::param('product_url'));
        if (!$product) {
            throw new waException('Product not found', 404);
        }

        if ($types = waRequest::param('type_id')) {
            if (!in_array($product['type_id'], (array)$types)) {
                throw new waException(_w('Product not found'), 404);
            }
        }

        return $product;
    }

    /**
     * @return bool|void
     * @throws waException
     */
    public function execute()
    {
        $product = $this->getProduct();
        $model = new shopProductReviewsModel();

        $data = $this->getData($product['id']);

        $this->preValidation();
        $this->errors += $model->validate($data);
        $this->errors += $this->checkServiceAgreement();

        if ($this->errors) {
            return false;
        }

        /**
         * Add new review to product. Before save.
         * @param array $data info about user and Review text
         * @param array $product info about product
         *
         * @event frontend_review_add.before
         */
        $params = array(
            'data'    => &$data,
            'product' => $product
        );

        wa()->event('frontend_review_add.before', $params);

        $id = $model->add($data, $data['parent_id']);
        if (!$id) {
            throw new waException("Error in adding review");
        } else {
            $options = [
                'review_id'  => $id,
                'product_id' => $data['product_id']
            ];
            $this->addImage($options);
            $this->logAction('product_review_add', $product['id']);
        }

        /**
         * Add new review to product. After save.
         *
         * @param int $id Id a new review
         * @param array $data info about user and Review text
         * @param array $product info about product
         *
         * @event frontend_review_add.after
         */

        $params = array(
            'id'      => $id,
            'data'    => &$data,
            'product' => $product
        );

        wa()->event('frontend_review_add.after', $params);

        $count = waRequest::post('count', 0, waRequest::TYPE_INT) + 1;

        $this->response = array(
            'id'               => $id,
            'parent_id'        => $this->getParentId(),
            'count'            => $count,
            'html'             => $this->renderTemplate(array(
                'review'        => $model->getReview($id, true),
                'reply_allowed' => true,
                'product'       => $product,
                'ajax_append'   => true
            ),
                'review.html'
            ),
            'review_count_str' => _w(
                    '%d review for ',
                    '%d reviews for ',
                    $count
                ).$product['name']
        );
    }

    private function renderTemplate($assign, $template)
    {
        $theme = waRequest::param('theme', 'default');
        $theme_path = wa()->getDataPath('themes', true).'/'.$theme;
        if (!file_exists($theme_path) || !file_exists($theme_path.'/theme.xml')) {
            $theme_path = wa()->getAppPath().'/themes/'.$theme;
        }

        $template_path = $theme_path . '/' . $template;
        if (!file_exists($template_path)) {
            return '';
        }

        $view = wa()->getView();
        $old_vars = $view->getVars();
        $view->clearAllAssign();
        $view->assign($assign);
        $html = $view->fetch($template_path);
        $view->clearAllAssign();
        $view->assign($old_vars);

        return $html;
    }

    private function getParentId()
    {
        return waRequest::post('parent_id', 0, waRequest::TYPE_INT);
    }

    private function getData($product_id)
    {
        $model = new shopProductReviewsModel();

        $parent_id = $this->getParentId();
        $rate = waRequest::post('rate', null, waRequest::TYPE_INT);
        if (!$product_id) {
            $parent_comment = $model->getById($parent_id);
            $product_id = $parent_comment['product_id'];
        }
        $text = waRequest::post('text', null, waRequest::TYPE_STRING_TRIM);
        $title = waRequest::post('title', null, waRequest::TYPE_STRING_TRIM);

        return array(
                'product_id'   => $product_id,
                'parent_id'    => $parent_id,
                'text'         => $text,
                'title'        => $title,
                'rate'         => $rate && !$parent_id ? $rate : null,
                'datetime'     => date('Y-m-d H:i:s'),
                'status'       => $this->getStatus(),
                'images_count' => count(waRequest::file('images')),
            ) + $this->getContactData();
    }

    protected function getStatus()
    {
        $need_moderation = wa()->getSetting('moderation_reviews');
        $status = shopProductReviewsModel::STATUS_PUBLISHED;
        if ($need_moderation) {
            $status = shopProductReviewsModel::STATUS_MODERATION;
        }
        return $status;
    }

    protected function checkServiceAgreement()
    {
        $agreed = waRequest::request('service_agreement');
        if ($agreed === null) {
            return array();
        }

        wa()->getStorage()->set('shop_review_agreement', !!$agreed);
        if ($agreed) {
            return array();
        } else {
            return array(
                'service_agreement' => _w('Please confirm your agreement'),
            );
        }
    }

    private function getContactData()
    {
        $contact_id = (int)$this->getUser()->getId();
        $adapter = 'user';

        if (!$contact_id) {
            $adapter = waRequest::post('auth_provider', 'guest', waRequest::TYPE_STRING_TRIM);
            if (!$adapter || $adapter == 'user') {
                $adapter = 'guest';
            };
        }

        if ($adapter == 'guest') {
            $data['name'] = waRequest::post('name', '', waRequest::TYPE_STRING_TRIM);
            $data['email'] = waRequest::post('email', '', waRequest::TYPE_STRING_TRIM);
            $data['site'] = waRequest::post('site', '', waRequest::TYPE_STRING_TRIM);
            $this->getStorage()->del('auth_user_data');
        } elseif ($adapter != 'user') {
            $auth_adapters = wa()->getAuthAdapters();
            if (!isset($auth_adapters[$adapter])) {
                $this->errors[] = _w('Invalid auth provider');
            } elseif ($user_data = $this->getStorage()->get('auth_user_data')) {
                $data['name'] = $user_data['name'];
                $data['email'] = '';
                $data['site'] = $user_data['url'];
            } else {
                $this->errors[] = _w('Invalid auth provider data');
            }
        }

        $data['auth_provider'] = $adapter;
        $data['contact_id'] = $contact_id;

        return $data;
    }

    /**
     * @param $options
     * @throws waException
     */
    protected function addImage($options)
    {
        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();

        $review_images_model = new shopProductReviewsImagesModel();
        $image_data = waRequest::post('images_data', [], waRequest::TYPE_ARRAY);
        $files = waRequest::file('images');

        $sizes = $config->getImageSizes();

        foreach ($files as $id => $file) {
            if (!$this->isValidImage($file)) {
                break;
            };
            $options['description'] = ifset($image_data, $id, 'description', '');
            $options['filename'] = $file->name;

            $image = $review_images_model->addImage($file->tmp_name, $options);
            shopImage::generateThumbs($image, $sizes);
        }
    }

    /**
     * @param waRequestFile $file
     * @return bool
     */
    protected function isValidImage($file)
    {
        $allowed = array('jpg', 'jpeg', 'png', 'gif');

        if (!$file->uploaded() || $file->error) {
            return false;
        } elseif (!in_array(strtolower($file->extension), $allowed)) {
            $this->errors[] = _w("Files with extensions *.gif, *.jpg, *.jpeg, *.png are allowed only.");
            return false;
        }
        return true;
    }

    protected function preValidation()
    {
        if (!waRequest::post()) {
            $this->errors[] = _w('Too large total image size. Remove some images or reduce their size with an image processing program before uploading.');
        }

        $allow_image_upload = wa('shop')->getSetting('allow_image_upload');

        if (!$allow_image_upload && waRequest::file('images')->count() > 0) {
            $this->errors[] = _w('Image uploading is disabled.');
        }
    }
}
