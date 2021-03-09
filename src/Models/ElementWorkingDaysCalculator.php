<?php

namespace DNADesign\ElementWorkingDaysCalculator\Models;

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
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

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
        'Introduction' => 'HTMLText'
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
}
