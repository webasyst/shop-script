<?php

class shopMigratePluginOzonSettings
{
    const NS_APP = 'shop';
    const NS_PLUGIN = 'migrate_ozon';

    const MODE_AUTO = 'auto';
    const MODE_MANUAL = 'manual';

    const LOG_FULL = 'full';
    const LOG_ERRORS = 'errors';

    const FEATURE_MODE_AUTO = 'auto';
    const FEATURE_MODE_SKIP = 'skip';
    const TAG_MODE_PRODUCT_ONLY = 'product_only';
    const TAG_MODE_PRODUCT_AND_SKU = 'product_and_sku';
    const TAG_MODE_SKU_ONLY = 'sku_only';

    /**
     * @var waAppSettingsModel
     */
    private $settings_model;

    public function __construct()
    {
        $this->settings_model = new waAppSettingsModel();
    }

    public function getCredentials()
    {
        return array(
            'client_id' => (string) $this->get('client_id', ''),
            'api_key'   => (string) $this->get('api_key', ''),
        );
    }

    public function saveCredentials($client_id, $api_key)
    {
        $this->set('client_id', (string) $client_id);
        $this->set('api_key', (string) $api_key);
    }

    public function getLogMode()
    {
        $mode = $this->get('log_mode', self::LOG_ERRORS);
        return in_array($mode, array(self::LOG_ERRORS, self::LOG_FULL), true) ? $mode : self::LOG_ERRORS;
    }

    public function setLogMode($mode)
    {
        $mode = in_array($mode, array(self::LOG_ERRORS, self::LOG_FULL), true) ? $mode : self::LOG_ERRORS;
        $this->set('log_mode', $mode);
    }

    public function getOperationMode()
    {
        $mode = (string) $this->get('mode', self::MODE_AUTO);
        return in_array($mode, array(self::MODE_AUTO, self::MODE_MANUAL), true) ? $mode : self::MODE_AUTO;
    }

    public function setOperationMode($mode)
    {
        $mode = in_array($mode, array(self::MODE_AUTO, self::MODE_MANUAL), true) ? $mode : self::MODE_AUTO;
        $this->set('mode', $mode);
    }

    public function getCurrentSnapshotId()
    {
        return (int) $this->get('snapshot_id', 0);
    }

    public function setCurrentSnapshotId($snapshot_id)
    {
        $this->set('snapshot_id', (int) $snapshot_id);
    }

    public function clearSnapshotReference()
    {
        $this->settings_model->del(self::NS_APP, self::NS_PLUGIN, 'snapshot_id');
    }

    public function getFeatureImportMode()
    {
        $mode = (string) $this->get('feature_mode', self::FEATURE_MODE_AUTO);
        return in_array($mode, array(self::FEATURE_MODE_AUTO, self::FEATURE_MODE_SKIP), true) ? $mode : self::FEATURE_MODE_AUTO;
    }

    public function setFeatureImportMode($mode)
    {
        $mode = in_array($mode, array(self::FEATURE_MODE_AUTO, self::FEATURE_MODE_SKIP), true) ? $mode : self::FEATURE_MODE_AUTO;
        $this->set('feature_mode', $mode);
    }

    public function shouldForceTextFeatures()
    {
        return (bool) $this->get('feature_force_text', 0);
    }

    public function setForceTextFeatures($force_text)
    {
        $this->set('feature_force_text', $force_text ? 1 : 0);
    }

    public function getTagImportMode()
    {
        $mode = (string) $this->get('tag_mode', self::TAG_MODE_PRODUCT_ONLY);
        $allowed = array(
            self::TAG_MODE_PRODUCT_ONLY,
            self::TAG_MODE_PRODUCT_AND_SKU,
            self::TAG_MODE_SKU_ONLY,
        );
        return in_array($mode, $allowed, true) ? $mode : self::TAG_MODE_PRODUCT_ONLY;
    }

    public function setTagImportMode($mode)
    {
        $allowed = array(
            self::TAG_MODE_PRODUCT_ONLY,
            self::TAG_MODE_PRODUCT_AND_SKU,
            self::TAG_MODE_SKU_ONLY,
        );
        $mode = in_array($mode, $allowed, true) ? $mode : self::TAG_MODE_PRODUCT_ONLY;
        $this->set('tag_mode', $mode);
    }

    public function getEffectiveTagImportMode()
    {
        if ($this->getOperationMode() === self::MODE_AUTO) {
            return self::TAG_MODE_PRODUCT_ONLY;
        }
        return $this->getTagImportMode();
    }

    private function get($name, $default = null)
    {
        return $this->settings_model->get(array(self::NS_APP, self::NS_PLUGIN), $name, $default);
    }

    private function set($name, $value)
    {
        $this->settings_model->set(array(self::NS_APP, self::NS_PLUGIN), $name, $value);
    }
}
