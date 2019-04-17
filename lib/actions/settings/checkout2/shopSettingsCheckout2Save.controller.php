<?php

class shopSettingsCheckout2SaveController extends waJsonController
{
    /**
     * @var null|string
     */
    protected $storefront;
    protected $work_dates = [];
    protected $data;

    public function execute()
    {
        $this->storefront = waRequest::post('storefront_id',null, waRequest::TYPE_STRING_TRIM);

        $this->data = waRequest::post('data',null, waRequest::TYPE_ARRAY_TRIM);
        $this->validateData();
        if ($this->errors) {
            return $this->errors;
        }

        $checkout_config = new shopCheckoutConfig($this->storefront);
        $checkout_config->setData($this->data);
        $checkout_config->commit();
    }

    protected function validateData()
    {
        $this->validateDesign(ifset($this->data, 'design', []));
        // Locations list
        $shipping_mode = ifset($this->data, 'shipping', 'mode', shopCheckoutConfig::SHIPPING_MODE_TYPE_DEFAULT);
        if ($shipping_mode !== shopCheckoutConfig::SHIPPING_MODE_TYPE_DEFAULT) {
            $this->validateShipping(ifset($this->data, 'shipping', []));
        }

        // Schedule
        $schedule_mode = ifset($this->data, 'schedule', 'mode', shopCheckoutConfig::SCHEDULE_MODE_DEFAULT);
        if ($schedule_mode !== shopCheckoutConfig::SCHEDULE_MODE_DEFAULT) {
            $this->validateSchedule(ifset($this->data, 'schedule', []));
            if (!$this->errors) {
                $this->prepareScheduleDates();
            }
        }
    }

    protected function prepareScheduleDates()
    {
        $this->prepareScheduleExtraWorkDates();
        $this->prepareScheduleExtraWeekendDates();
    }

    protected function prepareScheduleExtraWorkDates()
    {
        $extra_weekends = ifset($this->data, 'schedule', 'extra_workdays', []);
        foreach ($extra_weekends as $day_id => $workday) {
            $date = $this->parseDate($workday['date']);
            $this->data['schedule']['extra_workdays'][$day_id]['date'] = $date;
        }
    }

    protected function prepareScheduleExtraWeekendDates()
    {
        $extra_weekends = ifset($this->data, 'schedule',  'extra_weekends', []);
        foreach ($extra_weekends as $day_id => $date) {
            $date = $this->parseDate($date);
            $this->data['schedule']['extra_weekends'][$day_id] = $date;
        }
    }

    protected function validateDesign($design)
    {
        $this->validateDesignLogo(ifset($design, 'logo', null));
    }

    protected function validateDesignLogo($logo)
    {
        if ($logo && !file_exists(shopCheckoutConfig::getLogoPath($logo))) {
            return $this->insertError("design-logo", _w('Logo not uploaded. Please try again.'));
        }

        // Delete all or other storefront logos
        $storefront_logos = $this->getStorefrontLogos();
        foreach ($storefront_logos as $l) {
            if ($logo !== $l) {
                try {
                    waFiles::delete(shopCheckoutConfig::getLogoPath($l));
                } catch (waException $e) {
                }
            }
        }
    }

    protected function validateShipping($shipping)
    {
        $this->validateShippingLocationsList(ifset($shipping, 'locations_list', []));
    }

    protected function validateShippingLocationsList(array $locations_list)
    {
        foreach ($locations_list as $i => $location) {
            if (empty($location['name'])) {
                $this->insertError("[shipping][locations_list][{$i}][name]", _w('Empty value'));
            }
            if (empty($location['country'])) {
                $this->insertError("[shipping][locations_list][{$i}][country]", _w('Empty value'));
            }
        }
    }

    protected function validateSchedule($schedule)
    {
        $this->validateScheduleTimezone(ifset($schedule, 'timezone', ''));
        $this->validateScheduleWeek(ifset($schedule, 'week', []));
        $this->validateScheduleProcessingTime(ifset($schedule, 'processing_time', null));
        $this->validateScheduleExtraWorkdays(ifset($this->data['schedule']['extra_workdays'], []));
        $this->validateScheduleExtraWeekends(ifset($this->data['schedule']['extra_weekends'], []));
    }

    protected function validateScheduleTimezone($value) {
        $timezones = wa()->getDateTime()->getTimezones();
        if (!isset($timezones[$value])) {
            $this->insertError('[schedule][timezone]', _w('Invalid value'));
        }
    }

    protected function validateScheduleWeek($week)
    {
        if (!$week) {
            return $this->insertError('week', _w('Please enter at least one working day'));
        }
        foreach ($week as $day_id => $day) {
            $this->validateScheduleDayFields('week', $day_id, $day);
        }
    }

    protected function validateScheduleProcessingTime($value)
    {
        if (!wa_is_int($value)) {
            $this->insertError('[schedule][processing_time]', _w('Invalid time'));
        }
    }

    protected function validateScheduleExtraWorkdays($extra_workdays)
    {
        foreach ($extra_workdays as $day_id => $day) {
            $date = $this->parseDate(ifset($day, 'date', null));
            if (!$date) {
                $this->insertError("[schedule][{$day_id}][date]", _w('Invalid date'));
            } elseif ($date && ($interrelated_field = array_search($date, $this->work_dates)) !== false) {
                $this->insertError("[schedule][{$day_id}][date]", _w('Remove duplicate'), "[schedule][extra_workdays][{$interrelated_field}][date]");
            } else {
                $this->work_dates[$day_id] = $date;
            }
            $this->validateScheduleDayFields('extra_workdays', $day_id, $day);
        }
    }

    protected function validateScheduleExtraWeekends($extra_weekends)
    {
        foreach ($extra_weekends as $day_id => $date) {
            $date = $this->parseDate($date);
            if (!$date) {
                $this->insertError("[schedule][extra_weekends][{$day_id}]", _w('Invalid date'));
            } elseif ($date && ($subfield = array_search($date, $this->work_dates)) !== false) {
                $this->insertError("[schedule][extra_weekends][{$day_id}]", _w('Remove the date from extra work days days'), "[schedule][extra_workdays][{$subfield}][date]");
            }
        }
    }

    protected function validateScheduleDayFields($block, $day_id, $day)
    {
        $invalid_times = false;
        $time_validator = new waTimeValidator();

        $day_fields = ['start_work', 'end_work', 'end_processing'];
        foreach ($day_fields as $field) {
            if (!empty($day[$field]) && !$time_validator->isValid($day[$field])) {
                $this->insertError("[schedule][{$block}][{$day_id}][{$field}]", _w('Invalid time'));
                if (!$invalid_times) {
                    $invalid_times = true;
                }
            }
        }

        if ($invalid_times) {
            return;
        }

        if (!empty($day['start_work']) && !empty($day['end_work'])) {
            $start_time = strtotime($day['start_work']);
            $end_time = strtotime($day['end_work']);
            if ($start_time >= $end_time) {
                $this->insertError("[schedule][{$block}][{$day_id}][end_work]", _w('Invalid time'));
            }
        }
    }

    protected function parseDate($value)
    {
        $date = waDateTime::parse('date', $value, null, 'ru_RU');
        if (!$date) {
            $date = waDateTime::parse('date', $value, null, 'en_US');
        }
        return $date;
    }

    protected function getStorefrontLogos()
    {
        $dir_path = shopCheckoutConfig::getLogoPath();
        $files = waFiles::listdir($dir_path);
        foreach ($files as $id => $file) {
            if (!is_file($dir_path.'/'.$file) || !preg_match('~\.(jpe?g|png|gif|svg)$~i', $file)) {
                unset($files[$id]);
            }
            if (strpos($file, $this->storefront) !== 0) {
                unset($files[$id]);
            }
        }
        return array_values($files);
    }

    protected function insertError($field, $message, $interrelated_field = null)
    {
        $error = [
            'field'   => $field,
            'message' => $message,
        ];

        if ($interrelated_field) {
            $error['interrelated_field'] = $interrelated_field;
        }

        $this->errors[] = $error;
    }
}