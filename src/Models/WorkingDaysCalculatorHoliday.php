<?php

namespace DNADesign\ElementWorkingDaysCalculator\Models;

use DateInterval;
use DatePeriod;
use DateTime;
use SilverStripe\ORM\DataObject;

class WorkingDaysCalculatorHoliday extends DataObject
{
    private static $table_name = 'WorkingDaysCalculatorHoliday';

    private static $singular_name = 'Working Days Calculator Holiday';

    private static $plural_name = 'Working Days Calculator Holidays';

    private static $db = [
        'Title' => 'Varchar(255)',
        'Type' => 'Enum("Date, Range")',
        'From' => 'Date',
        'To' => 'Date',
        'Recurring' => 'Boolean'
    ];

    private static $has_one = [
        'Calculator' => ElementWorkingDaysCalculator::class
    ];

    private static $summary_fields = [
        'ID' => 'ID',
        'Title' => 'Title',
        'Type' => 'Type',
        'From' => 'From/On',
        'To' => 'Until',
        'Recurring.Nice' => 'Repeat every year'
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeByName('CalculatorID');

        // To date is not required if not a range
        $to = $fields->dataFieldByName('To');
        $to->displayIf('Type')->isEqualTo('Range')->end();

        return $fields;
    }

    /**
     * Builds an array of all the date to be considered
     * Taking into account whether  the date is recurring
     * and is a range
     *
     * @param string $startYear
     * @param string $endYear
     * @return array
     */
    public function getDates($startYear, $endYear)
    {
        $dates = [];
        if (!$this->From) {
            return $dates;
        }

        $years = range($startYear, $endYear);
        $from = new DateTime($this->From);

        if ($this->Recurring) {
            if ($this->Type === 'Date') {
                foreach ($years as $year) {
                    $date = sprintf('%s-%s-%s', $year, $from->format('m'), $from->format('d'));
                    $dates[$date] = $this->Title;
                }
            } elseif ($this->Type === 'Range' && $this->To) {
                $from = new DateTime($this->From);
                $to = new DateTime($this->To);
                $to->setTime(0, 0, 1); // need to set the time to include last date in period

                $holidayOverlapYears = (int) $from->format('Y') === (int) $to->format('Y') - 1;

                foreach ($years as $year) {
                    $from->setDate($year, $from->format('m'), $from->format('d'));
                    $toYear = ($holidayOverlapYears) ? (int)$year + 1 : $year;
                    $to->setDate($toYear, $to->format('m'), $to->format('d'));

                    $period = new DatePeriod(
                        $from,
                        new DateInterval('P1D'),
                        $to
                    );

                    foreach ($period as $dateInPeriod) {
                        $dates[$dateInPeriod->format('Y-m-d')] = $this->Title;
                    }
                }
            }
        } else {
            if ($this->Type === 'Date') {
                $dates[$this->From] = $this->Title;
            } elseif ($this->Type === 'Range' && $this->To) {
                $to = new DateTime($this->To);
                $to->setTime(0, 0, 1); // need to set the time to include last date in period

                $period = new DatePeriod(
                    new DateTime($this->From),
                    new DateInterval('P1D'),
                    $to
                );
                foreach ($period as $dateInPeriod) {
                    $dates[$dateInPeriod->format('Y-m-d')] = $this->Title;
                }
            }
        }

        // Order by date
        ksort($dates);

        return $dates;
    }
}
