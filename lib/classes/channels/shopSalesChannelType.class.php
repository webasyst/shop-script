<?php
/**
 * Base class for all sales channel types and collection of static methods to use them
 */
abstract class shopSalesChannelType
{
    private $type_data;

    public static function factory(string $type_id): shopSalesChannelType
    {
        $channel_type = self::getById($type_id);
        if (!$channel_type) {
            throw new waException('Channel type not found');
        }

        return new $channel_type['class']($channel_type);
    }

    public static function getById(string $type_id)
    {
        foreach (self::getAllTypes() as $t) {
            if ($t['id'] === $type_id) {
                return $t;
            }
        }
        return null;
    }

    public static function getAllTypes()
    {
        return self::getEnabledTypes('all');
    }

    public static function getEnabledTypes($loc=null)
    {
        $result = [];
        $loc = strtolower(ifset($loc, substr(wa()->getLocale(), -2)));
        $path_template = wa()->getAppPath('lib/config/data/channels/sales_channels.%s.php', 'shop');
        foreach ([$loc, 'other'] as $path_part) {
            $path = sprintf($path_template, $path_part);
            if (file_exists($path)) {
                $result = include($path);
                break;
            }
        }

        /**
         * @event sales_channel_types
         * Integration for sales channel types from plugins.
         *
         * @param string $params['locality'] locality code (e.g. 'ru', 'us'; or 'all' for everything)
         * @return array of items describing sales channel type info, like in lib/config/data/channels/sales_channels.all.php
         * @since 12.0.0
         */
        $event_result = wa('shop')->event('sales_channel_types', ref([
            'locality' => $loc,
        ]));
        foreach ($event_result as $plugin => $channels) {
            if (isset($channels['id']) && isset($channels['class'])) {
                $channels = [$channels];
            }
            foreach ($channels as $k => $channel) {
                if (isset($channel['class'])) {
                    $channel['id'] = ifset($channel, 'id', $k);
                    if (!isset($result[$channel['id']])) {
                        $result[$channel['id']] = $channel;
                    }
                }
            }
        }

        foreach ($result as $k => &$channel) {
            if (!isset($channel['class']) || !class_exists($channel['class'])) {
                unset($result[$k]);
                continue;
            }

            $channel['id'] = ifset($channel, 'id', $k);
            if (empty($channel['id']) || wa_is_int($channel['id'])) {
                unset($result[$k]);
                continue;
            }

            $channel['name'] = _w(ifset($channel, 'name', $channel['class']));
            $channel['available'] = ifset($channel, 'available', true);
        }
        unset($channel);

        return array_values($result);
    }

    // * * *

    public function __construct(array $type_data)
    {
        $this->type_data = $type_data;
    }

    /**
     * Used to make some of channel's settings available to read via public Shop storefront (Headless) API.
     *
     * @param array $channel     Channel data, including 'params' key
     * @return array with arbitrary keys and values specific to this sale channel type
     */
    public function getPublicStorefrontParams(array $channel): array
    {
        return [];
    }

    /**
     * Called during settings save to sanitize and validate POST data.
     * @param $id ?int              null for new channels
     * @param $params array         data[params] sent to save
     * @param $params_mode string   'set' will delete all params absent in $params; 'update' will only change what's in $params
     * @return array                validation errors as espected by JS [ 'error_description' => string, 'field' => 'data[params][fld]' ]
     */
    public function sanitizeAndValidateParams(?int $id, array &$params, $params_mode): array
    {
        return []; // override in subclasses if needed
    }

    /**
     * Called after settings save
     * @param $channel array    row from shop_sales_channel with additional 'params' key
     */
    public function onSave(array $channel)
    {
    }

    /**
     * Render form used to create new or edit existing sales channel.
     * Descendant classes may override this altogether and use custom HTML template
     * or just override getFormFieldsConfig and use form built from waHtmlControl fields.
     * @param $channel array    row from shop_sales_channel with additional 'params' key
     * @return string
     */
    public function getFormHtml(array $channel): string
    {
        $view = wa('shop')->getView();
        $view->assign([
            'channel' => $channel,
            'form_fields' => $this->getFormFields($channel),
        ]);
        return $view->fetch('file:templates/actions/channels/generic_form.include.html');
    }

    /**
     * Helper used by default implementaion of getFormHtml().
     * Takes field descriptions from getFormFieldsConfig() and getBaseFieldsConfig(),
     * as well as waHtmlControl params from getFormFieldParams()
     * and returns array of rendered form fields.
     */
    protected function getFormFields(array $channel): array
    {
        $result = [];
        $is_new = empty($channel['id']);
        if ($is_new) {
            $channel['name'] = $this->get('name');
        }

        $field_params = ['namespace' => 'data'] + $this->getFormFieldParams();
        foreach ($this->getBaseFieldsConfig() as $name => $row) {
            $result['__'.$name] = $this->getControl($name, ifset($channel, $name, ''), $field_params + $row);
        }

        $field_params['namespace'] = 'data[params]';
        foreach ($this->getFormFieldsConfig($channel['params']) as $name => $row) {
            try {
                $val = ifset($channel['params'], $name, (!$is_new && $row['control_type'] == waHtmlControl::CHECKBOX ? '' : ifset($row, 'value', '')));
                $result[$name] = $this->getControl($name, $val, $field_params + $row);
            } catch (waException $e) {
                continue;
            }
        }
        return $result;
    }

    // Used by default implementaion of getFormHtml() via getFormFields()
    protected function getFormFieldParams(): array
    {
        return [
            'namespace' => 'data',
            'title_wrapper' => '%s',
            'description_wrapper' => '<p class="hint">%s</p>',
            'control_wrapper' => '<div class="name">%s</div><div class="value">%s %s</div>',
        ];
    }

    // Used by default implementaion of getFormHtml()
    protected function getFormFieldsConfig($values = []): array
    {
        throw new waException('SalesChannelType must override either getFormFieldsConfig() or getFormFields()');
    }

    // Used by default implementaion of getFormHtml()
    protected function getBaseFieldsConfig(): array
    {
        return [
            'name' => [
                'title'        => _w('Name'),
                'description'  => _w('Sales channel name for internal use and order filtering.'),
                'control_type' => waHtmlControl::INPUT,
            ],
            'description' => [
                'title'        => _w('Comment'),
                'description'  => _w('Optional.') . ' ' . _w('Not visible to your customers.'),
                'control_type' => waHtmlControl::TEXTAREA,
            ],
        ];
    }

    /** Helper to render field HTML using waHtmlControl */
    protected function getControl($field_name, $field_value, $field_config): string
    {
        if (!is_array($field_config) || empty($field_config['control_type'])) {
            throw new waException('Control type not specified');
        }

        $field_config['value'] = $field_value;
        return waHtmlControl::getControl($field_config['control_type'], $field_name, $field_config);
    }

    /** Getter for type data passed to the constructor */
    public function get($name)
    {
        return ifset($this->type_data[$name]);
    }

    protected function isWaid()
    {
        return (new waWebasystIDClientManager)->isConnected();
    }
}
