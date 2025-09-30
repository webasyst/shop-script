<?php
/*
 * Formatter for fontend API for categories.
 */
class shopFrontApiCategoryFormatter extends shopFrontApiFormatter
{
    public $fields;
    public $options;
    protected $product_fields = null;

    public function format(array $c)
    {
        $allowed_fields = [
            "id" => "integer",
            "depth" => "integer",
            "parent_id" => "integer",
            "name" => "string",
            "meta_title" => "string",
            "meta_keywords" => "string",
            "meta_description" => "string",
            "type" => "integer",
            "url" => "string",
            "full_url" => "string",
            //"count" => "integer",
            "description" => "string",
            //"conditions" => "string",
            //"create_datetime" => "string",
            //"edit_datetime" => "string",
            //"filter" => "string",
            //"sort_products" => "string",
            //"include_sub_categories" => "integer",
            //"status" => "integer",
            "categories" => 'array',
            "filters" => "array",
        ];

        if (!empty($this->options['without_meta'])) {
            unset(
                $allowed_fields["meta_title"],
                $allowed_fields["meta_keywords"],
                $allowed_fields["meta_description"]
            );
        }

        $c = array_intersect_key($c, $allowed_fields);
        $c = self::formatFieldsToType($c, $allowed_fields);
        if (isset($c['categories']) && is_array($c['categories'])) {
            foreach ($c['categories'] as &$subc) {
                $subc = $this->format($subc);
            }
            unset($subc);
            $c['categories'] = array_values($c['categories']);
        } else {
            unset($c['categories']);
        }
        return $c;
    }
}
