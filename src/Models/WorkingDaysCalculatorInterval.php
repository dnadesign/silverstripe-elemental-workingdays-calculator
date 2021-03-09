<?php

namespace DNADesign\ElementWorkingDaysCalculator\Models;

use SilverStripe\ORM\DataObject;

class WorkingDaysCalculatorInterval extends DataObject
{
    private static $table_name = 'WorkingDaysCalculatorInterval';

    private static $singular_name = 'Working Days Calculator Interval';

    private static $plural_name = 'Working Days Calculator Intervals';

    private static $db = [
        'DayCount' => 'Int',
        'Sort' => 'Int'
    ];

    private static $has_one = [
        'Calculator' => ElementWorkingDaysCalculator::class
    ];

    private static $summary_fields = [
        'ID' => 'ID',
        'getIntervalNice' => 'Interval'
    ];

    public function getIntervalNice()
    {
        if ($this->DayCount) {
            return sprintf('+%s days', $this->DayCount);
        }
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeByName([
            'CalculatorID',
            'Sort'
        ]);

        // Days
        $days = $fields->dataFieldByName('DayCount');
        if ($days) {
            $days->setTitle('Number of days');
            $days->setDescription('Number of working days from the user selected date');
        }

        return $fields;
    }
}
