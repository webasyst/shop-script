<?php

class shopChestnyznakPluginCodeParser
{
    /**
     * All possible AI codes
     * https://www.gs1.org/docs/barcodes/GS1_General_Specifications.pdf
     *
     * Need for correct extract serial number
     * @var string[]
     */
    private static $ai_codes;

    private static $serial_max_len = 20;

    /**
     * @var array - conversion table, see method convert()
     * @see convert()
     */
    protected static $convert;

    /**
     * Парсим GS1 DataMatrix
     *
     * See https://www.gs1.org/docs/barcodes/GS1_DataMatrix_Guideline.pdf page 15
     *
     * To parse correctly this GS1 should has separators on right places
     * Also you can pass serial number length to ensure parse result be adequate
     *
     * @param string $gs1
     *
     * @return array $result
     *      bool $result['status']
     *      array $result['details'] - result details of parsing
     *
     *          $result['status'] === FALSE:
     *              string $result['details']['error_type'] - общий код ошибки (invalid_argument, invalid_format)
     *              string $result['details']['error_code'] - конкретный код ошибки - отражает суть
     *              string $result['details']['error_message'] - сообщение ошибки на русском
     *
     *          $result['status'] === TRUE:
     *              string $result['details']['gtin'] - GTIN (14 символов)
     *              string $result['details']['serial'] - серийный номер товара (7, 13, 20 символов)
     *              string $result['details']['is_separator_missed'] - While trying extract serial code there is missed group separator, so serial code could be not correct
     *
     */
    public static function parse($gs1)
    {
        if (!is_string($gs1)) {
            return [
                'status' => false,
                'details' => [
                    'error_type' => 'invalid_argument',
                    'error_code' => 'expected_string',
                    'error_message' => 'Ожидается строка'
                ]
            ];
        }

        if (self::containsCyrillic($gs1)) {
            return [
                'status' => false,
                'details' => [
                    'error_type' => 'invalid_argument',
                    'error_code' => 'contains_cyrillic',
                    'error_message' => 'Строка не должна содержать кириллические символы'
                ]
            ];
        }

        // AI codes for GTIN and for serial
        $ai_gtin = '01';
        $ai_serial = '21';

        $current_pos = strpos($gs1, $ai_gtin);
        if ($current_pos === false) {
            return [
                'status' => false,
                'details' => [
                    'error_type' => 'invalid_format',
                    'error_code' => 'not_found_gtin_ai',
                    'error_message' => 'Не найден идентификатор применения для GTIN'
                ]
            ];
        }

        $gtin_len = 14;
        $gtin = substr($gs1, $current_pos, $gtin_len + 2);

        if (!self::isAllDigits($gtin)) {
            return [
                'status' => false,
                'details' => [
                    'error_type' => 'invalid_format',
                    'error_code' => 'gtin_invalid',
                    'error_message' => 'GTIN должен исостоять только из цифр'
                ]
            ];
        }

        $gtin = substr($gtin, 2);

        $current_pos += $gtin_len + 2;

        $serial_pos = strpos($gs1, $ai_serial, $current_pos);
        if ($serial_pos === false) {
            return [
                'status' => false,
                'details' => [
                    'error_type' => 'invalid_format',
                    'error_code' => 'not_found_serial_ai',
                    'error_message' => 'Не найден идентификатор применения для серийного номера'
                ]
            ];
        }

        // skip ai code itself
        $current_pos = $serial_pos + 2;

        // group separator \x1d separates AI values for variadic lengths inside GS1
        // and there are all possible variants of group separators in practical reality
        $separators = [
            "\x1d",                 // as byte
            '\x1d',                 // as string
            '\\x1d',                // as string
            '\x001d',               // as string
            '\\x001d',              // as string
            '\u001d',               // as string
            '\\u001d',              // as string
            '<FNC1>',               // as string
            '<GS>',                 // as string
            '<GS> ',                // as string
        ];

        // try extract serial when if bound by one of separator
        $serial = self::getSerialBoundedBySep($gs1, $current_pos, $separators);
        if ($serial) {
            return [
                'status' => true,
                'details' => [
                    'gtin' => $gtin,
                    'serial' => $serial,
                    'is_separator_missed' => false,
                ]
            ];
        }

        // all possible AI codes - see https://www.gs1.org/docs/barcodes/GS1_General_Specifications.pdf page 143
        // exclude GTIN and serial
        // each of code could be separator for serial number segment
        $ai_codes = self::getAICodes([$ai_gtin, $ai_serial]);

        // try extract serial when if bound by one of ai code
        $serial = self::getSerialBoundedBySep($gs1, $current_pos, $ai_codes);
        if ($serial) {
            return [
                'status' => true,
                'details' => [
                    'gtin' => $gtin,
                    'serial' => $serial,
                    'is_separator_missed' => true,
                ]
            ];
        }

        // https://xn--80ajghhoc2aj1c8b.xn--p1ai/upload/iblock/7ea/ru_API_OMS_CLOUD.pdf (page 118)
        // 13 is most usable serial number - try this
        $serial = substr($gs1, $current_pos, 13);

        if (!$serial) {
            return [
                'status' => false,
                'details' => [
                    'error_type' => 'invalid_format',
                    'error_code' => 'could_not_extract_serial',
                    'error_message' => 'Не получилось вытащить серийный номер'
                ]
            ];
        }

        return [
            'status' => true,
            'details' => [
                'gtin' => $gtin,
                'serial' => $serial,
                'is_separator_missed' => true,
            ]
        ];
    }

    /**
     * Переводит символы кириллические раскладки (русской) клавиатуры в символы латинской раскладки
     * @param string $str
     * @return string
     * @throws waException
     */
    public static function convert($str)
    {
        if (!self::containsCyrillic($str)) {
            return $str;
        }

        if (self::$convert === null) {
            self::$convert = [];
            $file_path = wa()->getAppPath('plugins/chestnyznak/lib/config/data/convert.php', 'shop');
            if (file_exists($file_path)) {
                self::$convert = include($file_path);
            }
        }

        return strtr($str, self::$convert);
    }

    private static function getAICodes(array $exclude = [])
    {
        if (self::$ai_codes == null) {
            self::$ai_codes = [];
            $file_path = wa()->getAppPath('plugins/chestnyznak/lib/config/data/ai_codes.php', 'shop');
            if (file_exists($file_path)) {
                self::$ai_codes = include($file_path);
            }
        }

        return array_diff(self::$ai_codes, $exclude);
    }

    private static function getMinSubStrPos($string, $offset, $separators = [])
    {
        $min_sep_pos = false;
        foreach ($separators as $sep) {
            $sep_pos = strpos($string, $sep, $offset);
            if ($sep_pos !== false) {
                if ($min_sep_pos === false) {
                    $min_sep_pos = $sep_pos;
                } elseif ($sep_pos < $min_sep_pos) {
                    $min_sep_pos = $sep_pos;
                }
            }
        }
        return $min_sep_pos;
    }

    private static function getSerialBoundedBySep($gs1, $offset, $separators = [])
    {
        $max_serial_section = substr($gs1, $offset, self::$serial_max_len);

        $sep_pos = self::getMinSubStrPos($max_serial_section, 0, $separators);
        if ($sep_pos !== false) {
            return substr($max_serial_section, 0, $sep_pos);
        }

        $gs1_len = strlen($gs1);
        if ($gs1_len <= $offset + strlen($max_serial_section)) {
            return $max_serial_section;
        }

        return '';
    }

    /**
     * Кассовое программное обеспечение должно отнести отсканированный
     * код, к группе обувных маркируемых товаров, выделить из кода GTIN и
     * серийный номер, после чего передать информацию для формирования
     * тега 1162 фискального документа согласно следующему алгоритму:
     * https://xn--80ajghhoc2aj1c8b.xn--p1ai/upload/iblock/d04/formirovanie-tega-1162-na-KKT.pdf
     *
     * NOTICE: работает только ДЛЯ DataMatrix штрих-кодов
     * Для других видов кодов этот алогоримт не подходит (см доку)
     *
     * @param string $uid уникальный идентификатор товара - УИД, который поступает из DataMatrix или введен вручную
     * @param array $options
     *      $options['with_tag_code'] - default is TRUE
     * @return string - TLV значение для ККТ. Каждый байт разделен пробелом (байт=2 hex симвора)
     *  Пример - 8a 04 44 4d 15 20 04 36 03 be f5 14 73 67 45 4b 4b 50 50 63 53 32 35 79 35
     */
    public static function convertToFiscalCode($uid, $options = [])
    {
        $parse_result = self::parse($uid);
        if (!$parse_result['status']) {
            return false;
        }

        $parsed = $parse_result['details'];

        // Используется 14 разрядный GTIN, при записи в ККТ, GTIN представляется как
        // десятичное 14 знаковое число и преобразуется в BIN (big endian), размером 6 байт
        // ...
        // В случае, если GTIN менее 14 символов, его необходимо дополнить ведущими
        // нулями
        $gtin = $parsed['gtin'];
        $gtin_hex = base_convert($gtin, 10, 16);

        // Ожидаем 6 байт, каждый байт это 2 hex символа. Дополняем ведущими нулями до нужного размера
        if (strlen($gtin_hex) < 12) {
            $gtin_hex = str_pad($gtin_hex, 12, '0', STR_PAD_LEFT);
        }

        $serial = $parsed['serial'];
        $serial_hex = bin2hex($serial);

        // Формируем тег 1162:
        // Добавляем код типа маркировки: 44 4D - для DataMatrix
        // Формируем TLV для передачи в ККТ. Так как тег 1162 не имеет фиксированное
        // значение, 11 байт резерва в ККТ не передаются:
        $tag_value = '444d' . $gtin_hex . $serial_hex;

        if (!array_key_exists('with_tag_code', $options)) {
            $options['with_tag_code'] = true;
        } else {
            $options['with_tag_code'] = (bool)$options['with_tag_code'];
        }

        if (!$options['with_tag_code']) {
            // На выходе строка где байты (группы из 2 heх символов) разбит пробелами
            return join(' ', str_split($tag_value, 2));
        }

        $tag_value_len = strlen($tag_value) / 2;    // strlen дает кол-во hex символов, но нам надо кол-во байт

        $tag_value_len_hex = base_convert($tag_value_len, 10, 16);

        // Длина к TLV формате должна размещаться в 2 байтах. Каждый байт это 2 hex символа. Заполняем нулями справа до нужно размера
        if (strlen($tag_value_len_hex) < 4) {
            $tag_value_len_hex = str_pad($tag_value_len_hex, 4, '0', STR_PAD_RIGHT);
        }

        $tag_code = '8a04'; // это код для тега 1162 в hex формате

        $tlv = $tag_code . $tag_value_len_hex . $tag_value;

        // На выходе строка где байты (группы из 2 heх символов) разбит пробелами
        return join(' ', str_split($tlv, 2));
    }

    /**
     * Парсим уникальный идентификатор товара GS1 DataMatrix
     * И достаем код продукта - 01<gtin>21<serial_number>
     * @param string $uid
     * @returns 01<gtin>21<serial_number>
     * @return bool|string
     */
    public static function extractProductCode($uid)
    {
        $parsed = self::parse($uid);
        if (!$parsed['status']) {
            return false;
        }
        return '01' . $parsed['details']['gtin'] . '21' . $parsed['details']['serial'];
    }

    /**
     * Is string contains Cyrillic symbols
     * @param string $str - expect only string, method does not validate type, so please pass only string
     * @return bool
     */
    protected static function containsCyrillic($str)
    {
        return (bool)preg_match('/[\p{Cyrillic}]/u', $str);
    }

    private static function isAllDigits($str)
    {
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $val = ord($str[$i]) - ord('0');
            if ($val < 0 || $val > 9) {
                return false;
            }
        }
        return true;
    }
}
