<?php

function shop_rec_del_empty_dirs_1468571828($path)
{
    if (!is_dir($path)) {
        return false;
    }
    $list = waFiles::listdir($path);
    foreach($list as $i => $fname) {
        if ($fname) {
            if (shop_rec_del_empty_dirs_1468571828($path.'/'.$fname)) {
                unset($list[$i]);
            }
        }
    }
    if (!$list) {
        rmdir($path);
        return true;
    } else {
        return false;
    }
}

shop_rec_del_empty_dirs_1468571828(wa('shop')->getDataPath(null, true));
shop_rec_del_empty_dirs_1468571828(wa('shop')->getDataPath(null, false));
