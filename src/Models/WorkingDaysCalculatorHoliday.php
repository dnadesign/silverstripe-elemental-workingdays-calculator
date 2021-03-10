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
                $period = new DatePeriod(
                    new DateTime($this->From),
                    new DateInterval('P1D'),
                    new DateTime($this->To)
                );
                foreach ($period as $dateInPeriod) {
                    foreach ($years as $year) {
                        $date = sprintf('%s-%s-%s', $year, $dateInPeriod->format('m'), $dateInPeriod->format('d'));
                        $dates[$date] = $this->Title;
                    }
                }
            }
        }

        ksort($dates);

        return $dates;
    }
}
