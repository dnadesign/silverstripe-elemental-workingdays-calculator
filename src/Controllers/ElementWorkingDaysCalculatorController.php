<?php

namespace DNADesign\ElementWorkingDaysCalculator\Controllers;

use DNADesign\Elemental\Controllers\ElementController;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;

class ElementWorkingDaysCalculatorController extends ElementController
{
    private static $allowed_actions = [
        'calculate'
    ];

    public function CalculatorForm()
    {
        $fields = FieldList::create([
            $date = DateField::create('Date', 'Date')
        ]);

        $actions = FieldList::create([
            FormAction::create('calculate', 'Go')
        ]);
        
        $form = new Form(Controller::curr(), 'CalculatorForm', $fields, $actions);
        $form->loadDataFrom(Controller::curr()->getRequest()->postVars());
        $form->setAttribute('data-ajax-url', $this->getActionURL());
        $form->setFormAction(Controller::curr()->Link());
        $form->disableSecurityToken();

        return $form;
    }

    public function getActionURL()
    {
        $current = Controller::curr();

        $url = Controller::join_links(
            $current->Link(),
            'element',
            $this->ID,
            'calculate'
        );

        return $url;
    }

    public function calculate($request)
    {
        var_dump($request);
        die();
        // $form->customise([
        //     'Results' => 'blabla'
        // ]);

        return Controller::curr()->redirectBack();
    }

    public function Results($date = null)
    {
        if (!$date) {
            $date = Controller::curr()->getRequest()->postVar('Date');
        }

        if (!$date) {
            return 'Please enter a date';
        }

        var_dump($date);
    }
}
