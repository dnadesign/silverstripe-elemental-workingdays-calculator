<?php

namespace DNADesign\ElementWorkingDaysCalculator\Services;

use Exception;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;

class PublicHolidayService
{
    use Configurable;

    private static $endpoint = 'https://date.nager.at/api/v2/publicholidays';

    /**
     * Import the json from the end_point for each year and country supplied
     *
     * @param array|string $years
     * @param array|string $countries
     * @return json
     */
    public function import($years = null, $countries = ['NZ'])
    {
        $urls = [];
        
        // If date isn't supplied, use current year
        if (!$years) {
            $years = [date('Y')];
        } elseif (!is_array($years)) {
            $years = [$years];
        }

        // If date isn't supplied, use current year
        if (!is_array($countries) && $countries) {
            $countries = [$countries];
        }

        if ($years && $countries && is_array($years) && is_array($countries)) {
            // Make sure we use uppercase for countries
            $countries =  array_map('strtoupper', $countries);

            foreach ($years as $year) {
                foreach ($countries as $country) {
                    $urls[] = Controller::join_links($this->config()->get('endpoint'), $year, $country);
                }
            }
        }

        $json = '';

        if (!empty($urls)) {
            $json = $this->fetch($urls);
        }

        return $json;
    }

    /**
     * Actually perform the GET to retrieve the different json from the API
     * and concatenate them into a single json
     *
     * @param array $urls
     * @return json
     */
    private function fetch($urls)
    {
        $data = [];

        // CWP requires the use of a proxy
        $proxy = null;
        $canUseProxy = Environment::getEnv('SS_OUTBOUND_PROXY') && Environment::getEnv('SS_OUTBOUND_PROXY_PORT');
        if ($canUseProxy) {
            $proxy = sprintf('%s:%s', Environment::getEnv('SS_OUTBOUND_PROXY'), Environment::getEnv('SS_OUTBOUND_PROXY_PORT'));
        }

        $client = new \GuzzleHttp\Client([
            'proxy' => $proxy
        ]);

        foreach ($urls as $url) {
            $publicHolidays = null;

            try {
                $response = $client->request('GET', $url);
            } catch (Exception $e) {
                Injector::inst()->get(LoggerInterface::class)->error($e->getMessage());
                continue;
            }

            if ($response !== false) {
                $publicHolidays = (string) $response->getBody();
            }

            // If we can't get the holidays, bail out
            if (!$publicHolidays) {
                continue;
            }

            $data = array_merge($data, json_decode($publicHolidays));
        }

        if (!$data || !json_encode($data)) {
            $message = 'Unable to update public holidays';
            Injector::inst()->get(LoggerInterface::class)->error($message);
            return;
        }

        return json_encode($data);
    }
}
