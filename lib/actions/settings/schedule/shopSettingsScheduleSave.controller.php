<?php

class shopSettingsScheduleSaveController extends waJsonController
{
    protected $work_dates = [];

    protected $data;

    public function execute()
    {
        $this->data = waRequest::post('data', null, waRequest::TYPE_ARRAY_TRIM);
        $this->validateData();
        if ($this->errors) {
            return $this->errors;
        }
        $this->prepareDates();
        $app_settings = new waAppSettingsModel();
        $app_settings->set('shop', 'schedule', json_encode($this->data));
    }

    protected function validateData()
    {
        $this->validateTimezone(ifset($this->data,'timezone', ''));
        $this->validateWeek(ifset($this->data, 'week', []));
        $this->validateProcessingTime(ifset($this->data, 'processing_time', null));
        $this->validateExtraWorkdays(ifset($this->data, 'extra_workdays', []));
        $this->validateExtraWeekends(ifset($this->data, 'extra_weekends', []));
    }

    protected function prepareDates()
    {
        $this->prepareExtraWorkDates();
        $this->prepareExtraWeekendDates();
    }

    protected function prepareExtraWorkDates()
    {
        $extra_weekends = ifset($this->data, 'extra_workdays', []);
        foreach ($extra_weekends as $day_id => $workday) {
            $date = $this->parseDate($workday['date']);
            $this->data['extra_workdays'][$day_id]['date'] = $date;
        }
    }

    protected function prepareExtraWeekendDates()
    {
        $extra_weekends = ifset($this->data, 'extra_weekends', []);
        foreach ($extra_weekends as $day_id => $date) {
            $date = $this->parseDate($date);
            $this->data['extra_weekends'][$day_id] = $date;
        }
    }

    protected function validateTimezone($value)
    {
        $timezones = wa()->getDateTime()->getTimezones();
        if (!isset($timezones[$value])) {
            $this->insertError('[timezone]', _w('Invalid value'));
        }
    }

    protected function validateWeek($week)
    {
        if (!$week) {
            return $this->insertError('week', _w('Please enter at least one working day'));
        }
        foreach ($week as $day_id => $day) {
            $this->validateDayFields('week', $day_id, $day);
        }
    }

    protected function validateProcessingTime($value)
    {
        if (!wa_is_int($value)) {
            $this->insertError('[processing_time]', _w('Invalid time'));
        }
    }

    protected function validateExtraWorkdays($extra_workdays)
    {
        foreach ($extra_workdays as $day_id => $day) {
            $date = $this->parseDate(ifset($day, 'date', null));
            if (!$date) {
                $this->insertError("[extra_workdays][{$day_id}][date]", _w('Invalid date'));
            } elseif ($date && ($interrelated_field = array_search($date, $this->work_dates)) !== false) {
                $this->insertError("[extra_workdays][{$day_id}][date]", _w('Remove duplicate'), "[extra_workdays][{$interrelated_field}][date]");
            } else {
                $this->work_dates[$day_id] = $date;
            }
            $this->validateDayFields('extra_workdays', $day_id, $day);
        }
    }

    protected function validateExtraWeekends($extra_weekends)
    {
        foreach ($extra_weekends as $day_id => $date) {
            $date = $this->parseDate($date);
            if (!$date) {
                $this->insertError("[extra_weekends][{$day_id}]", _w('Invalid date'));
            } elseif ($date && ($subfield = array_search($date, $this->work_dates)) !== false) {
                $this->insertError("[extra_weekends][{$day_id}]", _w('Remove the date from extra work days days'), "[extra_workdays][{$subfield}][date]");
            }
        }
    }

    protected function validateDayFields($block, $day_id, $day)
    {
        $invalid_times = false;
        $time_validator = new waTimeValidator();

        $day_fields = ['start_work', 'end_work', 'end_processing'];
        foreach ($day_fields as $field) {
            if (!empty($day[$field]) && !$time_validator->isValid($day[$field])) {
                $this->insertError("[{$block}][{$day_id}][{$field}]", _w('Invalid time'));
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
                $this->insertError("[{$block}][{$day_id}][end_work]", _w('Invalid time'));
            }
            $end_processing = strtotime($day['end_processing']);
            if ($end_processing <= $start_time || $end_processing > $end_time) {
                $this->insertError("[{$block}][{$day_id}][end_processing]", _w('Invalid time'));
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