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
}
