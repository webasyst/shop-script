<?php

/**
 * Class shopRedirectPlugin
 */
class shopRedirectPlugin extends shopPlugin
{
    private static $demo_product = array(
        'url'         => '%product_url%',
        'category_id' => true,
    );

    private static $demo_category = array(
        'url'      => '%category_url%',
        'full_url' => '%category/full/url%',
    );

    /**
     * @param waException $params
     */
    public function frontendError($params)
    {
        if ($params->getCode() == 404) {
            $routing = wa()->getRouting();
            $url = $routing->getCurrentUrl();
            $redirect = null;
            $template = (array)$this->getSettings('template');
            $get = waRequest::get();
            foreach (self::settingsTemplates(false) as $key => $info) {

                if (!empty($template[$key])) {
                    $method = 'redirect'.ucfirst($key);
                    if (method_exists($this, $method) && ($redirect = $this->{$method}($url, $get, $routing))) {
                        break;
                    }
                }
            }

            if ($redirect === null) {
                $path = $url.($get ? '?'.http_build_query($get) : '');
                $redirect = $this->redirectCustom($path);
            }
            if ($redirect) {
                $this->redirect($redirect);
            }
        }
    }

    public function getControls($params = array())
    {
        waHtmlControl::registerControl('RedirectControl', array($this, 'settingRedirectControl'));
        return parent::getControls($params);
    }

    public function saveSettings($settings = array())
    {
        foreach (ifset($settings['custom'], array()) as $id => $value) {
            if (empty($value['pattern']) || empty($value['replacement'])) {
                unset($settings['custom'][$id]);
            }
        }
        $settings['custom'] = array_values($settings['custom']);
        parent::saveSettings($settings);
    }

    public function settingRedirectControl($name, $params = array())
    {
        $control = '';
        $default_rule = array(
            'pattern'     => '',
            'regex'       => false,
            'replacement' => '',
        );
        if (!isset($params['value']) || !is_array($params['value'])) {
            $params['value'] = array();
        }
        $params['value'][] = $default_rule;

        unset($params['options']);
        foreach ($params as $field => $param) {
            if (strpos($field, 'wrapper')) {
                unset($params[$field]);
            }
        }


        waHtmlControl::addNamespace($params, $name);
        $template = <<<HTML
<tr>
    <td>%s</td>
    <td><span class="hint">%s</span></td>
    <td>%s</td>
    <td>&rarr;</td>
    <td>%s</td>
    <td><a href="#/redirect/delete/" class="inline-link js-action"><i class="icon16 delete"></i></a></td>
</tr>
HTML;

        $params = array_merge(
            $params,
            array(
                'description'   => null,
                'title_wrapper' => false,
                'title'         => null,
            )
        );

        $control .= "<table class = \"zebra\">";

        $control .= "<tfoot>";
        $control_row = <<<HTML
<tr>
    <td colspan="6"><a href="#/redirect/add/" class="inline-link js-action"><i class="icon16 add"></i> %s</a></td>
</tr>
HTML;

        $control .= sprintf($control_row, _wp('Add rule'));

        $frontend_urls = $this->getFrontendUrls();
        if (false) {
            $urls = array();
            foreach ($frontend_urls as &$frontend_url) {
                $frontend_url = htmlspecialchars($frontend_url, ENT_QUOTES, waHtmlControl::$default_charset);
                $urls[] = sprintf('<span class="hint">%s</span>', $frontend_url);
                unset($frontend_url);
            }
            $control .= sprintf('<tr><td colspan="6"> %s</td></tr>', implode('<br/>', $urls));
        }

        $control .= "</tfoot>";
        $control .= "<tbody>";
        $redirect_template = '<span class="hint">%s</span>';


        foreach ($params['value'] as $id => $rule) {
            $rule_params = $params;
            waHtmlControl::addNamespace($rule_params, $id);
            $value = isset($params['value'][$id]) ? $params['value'][$id] : $default_rule;

            $pattern = waHtmlControl::getControl(waHtmlControl::INPUT, 'pattern', array_merge(
                $rule_params,
                array(
                    'description' => sprintf('<span class="hint">%s</span>', reset($frontend_urls)),
                    'value'       => ifset($value['pattern'], ''),
                    'placeholder' => '/auxpage/*/',
                    'class'       => 'long',
                )
            ));

            $regex = waHtmlControl::getControl(waHtmlControl::CHECKBOX, 'regex', array_merge(
                $rule_params,
                array(
                    'title' => 'regex',
                    'value' => ifset($value['regex']),
                )
            ));

            $redirect = $this->redirectCustom($value['pattern'], true);
            if ($redirect === null) {
                $hint = sprintf($redirect_template, '—');
            } else {
                $hint = htmlspecialchars($redirect, ENT_QUOTES, waHtmlControl::$default_charset);
                $hint = sprintf($redirect_template, $hint);
            }


            $replacement = waHtmlControl::getControl(waHtmlControl::INPUT, 'replacement', array_merge(
                $rule_params,
                array(
                    'placeholder' => '/*/',
                    'value'       => ifset($value['replacement'], ''),
                    'description' => $hint,
                )
            ));

            $control .= sprintf($template, '<i class="icon16 sort"></i>', $regex, $pattern, $replacement);
        }
        $control .= "</tbody>";


        $control .= "</table><script type='text/javascript'>";
        $control .= <<<JS
$('table.zebra ').sortable({
                distance: 5,
                opacity: 0.75,
                items: '>tbody>tr',
                axis: 'y',
                containment: 'parent',
                handle:'i.sort',
                tolerance: 'pointer'
            });
JS;
        $control .= '</script>';

        return $control;
    }

    public function frontendSearch()
    {
        if (waRequest::get('searchstring') && !waRequest::get('query')) {
            $this->redirect('?query='.waRequest::get('searchstring'), 301);
        }
    }

    public static function settingsTemplates($description = true)
    {
        $templates = array(
            'webasyst'    => array(
                'value' => 'webasyst',
                'title' => 'WebAsyst Shop-Script',
            ),
            'opencart'    => array(
                'value' => 'opencart',
                'title' => 'OpenCart',
            ),
            'insales'     => array(
                'value' => 'insales',
                'title' => 'InSales',
            ),
            'simpla'      => array(
                'value' => 'simpla',
                'title' => 'Simpla',
            ),
            'phpshop'     => array(
                'value' => 'phpshop',
                'title' => 'PhpShop',
            ),
            'magento'     => array(
                'value' => 'magento',
                'title' => 'Magento',
            ),
            'woocommerce' => array(
                'value' => 'woocommerce',
                'title' => 'WooCommerce',
            ),
        );
        if ($description) {

            $rules = array(
                'webasyst'    => array(
                    'index.php?productID=%product_id%',
                    'index.php?categoryID=%category_id%%',
                ),
                'insales'     => array(
                    'collection/%category_slug%',
                    'collection/%category_slug%/product/%product_slug%',
                    'page/%page_url%',
                ),
                'opencart'    => array(
                    'index.php?route=product/category&path=%category_id%',
                    //'index.php?route=product/category&path=%category_id%&sort=p.price&order=ASC&limit=50',
                    'index.php?route=product/product&product_id=%product_id%',
                    //'index.php?route=product/product&path=%category_id%&product_id=%product_id%&sort=p.price
                    //&order=ASC&limit=15',
                ),
                'simpla'      => array(
                    'products/%product_slug%',
                    'catalog/%category_slug%',
                ),
                'phpshop'     => array(//TODO

                ),
                'magento'     => array(
                    'index.php/%product_url%.html',
                    '%product_url%.html',
                ),
                'woocommerce' => array(
                    'product/%product_url%/',
                    'product-category/%category/full/url%/'
                ),
            );

            $instance = wa('shop')->getPlugin('redirect');
            /**
             * @var shopRedirectPlugin $instance
             */

            $routing = wa()->getRouting();
            $base = wa()->getRouteUrl('shop/frontend');
            foreach ($templates as $key => &$template) {
                $method = 'redirect'.ucfirst($key);
                if (method_exists($instance, $method)) {
                    if (isset($rules[$key])) {
                        $template['description'] = '<ul>';
                        foreach ($rules[$key] as $source) {
                            $get = array();
                            parse_str(parse_url($source, PHP_URL_QUERY), $get);
                            $url = parse_url($source, PHP_URL_PATH);
                            $redirect = $instance->{$method}($url, $get, $routing, true);
                            $replace = sprintf('<li>%s &rarr; %s</li>', $base.$source, ifempty($redirect, '—'));

                            $template['description'] .= preg_replace('@(%[\w/]+?%)@', '<b>$1</b>', $replace);
                        }
                        $template['description'] .= '<ul>';
                    }
                } else {
                    unset($templates[$key]);
                }
                unset($template);
            }
        }
        return $templates;
    }

    private function redirect($url)
    {
        wa()->getResponse()->redirect($url, 301);
    }

    /**
     * @return shopCategoryModel
     */
    private function categoryModel()
    {
        static $model;
        if (empty($model)) {
            $model = new shopCategoryModel();
        }
        return $model;
    }

    /**
     * @return shopProductModel
     */
    private function productModel()
    {
        static $model;
        if (empty($model)) {
            $model = new shopProductModel();
        }
        return $model;
    }

    /**
     * @return shopPageModel
     */
    private function pageModel()
    {
        static $model;
        if (empty($model)) {
            $model = new shopPageModel();
        }
        return $model;
    }

    /**
     * @param string $url
     * @param bool $demo
     * @return string
     */
    private function redirectCustom($url, $demo = false)
    {
        $redirect = null;
        foreach ((array)$this->getSettings('custom') as $custom) {
            $custom = (array)$custom;
            if (isset($custom['pattern']) && ($custom['pattern'] !== '')) {
                if (empty($custom['regex'])) {
                    $pattern = sprintf('@^%s$@', preg_quote($custom['pattern'], '@'));
                    $pattern = str_replace(array('\*'), array('(.+)'), $pattern);
                    $count = 0;
                    while (($offset = strpos($custom['replacement'], '*')) !== false) {
                        $custom['replacement'] = substr($custom['replacement'], 0, $offset)
                            .'$'
                            .(++$count).substr($custom['replacement'], $offset + 1);
                    }

                } else {
                    $pattern = sprintf('@^%s$@', preg_replace('/@/', '\@', $custom['pattern']));
                }

                if ($match = preg_match($pattern, $url)) {
                    if ($redirect = preg_replace($pattern, $custom['replacement'], $url)) {
                        if ($demo) {
                            if (strpos($redirect, '/') !== 0) {
                                $redirect = $url.$redirect;
                                $base = wa()->getRouteUrl('shop/frontend', array(), true);
                                $redirect = $base.$redirect;
                            } elseif (strpos($redirect, '/') === 0) {
                                $base = wa()->getRootUrl(true);
                                $redirect = $base.ltrim($redirect, '/');
                            } else {
                                $base = wa()->getRouteUrl('shop/frontend', array(), true);
                                $redirect = $base.$redirect;
                            }

                        }
                    }
                }

                if ($redirect !== null) {
                    break;
                }
            }
        }
        return $redirect;
    }


    /**
     * @param string $url
     * @param array $get
     * @param waRouting $routing
     * @param bool $demo
     * @return string
     */
    private function redirectWebasyst($url, $get, $routing, $demo = false)
    {
        $redirect = null;
        if ($url == 'index.php') {
            if ($id = ifset($get['productID'])) {
                $p = $demo ? self::$demo_product : $this->productModel()->getById($id);
                if ($p) {
                    $params = array('product_url' => $p['url']);
                    if ($p['category_id']) {
                        $c = $demo ? self::$demo_category : $this->categoryModel()->getById($p['category_id']);
                        $params['category_url'] = $c['full_url'];
                    }
                    $redirect = wa()->getRouteUrl('shop/frontend/product', $params);
                }
            } elseif ($id = ifset($get['categoryID'])) {
                $c = $demo ? self::$demo_category : $this->categoryModel()->getById($id);
                if ($c) {
                    $redirect_params = array(
                        'category_url' => waRequest::param('url_type') == 1 ? $c['url'] : $c['full_url']
                    );
                    $redirect = wa()->getRouteUrl('shop/frontend/category', $redirect_params);
                }
            }
        } elseif (substr($url, 0, 8) == 'product/' && waRequest::param('url_type') != 1) {
            $url = substr($url, 8);
            $url_parts = explode('/', $url);
            if ($this->productModel()->getByField('url', $url_parts[0])) {
                $redirect = wa()->getRootUrl(false, true).$routing->getRootUrl().$url;
            }
        } elseif (substr($url, 0, 9) == 'category/' && waRequest::param('url_type') != 1) {
            $url = substr($url, 9);
            if ($c = $this->categoryModel()->getByField('full_url', rtrim($url, '/'))) {
                $route = $routing->getDomain(null, true).'/'.$routing->getRoute('url');
                $cat_routes_model = new shopCategoryRoutesModel();
                $routes = $cat_routes_model->getRoutes($c['id']);
                if (!$routes || in_array($route, $routes)) {
                    $redirect = wa()->getRootUrl(false, true).$routing->getRootUrl().$url;
                }
            }
        }
        //TODO redirect to aux_pages
        //TODO redirect to news
        return $redirect;
    }


    /**
     * @param string $url
     * @param array $get
     * @param waRouting $routing
     * @param bool $demo
     * @return string
     */
    private function redirectSimpla($url, $get, $routing, $demo = false)
    {
        $redirect = null;
        if (substr($url, 0, 9) == 'products/') {
            # products/%product_slug%
            $url = substr($url, 9);
            $url_parts = explode('/', $url);
            if ($p = $demo ? self::$demo_product : $this->productModel()->getByField('url', $url_parts[0])) {
                $params = array(
                    'product_url' => $p['url'],
                );
                if ($p['category_id']) {
                    $c = $demo ? self::$demo_category : $this->categoryModel()->getById($p['category_id']);
                    $params['category_url'] = $c['full_url'];
                }
                $redirect = wa()->getRouteUrl('shop/frontend/product', $params);
            }
        } elseif (substr($url, 0, 8) == 'catalog/') {
            # catalog/%category_slug%
            $url = substr($url, 8);
            if ($demo) {
                $redirect_params = array(
                    'category_url' => $url,
                );
                $redirect = wa()->getRouteUrl('shop/frontend/category', $redirect_params);
            } else {
                if ($c = $this->categoryModel()->getByField('full_url', rtrim($url, '/'))) {
                    $route = $routing->getDomain(null, true).'/'.$routing->getRoute('url');
                    $cat_routes_model = new shopCategoryRoutesModel();
                    $routes = $cat_routes_model->getRoutes($c['id']);
                    if (!$routes || in_array($route, $routes)) {
                        $redirect = wa()->getRootUrl(false, true).$routing->getRootUrl().$url;
                    }
                }
            }
        }
        return $redirect;
    }


    /**
     * @param string $url
     * @param array $get
     * @param waRouting $routing
     * @param bool $demo
     * @return string
     */
    private function redirectOpenCart($url, $get, $routing, $demo = false)
    {
        $redirect = null;
        if ($url == 'index.php') {
            switch (ifset($get['route'])) {
                case 'product/category':
                    if ($c = $demo ? self::$demo_category : $this->categoryModel()->getById(ifset($get['path']))) {
                        $redirect = $this->getCategoryUrl($c);
                    }
                    //TODO add support for get params sort|order|page

                    break;
                case 'product/product':
                    if ($p = $demo ? self::$demo_product : $this->productModel()->getById(ifset($get['product_id']))) {
                        $redirect = $this->getProductUrl($p, $demo);
                    }
                    break;
                default:
                    break;
            }
        }
        return $redirect;
    }


    /**
     * @param string $url
     * @param array $get
     * @param waRouting $routing
     * @param bool $demo
     * @return string
     */
    private function redirectInSales($url, $get, $routing, $demo = false)
    {
        $redirect = null;
        if (preg_match('@^/?collection/(.+)$@', $url, $matches)) {
            if (preg_match('@^([^/])/product/(.+)$@', $matches[1], $product_matches)) {
                if ($p = $demo ? self::$demo_product : $this->productModel()->getByField('url', $product_matches[2])) {
                    $redirect = $this->getProductUrl($p, $demo);
                }
            } else {
                if ($c = $demo ? self::$demo_category : $this->categoryModel()->getByField('url', $matches[1])) {
                    $redirect = $this->getCategoryUrl($c);
                }
            }
        } elseif (preg_match('@^/?page/([^/]+)$@', $url, $matches)) {
            if ($p = $demo ? array('url' => '%page_url%') : $this->pageModel()->getByField('url', $matches[1])) {
                $redirect = $this->getPageUrl($p, $demo);
            }
        }

        return $redirect;
    }

    private function redirectWoocommerce($url, $get, $routing, $demo = false)
    {
        $redirect = null;
        if (preg_match('@^product/([^/]+)/$@', $url, $product_matches)) {
            if ($p = $demo ? self::$demo_product : $this->productModel()->getByField('url', $product_matches[1])) {
                $redirect = $this->getProductUrl($p, $demo);
            }
        } elseif (preg_match('@^product-category/(.*?)([^/]+)/$@', $url, $matches)) {
            if ($c = $demo ? self::$demo_category : $this->categoryModel()->getByField('url', $matches[2])) {
                $redirect = $this->getCategoryUrl($c);
            }
        }
        return $redirect;
    }

    private function redirectMagento($url, $get, $routing, $demo = false)
    {
        $redirect = null;
        if (preg_match('@^(index.php/)?([^/]+)\.html$@', $url, $product_matches)) {
            if ($p = $demo ? self::$demo_product : $this->productModel()->getByField('url', $product_matches[2])) {
                $redirect = $this->getProductUrl($p, $demo);
            }
        }
        return $redirect;
    }

    private function getProductUrl($p, $demo = false)
    {
        $params = array('product_url' => $p['url']);
        if (!empty($p['category_id']) || $demo) {
            $c = $demo ? self::$demo_category : $this->categoryModel()->getById($p['category_id']);
            $params['category_url'] = waRequest::param('url_type') == 1 ? $c['url'] : $c['full_url'];
        }
        return wa()->getRouteUrl('shop/frontend/product', $params);
    }

    /**
     * @param $p
     * @param bool $demo
     * @return string
     */

    private function getPageUrl($p, $demo = false)
    {
        return wa()->getRouteUrl('shop/frontend').$p['url'].'/';
    }

    private function getCategoryUrl($c)
    {
        $params = array(
            'category_url' => waRequest::param('url_type') == 1 ? $c['url'] : $c['full_url'],
        );
        return wa()->getRouteUrl('shop/frontend/category', $params);
    }

    private function getFrontendUrls()
    {
        $frontend_urls = array();
        $routing = wa()->getRouting();
        $current_route = $routing->getRoute();
        $current_domain = $routing->getDomain();
        $domain_routes = $routing->getByApp($this->app_id);
        foreach ($domain_routes as $domain => $routes) {
            foreach ($routes as $r) {
                if (!empty($r['private'])) {
                    continue;
                }
                $routing->setRoute($r, $domain);

                $frontend_urls[] = $routing->getUrl('/frontend', array(), true);
            }
        }
        $routing->setRoute($current_route, $current_domain);
        return $frontend_urls;
    }
}
