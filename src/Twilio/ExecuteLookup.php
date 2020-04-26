<?php

namespace Yosmy\Phone\Carrier\Twilio;

use Yosmy\Phone\Carrier;
use Yosmy\Http;
use Yosmy;

/**
 * @di\service()
 */
class ExecuteLookup implements Carrier\ExecuteLookup
{
    /**
     * @var string
     */
    private $accountSID;

    /**
     * @var string
     */
    private $authToken;

    /**
     * @var Carrier\Lookup\PickCache
     */
    private $pickCache;

    /**
     * @var Carrier\Lookup\AddCache
     */
    private $addCache;

    /**
     * @var Http\ExecuteRequest
     */
    private $executeRequest;

    /**
     * @var Yosmy\ReportError
     */
    private $reportError;

    /**
     * @di\arguments({
     *     accountSID: "%twilio_account_sid%",
     *     authToken:  "%twilio_auth_token%"
     * })
     *
     * @param string                   $accountSID
     * @param string                   $authToken
     * @param Carrier\Lookup\PickCache $pickCache
     * @param Carrier\Lookup\AddCache  $addCache
     * @param Http\ExecuteRequest      $executeRequest
     * @param Yosmy\ReportError        $reportError
     */
    public function __construct(
        string $accountSID,
        string $authToken,
        Carrier\Lookup\PickCache $pickCache,
        Carrier\Lookup\AddCache $addCache,
        Http\ExecuteRequest $executeRequest,
        Yosmy\ReportError $reportError
    ) {
        $this->accountSID = $accountSID;
        $this->authToken = $authToken;
        $this->pickCache = $pickCache;
        $this->addCache = $addCache;
        $this->executeRequest = $executeRequest;
        $this->reportError = $reportError;
    }

    /**
     * {@inheritDoc}
     */
    public function execute(
        string $country,
        string $prefix,
        string $number
    ): Carrier\Lookup {
        try {
            $cache = $this->pickCache->pick(
                'twilio',
                $country,
                $prefix,
                $number
            );

            $response = $cache->getResponse();

            if (!isset(
                $response['carrier'],
                $response['carrier']['name'],
                $response['carrier']['mobile_country_code'],
                $response['carrier']['mobile_network_code']
            )) {
                throw new Carrier\UnresolvableLookupException();
            }

            return new Carrier\Lookup(
                $response['carrier']['name'],
                $response['carrier']['mobile_country_code'],
                $response['carrier']['mobile_network_code']
            );
        } catch (Carrier\Lookup\NonexistentCacheException $e) {
        }

        try {
            $response = $this->executeRequest->execute(
                Http\ExecuteRequest::METHOD_GET,
                sprintf('https://lookups.twilio.com/v1/PhoneNumbers/%s%s?Type=carrier', $prefix, $number),
                [
                    'auth' => [$this->accountSID, $this->authToken]
                ]
            );

            $response = $response->getBody();
        } catch (Http\Exception $e) {
            $response = $e->getResponse();

            $this->addCache->add(
                'twilio',
                $country,
                $prefix,
                $number,
                $response
            );

            switch ($e->getResponse()['code']) {
                // Not Found
                case 20404:
                    break;
                default:
                    $this->reportError->report($e);
            }

            throw new Carrier\UnresolvableLookupException();
        }

        $this->addCache->add(
            'twilio',
            $country,
            $prefix,
            $number,
            $response
        );

        return new Carrier\Lookup(
            $response['carrier']['name'],
            $response['carrier']['mobile_country_code'],
            $response['carrier']['mobile_network_code']
        );
    }
}