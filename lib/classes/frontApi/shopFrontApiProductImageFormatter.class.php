<?php
/*
 * Formatter for fontend API. Takes data from ProductsCollection and prepares into strict API format.
 */
class shopFrontApiProductImageFormatter extends shopFrontApiFormatter
{
    public function format(array $img)
    {
        foreach (["url_thumb", "url_crop", "url_big"] as $f) {
            if (isset($img[$f])) {
                $img[$f] = self::urlToAbsolute($img[$f]);
            }
        }

        $schema = [
            'id' => 'integer',
            "description" => 'string',
            "sort" => 'integer',
            "width" => 'integer',
            "height" => 'integer',
            "size" => 'integer',
            "filename" => 'string',
            "ext" => 'string',
            "badge_type" => 'integer',
            "badge_code" => 'string',
            "url_thumb" => 'string',
            "url_crop" => 'string',
            "url_big" => 'string',
        ];
        $img = self::formatFieldsToType($img, $schema);
        return array_intersect_key($img, $schema);
    }
}
