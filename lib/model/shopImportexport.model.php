<?php

class shopImportexportModel extends shopSortableModel
{
    protected $table = 'shop_importexport';
    protected $context = 'plugin';

    /**
     * @param string|string[] $plugin
     * @return array
     */
    public function getProfiles($plugin)
    {
        $plugin_profiles = array_fill_keys((array)$plugin, array());
        $where = $this->getWhereByField('plugin', $plugin);
        $rows = $this->select('id,name,plugin,description,sort')->where($where)->fetchAll($this->id);
        $this->sortRows($rows);
        foreach ($rows as $profile) {
            $p = $profile['plugin'];
            unset($profile['plugin']);
            $id = $profile['id'];
            unset($profile['id']);
            $plugin_profiles[$p][$id] = $profile;
        }
        return $plugin_profiles;
    }

    /**
     * @param int[] $plugins
     * @return array
     */
    public function getDefaultProfiles($plugins)
    {
        $where = $this->getWhereByField(array(
            'plugin' => $plugins,
            'sort'   => 0,
        ));
        return array_map('intval', $this->select('id,plugin')->where($where)->fetchAll('plugin', true));

    }
}
