<?php

$settings = $this->getSettingsModel()->get($this->getSettingsKey());
$settings['orientation'] = ifset($settings['orientation'], ifset($settings['text_orientation']));
$settings['text_position'] = ifset($settings['text_position'], ifset($settings['text_align']));
$settings['font_size'] = ifset($settings['font_size'], ifset($settings['text_size']));
$settings['overlay'] = ifset($settings['overlay'], 'original');

if (empty($settings['watermark_type'])) {
    if (!empty($settings['image'])) {
        $settings['watermark_type'] = 'image';
    } else {
        $settings['watermark_type'] = 'text';
    }
}

if (!isset($settings['pos_x1'])) {
    switch(ifset($settings['image_align'])) {
        case 'tl':
            $settings = array(
                'pos_x1' => 0.02,
                'pos_y1' => 0.02,
                'pos_x2' => 0.3,
                'pos_y2' => 0.3,
            ) + $settings;
            break;
        case 'tr':
            $settings = array(
                'pos_x1' => 0.7,
                'pos_y1' => 0.02,
                'pos_x2' => 0.98,
                'pos_y2' => 0.3,
            ) + $settings;
            break;
        case 'bl':
            $settings = array(
                'pos_x1' => 0.02,
                'pos_y1' => 0.7,
                'pos_x2' => 0.3,
                'pos_y2' => 0.98,
            ) + $settings;
            break;
        case 'br':
        default:
            $settings = array(
                'pos_x1' => 0.7,
                'pos_y1' => 0.7,
                'pos_x2' => 0.98,
                'pos_y2' => 0.98,
            ) + $settings;
            break;
    }
}

foreach ($settings as $name => $value) {
    $this->getSettingsModel()->set($this->getSettingsKey(), $name, is_array($value) ? json_encode($value) : $value);
}

