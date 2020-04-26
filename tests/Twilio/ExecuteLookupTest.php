<?php

namespace Yosmy\Phone\Carrier\Test\Twilio\Lookup;

use PHPUnit\Framework\TestCase;
use Yosmy\Http;
use Yosmy\Phone\Carrier;
use Yosmy;
use LogicException;

class ExecuteLookupTest extends TestCase
{
    public function testExecute()
    {
        $country = 'country';
        $prefix = 'prefix';
        $number = 'number';

        $response = [
            'carrier' => [
                'name' => 'Carrier 1',
                'mobile_country_code' => 'Mcc 1',
                'mobile_network_code' => 'Mnc 1',
            ],

        ];

        $accountSID = 'account_sid';
        $authToken = 'auth_token';

        $pickCache = $this->createMock(Carrier\Lookup\PickCache::class);

        $pickCache->expects($this->once())
            ->method('pick')
            ->with(
                'twilio',
                $country,
                $prefix,
                $number
            )
            ->willThrowException(new Carrier\Lookup\NonexistentCacheException());

        $executeRequest = $this->createMock(Http\ExecuteRequest::class);

        $httpResponse = $this->createMock(Http\Response::class);

        $httpResponse->expects($this->once())
            ->method('getBody')
            ->with()
            ->willReturn($response);

        $executeRequest->expects($this->once())
            ->method('execute')
            ->with(
                Http\ExecuteRequest::METHOD_GET,
                sprintf('https://lookups.twilio.com/v1/PhoneNumbers/%s%s?Type=carrier', $prefix, $number),
                [
                    'auth' => [$accountSID, $authToken]
                ]
            )
            ->willReturn($httpResponse);

        $addCache = $this->createMock(Carrier\Lookup\AddCache::class);

        $addCache->expects($this->once())
            ->method('add')
            ->with(
                'twilio',
                $country,
                $prefix,
                $number,
                $response
            );

        $reportError = $this->createMock(Yosmy\ReportError::class);

        $executeRequest = new Carrier\Twilio\ExecuteLookup(
            $accountSID,
            $authToken,
            $pickCache,
            $addCache,
            $executeRequest,
            $reportError
        );

        try {
            $actualResponse = $executeRequest->execute(
                $country,
                $prefix,
                $number
            );
        } catch (Carrier\UnresolvableLookupException $e) {
            throw new LogicException();
        }

        $this->assertEquals(
            new Carrier\Lookup(
                $response['carrier']['name'],
                $response['carrier']['mobile_country_code'],
                $response['carrier']['mobile_network_code']
            ),
            $actualResponse
        );
    }

    public function testExecuteHavingCache()
    {
        $country = 'country';
        $prefix = 'prefix';
        $number = 'number';

        $response = [
            'carrier' => [
                'name' => 'Carrier 1',
                'mobile_country_code' => 'Mcc 1',
                'mobile_network_code' => 'Mnc 1',
            ],
        ];

        $accountSID = 'account_sid';
        $authToken = 'auth_token';

        $cache = $this->createMock(Carrier\Lookup\Cache::class);

        $cache->expects($this->once())
            ->method('getResponse')
            ->with()
            ->willReturn($response);

        $pickCache = $this->createMock(Carrier\Lookup\PickCache::class);

        $pickCache->expects($this->once())
            ->method('pick')
            ->with(
                'twilio',
                $country,
                $prefix,
                $number
            )
            ->willReturn($cache);

        $executeRequest = $this->createMock(Http\ExecuteRequest::class);

        $addCache = $this->createMock(Carrier\Lookup\AddCache::class);

        $reportError = $this->createMock(Yosmy\ReportError::class);

        $executeRequest = new Carrier\Twilio\ExecuteLookup(
            $accountSID,
            $authToken,
            $pickCache,
            $addCache,
            $executeRequest,
            $reportError
        );

        try {
            $actualResponse = $executeRequest->execute(
                $country,
                $prefix,
                $number
            );
        } catch (Carrier\UnresolvableLookupException $e) {
            throw new LogicException();
        }

        $this->assertEquals(
            new Carrier\Lookup(
                $response['carrier']['name'],
                $response['carrier']['mobile_country_code'],
                $response['carrier']['mobile_network_code']
            ),
            $actualResponse
        );
    }

    public function testExecuteHavingHttpExceptionWithKnownCode()
    {
        $codes = [20404];

        foreach ($codes as $code) {
            $country = 'country';
            $prefix = 'prefix';
            $number = 'number';

            $response = [
                'code' => $code
            ];

            $accountSID = 'account_sid';
            $authToken = 'auth_token';

            $pickCache = $this->createMock(Carrier\Lookup\PickCache::class);

            $pickCache->expects($this->once())
                ->method('pick')
                ->with(
                    'twilio',
                    $country,
                    $prefix,
                    $number
                )
                ->willThrowException(new Carrier\Lookup\NonexistentCacheException());

            $executeRequest = $this->createMock(Http\ExecuteRequest::class);

            $executeRequest->expects($this->once())
                ->method('execute')
                ->willThrowException(new Http\Exception($response));

            $addCache = $this->createMock(Carrier\Lookup\AddCache::class);

            $addCache->expects($this->once())
                ->method('add')
                ->with(
                    'twilio',
                    $country,
                    $prefix,
                    $number,
                    $response
                );

            $reportError = $this->createMock(Yosmy\ReportError::class);

            $executeRequest = new Carrier\Twilio\ExecuteLookup(
                $accountSID,
                $authToken,
                $pickCache,
                $addCache,
                $executeRequest,
                $reportError
            );

            $this->expectException(Carrier\UnresolvableLookupException::class);

            $executeRequest->execute(
                $country,
                $prefix,
                $number
            );
        }
    }

    public function testExecuteHavingHttpExceptionWithUnknownCode()
    {
        $country = 'country';
        $prefix = 'prefix';
        $number = 'number';

        $response = [
            'code' => 'code'
        ];

        $accountSID = 'account_sid';
        $authToken = 'auth_token';

        $pickCache = $this->createMock(Carrier\Lookup\PickCache::class);

        $pickCache->expects($this->once())
            ->method('pick')
            ->with(
                'twilio',
                $country,
                $prefix,
                $number
            )
            ->willThrowException(new Carrier\Lookup\NonexistentCacheException());

        $executeRequest = $this->createMock(Http\ExecuteRequest::class);

        $e = new Http\Exception($response);

        $executeRequest->expects($this->once())
            ->method('execute')
            ->willThrowException($e);

        $addCache = $this->createMock(Carrier\Lookup\AddCache::class);

        $addCache->expects($this->once())
            ->method('add')
            ->with(
                'twilio',
                $country,
                $prefix,
                $number,
                $response
            );

        $reportError = $this->createMock(Yosmy\ReportError::class);

        $reportError->expects($this->once())
            ->method('report')
            ->with($e);

        $executeLookup = new Carrier\Twilio\ExecuteLookup(
            $accountSID,
            $authToken,
            $pickCache,
            $addCache,
            $executeRequest,
            $reportError
        );

        $this->expectException(Carrier\UnresolvableLookupException::class);

        $executeLookup->execute(
            $country,
            $prefix,
            $number
        );
    }
}