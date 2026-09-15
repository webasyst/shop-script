<?php

class shopMigratePluginWbFeatureMapper
{
    const MODE_AUTO = 'auto';
    const FEATURE_CODE_MAX_LENGTH = 64;
    const FEATURE_CREATE_ATTEMPTS = 10;

    private $repository;
    private $feature_model;
    private $type_model;
    private $type_features_model;
    private $map_model;
    private $map = array();
    private $snapshot_id = 0;
    private $feature_catalog_loaded = false;
    private $features_by_code = array();
    private $features_by_name = array();
    private $force_text_features = false;

    public function __construct(shopMigratePluginWbSnapshotRepository $repository)
    {
        $this->repository = $repository;
        $this->feature_model = new shopFeatureModel();
        $this->type_model = new shopTypeModel();
        $this->type_features_model = new shopTypeFeaturesModel();
        $this->map_model = $repository->getFeatureMapModel();
    }

    public function setForceTextFeatures($force_text)
    {
        $this->force_text_features = (bool) $force_text;
    }

    public function warmup($snapshot_id)
    {
        $snapshot_id = $this->requirePositiveId($snapshot_id, _wp('Invalid Wildberries snapshot ID.'));
        $this->map = array();
        foreach ((array) $this->map_model->getMap($snapshot_id) as $row) {
            if (!is_array($row) || empty($row['subject_id']) || empty($row['characteristic_id'])) {
                continue;
            }
            // Manual feature mappings were available during development only.
            // Do not let persisted legacy choices affect the automatic first-release flow.
            if ((string) ifset($row['mode'], '') !== self::MODE_AUTO
                || (string) ifset($row['action'], '') !== 'auto'
            ) {
                continue;
            }
            $this->map[$this->getMapKey($row['subject_id'], $row['characteristic_id'])] = $row;
        }
        $this->snapshot_id = $snapshot_id;
        $this->loadFeatureCatalog();
    }

    /**
     * Resolves a Wildberries characteristic to a Shop-Script feature.
     *
     * @return array Shop-Script feature row.
     */
    public function resolve($snapshot_id, $subject_id, $characteristic_id, $shop_type_id, $value = null)
    {
        $snapshot_id = $this->requirePositiveId($snapshot_id, _wp('Invalid Wildberries snapshot ID.'));
        $subject_id = $this->requirePositiveId($subject_id, _wp('Invalid Wildberries subject ID.'));
        $characteristic_id = $this->requirePositiveId(
            $characteristic_id,
            _wp('Invalid Wildberries characteristic ID.')
        );
        $shop_type_id = $this->requireShopType($shop_type_id);
        $this->ensureWarm($snapshot_id);

        $key = $this->getMapKey($subject_id, $characteristic_id);
        $mapping = isset($this->map[$key]) ? $this->map[$key] : array();
        $attribute = $this->getSourceAttribute($snapshot_id, $subject_id, $characteristic_id);
        // Actual card values may be broader than the downloaded WB schema.
        // Infer a collection before reusing even a persisted mapping; this
        // only affects string/color collections, not force-text conversion.
        if (is_array($value) && (int) ifset($attribute['max_count'], 1) !== 0) {
            $attribute['max_count'] = max(
                (int) ifset($attribute['max_count'], 1),
                count(array_filter($value, 'is_scalar'))
            );
        }
        $feature_data = $this->buildFeatureData($attribute);

        if ($this->isAutoMapping($mapping) && !empty($mapping['shop_feature_id'])) {
            $feature = $this->feature_model->getById((int) $mapping['shop_feature_id']);
            if ($feature && $this->isCompatibleFeature($feature, $feature_data)) {
                $this->bindFeatureToType($feature['id'], $shop_type_id);
                return $feature;
            }
        }

        $feature = $this->findCompatibleFeature($feature_data);
        if (!$feature) {
            $feature_id = $this->createFeature($attribute, $shop_type_id);
            $feature = $this->feature_model->getById($feature_id);
        } else {
            $this->bindFeatureToType($feature['id'], $shop_type_id);
        }

        if (!$feature) {
            throw new waException(_wp('Unable to resolve a Shop-Script feature for the Wildberries characteristic.'));
        }

        $this->saveMapping($snapshot_id, $subject_id, $characteristic_id, array(
            'mode'              => self::MODE_AUTO,
            'action'            => 'auto',
            'shop_feature_id'   => (int) $feature['id'],
            'shop_feature_code' => (string) $feature['code'],
        ));

        return $feature;
    }

    /**
     * Creates a feature or reuses a compatible feature inserted after warmup.
     *
     * @return int Shop-Script feature ID.
     */
    public function createFeature(array $attribute, $shop_type_id)
    {
        $shop_type_id = $this->requireShopType($shop_type_id);
        $data = $this->buildFeatureData($attribute);

        $feature = $this->findFreshCompatibleFeature($data);
        if ($feature) {
            $this->indexFeature($feature);
            $this->bindFeatureToType($feature['id'], $shop_type_id);
            return (int) $feature['id'];
        }

        $base_code = (string) $data['code'];
        $last_duplicate = null;
        for ($attempt = 0; $attempt < self::FEATURE_CREATE_ATTEMPTS; $attempt++) {
            $candidate_code = $this->buildFeatureCodeCandidate($base_code, $attribute, $attempt);
            $existing = $this->feature_model->getByField('code', $candidate_code);
            if ($existing) {
                $this->indexFeature($existing);
                if ($this->isCompatibleFeature($existing, $data)) {
                    $this->bindFeatureToType($existing['id'], $shop_type_id);
                    return (int) $existing['id'];
                }
                continue;
            }

            $attempt_data = $data;
            $attempt_data['code'] = $candidate_code;
            try {
                $feature_id = (int) $this->feature_model->save($attempt_data);
            } catch (waDbException $e) {
                if ((int) $e->getCode() !== 1062) {
                    throw $e;
                }
                $last_duplicate = $e;
                // shopFeatureModel::save() may alter code before INSERT.
                $insert_code = (string) ifset($attempt_data['code'], $candidate_code);
                $winner = $this->feature_model->getByField('code', $insert_code);
                if ($winner) {
                    $this->indexFeature($winner);
                    if ($this->isCompatibleFeature($winner, $data)) {
                        $this->bindFeatureToType($winner['id'], $shop_type_id);
                        return (int) $winner['id'];
                    }
                }
                continue;
            }

            if ($feature_id <= 0) {
                throw new waException(_wp('Unable to create a Shop-Script feature.'));
            }
            $feature = $this->feature_model->getById($feature_id);
            if (!$feature) {
                throw new waException(_wp('The newly created Shop-Script feature was not found.'));
            }
            $this->indexFeature($feature);
            $this->bindFeatureToType($feature_id, $shop_type_id);
            return $feature_id;
        }

        if ($last_duplicate) {
            throw $last_duplicate;
        }
        throw new waException(_wp('Unable to create a Shop-Script feature.'));
    }

    /**
     * Produces the Shop-Script feature definition inferred from a WB schema row.
     */
    public function buildFeatureData(array $attribute)
    {
        $name = $this->cleanName(ifset($attribute['name'], ''));
        if ($name === '') {
            throw new waException(_wp('Wildberries characteristic name is missing from the current snapshot.'));
        }

        $source_type = $this->normalizeSourceType(ifset($attribute['type'], ''));
        $max_count = max(0, (int) ifset($attribute['max_count'], 1));
        $type = shopFeatureModel::TYPE_VARCHAR;
        $selectable = 0;
        $multiple = 0;
        $dimension = !$this->force_text_features && $this->isNumericSourceType($source_type)
            ? $this->detectDimensionFeature($attribute)
            : null;

        if ($this->force_text_features) {
            $type = shopFeatureModel::TYPE_TEXT;
        } elseif ($this->isColorAttribute($name, $source_type)) {
            $type = shopFeatureModel::TYPE_COLOR;
            $selectable = 1;
            $multiple = $this->isStringCollection($source_type, $max_count) ? 1 : 0;
        } elseif ($this->isBooleanSourceType($source_type)) {
            $type = shopFeatureModel::TYPE_BOOLEAN;
        } elseif ($dimension) {
            $type = 'dimension.'.$dimension['type'];
        } elseif ($this->isNumericSourceType($source_type)) {
            $type = shopFeatureModel::TYPE_DOUBLE;
        } elseif ($this->isDateSourceType($source_type)) {
            $type = shopFeatureModel::TYPE_DATE;
        } elseif ($this->isLongTextSourceType($source_type)) {
            $type = shopFeatureModel::TYPE_TEXT;
        } elseif ($this->isStringCollection($source_type, $max_count)) {
            $type = shopFeatureModel::TYPE_VARCHAR;
            $selectable = 1;
            $multiple = 1;
        }

        $meta = ifset($attribute['meta'], array());
        if (is_string($meta)) {
            $decoded_meta = json_decode($meta, true);
            $meta = is_array($decoded_meta) ? $decoded_meta : array();
        }
        $meta = is_array($meta) ? $meta : array();
        $code = $this->normalizeCode(ifset($meta['code'], ''));
        $code = preg_replace('/[^a-z0-9_]+/', '_', $code);
        $code = $code === null ? '' : trim(mb_substr($code, 0, 64), '_');
        if ($code === '') {
            $code = strtolower((string) $this->feature_model->getCode($name));
            $code = trim($code, '_');
        }
        if ($code === '') {
            $characteristic_id = max(0, (int) ifset($attribute['characteristic_id'], 0));
            $code = $characteristic_id > 0 ? 'wb_characteristic_'.$characteristic_id : 'wb_characteristic';
        }

        $data = array(
            'code'              => $code,
            'name'              => $name,
            'type'              => $type,
            'status'            => shopFeatureModel::STATUS_PUBLIC,
            'selectable'        => $selectable,
            'multiple'          => $multiple,
            'available_for_sku' => 1,
            'count'             => 0,
        );
        if ($dimension) {
            $data['default_unit'] = $dimension['unit'];
        }
        return $data;
    }

    /**
     * Returns the canonical Shop-Script unit supplied by WB for a dimension feature.
     */
    public function detectAttributeUnit(array $attribute, array $feature)
    {
        $dimension = $this->detectDimensionFeature($attribute);
        if (!$dimension) {
            return null;
        }
        return (string) ifset($feature['type'], '') === 'dimension.'.$dimension['type']
            ? $dimension['unit']
            : null;
    }

    /**
     * Public helper for mapping UIs and import workers.
     */
    public function bindFeatureToType($feature_id, $shop_type_id)
    {
        $feature_id = $this->requirePositiveId($feature_id, _wp('Invalid Shop-Script feature ID.'));
        $shop_type_id = $this->requireShopType($shop_type_id);
        if (!$this->feature_model->getById($feature_id)) {
            throw new waException(_wp('The mapped Shop-Script feature no longer exists.'));
        }
        $this->type_features_model->addFeaturesToType($shop_type_id, array($feature_id));
    }

    private function getSourceAttribute($snapshot_id, $subject_id, $characteristic_id)
    {
        $attribute = $this->repository->getAttributesModel()->getByField(array(
            'snapshot_id'       => (int) $snapshot_id,
            'subject_id'        => (int) $subject_id,
            'characteristic_id' => (int) $characteristic_id,
        ));
        if (!$attribute) {
            throw new waException(_wp('Wildberries characteristic was not found in the current snapshot.'));
        }
        return $attribute;
    }

    private function findCompatibleFeature(array $data)
    {
        $this->loadFeatureCatalog();
        $code = $this->normalizeCode(ifset($data['code'], ''));
        if ($code !== '' && !empty($this->features_by_code[$code])) {
            foreach ($this->features_by_code[$code] as $feature) {
                if ($this->isCompatibleFeature($feature, $data)) {
                    return $feature;
                }
            }
        }

        $name = $this->normalizeName(ifset($data['name'], ''));
        if ($name !== '' && !empty($this->features_by_name[$name])) {
            foreach ($this->features_by_name[$name] as $feature) {
                if ($this->isCompatibleFeature($feature, $data)) {
                    return $feature;
                }
            }
        }
        return null;
    }

    private function isCompatibleFeature(array $feature, array $data)
    {
        if (!empty($feature['parent_id'])) {
            return false;
        }

        $feature_type = strtolower((string) ifset($feature['type'], ''));
        $expected_type = strtolower((string) ifset($data['type'], ''));
        if ($feature_type !== $expected_type) {
            return false;
        }

        // A single-valued target would silently discard all but the first
        // imported value. The reverse mapping is safe: a multiple-valued
        // feature can also store a single value. Keep existing shop features
        // unchanged and let createFeature() allocate a free code if needed.
        return empty($data['multiple']) || !empty($feature['multiple']);
    }

    private function findFreshCompatibleFeature(array $data)
    {
        $candidates = array();
        $code = (string) ifset($data['code'], '');
        if ($code !== '') {
            $feature = $this->feature_model->getByField('code', $code);
            if ($feature) {
                $candidates[(int) $feature['id']] = $feature;
            }
        }

        $name = (string) ifset($data['name'], '');
        if ($name !== '') {
            foreach ((array) $this->feature_model->getByField('name', $name, true) as $feature) {
                if (is_array($feature) && !empty($feature['id'])) {
                    $candidates[(int) $feature['id']] = $feature;
                }
            }
        }

        foreach ($candidates as $feature) {
            if ($this->isCompatibleFeature($feature, $data)) {
                return $feature;
            }
        }
        return null;
    }

    private function buildFeatureCodeCandidate($base_code, array $attribute, $attempt)
    {
        $base_code = $this->normalizeCode($base_code);
        if ((int) $attempt <= 0) {
            return mb_substr($base_code, 0, self::FEATURE_CODE_MAX_LENGTH);
        }

        $characteristic_id = max(0, (int) ifset($attribute['characteristic_id'], 0));
        $suffix = '_wb'.($characteristic_id > 0 ? '_'.$characteristic_id : '');
        if ((int) $attempt > 1) {
            $suffix .= '_'.(int) $attempt;
        }
        $prefix_length = max(1, self::FEATURE_CODE_MAX_LENGTH - strlen($suffix));
        $prefix = rtrim(mb_substr($base_code, 0, $prefix_length), '_');
        if ($prefix === '') {
            $prefix = 'feature';
        }
        return mb_substr($prefix.$suffix, 0, self::FEATURE_CODE_MAX_LENGTH);
    }

    private function loadFeatureCatalog()
    {
        if ($this->feature_catalog_loaded) {
            return;
        }
        $this->features_by_code = array();
        $this->features_by_name = array();
        foreach ((array) $this->feature_model->getAll() as $feature) {
            if (is_array($feature)) {
                $this->indexFeature($feature);
            }
        }
        $this->feature_catalog_loaded = true;
    }

    private function indexFeature(array $feature)
    {
        $code = $this->normalizeCode(ifset($feature['code'], ''));
        if ($code !== '') {
            if (!isset($this->features_by_code[$code])) {
                $this->features_by_code[$code] = array();
            }
            $this->features_by_code[$code][] = $feature;
        }
        $name = $this->normalizeName(ifset($feature['name'], ''));
        if ($name !== '') {
            if (!isset($this->features_by_name[$name])) {
                $this->features_by_name[$name] = array();
            }
            $this->features_by_name[$name][] = $feature;
        }
    }

    private function saveMapping($snapshot_id, $subject_id, $characteristic_id, array $data)
    {
        $this->map_model->saveMapping($snapshot_id, $subject_id, $characteristic_id, $data);
        $this->map[$this->getMapKey($subject_id, $characteristic_id)] = array_merge(array(
            'snapshot_id'       => (int) $snapshot_id,
            'subject_id'        => (int) $subject_id,
            'characteristic_id' => (int) $characteristic_id,
        ), $data);
    }

    private function getMapKey($subject_id, $characteristic_id)
    {
        return (int) $subject_id.':'.(int) $characteristic_id;
    }

    private function ensureWarm($snapshot_id)
    {
        if ((int) $this->snapshot_id !== (int) $snapshot_id) {
            $this->warmup($snapshot_id);
        }
    }

    private function requireShopType($shop_type_id)
    {
        $shop_type_id = $this->requirePositiveId($shop_type_id, _wp('Invalid Shop-Script product type ID.'));
        if (!$this->type_model->getById($shop_type_id)) {
            throw new waException(_wp('The Shop-Script product type no longer exists.'));
        }
        return $shop_type_id;
    }

    private function isStringCollection($source_type, $max_count)
    {
        if ($this->isNumericSourceType($source_type)
            || $this->isBooleanSourceType($source_type)
            || $this->isDateSourceType($source_type)
        ) {
            return false;
        }
        return (int) $max_count === 0 || (int) $max_count > 1;
    }

    private function isNumericSourceType($source_type)
    {
        if ((string) $source_type === '4') {
            return true;
        }
        foreach (array('number', 'numeric', 'decimal', 'double', 'float', 'integer', 'int') as $needle) {
            if (strpos($source_type, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Maps explicit WB measurement units to Shop-Script dimension types.
     * Unknown units (for example, pieces) deliberately remain plain numbers.
     */
    private function detectDimensionFeature(array $attribute)
    {
        $raw_unit = trim((string) ifset($attribute['unit'], ''));
        if ($raw_unit === '') {
            $meta = ifset($attribute['meta'], array());
            if (is_string($meta)) {
                $decoded = json_decode($meta, true);
                $meta = is_array($decoded) ? $decoded : array();
            }
            if (is_array($meta)) {
                $raw_unit = trim((string) ifset($meta['unit'], ifset($meta['unitName'], '')));
            }
        }
        if ($raw_unit === '') {
            return null;
        }

        $token = $this->normalizeUnitToken($raw_unit);
        $dimensions = array(
            'length' => array(
                'mm' => array('мм', 'mm', 'миллиметр', 'миллиметры'),
                'cm' => array('см', 'cm', 'сантиметр', 'сантиметры'),
                'm'  => array('м', 'm', 'метр', 'метры'),
                'km' => array('км', 'km', 'километр', 'километры'),
                'in' => array('дюйм', 'дюймы', 'inch', 'in'),
                'ft' => array('фут', 'футы', 'foot', 'ft'),
                'yd' => array('ярд', 'ярды', 'yard', 'yd'),
                'mi' => array('миля', 'мили', 'mile', 'mi'),
            ),
            'weight' => array(
                'mg'  => array('мг', 'mg', 'миллиграмм'),
                'g'   => array('г', 'гр', 'g', 'gr', 'gram', 'грамм'),
                'kg'  => array('кг', 'kg', 'kilogram', 'килограмм'),
                'lbs' => array('lb', 'lbs', 'pound', 'фунт', 'фунты'),
                'oz'  => array('oz', 'ounce', 'унция', 'унции'),
            ),
            'volume' => array(
                'ml'  => array('мл', 'ml', 'миллилитр'),
                'cl'  => array('cl', 'сантилитр', 'центилитр'),
                'l'   => array('л', 'l', 'liter', 'litre', 'литр'),
                'mm3' => array('мм3', 'мм^3', 'мм³', 'mm3'),
                'cm3' => array('см3', 'см^3', 'см³', 'cm3', 'cc'),
                'm3'  => array('м3', 'м^3', 'м³', 'm3'),
            ),
            'area' => array(
                'sqmm' => array('мм2', 'мм^2', 'мм²', 'sqmm', 'mm2'),
                'sqcm' => array('см2', 'см^2', 'см²', 'sqcm', 'cm2'),
                'sqm'  => array('м2', 'м^2', 'м²', 'sqm', 'квм'),
                'sqkm' => array('км2', 'км^2', 'км²', 'sqkm'),
                'sqin' => array('дюйм2', 'дюйм²', 'sqin', 'in2'),
                'sqft' => array('фут2', 'фут²', 'sqft', 'ft2'),
                'sqyd' => array('ярд2', 'ярд²', 'sqyd', 'yd2'),
                'sqmi' => array('миля2', 'миля²', 'sqmi', 'mi2'),
                'ha'   => array('га', 'ha', 'hectare', 'гектар'),
                'ac'   => array('акр', 'ac', 'acre'),
            ),
            'memory' => array(
                'B'  => array('б', 'b', 'byte', 'байт'),
                'KB' => array('кб', 'kb', 'kilobyte', 'килобайт'),
                'MB' => array('мб', 'mb', 'megabyte', 'мегабайт'),
                'GB' => array('гб', 'gb', 'gigabyte', 'гигабайт'),
                'TB' => array('тб', 'tb', 'terabyte', 'терабайт'),
            ),
            'frequency' => array(
                'Hz'  => array('гц', 'hz', 'герц'),
                'kHz' => array('кгц', 'khz', 'килогерц'),
                'MHz' => array('мгц', 'mhz', 'мегагерц'),
                'GHz' => array('ггц', 'ghz', 'гигагерц'),
            ),
            'power' => array(
                'W'  => array('вт', 'w', 'ватт'),
                'KW' => array('квт', 'kw', 'киловатт'),
                'MW' => array('мегаватт', 'megawatt'),
                'mW' => array('милливатт', 'milliwatt'),
                'hp' => array('лс', 'hp', 'лошадинаясила'),
            ),
            'time' => array(
                'sec'   => array('с', 'сек', 'sec', 'second', 'секунда'),
                'min'   => array('мин', 'min', 'minute', 'минута'),
                'hr'    => array('ч', 'час', 'hr', 'hour'),
                'day'   => array('д', 'дн', 'день', 'сутки', 'day'),
                'week'  => array('нед', 'неделя', 'week'),
                'month' => array('мес', 'месяц', 'month'),
                'year'  => array('год', 'лет', 'year'),
            ),
            'temperature' => array(
                "\xC2\xB0C" => array('°c', 'celsius', 'цельсий', 'градусцельсия'),
                'K'           => array('k', 'kelvin', 'кельвин'),
                "\xC2\xB0F" => array('°f', 'fahrenheit', 'фаренгейт'),
            ),
            'amperage' => array(
                'mA' => array('ма', 'ma', 'миллиампер'),
                'A'  => array('а', 'a', 'amp', 'ампер'),
                'KA' => array('ка', 'ka', 'килоампер'),
                'MA' => array('мегаампер', 'megaamp'),
            ),
            'electric_charge' => array(
                'mAh' => array('мач', 'mah', 'миллиамперчас'),
                'Ah'  => array('ач', 'ah', 'амперчас'),
                'KAh' => array('кач', 'kah', 'килоамперчас'),
                'MAh' => array('мегаамперчас', 'megaamperehour'),
            ),
            'voltage' => array(
                'mV' => array('мв', 'mv', 'милливольт'),
                'V'  => array('в', 'v', 'вольт'),
                'kV' => array('кв', 'kv', 'киловольт'),
                'MV' => array('мегавольт', 'megavolt'),
            ),
        );

        foreach ($dimensions as $dimension_type => $units) {
            foreach ($units as $unit => $aliases) {
                foreach ($aliases as $alias) {
                    if ($token === $this->normalizeUnitToken($alias)) {
                        $available = shopDimension::getUnits($dimension_type);
                        return isset($available[$unit])
                            ? array('type' => $dimension_type, 'unit' => $unit)
                            : null;
                    }
                }
            }
        }
        return null;
    }

    private function normalizeUnitToken($unit)
    {
        $unit = $this->normalizeSourceType($unit);
        $unit = str_replace(array('ё', '²', '^2', '³', '^3'), array('е', '2', '2', '3', '3'), $unit);
        $unit = preg_replace('/[\s\.\-_\/]+/u', '', $unit);
        return $unit === null ? '' : trim($unit);
    }

    private function isBooleanSourceType($source_type)
    {
        return in_array($source_type, array('bool', 'boolean', 'switch'), true);
    }

    private function isDateSourceType($source_type)
    {
        return in_array($source_type, array('date', 'datetime'), true);
    }

    private function isLongTextSourceType($source_type)
    {
        return in_array($source_type, array('text', 'textarea', 'longtext'), true);
    }

    private function isColorAttribute($name, $source_type)
    {
        if (strpos($source_type, 'color') !== false || strpos($source_type, 'colour') !== false) {
            return true;
        }
        $name = $this->normalizeName($name);
        return in_array($name, array('color', 'colour', 'цвет', 'цвет товара', 'цвет изделия'), true);
    }

    private function normalizeSourceType($source_type)
    {
        $source_type = trim((string) $source_type);
        return function_exists('mb_strtolower')
            ? mb_strtolower($source_type, 'UTF-8')
            : strtolower($source_type);
    }

    private function isAutoMapping(array $mapping)
    {
        return ifset($mapping['mode'], '') === self::MODE_AUTO;
    }

    private function requirePositiveId($value, $message)
    {
        $value = (int) $value;
        if ($value <= 0) {
            throw new waException($message);
        }
        return $value;
    }

    private function cleanName($name)
    {
        $name = trim((string) $name);
        $clean = preg_replace('/\s+/u', ' ', $name);
        return $clean === null ? $name : $clean;
    }

    private function normalizeName($name)
    {
        $name = $this->cleanName($name);
        return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    }

    private function normalizeCode($code)
    {
        return strtolower(trim((string) $code));
    }
}
