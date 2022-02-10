<?php
$locale      = wa()->getLocale();
$locale_file = wa()->getAppPath("lib/config/data/units.$locale.php", 'shop');

try {
    if (file_exists($locale_file)) {
        $insert = [];
        $units  = include $locale_file;
        $sql    = "INSERT IGNORE INTO shop_unit
            (okei_code, name, short_name, storefront_name, status)
        VALUES ";

        foreach ($units as $unit) {
            $insert[] = "('".implode("', '", $unit)."', 0)";
        }

        if (!empty($insert)) {
            $model = new waModel();
            $count = $model->query("SELECT COUNT(*) FROM shop_unit")->fetchField();
            if ($count <= 0) {
                $sql .= implode(', ', $insert);
                $model->exec($sql);
            }
        }
    }
} catch (waDbException $e) {
    //
}
