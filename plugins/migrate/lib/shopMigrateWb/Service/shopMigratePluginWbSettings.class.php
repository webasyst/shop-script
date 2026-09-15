<?php

class shopMigratePluginWbSettings
{
    const NS_APP = 'shop';
    const NS_PLUGIN = 'migrate_wb';

    const MODE_AUTO = 'auto';
    const MODE_MANUAL = 'manual';

    const LOG_ERRORS = 'errors';
    const LOG_FULL = 'full';

    const FEATURE_MODE_AUTO = 'auto';
    const FEATURE_MODE_SKIP = 'skip';

    const IMAGE_MODE_IMMEDIATE = 'immediate';
    const IMAGE_MODE_LATER = 'later';

    const PRODUCT_BATCH_MIN = 1;
    const PRODUCT_BATCH_MAX = 26;
    const PRODUCT_BATCH_DEFAULT = 10;
    const PRODUCT_BATCH_REFERENCE_MEMORY_MB = 192;
    const PRODUCT_BATCH_REFERENCE_SIZE = 10;
    const PRODUCT_BATCH_MEMORY_CAP_MB = 512;

    private $settings_model;

    public function __construct()
    {
        $this->settings_model = new waAppSettingsModel();
    }

    public function getToken()
    {
        return (string) $this->settings_model->get(
            array(self::NS_APP, self::NS_PLUGIN),
            'api_token',
            ''
        );
    }

    public function hasToken()
    {
        return $this->getToken() !== '';
    }

    public function getPermissionCheckState($token_fingerprint)
    {
        // Bypass waAppSettingsModel's process-local cache: another request may
        // have finished a category check while this request was waiting.
        $json = $this->settings_model->select('value')->where(
            'app_id = ? AND name = ?',
            self::NS_APP.'.'.self::NS_PLUGIN,
            'permission_check_state'
        )->fetchField();
        $state = $json ? json_decode($json, true) : null;
        if (!is_array($state) || (int) ifset($state['version'], 0) !== 1
            || !hash_equals((string) ifset($state['token_fingerprint'], ''), (string) $token_fingerprint)
        ) {
            return array();
        }
        return (array) ifset($state['categories'], array());
    }

    public function savePermissionCheckState($token_fingerprint, array $categories)
    {
        // Keep a single bounded record. Never persist token text or localized
        // messages here; a changed token cannot reuse another token's results.
        $this->set('permission_check_state', json_encode(array(
            'version' => 1,
            'token_fingerprint' => (string) $token_fingerprint,
            'categories' => $categories,
        )));
    }

    public function saveToken($token)
    {
        $token = $this->normalizeToken($token);
        if ($token === '') {
            throw new waException(_wp('Enter a Wildberries API token.'));
        }

        $changed = $token !== $this->getToken();
        $this->set('api_token', $token);
        if ($changed) {
            $this->clearBuildingSnapshotReference();
            $this->clearSnapshotReference();
        }
    }

    public function getLogMode()
    {
        $mode = (string) $this->get('log_mode', self::LOG_ERRORS);
        return in_array($mode, array(self::LOG_ERRORS, self::LOG_FULL), true)
            ? $mode
            : self::LOG_ERRORS;
    }

    public function setLogMode($mode)
    {
        $mode = in_array($mode, array(self::LOG_ERRORS, self::LOG_FULL), true)
            ? $mode
            : self::LOG_ERRORS;
        $this->set('log_mode', $mode);
    }

    public function getOperationMode()
    {
        $mode = (string) $this->get('mode', self::MODE_AUTO);
        return in_array($mode, array(self::MODE_AUTO, self::MODE_MANUAL), true)
            ? $mode
            : self::MODE_AUTO;
    }

    public function setOperationMode($mode)
    {
        $mode = in_array($mode, array(self::MODE_AUTO, self::MODE_MANUAL), true)
            ? $mode
            : self::MODE_AUTO;
        $this->set('mode', $mode);
    }

    public function getCurrentSnapshotId()
    {
        return (int) $this->get('snapshot_id', 0);
    }

    public function setCurrentSnapshotId($snapshot_id)
    {
        $this->set('snapshot_id', max(0, (int) $snapshot_id));
    }

    public function clearSnapshotReference()
    {
        $this->delete('snapshot_id');
    }

    public function getBuildingSnapshotId()
    {
        return (int) $this->get('building_snapshot_id', 0);
    }

    public function setBuildingSnapshotId($snapshot_id)
    {
        $this->set('building_snapshot_id', max(0, (int) $snapshot_id));
    }

    public function clearBuildingSnapshotReference()
    {
        $this->delete('building_snapshot_id');
    }

    public function getCollectionRevision()
    {
        return max(0, (int) $this->get('collection_revision', 0));
    }

    public function getFreshCollectionRevision()
    {
        $value = $this->settings_model
            ->select('value')
            ->where('app_id = ? AND name = ?', self::NS_APP.'.'.self::NS_PLUGIN, 'collection_revision')
            ->limit(1)
            ->fetchField();
        return max(0, (int) $value);
    }

    public function incrementCollectionRevision()
    {
        $revision = $this->getFreshCollectionRevision() + 1;
        $this->set('collection_revision', $revision);
        return $revision;
    }

    public function getFeatureImportMode()
    {
        $mode = (string) $this->get('feature_mode', self::FEATURE_MODE_AUTO);
        return in_array($mode, array(self::FEATURE_MODE_AUTO, self::FEATURE_MODE_SKIP), true)
            ? $mode
            : self::FEATURE_MODE_AUTO;
    }

    public function setFeatureImportMode($mode)
    {
        $mode = in_array($mode, array(self::FEATURE_MODE_AUTO, self::FEATURE_MODE_SKIP), true)
            ? $mode
            : self::FEATURE_MODE_AUTO;
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

    public function getImageMode()
    {
        $mode = (string) $this->get('image_mode', self::IMAGE_MODE_IMMEDIATE);
        return in_array($mode, $this->getImageModes(), true) ? $mode : self::IMAGE_MODE_IMMEDIATE;
    }

    public function setImageMode($mode)
    {
        $mode = in_array($mode, $this->getImageModes(), true) ? $mode : self::IMAGE_MODE_IMMEDIATE;
        $this->set('image_mode', $mode);
    }

    public function getProductBatchSize()
    {
        $memory_mb = $this->get('server_memory_limit_mb', null);
        $unlimited = $this->get('server_memory_unlimited', null);
        $size = $this->get('product_batch_size', null);
        if ($memory_mb === null || $unlimited === null || $size === null || $size === '') {
            $profile = self::detectServerCapacity();
            $size = $profile['batch_size'];
        }
        return min(self::PRODUCT_BATCH_MAX, max(self::PRODUCT_BATCH_MIN, (int) $size));
    }

    public function setProductBatchSize($size)
    {
        $this->set(
            'product_batch_size',
            min(self::PRODUCT_BATCH_MAX, max(self::PRODUCT_BATCH_MIN, (int) $size))
        );
    }

    public function refreshServerCapacity()
    {
        $profile = self::detectServerCapacity();
        $this->set('server_memory_limit_mb', $profile['memory_limit_mb']);
        $this->set('server_memory_unlimited', $profile['memory_limit_unlimited'] ? 1 : 0);
        $this->setProductBatchSize($profile['batch_size']);
        return $profile;
    }

    public function getServerCapacity()
    {
        $memory_mb = $this->get('server_memory_limit_mb', null);
        $unlimited = $this->get('server_memory_unlimited', null);
        if ($memory_mb === null || $unlimited === null) {
            return self::detectServerCapacity();
        }
        return array(
            'memory_limit_mb'        => max(0, (int) $memory_mb),
            'memory_limit_unlimited' => (bool) $unlimited,
            'batch_size'             => $this->getProductBatchSize(),
        );
    }

    public static function detectServerCapacity($memory_limit = null)
    {
        $memory_limit = $memory_limit === null ? ini_get('memory_limit') : $memory_limit;
        $bytes = self::parseIniBytes($memory_limit);
        $unlimited = $bytes < 0;
        $memory_mb = $unlimited ? 0 : max(1, (int) floor($bytes / 1048576));
        $calculation_mb = $unlimited
            ? self::PRODUCT_BATCH_MEMORY_CAP_MB
            : min(self::PRODUCT_BATCH_MEMORY_CAP_MB, $memory_mb);

        return array(
            'memory_limit_mb'        => $memory_mb,
            'memory_limit_unlimited' => $unlimited,
            'batch_size'             => self::calculateProductBatchSize($calculation_mb),
        );
    }

    public static function calculateProductBatchSize($memory_mb)
    {
        $memory_mb = min(self::PRODUCT_BATCH_MEMORY_CAP_MB, max(0, (int) $memory_mb));
        $size = (int) floor(
            $memory_mb
            * self::PRODUCT_BATCH_REFERENCE_SIZE
            / self::PRODUCT_BATCH_REFERENCE_MEMORY_MB
        );
        return min(self::PRODUCT_BATCH_MAX, max(self::PRODUCT_BATCH_MIN, $size));
    }

    private static function parseIniBytes($value)
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-1') {
            return -1;
        }
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;
        switch ($unit) {
            case 't':
                $number *= 1024;
                // no break
            case 'g':
                $number *= 1024;
                // no break
            case 'm':
                $number *= 1024;
                // no break
            case 'k':
                $number *= 1024;
        }
        return (int) floor($number);
    }

    public function getImageModes()
    {
        return array(
            self::IMAGE_MODE_IMMEDIATE,
            self::IMAGE_MODE_LATER,
        );
    }

    public function getImportOptions()
    {
        $capacity = $this->getServerCapacity();
        return array(
            'mode'               => $this->getOperationMode(),
            'log_mode'           => $this->getLogMode(),
            'feature_mode'       => $this->getFeatureImportMode(),
            'feature_force_text' => $this->shouldForceTextFeatures(),
            'image_mode'         => $this->getImageMode(),
            'batch_size'         => $this->getProductBatchSize(),
            'server_memory_mb'   => $capacity['memory_limit_mb'],
            'server_memory_unlimited' => $capacity['memory_limit_unlimited'],
        );
    }

    public function saveImportOptions(array $options)
    {
        if (array_key_exists('mode', $options)) {
            $this->setOperationMode($options['mode']);
        }
        if (array_key_exists('log_mode', $options)) {
            $this->setLogMode($options['log_mode']);
        }
        if (array_key_exists('feature_mode', $options)) {
            $this->setFeatureImportMode($options['feature_mode']);
        }
        if (array_key_exists('feature_force_text', $options)) {
            $this->setForceTextFeatures($options['feature_force_text']);
        }
        if (array_key_exists('image_mode', $options)) {
            $this->setImageMode($options['image_mode']);
        }
    }

    private function normalizeToken($token)
    {
        $token = trim((string) $token);
        if (stripos($token, 'Bearer ') === 0) {
            $token = trim(substr($token, 7));
        }
        return $token;
    }

    private function get($name, $default = null)
    {
        return $this->settings_model->get(
            array(self::NS_APP, self::NS_PLUGIN),
            $name,
            $default
        );
    }

    private function set($name, $value)
    {
        $this->settings_model->set(
            array(self::NS_APP, self::NS_PLUGIN),
            $name,
            $value
        );
    }

    private function delete($name)
    {
        $this->settings_model->del(array(self::NS_APP, self::NS_PLUGIN), $name);
    }
}
