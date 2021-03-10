<?php

namespace DNADesign\ElementWorkingDaysCalculator\Models;

use DateInterval;
use DatePeriod;
use DateTime;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\ElementWorkingDaysCalculator\Controllers\ElementWorkingDaysCalculatorController;
use DNADesign\ElementWorkingDaysCalculator\Services\PublicHolidayService;
use Exception;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\i18n\Data\Intl\IntlLocales;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBField;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Symfony\Component\Translation\Interval;

class ElementWorkingDaysCalculator extends BaseElement
{
    private static $icon = 'font-icon-clock';

    private static $table_name = 'ElementWorkingDaysCalculator';

    private static $title = '  Working Days Calculator';

    private static $description = 'A calculator to work out the next working day';

    private static $singular_name = 'Working Days Calculator';

    private static $plural_name = 'Working Days Calculators';

    private static $inline_editable = false;

    private static $controller_class = ElementWorkingDaysCalculatorController::class;

    private static $db = [
        'Country' => 'Varchar(2)',
        'MinYear' => 'Varchar(4)', // use varchar instead of int for cms validation
        'MaxYear' => 'Varchar(4)', // use varchar instead of int for cms validation
        'PublicHolidayJson' => 'Text',
        'Introduction' => 'HTMLText',
        'FormFieldLabel' => 'Varchar(255)',
        'FormActionLabel' => 'Varchar(100)'
    ];

    private static $has_many = [
        'Intervals' => WorkingDaysCalculatorInterval::class,
        'ExtraHolidays' => WorkingDaysCalculatorHoliday::class
    ];

    private static $owns = [
        'Intervals',
        'ExtraHolidays'
    ];

    private static $cascade_deletes = [
        'Intervals',
        'ExtraHolidays'
    ];

    private static $cascade_duplicates = [
        'Intervals',
        'ExtraHolidays'
    ];

    private static $defaults = [
        'Country' => 'nz',
    ];

    /**
     * Require fields
     *
     * @return void
     */
    public function getCMSValidator()
    {
        return new RequiredFields([
            'Country',
            'MinYear',
            'MaxYear'
        ]);
    }

    public function getType()
    {
        return _t(__CLASS__ . '.BlockType', $this->config()->get('singular_name'));
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        //  Introduction
        $intro = $fields->dataFieldByName('Introduction');
        if ($intro) {
            $intro->setRows(3);
        }

        // Country
        $country = DropdownField::create('Country', 'Country', $this->getCountryOptions());
        $fields->addFieldToTab('Root.Main', $country, 'Introduction');

        // Years
        $start = $fields->dataFieldByName('MinYear');
        if ($start) {
            $start->setDescription('The first year a user can start searching from, eg: 2018');
        }

        $end = $fields->dataFieldByName('MaxYear');
        if ($end) {
            $end->setDescription('The first year a user can search up to, eg: 2023');
        }

        // Json
        $json = $fields->dataFieldByName('PublicHolidayJson');
        if ($json) {
            $fields->addFieldToTab('Root.Holidays', $json);
        }

        // Extra Holidays
        $holidays = $fields->dataFieldByName('ExtraHolidays');
        if ($holidays) {
            $fields->removeByName('ExtraHolidays');
            $fields->addFieldsToTab('Root.Holidays', $holidays);
        }

        // Intervals
        $intervals = $fields->dataFieldByName('Intervals');
        $fields->removeByName('Intervals');
        if ($intervals && $this->IsInDB()) {
            $config = GridFieldConfig_RecordEditor::create();
            $config->addComponent(new GridFieldOrderableRows);

            $intervals->setConfig($config);

            $fields->addFieldsToTab('Root.Main', $intervals);
        }
 
        return $fields;
    }

    /**
     * Builds array of country code => country name
     * for selection
     *
     * @return array
     */
    private function getCountryOptions()
    {
        return IntlLocales::singleton()->getCountries();
    }

    /**
     * Retrieve the public holiday json
     *
     * @return json
     */
    public function importPublicHolidayJson()
    {
        if ($this->MinYear && $this->MaxYear && $this->Country) {
            $dates = range($this->MinYear, $this->MaxYear);

            $service = new PublicHolidayService();
            try {
                return $service->import($dates, $this->Country);
            } catch (Exception $e) {
                Injector::inst()->get(LoggerInterface::class)->error($e->getMessage());
            }
        }

        return '';
    }

    /**
     * Try to fetch the PublicHolidayJson
     * when we save the element
     *
     * @return void
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();

        if (!$this->PublicHolidayJson) {
            $json = $this->importPublicHolidayJson();
            if ($json && $json !== '') {
                $this->PublicHolidayJson = $json;
                $this->write();
            }
        }
    }

    /**
     * For each intervals, this calculate the next working day date
     *
     * @param string $date
     * @return array
     */
    public function calculate($date)
    {
        $intervals = $this->Intervals();
        if (!$intervals || $intervals->count() === 0) {
            return [];
        }

        $holidays = $this->getFutureHolidays($date);
        $date = new DateTime($date);
        
        $results = [];
        foreach ($intervals as $intervalObj) {
            $interval = (int) $intervalObj->DayCount;

            $end = clone $date;
            $end->modify('+ '.$interval.' days');
            // BuIld a period of time for this interval
            $period = new DatePeriod(
                $date,
                new DateInterval('P1D'),
                $end
            );

            // Find holidays within the period
            $periodDates = [];
            $weekendDaysInPeriod = 0;
            foreach ($period as $periodDate) {
                $periodDates[] = $periodDate->format('Y-m-d');

                // Add weekend days
                if ($periodDate->format('D') === 'Sat' || $periodDate->format('D') === 'Sun') {
                    $weekendDaysInPeriod++;
                }
            }

            $holidaysInPeriod = array_filter($holidays, function ($holidayDate) use ($periodDates) {
                return in_array($holidayDate, $periodDates);
            }, ARRAY_FILTER_USE_KEY);

            // Add the number of holidays to the end date
            if (count($holidaysInPeriod) > 0 || $weekendDaysInPeriod > 0) {
                $notWorkableDays = count($holidaysInPeriod) + $weekendDaysInPeriod;
                $end->modify('+ '.$notWorkableDays.' days');
            }
        
            // Find the next working day
            $nextWorkingDayDate = $this->findNextWorkingDay($end, $holidays);
            $results[] = [
                'Interval' => $intervalObj,
                'Date' =>  DBField::create_field(DBDate::class, $nextWorkingDayDate->format('Y-m-d')),
                'Holidays' => $this->formatHolidaysForTemplate($holidaysInPeriod)
            ];
        }

        return $results;
    }

    /**
     * Givin a date, this will check if the date is not a public holiday or a weekend
     * and return the next working date
     *
     * @param DateTime $initialDate
     * @param array $holidays
     * @return DateTime
     */
    private function findNextWorkingDay($initialDate, $holidays)
    {
        $date = clone $initialDate;

        // Check new date is not a holiday
        if (array_key_exists($date->format('Y-m-d'), $holidays)) {
            $date->modify('+ 1 day');
        }

        // Check if we are on Saturday or Sunday
        if ($date->format('D') === 'Sat') {
            $date->modify('+ 2 days');
        } elseif ($date->format('D') === 'Sun') {
            $date->modify('+ 1 days');
        }

        // If we haven't modified the date, return it
        if ($initialDate->format('Y-m-d') === $date->format('Y-m-d')) {
            return $date;
        }

        // If the date has been modified, check again for holidays and weekends
        return $this->findNextWorkingDay($date, $holidays);
    }

    /**
     * Returned an ordered array of public holidays in the future
     *
     * @param string $date
     * @return array
     */
    public function getFutureHolidays($date)
    {
        $holidays = [];
        $queryDate = strtotime($date);

        // Rebuild array from json
        $json = json_decode($this->PublicHolidayJson);
        foreach ($json as $entry) {
            $entryDate = strtotime($entry->date);
            if ($entryDate >= $queryDate) {
                $holidays[$entry->date] = $entry->name;
            }
        }

        // Add extra holidays
        $extras = $this->ExtraHolidays();
        if ($extras && $extras->exists()) {
            foreach ($extras as $extra) {
                $extraDates = $extra->getDates($this->MinYear, $this->MaxYear);
                if ($extraDates && !empty($extraDates)) {
                    foreach ($extraDates as $extraDate => $extraDateName) {
                        if (strtotime($extraDate) >= $queryDate) {
                            $holidays[$extraDate] = $extraDateName;
                        }
                    }
                }
            }
        }

        // Sort by date
        ksort($holidays);

        return $holidays;
    }

    /**
     * Format a holiday date to be usable in the template
     *
     * @param array $holidays
     * @return ArrayList
     */
    private function formatHolidaysForTemplate($holidays)
    {
        $formatted = [];
        foreach ($holidays as $date => $title) {
            $holiday = [
                'Title' => $title,
                'Date' => DBField::create_field(DBDate::class, $date)
            ];

            array_push($formatted, $holiday);
        }

        return new ArrayList($formatted);
    }

    public function getLabelForField()
    {
        return $this->FormFieldLabel ?: 'Select a date';
    }

    public function getLabelForAction()
    {
        return $this->FormActionLabel ?: 'Go';
    }
}
