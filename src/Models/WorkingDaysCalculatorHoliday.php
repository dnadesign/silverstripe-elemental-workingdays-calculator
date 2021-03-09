<?php

namespace DNADesign\ElementWorkingDaysCalculator\Models;

use SilverStripe\ORM\DataObject;

class WorkingDaysCalculatorHoliday extends DataObject
{
    private static $table_name = 'WorkingDaysCalculatorHoliday';

    private static $singular_name = 'Working Days Calculator Holiday';

    private static $plural_name = 'Working Days Calculator Holidays';

    private static $db = [
        'Type' => 'Enum("Date, Range")',
        'From' => 'Datetime',
        'To' => 'Datetime'
    ];

    private static $has_one = [
        'Calculator' => ElementWorkingDaysCalculator::class
    ];
}
