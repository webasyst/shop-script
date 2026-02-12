<?php

class shopMigrateOzonFeatureMapper
{
    private $repository;
    private $settings;
    private $feature_model;
    private $type_features_model;
    private $map_model;
    private $map = array();

    public function __construct(shopMigrateOzonSnapshotRepository $repository, shopMigrateOzonSettings $settings)
    {
        $this->repository = $repository;
        $this->settings = $settings;
        $this->feature_model = new shopFeatureModel();
        $this->type_features_model = new shopTypeFeaturesModel();
        $this->map_model = $repository->getFeatureMapModel();
    }

    public function warmup($snapshot_id)
    {
        $this->map = $this->map_model->getMap($snapshot_id);
    }

    public function resolve($snapshot_id, $attribute_id, array $attribute, $shop_type_id)
    {
        if ($this->shouldSkipAttribute($attribute)) {
            return null;
        }

        if (!$this->settings->shouldForceTextFeatures()) {
            if ($builtin = $this->resolveBuiltinFeature($attribute, $shop_type_id)) {
                return $builtin;
            }
        }

        $mode = $this->settings->getOperationMode();
        if ($mode === shopMigrateOzonSettings::MODE_MANUAL && isset($this->map[$attribute_id])) {
            $option = $this->map[$attribute_id];
            if (isset($option['action']) && $option['action'] === 'skip') {
                return null;
            }
            if (!empty($option['shop_feature_id'])) {
                $this->bindFeatureToType($option['shop_feature_id'], $shop_type_id);
                return $this->feature_model->getById((int) $option['shop_feature_id']);
            }
        }

        if (!empty($this->map[$attribute_id]['shop_feature_id']) && $this->map[$attribute_id]['mode'] === shopMigrateOzonSettings::MODE_AUTO) {
            $this->bindFeatureToType($this->map[$attribute_id]['shop_feature_id'], $shop_type_id);
            return $this->feature_model->getById((int) $this->map[$attribute_id]['shop_feature_id']);
        }

        $feature = $this->createFeature($attribute, $shop_type_id);
        $this->map_model->saveAuto($snapshot_id, $attribute_id, array(
            'mode'            => shopMigrateOzonSettings::MODE_AUTO,
            'shop_feature_id' => $feature['id'],
            'shop_feature_code' => $feature['code'],
            'action'          => 'auto',
        ));
        $this->map[$attribute_id] = array(
            'shop_feature_id' => $feature['id'],
            'mode'            => shopMigrateOzonSettings::MODE_AUTO,
        );

        return $feature;
    }

    private function resolveBuiltinFeature(array $attribute, $shop_type_id)
    {
        $name = $this->normalizeAttributeText(ifset($attribute['name'], ''));
        if ($name === '') {
            return null;
        }
        $color_aliases = array(
            'цвет товара',
            'цвет',
            'цвет изделия',
            'цвет продукта',
            'цвет предмета',
        );
        if (in_array($name, $color_aliases, true)) {
            $feature = $this->feature_model->getByField('code', 'color');
            if ($feature) {
                $this->bindFeatureToType($feature['id'], $shop_type_id);
                return $feature;
            }
        }
        return null;
    }

    private function createFeature(array $attribute, $shop_type_id)
    {
        $data = $this->guessFeatureData($attribute);
        list($selectable, $multiple) = $this->resolveFeatureFlags($data['type'], $attribute);
        $normalized_name = $this->normalizeAttributeText(ifset($attribute['name'], ''));
        if ($this->isForcedSingleSelectableText($normalized_name)) {
            $selectable = 1;
            $multiple = 0;
        }
        $data['status'] = 'public';
        $data['selectable'] = $selectable;
        $data['multiple'] = $multiple;
        $data['available_for_sku'] = 1;
        $data['count'] = 1;
        $default_unit = $this->detectDefaultUnit($attribute, $data['type']);
        if ($default_unit !== null) {
            $data['default_unit'] = $default_unit;
        }

        $feature = $this->feature_model->getByField('code', $data['code']);
        if (!$feature) {
            $feature = $this->feature_model->getByField('name', $data['name']);
        }

        if ($feature) {
            $this->bindFeatureToType($feature['id'], $shop_type_id);
            return $feature;
        }

        $id = $this->feature_model->insert($data);
        $feature = $this->feature_model->getById($id);
        $this->bindFeatureToType($feature['id'], $shop_type_id);

        return $feature;
    }

    private function resolveFeatureFlags($type, array $attribute)
    {
        $normalized = strtolower($type);
        $dictionary_id = $this->getAttributeMetaValue($attribute, 'dictionary_id');
        $has_dictionary = !empty($dictionary_id) || (!empty($attribute['type']) && stripos($attribute['type'], 'dict') !== false);
        $is_collection_flag = $this->getAttributeMetaValue($attribute, 'is_collection');
        $is_collection = $is_collection_flag !== null ? (bool) $is_collection_flag : !empty($attribute['is_collection']);

        $selectable = $has_dictionary ? 1 : 0;
        $multiple = $is_collection ? 1 : 0;

        $numeric_types = array('double', 'range', 'date', 'datetime', 'time', 'boolean', 'switch');
        if (strpos($normalized, 'dimension.') === 0 || in_array($normalized, $numeric_types, true)) {
            $selectable = 0;
            $multiple = 0;
        }

        if ($normalized === 'color') {
            $selectable = 1;
            $multiple = $is_collection ? 1 : 0;
        }

        return array($selectable, $multiple);
    }

    private function guessFeatureData(array $attribute)
    {
        $name = ifset($attribute['name'], 'Ozon attribute');
        if ($this->settings->shouldForceTextFeatures()) {
            return array(
                'code' => $this->generateCode($name),
                'name' => $name,
                'type' => 'text',
            );
        }
        $normalized_name = $this->normalizeAttributeText($name);
        $has_comma = (mb_strpos($name, ',') !== false);
        $code = $this->generateCode($name);
        $unit = isset($attribute['unit']) ? trim($attribute['unit']) : '';
        $type = 'text';

        if ($this->isForcedTextAttribute($normalized_name)) {
            $type = 'text';
        } elseif ($this->attributeIsAccumulatorCapacity($normalized_name)) {
            $type = 'dimension.electric_charge';
        } elseif ($this->attributeContainsTimeKeyword($normalized_name)) {
            $type = 'dimension.time';
        } elseif ($this->shouldForceDoubleType($name, $attribute)) {
            $type = 'double';
        } elseif (!empty($attribute['is_collection'])) {
            $type = 'text';
        } elseif (!empty($attribute['type']) && stripos($attribute['type'], 'dict') !== false) {
            $type = 'text';
        } elseif (!$has_comma && !$this->attributeIsNumeric($attribute)) {
            $type = 'text';
        } else {
            $guess = $this->guessUnitType($unit, $name);
            if ($guess) {
                $type = $guess;
            } elseif (!empty($attribute['type']) && stripos($attribute['type'], 'number') !== false) {
                $type = 'double';
            }
        }

        if ($this->attributeLooksLikeColor($attribute)) {
            $type = 'color';
        }

        return array(
            'code' => $code,
            'name' => $name,
            'type' => $type,
        );
    }

    private function generateCode($name)
    {
        $base = strtolower(waLocale::transliterate($name));
        $base = preg_replace('/[^a-z0-9_]+/', '_', $base);
        $base = trim($base, '_');
        if ($base === '') {
            $base = 'ozon_attr';
        }
        $code = $base;
        $suffix = 1;
        while ($this->feature_model->getByField('code', $code)) {
            $code = $base.'_'.$suffix++;
        }
        return $code;
    }

    private function guessUnitType($unit, $name)
    {
        $unit = mb_strtolower($unit);
        $mapping = array(
            array('units' => array('мм', 'mm', 'см', 'cm', 'м', 'm', 'inch', 'дюйм'), 'type' => 'dimension.length'),
            array('units' => array('кг', 'kg', 'г', 'gr', 'гр', 'lb'), 'type' => 'dimension.weight'),
            array('units' => array('л', 'l', 'ml', 'мл'), 'type' => 'dimension.volume'),
            array('units' => array('м2', 'кв.м', 'sq'), 'type' => 'dimension.area'),
            array('units' => array('гб', 'gb', 'mb', 'tb'), 'type' => 'dimension.memory'),
            array('units' => array('°c', 'c', 'градус', 'celsius'), 'type' => 'dimension.temperature'),
            array('units' => array('ммоль', 'ma', 'a', 'ампер'), 'type' => 'dimension.electric_current'),
            array('units' => array('в', 'v', 'вольт'), 'type' => 'dimension.voltage'),
            array('units' => array('ватт', 'w', 'kw', 'квт'), 'type' => 'dimension.power'),
            array('units' => array('час', 'мин', 'сек', 'h', 'min', 's'), 'type' => 'dimension.time'),
        );
        foreach ($mapping as $item) {
            foreach ($item['units'] as $known) {
                if ($known !== '' && (strpos($unit, $known) !== false || strpos(mb_strtolower($name), $known) !== false)) {
                    return $item['type'];
                }
            }
        }
        return null;
    }

    private function attributeLooksLikeColor(array $attribute)
    {
        $name = mb_strtolower(ifset($attribute['name'], ''));
        if ($name !== '' && strpos($name, 'цвет') !== false) {
            return true;
        }
        if (!empty($attribute['type']) && stripos($attribute['type'], 'color') !== false) {
            return true;
        }

        return false;
    }

    private function shouldSkipAttribute(array $attribute)
    {
        $name = mb_strtolower(ifset($attribute['name'], ''));
        if ($name === '') {
            return false;
        }
        if (strpos($name, '(для объединения в одну карточку)') !== false) {
            return true;
        }
        if (strpos($name, 'для объединения модели в одну карточку') !== false) {
            return true;
        }
        if ($name === 'название модели (для объединения в одну карточку)') {
            return true;
        }
        if (strpos($name, 'для шаблона наименования') !== false) {
            return true;
        }
        if ($name === 'название' || $name === 'код продавца') {
            return true;
        }
        if ($name === 'аннотация') {
            return true;
        }
        return false;
    }

    private function getAttributeMetaValue(array $attribute, $key)
    {
        if (array_key_exists($key, $attribute)) {
            return $attribute[$key];
        }
        if (!isset($attribute['meta'])) {
            return null;
        }
        $meta = $attribute['meta'];
        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            if (is_array($decoded)) {
                $meta = $decoded;
            } else {
                return null;
            }
        }
        if (!is_array($meta)) {
            return null;
        }
        return array_key_exists($key, $meta) ? $meta[$key] : null;
    }

    public function detectAttributeUnit(array $attribute, array $feature)
    {
        return $this->detectUnitFromAttribute($attribute, ifset($feature['type']));
    }

    private function detectDefaultUnit(array $attribute, $feature_type)
    {
        return $this->detectUnitFromAttribute($attribute, $feature_type);
    }

    private function detectUnitFromAttribute(array $attribute, $feature_type)
    {
        $dimension = $this->extractDimensionName($feature_type);
        if ($dimension === null) {
            return null;
        }
        $available_units = shopDimension::getUnits($dimension);
        if (!$available_units) {
            return null;
        }
        $name = $this->normalizeAttributeText(ifset($attribute['name'], ''));
        if ($name === '') {
            return null;
        }
        $raw_unit = $this->extractRawUnit($attribute);
        $extra = array();
        if ($raw_unit !== '') {
            $extra[] = $this->normalizeAttributeText($raw_unit);
        }
        list($base_name, $candidates) = $this->extractUnitCandidates($name, $extra);
        $unit = null;
        switch ($dimension) {
            case 'weight':
                if ($this->containsAny($base_name, array('срок', 'гарант', 'служб'))) {
                    break;
                }
                $unit = $this->matchUnitByTokens($candidates, $base_name, array(
                    'kg'  => array('кг', 'килограмм', 'килог', 'kg', 'kilogram'),
                    'g'   => array('г', 'г.', 'гр', 'грамм', 'gram', 'g'),
                    'mg'  => array('мг', 'миллиграмм', 'mg', 'milligram'),
                    'lbs' => array('lb', 'lbs', 'фунт', 'фунтов', 'pound'),
                    'oz'  => array('oz', 'унция', 'унций'),
                ));
                break;
            case 'length':
                $unit = $this->matchUnitByTokens($candidates, $base_name, array(
                    'mm' => array('мм', 'millimeter', 'миллиметр', 'mm'),
                    'cm' => array('см', 'centimeter', 'сантиметр', 'cm'),
                    'm'  => array('м', 'метр', 'meter', 'm'),
                    'km' => array('км', 'километр', 'km', 'kilometer'),
                    'in' => array('дюйм', '"', 'inch', 'in'),
                    'ft' => array('фут', 'ft'),
                    'yd' => array('ярд', 'yd'),
                    'mi' => array('миля', 'mile', 'mi'),
                ));
                break;
            case 'volume':
                $unit = $this->matchUnitByTokens($candidates, $base_name, array(
                    'l'   => array('л', 'литр', 'l', 'liter'),
                    'ml'  => array('мл', 'миллилитр', 'ml'),
                    'cm3' => array('см3', 'см^3', 'см³', 'кубсм', 'cubic centimeter', 'cm3', 'cc'),
                    'm3'  => array('м3', 'м^3', 'м³', 'кубм', 'кубометр', 'cubic meter', 'm3'),
                    'mm3' => array('мм3', 'мм^3', 'mm3'),
                    'cl'  => array('cl', 'центилитр', 'сантилитр'),
                ));
                break;
            case 'area':
                $unit = $this->matchUnitByTokens($candidates, $base_name, array(
                    'sqm'  => array('кв м', 'м2', 'м^2', 'м²', 'square meter', 'квадратный метр', 'sqm'),
                    'sqcm' => array('кв см', 'см2', 'см^2', 'см²', 'sqcm'),
                    'sqmm' => array('кв мм', 'мм2', 'мм^2', 'mm2', 'sqmm'),
                    'sqft' => array('кв фут', 'фут2', 'sqft', 'ft2'),
                    'sqyd' => array('кв ярд', 'ярд2', 'sqyd'),
                    'sqin' => array('кв дюйм', 'дюйм2', 'sqin', 'in2'),
                    'sqkm' => array('кв км', 'км2', 'sqkm'),
                    'sqmi' => array('кв миля', 'миля2', 'sqmi'),
                    'ha'   => array('га', 'hectare', 'ha'),
                    'ac'   => array('акр', 'acre', 'ac'),
                ));
                break;
            case 'frequency':
                $unit = $this->matchUnitByTokens($candidates, $base_name, array(
                    'Hz'  => array('гц', 'герц', 'hz'),
                    'kHz' => array('кгц', 'килогерц', 'khz'),
                    'MHz' => array('мгц', 'мегагерц', 'mhz'),
                    'GHz' => array('ггц', 'гигагерц', 'ghz'),
                ));
                break;
            case 'power':
                $unit = $this->matchUnitByTokens($candidates, $base_name, array(
                    'W'  => array('вт', 'ватт', 'w'),
                    'KW' => array('квт', 'киловатт', 'kw'),
                    'MW' => array('мвт', 'мегаватт', 'mw'),
                    'mW' => array('мв', 'милливатт', 'mw'),
                    'hp' => array('лс', 'лошад', 'hp'),
                ));
                break;
            case 'time':
                $unit = $this->matchUnitByTokens($candidates, $base_name, array(
                    'sec'  => array('с', 'сек', 'секунд', 'sec'),
                    'min'  => array('мин', 'minute', 'минут'),
                    'hr'   => array('ч', 'час', 'часов', 'hr', 'h'),
                    'day'  => array('д', 'дн', 'день', 'сут', 'day'),
                    'week' => array('нед', 'недел', 'week'),
                    'month'=> array('мес', 'месяц', 'month'),
                    'year' => array('г', 'г.', 'год', 'лет', 'year'),
                ));
                break;
            case 'memory':
                $unit = $this->matchUnitByTokens($candidates, $base_name, array(
                    'B'  => array('б', 'байт', 'b', 'byte'),
                    'KB' => array('кб', 'кбайт', 'kb', 'kilobyte'),
                    'MB' => array('мб', 'мбайт', 'mb', 'megabyte'),
                    'GB' => array('гб', 'гбайт', 'gb', 'gigabyte'),
                    'TB' => array('тб', 'тбайт', 'tb', 'terabyte'),
                ));
                break;
            case 'temperature':
                $unit = $this->matchUnitByTokens($candidates, $base_name, array(
                    "\xC2\xB0C" => array('c', 'градус цельсия', 'цельс', '°c'),
                    'K'         => array('k', 'келвин', 'kelvin'),
                    "\xC2\xB0F" => array('f', 'фаренгейт', '°f'),
                ));
                break;
            case 'amperage':
                $unit = $this->matchUnitByTokens($candidates, $base_name, array(
                    'mA' => array('ма', 'миллиампер', 'ma'),
                    'A'  => array('а', 'ампер', 'a', 'amp'),
                    'KA' => array('ка', 'килоампер', 'ka'),
                    'MA' => array('мегаампер', 'megaamp'),
                ));
                break;
            case 'electric_charge':
                $unit = $this->matchUnitByTokens($candidates, $base_name, array(
                    'mAh' => array('мач', 'mah'),
                    'Ah'  => array('ач', 'ah'),
                    'KAh' => array('кач', 'ках', 'kah'),
                    'MAh' => array('мах', 'megah'),
                ));
                break;
            case 'voltage':
                $unit = $this->matchUnitByTokens($candidates, $base_name, array(
                    'mV' => array('мв', 'милливольт', 'mv'),
                    'V'  => array('в', 'вольт', 'v'),
                    'kV' => array('кв', 'киловольт', 'kv'),
                    'MV' => array('мв', 'мегавольт', 'mv'),
                ));
                break;
        }
        if ($unit && isset($available_units[$unit])) {
            return $unit;
        }
        return null;
    }

    private function extractDimensionName($feature_type)
    {
        if (!is_string($feature_type) || $feature_type === '') {
            return null;
        }
        if (strpos($feature_type, 'dimension.') === 0 || strpos($feature_type, 'range.') === 0) {
            return substr($feature_type, strpos($feature_type, '.') + 1);
        }
        return null;
    }

    private function normalizeAttributeText($text)
    {
        if (!is_string($text)) {
            return '';
        }
        $text = mb_strtolower($text, 'UTF-8');
        $text = str_replace('ё', 'е', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    private function extractUnitCandidates($name, array $extra_candidates = array())
    {
        $candidates = array();
        $base = $name;
        $pos = mb_strrpos($base, ',');
        if ($pos !== false) {
            $tail = trim(mb_substr($base, $pos + 1));
            if ($tail !== '') {
                $candidates[] = $tail;
            }
            $base = trim(mb_substr($base, 0, $pos));
        }
        if (preg_match('/\(([^)]+)\)\s*$/u', $base, $m)) {
            $value = trim($m[1]);
            if ($value !== '') {
                $candidates[] = $value;
            }
            $base = trim(mb_substr($base, 0, -mb_strlen($m[0])));
        }
        $last_word = $this->extractLastWord($base);
        if ($last_word !== '') {
            $candidates[] = $last_word;
        }
        $candidates = array_merge($extra_candidates, $candidates);
        $candidates = array_values(array_unique(array_filter($candidates, 'strlen')));
        return array($base, $candidates);
    }

    private function extractRawUnit(array $attribute)
    {
        if (!empty($attribute['meta']['unit']) && is_string($attribute['meta']['unit'])) {
            return $attribute['meta']['unit'];
        }
        if (!empty($attribute['unit']) && is_string($attribute['unit'])) {
            return $attribute['unit'];
        }
        return '';
    }

    private function extractLastWord($text)
    {
        if ($text === '') {
            return '';
        }
        $parts = preg_split('/[\s\/-]+/u', $text);
        $last = array_pop($parts);
        return $last ? trim($last) : '';
    }

    private function matchUnitByTokens(array $candidates, $text, array $map)
    {
        foreach ($candidates as $candidate) {
            $unit = $this->matchUnitToken($candidate, $map);
            if ($unit !== null) {
                return $unit;
            }
        }
        if ($text !== '') {
            foreach ($map as $unit => $variants) {
                if ($this->textContainsVariant($text, $variants)) {
                    return $unit;
                }
            }
        }
        return null;
    }

    private function matchUnitToken($candidate, array $map)
    {
        $normalized = $this->normalizeUnitToken($candidate);
        if ($normalized === '') {
            return null;
        }
        foreach ($map as $unit => $variants) {
            foreach ($variants as $variant) {
                if ($normalized === $this->normalizeUnitToken($variant)) {
                    return $unit;
                }
            }
        }
        return null;
    }

    private function normalizeUnitToken($token)
    {
        $token = $this->normalizeAttributeText($token);
        if ($token === '') {
            return '';
        }
        $token = str_replace(array('²', '³', '°'), array('2', '3', ''), $token);
        $token = str_replace(array('·', '•', '∙', '*'), '', $token);
        $token = preg_replace('/[^a-z0-9а-я]+/u', '', $token);
        return $token;
    }

    private function textContainsVariant($text, array $variants)
    {
        foreach ($variants as $variant) {
            $needle = $this->normalizeAttributeText($variant);
            if ($needle === '') {
                continue;
            }
            $pattern = '/(^|[\s,;:\(\)\/\-])'.preg_quote($needle, '/').'($|[\s,;:\(\)\/\-])/u';
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        return false;
    }

    private function containsAny($text, array $needles)
    {
        foreach ($needles as $needle) {
            if (mb_strpos($text, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    private function attributeContainsTimeKeyword($normalized_name)
    {
        return $normalized_name !== '' && mb_strpos($normalized_name, 'время') !== false;
    }

    private function shouldForceDoubleType($original_name, array $attribute)
    {
        if (!is_string($original_name) || $original_name === '') {
            return false;
        }
        if (mb_strpos($original_name, ',') !== false) {
            return false;
        }
        return $this->attributeIsNumeric($attribute);
    }

    private function attributeIsNumeric(array $attribute)
    {
        $type_meta = '';
        if (!empty($attribute['type']) && is_string($attribute['type'])) {
            $type_meta = mb_strtolower($attribute['type'], 'UTF-8');
        }
        if ($type_meta === '') {
            return false;
        }
        foreach (array('decimal', 'number', 'numeric', 'double', 'float', 'integer', 'int') as $needle) {
            if (strpos($type_meta, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    private function attributeIsAccumulatorCapacity($normalized_name)
    {
        return $normalized_name !== ''
            && mb_strpos($normalized_name, 'емкост') !== false
            && mb_strpos($normalized_name, 'аккумулятор') !== false;
    }

    private function isForcedTextAttribute($normalized_name)
    {
        static $forced = array(
            'гарантийный срок' => true,
            'обратная связь'   => true,
        );
        return isset($forced[$normalized_name]);
    }

    private function isForcedSingleSelectableText($normalized_name)
    {
        static $forced = array(
            'гарантийный срок' => true,
        );
        return isset($forced[$normalized_name]);
    }

    private function bindFeatureToType($feature_id, $type_id)
    {
        $exists = $this->type_features_model->getByField(array(
            'type_id'    => (int) $type_id,
            'feature_id' => (int) $feature_id,
        ));
        if ($exists) {
            return;
        }
        $sort = (int) $this->type_features_model->select('MAX(sort) sort')->where('type_id = ?', (int) $type_id)->fetchField('sort');
        $this->type_features_model->insert(array(
            'type_id'    => (int) $type_id,
            'feature_id' => (int) $feature_id,
            'sort'       => $sort + 1,
        ));
    }

    public function updateFeatureFlags($feature_id, array $data)
    {
        $allowed = array('selectable', 'multiple', 'status');
        $update = array();
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $update[$key] = (int) $value;
            }
        }
        if ($update) {
            $this->feature_model->updateById($feature_id, $update);
        }
    }
}
