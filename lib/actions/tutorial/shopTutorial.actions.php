<?php
/**
 * Tutorial tab in shop backend.
 */
class shopTutorialActions extends waViewActions
{
    // Right sidebar and inner layout for Tutorial section
    protected function defaultAction()
    {
        $this->setLayout(new shopBackendLayout());
        $this->getResponse()->setTitle(_w('Welcome to Shop-Script'));

        $this->layout->assign('no_level2', true);
        $this->view->assign(array(
            'backend_tutorial' => self::backendTutorialEvent(),
            'sidebar_width' => wa('shop')->getConfig()->getSidebarWidth(),
            'actions' => self::getActions(self::backendTutorialEvent()),
            'lang' => substr(wa()->getLocale(), 0, 2),
        ));
    }

    protected function installAction()
    {
        // Nothing to do!
    }

    protected function productsAction()
    {
        // Nothing to do!
    }

    protected function designAction()
    {
        // Nothing to do!
    }

    protected function checkoutAction()
    {
        // Nothing to do!
    }

    protected function profitAction()
    {
        // Nothing to do! Love to code actions like that.
    }

    protected function doneAction()
    {
        $app_settings_model = new waAppSettingsModel();
        $app_settings_model->del('shop', 'show_tutorial');
        exit;
    }

    protected function getTemplate()
    {
        $template = parent::getTemplate();
        $ext = $this->view->getPostfix();
        $locale_template = str_replace($ext, '.'.wa()->getLocale().$ext, $template);
        if (is_readable(wa()->getAppPath($locale_template, 'shop'))) {
            return $locale_template;
        }
        return $template;
    }

    protected function customAction()
    {
        $page_id = waRequest::request('page', '', 'string');
        $params = array(
            'page' => $page_id,
        );
        $blocks = wa()->event('backend_tutorial_page', $params);
        if (!$blocks) {
            throw new waException('Not found', 404);
        }
        $html = array();
        foreach($blocks as $app_id => $b) {
            if ($b && !is_array($b) && !is_object($b)) {
                $html[] = '<div class="block-'.$app_id.'">'.$b."</div>\n";
            }
        }

        $this->view->assign('html', join('', $html));
    }

    public static function getActions($backend_tutorial)
    {
        $actions = array();

        $actions[] = array(
            'href' => '#/install/',
            'name' => _w('Install Shop-Script'),
            'complete' => true,
        );

        // Action can only be marked as complete if all previous actions are complete
        $prev_complete = true;

        // This action is complete if there is at least one product
        $a = array(
            'href' => '#/products/',
            'name' => _w('Add your first product'),
            'complete' => false,
        );
        if ($prev_complete) {
            $product_model = new shopProductModel();
            $prev_complete = $a['complete'] = $product_model->countAll() > 0;
        }
        $actions[] = $a;

        // This action is complete if there's a non-default theme installed for Shop
        $a = array(
            'href' => '#/design/',
            'name' => _w('Choose design'),
            'complete' => false,
        );
        if ($prev_complete) {
            $prev_complete = $a['complete'] = count(wa()->getThemes('shop')) > 1;
        }
        $actions[] = $a;

        // Complete when user set up at least one payment or shipping option
        $a = array(
            'href' => '#/checkout/',
            'name' => _w('Setup payment & shipping'),
            'complete' => false,
        );
        if ($prev_complete) {
            $plugin_model = new shopPluginModel();
            $prev_complete = $a['complete'] = $plugin_model->countAll() > 0;
        }
        $actions[] = $a;

        foreach($backend_tutorial as $plugin_id => $event_result) {
            if (empty($event_result['sidebar_li']) || !is_array($event_result['sidebar_li'])) {
                continue;
            }
            $acts = $event_result['sidebar_li'];
            if (empty($acts[0])) {
                $acts = array($acts);
            }
            foreach($acts as $a) {
                if(empty($a['href'])) {
                    $a['href'] = 'javascript:void(0)';
                }
                if (empty($a['name'])) {
                    $a['name'] = $plugin_id;
                }
                $prev_complete = $a['complete'] = $prev_complete && !empty($a['complete']);
            }
            $actions[] = $a;
        }

        return $actions;
    }

    public static function backendTutorialEvent()
    {
        static $result = null;
        if ($result === null) {
            /*
             * @event backend_tutorial
             * @return array[string][string]string $return[%plugin_id%]['sidebar_li'] html output
             * @return array[string][string]string $return[%plugin_id%]['sidebar_block'] html output
             */
            $result = wa('shop')->event('backend_tutorial');
            if (!$result) {
                $result = array();
            }
        }
        return $result;
    }
}

