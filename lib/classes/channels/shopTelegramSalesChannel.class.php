<?php
/**
 * Implements sales channel type 'telegram:<id>'
 * Storefront via Telegram messenger bot.
 */
class shopTelegramSalesChannel extends shopSalesChannelType implements shopSalesChannelWaidInterface
{
    protected function getFormFieldsConfig($values = []): array
    {
        $product_sets = array_map(function($set) {
            return [
                'id' => (string) $set['id'],
                'name' => $set['name'],
            ];
        }, (new shopSetModel())->getAll());

        $storefronts = array_map(static function($storefront) {
            return $storefront['url'];
        }, shopStorefrontList::getAllStorefronts(true));

        $storefront = ifset($values, 'storefront', '');
        $catalog_categories = $this->getCatalogCategories($storefront);
        $banner_promos_map = $this->getBannerPromosMap($storefronts);
        $banner_promos = ifset($banner_promos_map, $storefront, []);
        $coupons = $this->getHomepageCouponOptions();

        $fields = [
            'storefront'       => array(
                'value'        => '',
                'title'        => _w('Storefront'),
                'description'  => _w('A mini-app is linked to a storefront to utilize its basic settings such as product types & listings, active marketing campaigns, and more. Headless API will be enabled for the storefront you select.'),
                'control_type' => waHtmlControl::SELECT,
                'options'      => array_map(function($s) {
                    return ['value' => $s['url'], 'title' => $s['url_decoded']];
                }, shopStorefrontList::getAllStorefronts(true)),
            ),

            'core_section' => array(
                'value'        => _w('Colors'),
                'title'        => '',
                'class'        => 'bold',
                'description'  => _w('Customize the mini-app layout and colors to align with your branding.'),
                'control_type' => waHtmlControl::TITLE,
                'custom_control_wrapper' => '<!-- %s --><div>%s %s</div>',
                'custom_description_wrapper' => '<p class="small">%s</p>',
            ),

            'accent_color'     => array(
                'value'        => '#901010',
                'title'        => _w('Brand color'),
                'description'  => _w('Primary accent color for all action buttons'),
                'control_type' => waHtmlControl::COLORPICKER,
                'options' => [
                    '#A538DC' => [
                        'ru_RU' => 'Сиреневый',
                        'en_US' => 'Violet',
                    ],
                    '#BC2192' => [
                        'ru_RU' => 'Розовый',
                        'en_US' => 'Pink',
                    ],
                    '#BA2621' => [
                        'ru_RU' => 'Красный',
                        'en_US' => 'Red',
                    ],
                    '#B37120' => [
                        'ru_RU' => 'Оранжевый',
                        'en_US' => 'Orange',
                    ],
                    '#7D9B1B' => [
                        'ru_RU' => 'Оливковый',
                        'en_US' => 'Olive',
                    ],
                    '#2E941A' => [
                        'ru_RU' => 'Зеленый',
                        'en_US' => 'Green',
                    ],
                    '#178269' => [
                        'ru_RU' => 'Бирюзовый',
                        'en_US' => 'Turquoise',
                    ],
                    '#1D94A6' => [
                        'ru_RU' => 'Голубой',
                        'en_US' => 'Light blue',
                    ],
                    '#516DE0' => [
                        'ru_RU' => 'Синий',
                        'en_US' => 'Blue',
                    ],
                    '#7041DD' => [
                        'ru_RU' => 'Фиолетовый',
                        'en_US' => 'Violet',
                    ],
                ]
            ),
            'background_color_light'     => array(
                'value'        => '#FFFFFF',
                'title'        => _w('Background color (light mode)'),
                'control_type' => waHtmlControl::COLORPICKER,
                'options' => [
                    '#FFFFFF' => [],
                    '#F0F0F0' => [],
                    '#E9E4EC' => [],
                    '#ECE4EA' => [],
                    '#EDE4E3' => [],
                    '#EBE5E0' => [],
                    '#E6E8DE' => [],
                    '#E8EFE7' => [],
                    '#E4ECEA' => [],
                    '#E4EBEC' => [],
                    '#E7E8EE' => [],
                    '#E6E3ED' => []
                ]
            ),
            'background_color_dark'     => array(
                'value'        => '#000000',
                'title'        => _w('Background color (dark mode)'),
                'control_type' => waHtmlControl::COLORPICKER,
                'options' => [
                    '#000000' => [],
                    '#262626' => [],
                    '#28212C' => [],
                    '#261C23' => [],
                    '#261D1C' => [],
                    '#2B2621' => [],
                    '#292B22' => [],
                    '#272C26' => [],
                    '#252D2B' => [],
                    '#252C2D' => [],
                    '#23262E' => [],
                    '#27242D' => []
                ]
            ),

            'products_section' => array(
                'value'        => _w('Products'),
                'title'        => '',
                'class'        => 'bold',
                'description'  => _w('Customize the mini-app product list display and navigation style.'),
                'control_type' => waHtmlControl::TITLE,
                'custom_control_wrapper' => '<!-- %s --><div>%s %s</div>',
                'custom_description_wrapper' => '<p class="small">%s</p>',
            ),
            'border_radius'    => array(
                'value'        => '25',
                'title'        => _w('Border radius'),
                'description'  => _w('Rounded corners for buttons (in pixels)'),
                'control_type' => waHtmlControl::INPUT,
                'class'        => 'number shortest',
            ),
            'products_per_row' => array(
                'value'        => '2',
                'title'        => _w('Products per row'),
                'description'  => _w('Supported values: 1, 2, 3').'<br>'._w('(Mobile only. Not applicable to the wider desktop mode.)'),
                'control_type' => waHtmlControl::INPUT,
                'class'        => 'number shortest',
            ),
            'category_grid'    => array(
                'value'        => '1',
                'title'        => _w('Catalog grid mode'),
                'description'  => _w('When on, category tree navigation will be replaced with a flat root category display with category thumbnails.').' '._w('(Mobile only. Not applicable to the wider desktop mode.)'),
                'control_type' => waHtmlControl::CHECKBOX,
            ),
            'subcategory_grid'    => array(
                'value'        => '1',
                'title'        => _w('Subcategory grid mode'),
                'description'  => _w('Use similar no-tree category navigation for subcategories too.').' '._w('(Mobile only. Not applicable to the wider desktop mode.)'),
                'control_type' => waHtmlControl::CHECKBOX,
            ),

            'misc_section' => array(
                'value'        => _w('Misc'),
                'title'        => '',
                'class'        => 'bold',
                'control_type' => waHtmlControl::TITLE,
                'custom_control_wrapper' => '<!-- %s --><div>%s %s</div>',
                'custom_description_wrapper' => '<p class="small">%s</p>',
            ),
            'locale' => array(
                'value'        => '',
                'title'        => _w('Locale'),
                'description'  => _w('With “Auto”, the storefront locale will depend on the messaging app’s custom user settings.'),
                'control_type' => waHtmlControl::SELECT,
                'options'      => array(
                    'auto' => _w('Auto'),
                    'en'   => _w('English'),
                    'ru'   => _w('Russian'),
                ),
            ),
            'powered_by' => array(
                'value'        => '1',
                'title'        => _w('Powered by'),
                'description'  => sprintf_wp(
                    'Disable to remove the “%s” link within the mini-app (removing the link is available in Shop-Script premium version only).',
                    _w('Created with Shop-Script')
                ),
                'control_type' => waHtmlControl::CHECKBOX,
            ),

            'homepage_section' => array(
                'value'        => _w('Homepage'),
                'title'        => '',
                'class'        => 'bold',
                'control_type' => waHtmlControl::TITLE,
                'custom_control_wrapper' => '<!-- %s --><div>%s %s</div>',
                'custom_description_wrapper' => '<p class="small">%s</p>',
            ),

            'homepage_promos' => array(
                'value'        => '1',
                'title'        => _w('Homepage promos'),
                'description'  => sprintf_wp(
                    'Display promo banners enabled for the selected storefront in <em>%s › %s</em>.',
                    _w('Marketing'),
                    _w('Promos')
                ),
                'control_type' => waHtmlControl::CHECKBOX,
            ),
            'homepage_product_list' => array(
                'value'        => '',
                'title'        => _w('Homepage products'),
                'description'  => sprintf_wp(
                    'Defines featured products displayed on the app’s homepage. Manage product sets in <em>%s › %s</em>.',
                    _w('Products'),
                    _w('Sets')
                ),
                'control_type' => waHtmlControl::SELECT,
                'options'      => array_map(function($s) {
                    return ['value' => $s['id'], 'title' => $s['name']];
                }, (new shopSetModel())->getAll()),
            ),
            'homepage_text_footer' => array(
                'value'        => '',
                'title'        => _w('Homepage footer text'),
                'description'  => _w('Any useful footer text for the app’s homepage. Basic HTML markup is allowed.'),
                'control_type' => waHtmlControl::TEXTAREA,
                'class'        => 'width-100',
            ),
            'homepage_blocks' => array(
                'control_type'      => 'shop_homepage_blocks', // see templates/actions/channels/shop_homepage_blocks.include.html
                'product_sets'      => $product_sets,
                'catalog_categories' => $catalog_categories,
                'banner_promos'     => $banner_promos,
                'banner_promos_map' => $banner_promos_map,
                'coupons'           => $coupons,
                'storefront'        => $storefront,
            ),

            'checkout_section' => array(
                'value'        => _w('Checkout'),
                'title'        => '',
                'class'        => 'bold',
                'description'  => _w('In-app checkout offers a minimized (express) configuration compared to your main site to help you optimize the customer’s mobile device experience and improve conversions.'),
                'control_type' => waHtmlControl::TITLE,
                'custom_control_wrapper' => '<!-- %s --><div>%s %s</div>',
                'custom_description_wrapper' => '<p class="small">%s</p>',
            ),

            'checkout_external' => array(
                'value'        => '',
                'title'        => _w('Disable in-app checkout'),
                'description'  => _w('When on, the checkout button will open your storefront in a browser. No direct checkout within the app will be available. This won’t work well for the conversion but may be required due to legal considerations in your country.'),
                'control_type' => waHtmlControl::CHECKBOX,
            ),
            'checkout_phone' => array(
                'value'        => '1',
                'title'        => _w('Checkout phone'),
                'description'  => _w('The list of required checkout contact fields is minimized for the app compared to the site.'),
                'control_type' => waHtmlControl::CHECKBOX,
            ),
            'checkout_email' => array(
                'value'        => '',
                'title'        => _w('Checkout email'),
                'description'  => _w('The list of required checkout contact fields is minimized for the app compared to the site.'),
                'control_type' => waHtmlControl::CHECKBOX,
            ),
            'checkout_country' => array(
                'value'        => '',
                'title'        => _w('Country'),
                'description'  => _w('Shipping will be restricted to the selected country only. If a global shipping option is selected, customers will be prompted to select a country during the checkout.'),
                'control_type' => waHtmlControl::SELECT,
                'options'      => array_merge([
                        ['value' => '', 'title' => _wp('All countries')],
                    ], array_map(function($c) {
                        return [
                            'value' => $c['iso3letter'],
                            'title' => $c['name'],
                            'disabled' => empty($c['iso3letter']),
                        ];
                    }, (new waCountryModel())->allWithFav()),
                ),
            ),
            'checkout_terms_link' => array(
                'value'        => '',
                'title'        => _w('Checkout terms & privacy agreement'),
                'description'  => _w('A link to a checkout & privacy terms page. If a link is provided, a checkbox with caption “I agree to the terms of service & privacy policy” will be displayed.'),
                'control_type' => waHtmlControl::INPUT,
                'class'        => 'width-100',
            ),

        ];

        $fields = $this->hideFieldsIfBlocks($fields, $values);

        return $fields;
    }

    public function sanitizeAndValidateParams(?int $id, array &$params, $params_mode): array
    {
        $errors = [];
        if ($params_mode == 'set' && empty($params['storefront'])) {
            $errors['storefront'] = [
                'error_description' => _w('This field is required'),
                'field' => 'data[params][storefront]',
            ];
        }

        if (isset($params['storefront'])) {
            $storefronts = array_flip(shopStorefrontList::getAllStorefronts(false));
            if (!isset($storefronts[$params['storefront']])) {
                $errors['storefront'] = [
                    'error_description' => _w('This field is required'),
                    'field' => 'data[params][storefront]',
                ];
            }
        }

        if (array_key_exists('homepage_blocks', $params)) {
            $storefront = ifset($params, 'storefront', null);
            if ($storefront === null && $id > 0) {
                $storefront = (string) (new shopSalesChannelParamsModel())->getOne($id, 'storefront');
            }

            $params['homepage_blocks'] = json_encode(
                $this->normalizeHomepageBlocks($params['homepage_blocks'], (string) $storefront)
            );
        }

        return array_values($errors);
    }

    public function getFormHtml(array $channel): string
    {
        $view = wa('shop')->getView();
        $view->assign([
            'is_waid' => $this->isWaid(),
            'channel' => $channel,
            'form_fields' => $this->getFormFields($channel),
        ]);
        return $view->fetch('file:templates/actions/channels/telegram_channel.include.html');
    }

    public function getPublicStorefrontParams(array $channel): array
    {
        $params = ifset($channel, 'params', []);

        $result = array_intersect_key($params, [
            'accent_color'           => 1,
            'background_color_light' => 1,
            'background_color_dark'  => 1,
            'border_radius'          => 1,
            'products_per_row'       => 1,
            'category_grid'          => 1,
            'subcategory_grid'       => 1,
            'homepage_promos'        => 1,
            'homepage_product_list'  => 1,
            'homepage_text_footer'   => 1,
            'checkout_external'      => 1,
            'checkout_phone'         => 1,
            'checkout_email'         => 1,
            'checkout_country'       => 1,
            'checkout_terms_link'    => 1,
            'locale'                 => 1,
            'powered_by'             => 1,
        ]) + [
            'is_custom_bot' => !empty($params['bot_token']),
            'homepage_blocks' => json_decode(ifempty($params, 'homepage_blocks', '[]')),
        ];

        $result['homepage_blocks'] = $this->enrichStorefrontDependentHomepageBlocks(
            $result['homepage_blocks'],
            (string) ifset($params, 'storefront', '')
        );
        $result['homepage_blocks'] = $this->hydratePublicHomepageBlocks($result['homepage_blocks']);

        return $result;
    }

    public function getWaidChannelParams(array $channel): array
    {
        $store_params = [
            'is_custom_bot' => !empty($channel['params']['bot_token']),
        ];
        if (wa()->getSetting('headless_api_antispam_enabled', false, 'shop')) {
            $store_params['antispam_api_key'] = wa()->getSetting('headless_api_antispam_key', '', 'shop');
        }
        return [
            'https://'.rtrim($channel['params']['storefront'], '/').'/',
            $store_params
        ];
    }

    public function onSave(array $channel)
    {
        // make sure selected storefront has Headless API enabled
        $storefront = ifset($channel, 'params', 'storefront', null);

        $st_info = array_filter(shopStorefrontList::getAllStorefronts(true), function($s) use ($storefront) {
            return $s['url'] === $storefront;
        });
        if (!$st_info) {
            return;
        }
        $st_info = reset($st_info);
        $storefront_mode = ifset($st_info, 'route', 'storefront_mode', '');
        if ($storefront_mode) {
            return; // already enabled
        }

        $path = wa()->getConfig()->getPath('config', 'routing');
        if (file_exists($path) && is_writable($path)) {
            $routes = include($path);
            $domain = $st_info['domain'];
            if (isset($routes[$domain]) && is_array($routes[$domain])) {
                foreach ($routes[$domain] as $id => $route) {
                    if (ifset($route, 'app', null) === 'shop' && $route['url'] === ifset($st_info, 'route', 'url', null)) {
                        $routes[$domain][$id]['storefront_mode'] = 'storefront_api';
                        waUtils::varExportToFile($routes, $path);
                        break;
                    }
                }
            }
        }
    }

    protected function getBannerPromosMap(array $storefronts): array
    {
        $result = [];
        $promo_model = new shopPromoModel();

        foreach (array_unique(array_filter($storefronts, 'strlen')) as $storefront) {
            $result[$storefront] = array_values(array_filter(array_map(function($promo) {
                if (empty($promo['image'])) {
                    return null;
                }

                return [
                    'id' => (int) $promo['id'],
                    'name' => $promo['name'],
                ];
            }, $promo_model->getList([
                'storefront' => $storefront,
                'status' => shopPromoModel::STATUS_ACTIVE,
                'rule_type' => 'banner',
                'with_images' => true,
            ]))));
        }

        return $result;
    }

    protected function normalizeHomepageBlocks($homepage_blocks, string $storefront): array
    {
        if (is_string($homepage_blocks)) {
            $homepage_blocks = json_decode($homepage_blocks, true);
        }

        if (!is_array($homepage_blocks)) {
            return [];
        }

        $banner_promos_map = $this->getBannerPromosMap([$storefront]);
        $storefront_promos = isset($banner_promos_map[$storefront]) && is_array($banner_promos_map[$storefront])
            ? $banner_promos_map[$storefront]
            : [];
        $allowed_promo_ids = array_flip(array_column($storefront_promos, 'id'));
        $allowed_coupon_ids = array_flip(array_column($this->getHomepageCouponOptions(), 'id'));
        $result = [];
        foreach ($homepage_blocks as $block) {
            if (!is_array($block) || empty($block['block_type'])) {
                continue;
            }

            if ($block['block_type'] === 'promo') {
                $selection_mode = ifset($block, 'selection_mode', 'all') === 'selected' ? 'selected' : 'all';
                $normalized_block = [
                    'block_type' => 'promo',
                    'selection_mode' => $selection_mode,
                ];

                if ($selection_mode === 'selected') {
                    $promo_ids = array_values(array_filter(
                        array_map('intval', (array) ifset($block, 'promo_ids', [])),
                        static function($promo_id) use ($allowed_promo_ids) {
                            return $promo_id > 0 && isset($allowed_promo_ids[$promo_id]);
                        }
                    ));
                    $normalized_block['promo_ids'] = $promo_ids;
                }

                $result[] = $normalized_block;
                continue;
            }

            if ($block['block_type'] === 'coupons') {
                $selection_mode = ifset($block, 'selection_mode', 'all') === 'selected' ? 'selected' : 'all';
                $normalized_block = [
                    'block_type' => 'coupons',
                    'selection_mode' => $selection_mode,
                ];

                if ($selection_mode === 'selected') {
                    $coupon_ids = array_values(array_filter(
                        array_map('intval', (array) ifset($block, 'coupon_ids', [])),
                        static function($coupon_id) use ($allowed_coupon_ids) {
                            return $coupon_id > 0 && isset($allowed_coupon_ids[$coupon_id]);
                        }
                    ));
                    $normalized_block['coupon_ids'] = $coupon_ids;
                }

                $result[] = $normalized_block;
                continue;
            }

            if ($block['block_type'] === 'productlist') {
                $products_per_row = (string) ifset($block, 'products_per_row', '');
                if (!in_array($products_per_row, ['1', '2', '3'], true)) {
                    $products_per_row = '';
                }

                $result[] = [
                    'block_type' => 'productlist',
                    'set_id' => (string) ifset($block, 'set_id', ''),
                    'block_title' => trim(strip_tags((string) ifset($block, 'block_title', ''))),
                    'block_subtitle' => trim(strip_tags((string) ifset($block, 'block_subtitle', ''))),
                    'products_per_row' => $products_per_row,
                    'products_single_row_with_scroll' => !empty($block['products_single_row_with_scroll']),
                ];
                continue;
            }

            if ($block['block_type'] === 'links') {
                $links = [];
                foreach ((array) ifset($block, 'links', []) as $link) {
                    $normalized_link = $this->normalizeHomepageLink($link);
                    if ($normalized_link) {
                        $links[] = $normalized_link;
                    }
                }

                if ($links) {
                    $result[] = [
                        'block_type' => 'links',
                        'links' => $links,
                    ];
                }
                continue;
            }

            $result[] = $block;
        }

        return $result;
    }

    protected function getHomepageCouponOptions(): array
    {
        if (!shopDiscounts::isEnabled('coupons')) {
            return [];
        }

        $coupons = (new shopCouponModel())->getActiveCoupons();
        $currencies = (new shopCurrencyModel())->getAll('code');
        $result = [];

        foreach ($coupons as $coupon) {
            if (!shopCouponModel::isEnabled($coupon)) {
               continue;
            }
            $formatted_value = shopCouponModel::formatValue($coupon, $currencies);
            $result[] = [
                'id' => (int) $coupon['id'],
                'code' => (string) $coupon['code'],
                'label' => trim($coupon['code'].' '.$formatted_value),
            ];
        }

        usort($result, static function($a, $b) {
            return strcmp($b['code'], $a['code']);
        });

        return $result;
    }

    protected function getCatalogCategories(string $storefront = ''): array
    {
        $category_model = new shopCategoryModel();
        $categories = $storefront
            ? $category_model->getTree(0, null, false, $storefront)
            : $category_model->getFullTree('id, parent_id, depth, name, status, thumb_ext, edit_datetime, create_datetime');
        $result = [];
        foreach ($categories as $category) {
            if (empty($category['status'])) {
                continue;
            }
            $result[] = [
                'id' => (int) $category['id'],
                'name' => str_repeat('— ', max(0, (int) $category['depth'])).$category['name'],
                'plain_name' => $category['name'],
                'thumb' => shopCategoryHelper::getThumbInfo($category),
            ];
        }
        return $result;
    }

    protected function normalizeHomepageLink($link): ?array
    {
        if (!is_array($link)) {
            return null;
        }

        $type = (string) ifset($link, 'link_type', '');
        if (!in_array($type, ['catalog', 'set', 'category'], true)) {
            return null;
        }

        $result = [
            'link_type' => $type,
        ];

        if ($type === 'set') {
            $set_id = (string) ifset($link, 'set_id', '');
            if ($set_id === '' || !(new shopSetModel())->getById($set_id)) {
                return null;
            }
            $result['set_id'] = $set_id;
        } elseif ($type === 'category') {
            $category_id = (int) ifset($link, 'category_id', 0);
            $category = $category_id > 0 ? (new shopCategoryModel())->getById($category_id) : null;
            if (!$category || empty($category['status'])) {
                return null;
            }
            $result['category_id'] = $category_id;
        }

        $title = trim(strip_tags((string) ifset($link, 'title', '')));
        if ($title !== '') {
            $result['title'] = $title;
        }

        $icon = $this->normalizeHomepageLinkIcon(ifset($link, 'icon', null), $type);
        if ($icon) {
            $result['icon'] = $icon;
        }

        return $result;
    }

    protected function normalizeHomepageLinkIcon($icon, string $link_type): ?array
    {
        if (!is_array($icon) || empty($icon['type'])) {
            return null;
        }

        $type = (string) $icon['type'];
        if ($type === 'fa') {
            $value = (string) ifset($icon, 'value', '');
            return in_array($value, $this->getHomepageLinkFaIcons(), true) ? [
                'type' => 'fa',
                'value' => $value,
            ] : null;
        }

        if ($type === 'category' && $link_type === 'category') {
            $result = ['type' => 'category'];
            $url = trim((string) ifset($icon, 'url', ''));
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            if ($url !== '' && in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL)) {
                $result['url'] = $url;
            }
            return $result;
        }

        if ($type === 'upload') {
            $path = $this->normalizeHomepageLinkIconPath((string) ifset($icon, 'path', ''));
            if (!$path) {
                $path = $this->normalizeHomepageLinkIconUrl((string) ifset($icon, 'url', ''));
            }

            if ($path) {
                return [
                    'type' => 'upload',
                    'path' => $path,
                    'url' => wa()->getDataUrl($path, true, 'shop', true),
                ];
            }
        }

        return null;
    }

    protected function normalizeHomepageLinkIconPath(string $path): string
    {
        $path = trim($path, '/');
        if (!preg_match('~^homepage-link-icons/[a-f0-9]{32}\.(?:jpe?g|png|gif|webp)$~i', $path)) {
            return '';
        }

        return file_exists(wa()->getDataPath($path, true, 'shop', false)) ? $path : '';
    }

    protected function normalizeHomepageLinkIconUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        $prefix = '/wa-data/public/shop/homepage-link-icons/';
        $pos = strpos($path, $prefix);
        if ($pos === false) {
            return '';
        }

        return $this->normalizeHomepageLinkIconPath('homepage-link-icons/'.basename($path));
    }

    protected function enrichStorefrontDependentHomepageBlocks($homepage_blocks, string $storefront = ''): array
    {
        if (!is_array($homepage_blocks)) {
            return [];
        }

        $set_model = new shopSetModel();
        $category_model = new shopCategoryModel();
        $available_category_ids = null;
        if ($storefront !== '') {
            $available_category_ids = [];
            foreach ($this->getCatalogCategories($storefront) as $category) {
                $available_category_ids[(int) $category['id']] = true;
            }
        }
        $result = [];

        foreach ($homepage_blocks as $block) {
            if (!is_object($block)) {
                $result[] = $block;
                continue;
            }

            if (($block->block_type ?? null) !== 'links' || empty($block->links) || !is_array($block->links)) {
                $result[] = $block;
                continue;
            }

            $links = [];
            foreach ($block->links as $link) {
                if (!is_object($link) || empty($link->link_type)) {
                    continue;
                }

                if ($link->link_type === 'catalog') {
                    $link->title = _w('All catalog');
                    if (isset($link->icon) && is_object($link->icon) && ($link->icon->type ?? null) === 'upload' && !$this->refreshHomepageUploadedIconUrl($link->icon)) {
                        unset($link->icon);
                    }
                    $links[] = $link;
                    continue;
                }

                if ($link->link_type === 'set' && !empty($link->set_id)) {
                    $set = $set_model->getById((string) $link->set_id);
                    if (!$set) {
                        continue;
                    }
                    $link->title = $set['name'];
                    if (isset($link->icon) && is_object($link->icon) && ($link->icon->type ?? null) === 'upload' && !$this->refreshHomepageUploadedIconUrl($link->icon)) {
                        unset($link->icon);
                    }
                    $links[] = $link;
                    continue;
                }

                if ($link->link_type === 'category' && !empty($link->category_id)) {
                    $category_id = (int) $link->category_id;
                    if (is_array($available_category_ids) && empty($available_category_ids[$category_id])) {
                        continue;
                    }

                    $category = $category_model->getById($category_id);
                    if (!$category || empty($category['status'])) {
                        continue;
                    }
                    $link->title = $category['name'];
                    $thumb = shopCategoryHelper::getThumbInfo($category);
                    $icon = isset($link->icon) && is_object($link->icon) ? $link->icon : null;
                    if ($icon && ($icon->type ?? null) === 'category') {
                        if ($thumb) {
                            $icon->url = ifset($thumb, 'url96x96', ifset($thumb, 'default', ''));
                        } else {
                            unset($icon->url);
                        }
                    } elseif ($icon && ($icon->type ?? null) === 'upload' && !$this->refreshHomepageUploadedIconUrl($icon)) {
                        unset($link->icon);
                    }
                    $links[] = $link;
                }
            }

            if ($links) {
                $block->links = $links;
                $result[] = $block;
            }
        }

        return $result;
    }

    protected function hydratePublicHomepageBlocks($homepage_blocks): array
    {
        if (!is_array($homepage_blocks)) {
            return [];
        }

        $result = [];
        foreach ($homepage_blocks as $block) {
            if (!is_object($block)) {
                $result[] = $block;
                continue;
            }

            if (($block->block_type ?? null) !== 'coupons') {
                $result[] = $block;
                continue;
            }

            $coupons = $this->getHomepageCouponsForBlock($block);
            if ($coupons) {
                $block->coupons = $coupons;
                $result[] = $block;
            }
        }

        return $result;
    }

    protected function getHomepageCouponsForBlock($block): array
    {
        if (!shopDiscounts::isEnabled('coupons')) {
            return [];
        }

        $coupon_model = new shopCouponModel();
        $coupons = $coupon_model->getActiveCoupons();

        if (($block->selection_mode ?? 'all') === 'selected') {
            $selected_ids = array_flip(array_map('intval', (array) ($block->coupon_ids ?? [])));
            $coupons = array_filter($coupons, static function($coupon) use ($selected_ids) {
                return isset($selected_ids[(int) $coupon['id']]);
            });
        }

        $currencies = (new shopCurrencyModel())->getAll('code');
        $result = [];
        foreach ($coupons as $coupon) {
            if (!shopCouponModel::isEnabled($coupon)) {
                continue;
            }
            $result[] = $this->formatHomepageCoupon($coupon, $currencies);
        }

        usort($result, static function($a, $b) {
            return strcmp($b['code'], $a['code']);
        });

        return $result;
    }

    protected function formatHomepageCoupon(array $coupon, array $currencies): array
    {
        $formatted_value = shopCouponModel::formatValue($coupon, $currencies);
        $scope = $this->getHomepageCouponScope((string) ifset($coupon, 'products_hash', ''));
        $display_title = $this->getHomepageCouponDisplayTitle($coupon, $formatted_value, $scope);

        $result = [
            'id' => (int) $coupon['id'],
            'code' => (string) $coupon['code'],
            'type' => (string) $coupon['type'],
            'value' => (float) $coupon['value'],
            'expire_datetime' => ifempty($coupon, 'expire_datetime', null),
            'formatted_value' => $formatted_value,
            'display_title' => $display_title,
            'scope_type' => $scope['scope_type'],
            'scope_title' => $scope['scope_title'],
        ];

        if (!empty($scope['product_ids'])) {
            $result['product_ids'] = $scope['product_ids'];
        }

        return $result;
    }

    protected function getHomepageCouponDisplayTitle(array $coupon, string $formatted_value, array $scope): string
    {
        if ($coupon['type'] === '$FS') {
            return _w('Free shipping');
        }

        $discount = $coupon['type'] === '%' ? '−'.$formatted_value : '−'.$formatted_value;
        if (!empty($scope['scope_title'])) {
            return $scope['scope_title'].' '.$discount;
        }

        return _w('Discount').' '.$discount;
    }

    protected function getHomepageCouponScope(string $products_hash): array
    {
        $result = [
            'scope_type' => 'all',
            'scope_title' => '',
        ];

        if ($products_hash === '') {
            return $result;
        }

        $hash = shopImportexportHelper::parseHash($products_hash);
        if ($hash['type'] === 'type' && !empty($hash['type_id'])) {
            $type = (new shopTypeModel())->getById((int) $hash['type_id']);
            if ($type) {
                return [
                    'scope_type' => 'type',
                    'scope_title' => $type['name'],
                ];
            }
        }

        if ($hash['type'] === 'set' && !empty($hash['set_id'])) {
            $set = (new shopSetModel())->getById((string) $hash['set_id']);
            if ($set) {
                return [
                    'scope_type' => 'set',
                    'scope_title' => $set['name'],
                ];
            }
        }

        if ($hash['type'] === 'category' && !empty($hash['category_ids'])) {
            $category_ids = array_filter(array_map('intval', explode(',', $hash['category_ids'])));
            $category_id = reset($category_ids);
            $category = $category_id ? (new shopCategoryModel())->getById($category_id) : null;
            if ($category) {
                return [
                    'scope_type' => 'category',
                    'scope_title' => $category['name'],
                ];
            }
        }

        if ($hash['type'] === 'id' && !empty($hash['product_ids'])) {
            $product_ids = array_values(array_filter(array_map('intval', explode(',', $hash['product_ids']))));
            if ($product_ids) {
                return [
                    'scope_type' => 'products',
                    'scope_title' => _w('%d product', '%d products', count($product_ids)),
                    'product_ids' => $product_ids,
                ];
            }
        }

        return $result;
    }

    protected function refreshHomepageUploadedIconUrl($icon): bool
    {
        $path = '';
        if (is_array($icon)) {
            $path = (string) ifset($icon, 'path', '');
        } elseif (is_object($icon)) {
            $path = (string) ($icon->path ?? '');
        }

        $path = $this->normalizeHomepageLinkIconPath($path);
        if ($path) {
            $icon->url = wa()->getDataUrl($path, true, 'shop', true);
            return true;
        }
        return false;
    }

    protected function getHomepageLinkFaIcons(): array
    {
        return [
            'th-large',
            'fire',
            'thumbs-up',
            'bullhorn',
            'tag',
            'award',
            'gem',
            'leaf',
            'certificate',
            'clock',
            'magic',
            'gift',
            'percent',
            'lightbulb',
            'check-circle',
            'recycle',
            'shipping-fast',
        ];
    }

    /**
     * Hide some fields if at least one block
     *
     * @param array $fields
     * @return array
     */
    protected function hideFieldsIfBlocks(array $fields, $values = [])
    {
        $has_blocks = !empty($values['homepage_blocks']) && $values['homepage_blocks'] !== '[]';
        $hidden_field_ids = ['homepage_promos','homepage_product_list','homepage_text_footer'];
        foreach ($fields as $id => &$field) {
            if (in_array($id, $hidden_field_ids)) {
                if (!isset($field['class'])) {
                    $field['class'] = '';
                }
                $field['class'] .= ' hide-if-blocks';
                $field['class'] .= $has_blocks ? ' hide' : '';
            }
        }
        unset($field);

        return $fields;
    }
}
