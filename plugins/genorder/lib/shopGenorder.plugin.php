<?php

class shopGenorderPlugin extends shopPlugin
{
    /** Добавляет вкладку в старом меню 1.3 по хуку backend_menu */
    public function backendMenu($params)
    {
        $selected = (waRequest::get('plugin') == $this->id) ? 'selected' : 'no-tab';

        return [
            'aux_li' => '<li class="small float-right '.$selected.'" id="s-plugin-debug">
                <a href="?plugin=genorder&action=generator">'._wp('Order generation').'</a>
            </li>',
        ];
    }

    /** Добавляет ссылку в боковое меню магазина WA2.0 по хуку backend_extended_menu */
    public function backendExtendedMenu($params)
    {
        $wa_app_url = wa('shop')->getAppUrl(null, true);

        // Вставить новый пункт в глобальном меню перед пунктом Настройки ('settings')
        $offset = array_search('settings', array_keys($params['menu']));
        $params['menu'] = array_merge(
            array_slice($params['menu'], 0, $offset),
            array('genorder' => [
                "id" => "genorder",
                "name" => _wp('Order generation'),
                "url" => "{$wa_app_url}?plugin=genorder&action=generator",
                "icon" => '<i class="fas fa-solid fa-truck-monster"></i>',
                "placement" => 'footer',
            ]),
            array_slice($params['menu'], $offset, null)
        );
    }
}
