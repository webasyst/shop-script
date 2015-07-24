<?php

class shopDebugPlugin extends shopPlugin
{
    /**
     *
     * @param array $params
     * @param array [string]array $params['data'] raw product entry data
     * @param array [string]shopProduct $params['instance'] product entry instance
     */
    public function productSaveHandler($params)
    {
        waLog::log(var_export($params['data'], true), __FUNCTION__.'.log');
    }

    public function backendMenu($params)
    {
        $selected = (waRequest::get('plugin') == $this->id) ? 'selected' : 'no-tab';
        return array(
            'aux_li' => "<li class=\"small float-right {$selected}\" id=\"s-plugin-debug\"><a href=\"?plugin=debug\">Debug tools</a></li>",
        );
    }

    /**
     *
     * @param array $ids
     */
    public function productDeleteHandler($ids)
    {
        waLog::log(var_export($ids, true), __FUNCTION__.'.log');
    }

    public static function getContactFields()
    {
        $options = array();
        $fields = waContactFields::getAll();
        foreach ($fields as $field) {
            if ($field instanceof waContactCompositeField) {
                /**
                 * @var waContactCompositeField $field
                 */
                $composite_fields = $field->getFields();
                foreach ($composite_fields as $composite_field) {
                    /**
                     * @var waContactField $composite_field
                     */
                    $options[] = array(
                        'group' => $field->getName(),
                        'title' => $composite_field->getName(),
                        'value' => $field->getId().'.'.$composite_field->getId(),
                    );
                }
            } else {
                /**
                 * @var waContactField $field
                 */
                $options[] = array(
                    'title' => $field->getName(),
                    'value' => $field->getId(),
                );
            }
        }
        return $options;
    }

    function backendOrders($params)
    {
        return array(
            'sidebar_top_li' => '<li class="list"><a href="#/orders/hash/id/15271,15270/">Hello debug</a></li>',
            'sidebar_section' => '',
            'sidebar_bottom_li' => '<li class="list"><a href="#/orders/view=split&hash=id%2F15271%2C15270/">Goodbye debug</a></li>',
        );
    }
}
