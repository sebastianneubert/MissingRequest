<?php

namespace whm\MissingRequest\Reporter;

use GuzzleHttp\Client;
use Koalamon\Client\Reporter\Event;

class Incident implements Reporter
{
    private $tests;

    private $apiKey;

    private $server = 'https://webhook.koalamon.com';

    private $system;

    private $systemId;

    public function __construct($apiKey, $system, $systemId, $server = null)
    {
        $this->apiKey = $apiKey;

        if ($server) {
            $this->server = $server;
        }

        $this->systemId = $systemId;
        $this->system = $system;
    }

    /**
     * @param boolean $isFailure
     */
    public function addTestcase($url, $mandatoryUrl, $isFailure, $groupKey, $urlKey, $message = '')
    {
        if ($isFailure) {
            $this->tests[$url][$groupKey][$urlKey][] = ['url' => $mandatoryUrl, 'message' => $message];
        } else {
            $this->tests[$url][$groupKey][$urlKey][] = false;
        }
    }

    public function getReport()
    {
        foreach ($this->tests as $url => $urlKeys) {
            $message = '';
            $status = 'success';

            foreach ($urlKeys as $groupIdentifier => $groups) {
                $groupFound = false;
                foreach ($groups as $groupName => $missingUrls) {
                    foreach ($missingUrls as $missingUrl) {
                        if ($missingUrl !== false) {
                            if (!$groupFound) {
                                $message .= 'Requests for <strong>' . $groupIdentifier . '</strong> on ' . $url . ' were not found.';
                                $message .= '<ul>';
                                $groupFound = true;
                            }

                            $message .= '<li>' . stripslashes($missingUrl['url']) . ' - ' . $missingUrl['message'] . '</li>';
                        }
                    }
                }
                if ($groupFound) {
                    $message .= '</ul>';
                    $status = 'failure';
                }
            }

            if ($status == 'success') {
                $message = 'All mandatory requests for ' . implode(', ', array_keys($urlKeys)) . ' were found.';
            }

            $this->doReport($status, $message);
        }

        return 'Incident was sent';
    }

    /**
     * @param string $status
     * @param string $message
     * @param string $identifier
     */
    private function doReport($status, $message)
    {
        $identifier = 'MissingRequest_' . $this->systemId;
        $reporter = new \Koalamon\Client\Reporter\Reporter('', $this->apiKey, new Client(), $this->server);
        $event = new Event($identifier, $this->system, $status, 'missingRequest', $message, '', '', $this->systemId);
        $reporter->sendEvent($event);
    }
}
