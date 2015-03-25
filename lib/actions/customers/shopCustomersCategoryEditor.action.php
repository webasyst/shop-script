<?php

/**
 * Form to add, edit or delete customer categories; and submit controller for this form.
 */
class shopCustomersCategoryEditorAction extends waViewAction
{
    public function execute()
    {
        $id = waRequest::request('id', 0, 'int');

        $category = null;
        $ccm = new waContactCategoryModel();
        if ($id) {
            $category = $ccm->getById($id);
        }

        if (waRequest::post()) {

            if ($id && waRequest::post('delete')) {
                $ccm->delete($id);
                exit;
            }

            $name = waRequest::request('name');
            $icon = waRequest::request('icon');
            if ($id && $category) {
                $category['name'] = $name;
                $category['icon'] = $icon;
                $ccm->updateById($id, array(
                    'name' => $name,
                    'icon' => $icon,
                ));
            } else {
                $category = array(
                    'name' => $name,
                    'icon' => $icon,
                    'app_id' => 'shop',
                );
                $id = $ccm->insert($category);
                $category['id'] = $id;
            }

            echo "<script>window.location.hash = '#/category/{$id}';$.customers.reloadSidebar();</script>";
            exit;
        }

        if (!$category) {
            $category = array(
                'id' => '',
                'name' => '',
                'icon' => '',
                'app_id' => '',
            );
        }

        $icons = self::getIcons();
        if (empty($category['icon'])) {
            $category['icon'] = reset($icons);
        }

        $discount = null;
        if (wa()->getSetting('discount_category')) {
            $ccdm = new shopContactCategoryDiscountModel();
            $discount = $ccdm->getDiscount($category['id']);
        }

        $this->view->assign(array(
            'category' => $category,
            'icons' => $icons,
            'discount' => $discount
        ));
    }

    public static function getIcons()
    {
        return array(
            'notebook',
            'lock',
            'lock-unlocked',
            'broom',
            'star',
            'livejournal',
            'contact',
            'lightning',
            'light-bulb',
            'pictures',
            'reports',
            'books',
            'marker',
            'lens',
            'alarm-clock',
            'animal-monkey',
            'anchor',
            'bean',
            'car',
            'disk',
            'cookie',
            'burn',
            'clapperboard',
            'bug',
            'clock',
            'cup',
            'home',
            'fruit',
            'luggage',
            'guitar',
            'smiley',
            'sport-soccer',
            'target',
            'medal',
            'phone',
            'store',
        );
    }
}

