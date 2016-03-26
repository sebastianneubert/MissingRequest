<?php

namespace whm\MissingRequest\Reporter;

use GuzzleHttp\Client;
use Koalamon\Client\Reporter\Event;
use phmLabs\XUnitReport\Elements\Failure;

class Incident implements Reporter
{
    private $tests;

    private $apiKey;

    private $server = 'http://www.koalamon.com';

    private $system;

    public function __construct($apiKey, $system, $server = null)
    {
        $this->apiKey = $apiKey;

        if ($server) {
            $this->server = $server;
        }

        $this->system = $system;
    }

    public function addTestcase($url, $mandatoryUrl, $isFailure, $groupKey, $urlKey)
    {
        if ($isFailure) {
            $this->tests[$url][$urlKey][$groupKey][] = $mandatoryUrl;
        } else {
            $this->tests[$url][$urlKey][$groupKey][] = false;
        }
    }

    public function getReport()
    {
        foreach ($this->tests as $url => $urlKeys) {
            foreach ($urlKeys as $urlKey => $groups) {
                $message = '';
                $status = 'success';
                foreach ($groups as $groupName => $missingUrls) {
                    $groupFound = false;
                    foreach ($missingUrls as $missingUrl) {
                        if ($missingUrl !== false) {
                            if (!$groupFound) {
                                $message .= 'Requests for <strong>' . $groupName . '</strong> on ' . $url . ' were not found.';
                                $message .= '<ul>';
                                $groupFound = true;
                            }
                            $message .= '<li>' . stripslashes($missingUrl) . '</li>';
                        }
                    }
                    if ($groupFound) {
                        $message .= '</ul>';
                        $status = 'failure';
                    }
                }
            }

            $identifier = 'MissingRequest_' . $url;
            $this->doReport($this->system, $status, $message, $identifier);
        }

        return 'Incident was sent';
    }

    private function doReport($system, $status, $message, $identifier)
    {
        $reporter = new \Koalamon\Client\Reporter\Reporter('', $this->apiKey, new Client(), $this->server);
        $event = new Event($identifier, $system, $status, 'missingRequest', $message);

        $reporter->sendEvent($event);
    }
}
