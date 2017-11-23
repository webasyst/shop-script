<?php

class shopInvoicePlugin extends shopPrintformPlugin
{
    public function renderPrintform($data)
    {
        $this->setOrderOption('items', null);
        return parent::renderPrintform($data);
    }

    public function preparePrintform($data, waView $view)
    {
        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config
         */
        $data['shop_settings'] = array(
            'url'     => $config->getRootUrl(true),
            'img_url' => wa()->getDataUrl('plugins/invoice/', true),
            'name'    => $config->getGeneralSettings('name'),
            'phone'   => $config->getGeneralSettings('phone'),
        );

        $fields = array(
            'name',
            'street',
            'city',
            'zip',
        );
        if (!empty($this->settings['contact_phone'])) {
            $fields[] = $this->settings['contact_phone'];
        }
        if (!empty($this->settings['contact_company'])) {
            $fields[] = $this->settings['contact_company'];
        }

        $order = $data['order'];
        /**
         * @var waOrder $order
         */

        $same = true;
        foreach ($fields as $field) {
            if (isset($order->shipping_address[$field]) && isset($order->billing_address[$field])) {
                if ($order->shipping_address[$field] != $order->billing_address[$field]) {
                    $same = false;
                    break;
                }
            }
        }
        $data['same_address'] = $same;
        return parent::preparePrintform($data, $view);
    }

    public function saveSettings($settings = array())
    {
        if (isset($settings['logo']) && ($settings['logo'] instanceof waRequestFile)) {
            /**
             * @var waRequestFile $file
             */
            $file = $settings['logo'];
            if ($file->uploaded()) {
                // check that file is image
                try {
                    // create waImage
                    $image = $file->waImage();
                } catch (Exception $e) {
                    throw new waException(_wp("File isn't an image").' '.$e->getMessage());
                }

                $path = wa()->getDataPath(sprintf('plugins/%s/', $this->id), true, $this->app_id);

                $file_name = 'logo.'.$image->getExt();
                if (!file_exists($path) || !is_writable($path)) {
                    $message = _wp('File could not be saved due to the insufficient file write permissions for the %s folder.');
                    throw new waException(sprintf($message, 'wa-data/public/shop/data/'));
                } elseif (!$file->moveTo($path, $file_name)) {
                    throw new waException(_wp('Failed to upload file.'));
                }
                $settings['logo'] = $file_name;
            } else {
                $image = $this->getSettings('logo');
                $settings['logo'] = $image;
            }
        }

        parent::saveSettings($settings);
    }
}
