<?php

class shopFeatureValuesColorModel extends shopFeatureValuesModel
{
    protected $table = 'shop_feature_values_color';
    protected $changed_fields = array('code');

    /**
     * @param $row
     * @return shopColorValue
     */
    protected function getValue($row)
    {
        return new shopColorValue($row);
    }

    protected function isChanged($row, $data)
    {
        $changed = false;
        if (isset($data['code']) && empty($data['suggest']) && ($data['code'] != $row['code'])) {
            $changed = array(
                'code' => $data['code'],
            );
        }
        return $changed;
    }

    protected function getSearchCondition()
    {
        return "`value` LIKE 'l:search_value'";
    }

    protected function parseValue($value, $type)
    {

        $code = null;
        $suggest = false;
        if (is_array($value)) {
            $code = ifset($value['code']);
            $value = trim(ifset($value['value']));
            //rgb(r,g,b) #ABC #AABBCC;
            if ($code === '') {
                $code = shopColorValue::getCode($value);
            } elseif (preg_match('@^#?([0-9A-F]{3}|[0-9A-F]{6})@ui', $code)) {
                if ((strpos($code, '#') === 0)) {
                    $code = substr($code, 1);
                }
                if ($parsed = sscanf(strtoupper($code), '%03X%03X')) {
                    if ($parsed[1] === null) {
                        $code = (0xF00 & $parsed[0]) << 12;
                        $code |= (0xFF0 & $parsed[0]) << 8;
                        $code |= (0x0FF & $parsed[0]) << 4;
                        $code |= (0x00F & $parsed[0]);
                    } else {
                        $code = ($parsed[0] << 12) | $parsed[1];
                    }
                } else {
                    $code = null;
                }
            } elseif (strpos('rgb', $code) === 0) {
                //TODO
            } else {
                $code = intval($code);
            }
            if (($value === '') && ($code !== null)) {
                $suggest = true;
                $value = shopColorValue::getName($code);
            }

        } else {
            $value = trim($value);
            if (preg_match('@^#?(([0-9A-F]{3})|([0-9A-F]{6}))$@ui', $value, $matches)) {
                if ($matches[2]) {
                    $value = sscanf(strtoupper($matches[2]), '%03X');
                    $code = reset($value);
                } elseif ($matches[3]) {
                    $value = sscanf(strtoupper($matches[3]), '%06X');
                    $code = reset($value);
                } else {
                    $code = 0;
                }
                $value = shopColorValue::getName($code);
            } else {
                $suggest = true;
                $code = shopColorValue::getCode($value);
            }
        }
        $value = substr($value, 0, 255);
        $data = array(
            'value'        => $value,
            'search_value' => $value,
            'suggest'      => $suggest,
        );
        if ($code !== null) {
            $data['code'] = $code;
        }
        return $data;
    }
}
