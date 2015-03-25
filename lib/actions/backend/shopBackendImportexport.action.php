<?php

class shopBackendImportexportAction extends waViewAction
{
    public function execute()
    {

        if (!$this->getUser()->isAdmin('shop') && !wa()->getUser()->getRights('shop', 'importexport')) {
            throw new waRightsException('Access denied');
        }

        $this->setLayout(new shopBackendLayout());
        $this->layout->assign('no_level2', true);
        $plugins = $this->getConfig()->getPlugins();

        $plugins = array_merge(
            array('csv:product:export' => array(
                'id'           => 'csv:product:export',
                'name'         => _w('Export products to CSV'),
                'description'  => _w('Save your existing products information in a CSV file'),
                'icon'         => 'ss excel',
                'importexport' => 'profiles',
            )
            ), $plugins);


        $plugin_profiles = array();
        foreach ($plugins as $id => $plugin) {
            if (empty($plugin['importexport'])) {
                unset($plugins[$id]);
            } elseif (in_array('profiles', (array)$plugin['importexport'], true)) {
                $plugins[$id]['default_profile'] = '';
                $plugin_profiles[] = $id;
            }
            unset($plugin);
        }

        if ($plugin_profiles) {
            $model = new shopImportexportModel();
            foreach ($model->getDefaultProfiles($plugin_profiles) as $id => $profile_id) {
                $plugins[$id]['default_profile'] = $profile_id;
            }
        }

        $this->view->assign('plugins', $plugins);
        $this->view->assign('plugin_profiles', array_fill_keys($plugin_profiles, true));
    }
}
