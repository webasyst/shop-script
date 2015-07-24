<?php
/**
 * @var shopPrintformPlugin $this
 */


$source = wa()->getDataPath('plugins/'.$this->id.'/PrintformDisplay.html', false, 'shop');
if (file_exists($source)) {
    $target = wa()->getDataPath('plugins/'.$this->id.'/template.html', false, 'shop');
    waFiles::move($source, $target);
}
