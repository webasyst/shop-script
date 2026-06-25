<?php
/**
 * Implements sales channel type 'telegram:<id>'
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
        $banner_promos_map = $this->getBannerPromosMap($storefronts);
        $banner_promos = ifset($banner_promos_map, $storefront, []);

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
                'banner_promos'     => $banner_promos,
                'banner_promos_map' => $banner_promos_map,
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

        return array_intersect_key($params, [
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

            if ($block['block_type'] === 'productlist') {
                $result[] = [
                    'block_type' => 'productlist',
                    'set_id' => (string) ifset($block, 'set_id', ''),
                ];
                continue;
            }

            $result[] = $block;
        }

        return $result;
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
