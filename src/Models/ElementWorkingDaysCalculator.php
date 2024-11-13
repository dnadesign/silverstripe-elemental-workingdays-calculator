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
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TextField;
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

    private static $output_date_format = 'd/m/Y';

    private static $db = [
        'Country' => 'Varchar(2)',
        'MinYear' => 'Varchar(4)', // use varchar instead of int for cms validation
        'MaxYear' => 'Varchar(4)', // use varchar instead of int for cms validation
        'PublicHolidayJson' => 'Text',
        'Introduction' => 'HTMLText',
        'FormFieldLabel' => 'Varchar(255)',
        'FormActionLabel' => 'Varchar(100)',
        'ExcludeRegionalHolidays' => 'Boolean',
        'IncludeHolidaysForRegionCodes' => 'Varchar(255)'
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

        // Global + Region
        $global = CheckboxField::create('ExcludeRegionalHolidays');
        $regions = TextField::create('IncludeHolidaysForRegionCodes')->setDescription('comma separated list. eg: NZ-WGN');
        $fields->addFieldsToTab('Root.Main', [$global, $regions], 'Introduction');
        $regions->displayIf('ExcludeRegionalHolidays')->isChecked()->end();

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
            $jsonInfo = LiteralField::create('jsonInfo', '<div class="alert alert-info">The Json will automatically be updated if you delete it, or of the dates or country change</div>');
            $fields->addFieldsToTab('Root.Holidays', [$json,  $jsonInfo]);
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
     * Reset json if variables have changed
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if ($this->isChanged('MinYear') || $this->isChanged('MaxYear') || $this->isChanged('Country')) {
            $this->PublicHolidayJson = null;
        }
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
            $end->modify('+ '.$interval.' weekdays');
            $end->setTime(23, 59, 59);
            // Build a period of time for this interval
            $period = new DatePeriod(
                $date,
                new DateInterval('P1D'),
                $end
            );

            // Extends the period by the number of holidays in it until there are no new holidays
            // in the extended period
            $holidaysInPeriod = $this->findHolidaysInPeriod($period, $holidays);

            if (count($holidaysInPeriod) > 0) {
                $holidaysCountInExtendedPeriod = 0;

                while (count($holidaysInPeriod) !== $holidaysCountInExtendedPeriod) {
                    // Record how many holiday in current period
                    $holidaysCountInExtendedPeriod = count($holidaysInPeriod);
                    // Extend period
                    $extendedEnd = clone $end;
                    $extendedEnd->modify('+ '.count($holidaysInPeriod).' weekdays');
                    $extendedEnd->setTime(23, 59, 59);
                    
                    $period = new DatePeriod(
                        $date,
                        new DateInterval('P1D'),
                        $extendedEnd
                    );
                    // Rebuild array f holidays in extend period
                    $holidaysInPeriod = $this->findHolidaysInPeriod($period, $holidays);
                }
                // Once the period has been extended, use the extended date as the end of the period
                $end = $extendedEnd;
            }

            // Find the next working day (as $end/$extendedEnd can fall on the weekend)
            $nextWorkingDayDate = $this->findNextWorkingDay($end, $holidays);
            $results[] = [
                'Interval' => $intervalObj,
                'Date' =>  DBField::create_field(DBDate::class, $nextWorkingDayDate['Date']->format('Y-m-d')),
                'Holidays' => $this->formatHolidaysForTemplate(array_merge($holidaysInPeriod, $nextWorkingDayDate['AddedHolidays']))
            ];
        }

        return $results;
    }

    /**
     * Return an array of all the holidays within the period
     *
     * @param DatePeriod $period
     * @param array $holidays
     * @return array
     */
    public function findHolidaysInPeriod($period, $holidays)
    {
        $periodDates = [];
        foreach ($period as $periodDate) {
            $periodDates[] = $periodDate->format('Y-m-d');
        }

        $holidaysInPeriod = array_filter($holidays, function ($holidayDate) use ($periodDates) {
            $day = date('D', strtotime($holidayDate));
            return in_array($holidayDate, $periodDates) && $day !== 'Sat' && $day !== 'Sun';
        }, ARRAY_FILTER_USE_KEY);

        return $holidaysInPeriod;
    }

    /**
     * Given a date, this will check if the date is not a public holiday or a weekend
     * and return the next working date
     *
     * @param DateTime $initialDate
     * @param array $holidays
     * @return DateTime
     */
    private function findNextWorkingDay($initialDate, $holidays, $addedHolidays = [])
    {
        $date = clone $initialDate;
        $dateString = $date->format('Y-m-d');

        // Check new date is not a holiday
        if (array_key_exists($dateString, $holidays)) {
            // Record holiday
            $addedHolidays[$dateString] = $holidays[$dateString];
            // Jump to next day
            $date->modify('+ 1 day');
        }

        // Check if we are on Saturday or Sunday
        if ($date->format('D') === 'Sat') {
            $date->modify('+ 2 days');
        } elseif ($date->format('D') === 'Sun') {
            $date->modify('+ 1 days');
        }

        // If we haven't modified the date, return it
        if ($initialDate->format('Y-m-d') ===  $dateString) {
            return ['Date' => $date, 'AddedHolidays' => $addedHolidays];
        }

        // If the date has been modified, check again for holidays and weekends
        return $this->findNextWorkingDay($date, $holidays, $addedHolidays);
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
                $canInclude = true;
                // Filter regional holiday where applicable
                if ($this->ExcludeRegionalHolidays) {
                    $canInclude = $entry->global === true;
                    if (trim($this->IncludeHolidaysForRegionCodes)) {
                        $codes = explode(',', trim($this->IncludeHolidaysForRegionCodes));
                        $canInclude = $entry->global === true || count(array_intersect($codes, $entry->counties)) > 0;
                    }
                }
                if ($canInclude) {
                    $holidays[$entry->date] = $entry->name;
                }
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
        $dateFormat = $this->config()->get('output_date_format');

        if (!empty($holidays)) {
            $minYear = date('Y', strtotime(array_key_first($holidays)));
            // Format ranges as one date
            $ranges = $this->ExtraHolidays()->filter(['Type' => 'Range']);
            if ($ranges && $ranges->exists()) {
                foreach ($ranges as $range) {
                    // Get all the dates in the range for the one year
                    $rangeDates = $range->getDates($minYear, $minYear);
                    if ($rangeDates && !empty($rangeDates) && count(array_intersect_key($holidays, $rangeDates))) {
                        // Build the range dates string
                        $rangeStart = date($dateFormat, strtotime(array_key_first($rangeDates)));
                        $rangeEnd = date($dateFormat, strtotime(array_key_last($rangeDates)));
                        $dateString = sprintf('%s range-delimiter %s', $rangeStart, $rangeEnd);
                        // If range start on the weekend, it might not be in the holiday array
                        // so key only the dates that are in the holidays array
                        $rangeDates = array_intersect_key($holidays, $rangeDates);
                        // Set the range in array and remove all other dates
                        $holidays[$dateString] = $holidays[array_key_first($rangeDates)];
                        foreach ($rangeDates as $date => $title) {
                            if (isset($holidays[$date])) {
                                unset($holidays[$date]);
                            }
                        }
                    }
                }
            }
        }

        $formatted = [];
        foreach ($holidays as $date => $title) {
            // Range
            if (strpos($date, 'range-delimiter') !== false) {
                $date = str_replace('range-delimiter', '-', $date);
            } else {
                $date = date($this->config()->get('output_date_format'), strtotime($date));
            }
    
            $holiday = [
                'Title' => $title,
                'Date' => $date
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

    public function getMinDate()
    {
        if ($this->MinYear) {
            return date('Y-m-d', strtotime('01-01-'.$this->MinYear));
        }

        return null;
    }

    public function getMaxDate()
    {
        if ($this->MaxYear) {
            return date('Y-m-d', strtotime('31-12-'.$this->MaxYear));
        }

        return null;
    }
}
