<?php

namespace DNADesign\ElementWorkingDaysCalculator\Controllers;

use DNADesign\Elemental\Controllers\ElementController;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\Requirements;

class ElementWorkingDaysCalculatorController extends ElementController
{
    private static $allowed_actions = [
        'calculate'
    ];

    public function init()
    {
        parent::init();

        Requirements::javascript('dnadesign/silverstripe-elemental-workingdays-calculator:client/dist/js/workingdayscalculator.js');

        // Polyfill for date field on IE: https://kreutzercode.github.io/configurable-date-input-polyfill/
        Requirements::javascript('dnadesign/silverstripe-elemental-workingdays-calculator:client/dist/js/configurable-date-input-polyfill.dist.js');
    }

    /**
     * Outputs the form to select the date
     *
     * @return Form
     */
    public function CalculatorForm()
    {
        $element = $this->getElement();

        $fields = FieldList::create([
            $date = DateField::create($this->getDateFieldName(), $element->getLabelForField(), date('y-m-d'))
        ]);

        $date->setMinDate($element->getMinDate());
        $date->setMaxDate($element->getMaxDate());

        $actions = FieldList::create([
            FormAction::create('calculate', $element->getLabelForAction())
        ]);
        
        $form = new Form(Controller::curr(), 'CalculatorForm', $fields, $actions);
        $form->setFormMethod('GET');
        $form->loadDataFrom(Controller::curr()->getRequest()->getVars());
        $form->setAttribute('data-wdc-ajax-url', $this->getActionURL());
        $form->setFormAction(Controller::curr()->Link());
        $form->disableSecurityToken();

        return $form;
    }

    /**
     * Action/route for the ajax call
     *
     * @return string
     */
    public function getActionURL()
    {
        $current = Controller::curr();

        $url = Controller::join_links(
            $current->Link(),
            'element',
            $this->ID,
            'calculate',
            '?'.$this->getDateFieldName().'='
        );

        return Director::absoluteURL($url);
    }

    /**
     * Make a unique label for the date so we can have multiple calculator on the same page
     *
     * @return string
     */
    public function getDateFieldName()
    {
        return $this->getElement()->getAnchor().'Date';
    }

    /**
     * Generate the results for the template
     *
     * @param string $date
     * @return ArrayList|null
     */
    public function getResults($date = null)
    {
        if (!$date) {
            $date = Controller::curr()->getRequest()->getVar($this->getDateFieldName());
        }

        if (!$date) {
            return;
        }

        $results = $this->getElement()->calculate($date);
        return new ArrayList($results);
    }

    /**
     * Action performing the calculation and return a json
     * containing the results HTML
     * Used by ajax
     *
     * @return json
     */
    public function calculate()
    {
        $date = $this->getRequest()->getVar($this->getDateFieldName());
        $results = $this->getResults($date);

        $html = Controller::curr()->customise(['Results' => $results])->renderWith('Includes\WorkingDaysCalculatorResults');

        $data = [
            'HTML' => $html->RAW()
        ];

        return json_encode($data);
    }
}
