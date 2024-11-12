# SilverStripe Elemental Working Days Calculator

## Introduction

Adds an element to allow user to calculate the next working day date.
Takes into account public holidays (from an API) and custom holidays you may add via the CMS.

## Installation

```
composer require "dnadesign/silverstripe-elemental-workingdays-calculator"
```

## Set up
To enable the calculator to work properly, set a minimum and maximum date (year) as well as a country (defaults to NZ).
These parameters will be used to fetch the json holding all the known holidays within the set period from https://date.nager.at/api/v2/publicholidays.
The json is automatically fetched when the field is left blank, or a date or country changes.

## Debug
In order to check if dates are correct, please refer to [https://newzealand.workingdays.org/]

## TODO
* Add Unit Tests
* Prevent element from displaying if not set up properly


