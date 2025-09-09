<?php
/*
 * Formatter for /shop 'shop_schedule'
 */
class shopFrontApiScheduleFormatter extends shopFrontApiFormatter
{
    public function format(array $arr)
    {
        $day_format = [
            "name" => 'string',
            "work" => 'boolean',
            "start_work" => 'string',
            "end_work" => 'string',
            "end_processing" => 'string',
        ];
        $schema = [
            'timezone' => 'string',
            'week' => [
                '1' => $day_format,
                '2' => $day_format,
                '3' => $day_format,
                '4' => $day_format,
                '5' => $day_format,
                '6' => $day_format,
                '7' => $day_format,
            ],
            'processing_time' => 'number',
            'extra_workdays' => [
                '_multiple' => true,
                "date" => 'string',
                "start_work" => 'string',
                "end_work" => 'string',
                "end_processing" => 'string',
            ],
            'extra_weekends' => [
                '_multiple' => true,
                "_type" => 'string',
            ],
        ];
        $arr = self::formatFieldsToType($arr, $schema);
        return array_intersect_key($arr, $schema);
    }
}
