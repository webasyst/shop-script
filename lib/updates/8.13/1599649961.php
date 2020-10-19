<?php

$model = new waModel();

try {
    $model->query('select `followup_id` from shop_followup_sources where 0');
} catch (waDbException $e) {
    $create = 'create table shop_followup_sources (
                `followup_id` int not null,
                `source` varchar(510) null,
                key `followup_id` (`followup_id`),
                key `source` (`source`(190))
    )';
    $model->exec($create);

    $insert = 'insert into shop_followup_sources (`followup_id`, `source`)
                select `id`, `source`
                from shop_followup';
    $model->exec($insert);

    $update = "update shop_followup_sources set `source` = 'all_sources' where `source` is null";
    $model->exec($update);

    $drop = 'alter table shop_followup DROP COLUMN `source`';
    $model->exec($drop);
}

try {
    $model->query('select `notification_id` from shop_notification_sources where 0');
} catch (waDbException $e) {
    $create = 'create table shop_notification_sources (
                `notification_id` int not null,
                `source` varchar(510) null,
                key `notification_id` (`notification_id`),
                key `source` (`source`(190))
                )';
    $model->exec($create);

    $insert = 'insert into shop_notification_sources (`notification_id`, `source`)
                select `id`, `source`
                from shop_notification';
    $model->exec($insert);

    $update = "update shop_notification_sources set `source` = 'all_sources' where `source` is null";
    $model->exec($update);

    $drop = 'alter table shop_notification DROP COLUMN `source`';
    $model->exec($drop);
}
