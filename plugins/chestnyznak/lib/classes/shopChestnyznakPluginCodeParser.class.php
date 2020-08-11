<?php

class shopChestnyznakPluginCodeParser
{
    /**
     * @var array - conversion table, see method convert()
     * @see convert()
     */
    protected static $convert;

    /**
     * Парсим уникальный идентификатор товара - УИД
     *
     * Из документации, УИД выглядит так
     *  01+XXXXXXXXXXXXXX+21+XXXXXXXXXXXXX+240+XXXX+[Некий остаточный "Хвост"]
     *  Где
     *      - Первая группа (идет после идентификатора применения 01) это GTIN (14 символов)
     *      - Вторая группа (идет после идентификатора применения 21) это серийный номер товара (13 символов)
     *      - дальше идет символ \x1d (символ с ASCII кодом 29, так назыаемый Group Separator)
     *          В доке говорится, что этот символ необходимо исползовать :)
     *      - Третья группа (идет после идентификатора применения 240) это ТН ВЭД ЕАЭС (4 символа)
     *
     * @param string $uid
     *
     * @return array $result
     *      bool $result['status'] -
     *          Если УИД не соответствует вышеприведенному формату, то FALSE (при этом \x1d после второй группы может быть опущен)
     *          Иначе TRUE
     *
     *      array $result['details'] - дополнительные детали парсинга (например код ошибки, сообщение ошибки, или результат парсинга при успехе)
     *
     *          $result['status'] === FALSE:
     *              string $result['details']['error_type'] - общий код ошибки (invalid_argument, invalid_format)
     *              string $result['details']['error_code'] - конкретный код ошибки - отражает суть
     *              string $result['details']['error_message'] - сообщение ошибки на русском
     *
     *          $result['status'] === TRUE:
     *              string $result['details']['gtin'] - GTIN (14 символов)
     *              string $result['details']['serial'] - серийный номер товара (13 символов)
     *              string $result['details']['tnved'] - код ТН ВЭД ЕАЭС (4 символа)
     *              string $result['details']['tail'] - остаточный "хвост"
     *
     */
    public static function parse($uid)
    {
        if (!is_string($uid)) {
            return [
                'status' => false,
                'details' => [
                    'error_type' => 'invalid_argument',
                    'error_code' => 'expected_string',
                    'error_message' => 'Ожидается строка'
                ]
            ];
        }

        if (self::containsCyrillic($uid)) {
            return [
                'status' => false,
                'details' => [
                    'error_type' => 'invalid_argument',
                    'error_code' => 'contains_cyrillic',
                    'error_message' => 'Строка не должна содержать кириллические символы'
                ]
            ];
        }

        // group separator\x1d (29 ascii code) must not be at all or be on place 31
        $pos = strpos($uid, "\x1d");
        if ($pos !== false && $pos != 31) {
            return [
                'status' => false,
                'details' => [
                    'error_type' => 'invalid_format',
                    'error_code' => 'group_separator_wrong_place',
                    'error_message' => 'Символ группового разделителя расположен в неправильном месте'
                ]
            ];
        }

        // normalize uid, insert group separator
        if ($pos === false) {
            $uid = substr($uid, 0, 31) . "\x1d" . substr($uid, 31);
        }

        // len of uid with group separator must not less than 39
        $len = strlen($uid);
        if ($len < 39) {
            return [
                'status' => false,
                'details' => [
                    'error_type' => 'invalid_format',
                    'error_code' => 'short_string',
                    'error_message' => 'Слишком короткая строка кода'
                ]
            ];
        }

        // aid is "application identifier" - идентификатор применения

        $aid = substr($uid, 0, 2);
        if ($aid != "01") {
            return [
                'status' => false,
                'details' => [
                    'error_type' => 'invalid_format',
                    'error_code' => 'unexpected_application_identifier',
                    'error_message' => 'Неожиданный идентификатор применения перед GTIN (должно быть 01)'
                ]
            ];
        }

        $gtin = substr($uid, 2, 14);

        $aid = substr($uid, 16, 2);
        if ($aid != "21") {
            return [
                'status' => false,
                'details' => [
                    'error_type' => 'invalid_format',
                    'error_code' => 'unexpected_application_identifier',
                    'error_message' => 'Неожиданный идентификатор применения перед серийным номером (должно быть 21)'
                ]
            ];
        }

        $serial = substr($uid, 18, 13);

        $aid = substr($uid, 32, 3);
        if ($aid != "240") {
            return [
                'status' => false,
                'details' => [
                    'error_type' => 'invalid_format',
                    'error_code' => 'unexpected_application_identifier',
                    'error_message' => 'Неожиданный идентификатор применения перед кодом ТН ВЭД ЕАЭС (должно быть 240)'
                ]
            ];
        }

        $tnved = substr($uid, 35, 4);

        if (!self::isAllDigits($gtin)) {
            return [
                'status' => false,
                'details' => [
                    'error_type' => 'invalid_format',
                    'error_code' => 'gtin_invalid',
                    'error_message' => 'GTIN должен иметь длину 14 символов и состоять только из цифр'
                ]
            ];
        }

        if (!self::isAllDigits($tnved)) {
            return [
                'status' => false,
                'details' => [
                    'error_type' => 'invalid_format',
                    'error_code' => 'tnved_invalid',
                    'error_message' => 'Код ТН ВЭД ЕАЭС иметь длину 4 символа и состоять только из цифр'
                ]
            ];
        }

        $tail = substr($uid, 39);

        return [
            'status' => true,
            'details' => [
                'gtin' => $gtin,
                'serial' => $serial,
                'tnved' => $tnved,
                'tail' => $tail !== false ? $tail : '',
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

    /**
     * Кассовое программное обеспечение должно отнести отсканированный
     * код, к группе обувных маркируемых товаров, выделить из кода GTIN и
     * серийный номер, после чего передать информацию для формирования
     * тега 1162 фискального документа согласно следующему алгоритму:
     * https://xn--80ajghhoc2aj1c8b.xn--p1ai/upload/iblock/a6a/Rekomendatsii_dlya_uchastnikov_osushchestvlyayushchikh_realizatsiyu_v_roznitsu.pdf
     *
     * @param string $uid уникальный идентификатор товара - УИД, который поступает из DataMatrix или введен вручную
     * @return string - TLV значение для ККТ. Каждый байт разделен пробелом (байт=2 hex симвора)
     *  Пример - 8A 04 15 00 15 20 04 36 03 BE F5 14 73 67 45 4b 4b 50 50 63 53 32 35 79 35
     */
    public static function convertToFiscalCode($uid)
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
        // Добавляем код типа маркировки: 15 20
        // Формируем TLV для передачи в ККТ. Так как тег 1162 не имеет фиксированное
        // значение, 11 байт резерва в ККТ не передаются:
        $tag_value = '1520' . $gtin_hex . $serial_hex;
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
