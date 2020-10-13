<?php
/**
 * Class shopDepartureTime
 *
 * @property string timezone
 * @property float processing_time
 * @property array extra_workdays
 * @property array extra_weekends
 * @property array week
 *
 * @see Test tests/wa-apps/shop/classes/checkout2/shopDepartureDateTimeFacadeTest.php
 */
class shopDepartureDateTimeFacade
{
    protected $schedule = [];
    protected $days = null;
    protected $extra_processing_time = 0;


    /**
     * shopDepartureDateTimeFacade constructor.
     * @param array $schedule
     * @param string $storefront
     * @throws waException
     */
    public function __construct($schedule = [], $storefront = '')
    {
        $hash = null;

        if (!$schedule && $storefront) {
            $hash = shopHelper::getStorefrontCheckoutHash($storefront);

            if ($hash) {
                $config = new shopCheckoutConfig($hash);
                $schedule = $config['schedule'];
            }
        }

        if (!$schedule) {
            $schedule = wa('shop', 1)->getConfig()->getSchedule();
        }

        $params = [
            'hash'       => $hash,
            'storefront' => $storefront,
            'schedule'   => &$schedule,
        ];

        /**
         * @event departure_datetime.before
         * @param array $params
         * @param array [string] $params['hash']
         * @param array [string] $params['storefront']
         * @param array [array] $params['schedule']
         *
         * @return bool
         */
        wa('shop')->event('departure_datetime.before', $params);

        $this->schedule = $schedule;
    }

    public function __toString()
    {
        return (string)$this->getDepartureDateTime();
    }

    /**
     * @param int $time
     */
    public function setExtraProcessingTime($time)
    {
        $this->extra_processing_time = max(0, intval($time));
    }


    /**
     * Получение первой доступной даты и времени доставки,
     * начиная с которой можно считать, что заказ готов для отправки
     *
     * @return false|int|mixed|string SQL DATETIME
     * @throws waException
     */
    public function getDepartureDateTime()
    {
        $timestamp = $this->getFirstDay();

        /** Учитываем "Количество рабочих часов на обработку заказа" (processing_time)
         *  и "Дополнительное время на комплектацию" (extra_processing_time) */
        $processing = $processing_residue = round((float)$this->processing_time * 3600 + $this->extra_processing_time);

        //get first day processing
        $day_info = $this->getDayInfo($timestamp);
        if ($day_info) {
            //Check whether the order is accepted before the end of processing time
            if ($day_info['end_processing'] > $timestamp) {
                //Set the beginning of the working day
                if ($day_info['start_work'] < $timestamp) {
                    $day_info['start_work'] = $timestamp;
                } else {
                    //If made before the start of the working day, need to reset the time.
                    $timestamp = $day_info['start_work'];
                }
                $processing = $this->getProcessing($processing_residue, $day_info);
            } else {
                //Reset day info
                $day_info = [];
            }
        }

        $day_count = 0; //Max iteration 1 year
        while ((!$day_info || $processing > 0) && $day_count < 365) {
            $day_count++;
            $timestamp = strtotime('+1 day', $timestamp);
            $day_info = $this->getDayInfo($timestamp);

            //Save processing time on the last day
            //Reset the timestamp to the beginning of the working day
            //This is necessary to calculate the exact time of the end of processing.
            $processing_residue = $processing;
            $timestamp = ifset($day_info, 'start_work', $timestamp);

            $processing = $this->getProcessing($processing_residue, $day_info);

        }
        //Calculate the exact time
        $timestamp += $processing_residue;

        //convert to server timezone
        $timestamp = $this->changeTimezone('Y-m-d H:i:s', $timestamp, $this->timezone, date_default_timezone_get());

        $params = [
            'this'     => $this,
            'timezone' => $this->timezone,
            'timestamp' => &$timestamp,
        ];

        /**
         * @event departure_datetime.after
         * @param array $params
         * @param array [string] $params['hash']
         * @param array [string] $params['storefront']
         * @param array [array] $params['schedule']
         *
         * @return bool
         */
        wa('shop')->event('departure_datetime.after', $params);

        return $timestamp;
    }

    public function getFirstDay()
    {
        //Convert to schedule timezone
        return $this->changeTimezone('U', time(), date_default_timezone_get(), $this->timezone);
    }

    protected function getDayInfo($timestamp)
    {
        $date_timestamp = $this->changeTimezone('U', date('Y-m-d', $timestamp), $this->timezone);

        $info = [];
        if ($this->isExtraWorkday($date_timestamp)) {
            if (isset($this->days['workdays'][$date_timestamp])) {
                $info = $this->days['workdays'][$date_timestamp];
            } else {
                $workdays = $this->getWorkdays();
                $info = ifset($workdays, $date_timestamp, []);
            }
        } else {
            if (!$this->isExtraWeekend($date_timestamp)) {
                if ($this->isWorkday($date_timestamp)) {
                    $info = $this->getWeekday($date_timestamp);
                }
            }
        }

        return $info;
    }

    /**
     * Calculate how much time per day the order will be processed
     * @param $processing
     * @param $day_info
     * @return int
     */
    protected function getProcessing($processing, $day_info)
    {

        $end_work = ifset($day_info, 'end_work', 0);
        $start_work = ifset($day_info, 'start_work', 0);

        if (!is_numeric($processing)) {
            $processing = 0;
        }
        if (!is_numeric($start_work)) {
            $start_work = 0;
        }
        if (!is_numeric($end_work)) {
            $end_work = 0;
        }

        $processing = $processing - ($end_work - $start_work);

        return max(0, (int)$processing);
    }

    /**
     * Check if it time is a working day.
     * @param $time
     * @return bool
     */
    protected function isWorkday($time)
    {
        $weekdays = $this->week;

        if (is_numeric($time)) {
            $day_name_code = date('N', $time);
            $day = ifset($weekdays, $day_name_code, []);
        }

        if (isset($day) && !empty($day['work'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check if it is a extra working day.
     * @param $time
     * @return bool
     */
    protected function isExtraWorkday($time)
    {
        $workdays = $this->getWorkdays();

        if ($workdays && is_numeric($time) && isset($workdays[$time])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check if it time is a extra weekend day.
     * @param $time
     * @return bool
     */
    protected function isExtraWeekend($time)
    {
        $weekend = $this->getWeekends();

        if ($weekend && is_numeric($time) && isset($weekend[$time])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get the day of the week in timestamp
     * @param $date_timestamp
     * @return array
     */
    protected function getWeekday($date_timestamp)
    {
        $result = [
            'start_work'     => 0,
            'end_work'       => 0,
            'end_processing' => 0,
            'work'           => false,
        ];

        if (is_numeric($date_timestamp)) {
            $weekdays = $this->week;

            $day_name_code = date('N', $date_timestamp);
            $day = ifset($weekdays, $day_name_code, []);

            if ($day) {
                $result = [
                    'start_work'     => $date_timestamp + $this->formatTimeToSecond(ifset($day, 'start_work', null)),
                    'end_work'       => $date_timestamp + $this->formatTimeToSecond(ifset($day, 'end_work', null)),
                    'end_processing' => $date_timestamp + $this->formatTimeToSecond(ifset($day, 'end_processing', null)),
                    'work'           => $day['work'],
                ];
            }
        }

        return $result;
    }

    /**
     * Get saved extra weekend formatted to timestamp
     * @return array
     */
    protected function getWeekends()
    {
        $extra_weekend = ifset($this->days, 'weekend', null);

        if ($extra_weekend === null) {
            $extra_weekend = [];

            $weekend = $this->extra_weekends;
            if (is_array($weekend)) {
                foreach ($weekend as $day) {
                    $date = $day;
                    $date_timestamp = strtotime($date);
                    $extra_weekend[$date_timestamp] = true;
                }
            }
        }
        $this->days['weekend'] = $extra_weekend;

        return $extra_weekend;
    }


    /**
     * Get saved extra workdays formatted to timestamp
     * @return array
     */
    protected function getWorkdays()
    {
        $extra_workdays = ifset($this->days, 'workdays', null);

        if ($extra_workdays === null) {
            $extra_workdays = array();

            $workdays = $this->extra_workdays;
            if (is_array($workdays)) {
                foreach ($workdays as $workday) {
                    $date_timestamp = strtotime($workday['date']);
                    $extra_workdays[$date_timestamp] = array(
                        'start_work'     => strtotime($workday['date'].' '.$workday['start_work']),
                        'end_work'       => strtotime($workday['date'].' '.$workday['end_work']),
                        'end_processing' => strtotime($workday['date'].' '.$workday['end_processing']),
                    );
                }
            }
        }
        $this->days['workdays'] = $extra_workdays;

        return $extra_workdays;
    }

    /**
     * Convert string time to second (01:10 => 4200)
     * @param $time
     * @return float|int
     */
    protected function formatTimeToSecond($time)
    {
        $timestamp = 0;

        if (is_string($time) || is_integer($time)) {
            $time = explode(':', $time);

            $hours = (int)ifset($time, 0, 0);
            $minutes = (int)ifset($time, 1, 0);
            $second = (int)ifset($time, 2, 0);

            $timestamp = ($hours * 3600) + ($minutes * 60) + $second;
        }

        return $timestamp;
    }

    /**
     * @param $format
     * @param $time
     * @param $from
     * @param null $to
     * @return false|int|string
     */
    protected function changeTimezone($format, $time, $from, $to = null)
    {
        if (is_numeric($time)) {
            $time = date('Y-m-d H:i:s', $time);
        }

        $date_time = new DateTime($time, new DateTimeZone($from));

        if ($to) {
            $date_time->setTimezone(new DateTimeZone($to));
        }

        if ($format === 'U') {
            return strtotime($date_time->format('Y-m-d H:i:s'));
        } else {
            return $date_time->format($format);
        }
    }

    /**
     * @param $name
     * @return mixed|null
     */
    public function __get($name)
    {
        if (isset($this->schedule[$name])) {
            return $this->schedule[$name];
        } else {
            return null;
        }
    }

    /**
     * Quick class call
     * @param $schedule
     * @param null $storefront
     * @return self
     */
    public static function getDeparture($schedule = null, $storefront = null)
    {
        return new self($schedule, $storefront);
    }
}
