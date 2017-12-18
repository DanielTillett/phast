<?php

namespace Kibo\Phast\Services;

use Kibo\Phast\Cache\Cache;
use Kibo\Phast\HTTP\Request;
use Kibo\Phast\Security\ServiceSignature;
use Kibo\Phast\ValueObjects\URL;
use PHPUnit\Framework\TestCase;

class ServiceRequestTest extends TestCase {

    /**
     * @dataProvider getSerializeParamsTestData
     */
    public function testSerializeParams(array $params, $expectedQuery, $expectedPath) {
        $request = (new ServiceRequest())->withParams($params);
        $this->checkRequest($request, $expectedQuery, $expectedPath);
    }

    public function getSerializeParamsTestData() {
        return [
            [
                ['src' => '/images/file.png'],
                'src=%2Fimages%2Ffile.png',
                '/-2Fimages-2Ffile.png'
            ],
            [
                ['src' => 'http://example.com/path/file.png'],
                'src=http%3A%2F%2Fexample.com%2Fpath%2Ffile.png',
                '/http-3A-2F-2Fexample.com-2Fpath-2Ffile.png'
            ],
            [
                ['src' => 'http://example.com///path////file.png'],
                'src=http%3A%2F%2Fexample.com%2F%2F%2Fpath%2F%2F%2F%2Ffile.png',
                '/http-3A-2F-2Fexample.com-2F-2F-2Fpath-2F-2F-2F-2Ffile.png'
            ],
            [
                ['src' => 'http://example.com/path/file-file.png'],
                'src=http%3A%2F%2Fexample.com%2Fpath%2Ffile-file.png',
                '/http-3A-2F-2Fexample.com-2Fpath-2Ffile-2Dfile.png'
            ],
            [
                ['src' => 'the-file.png', 'width' => 20],
                'src=the-file.png&width=20',
                '/the-2Dfile.png/width=20'
            ],
            [
                ['src' => 'the-file.png', 'width' => 20, 'height' => 30],
                'src=the-file.png&width=20&height=30',
                '/the-2Dfile.png/width=20/height=30'
            ]
        ];
    }

    /**
     * @dataProvider getSerializeParamsAndURLTestData
     */
    public function testSerializeParamsAndURL($url, $params, $expectedQuery, $expectedPath) {
        $request = (new ServiceRequest())->withParams($params)->withUrl(URL::fromString($url));
        $this->checkRequest($request, $expectedQuery, $expectedPath);
    }

    public function testFromHTTPRequest() {
        $pathInfo = '/http-3A-2F-2Fexample.com-2Fthe-2Dimage.png/key2=value2';
        $getParams = ['key3' => 'value3'];
        $expectedParams = [
            'src' => 'http://example.com/the-image.png',
            'key2' => 'value2',
            'key3' => 'value3'
        ];

        $httpRequest = Request::fromArray($getParams, ['PATH_INFO' => $pathInfo]);
        $serviceRequest = ServiceRequest::fromHTTPRequest($httpRequest);
        $this->assertEquals($expectedParams, $serviceRequest->getParams());
        $this->assertSame($httpRequest, $serviceRequest->getHTTPRequest());
    }

    public function getSerializeParamsAndURLTestData() {
        return [
            [
                'images.php',
                ['src' => 'http://example.com/the-image.png'],
                'images.php?src=http%3A%2F%2Fexample.com%2Fthe-image.png',
                'images.php/http-3A-2F-2Fexample.com-2Fthe-2Dimage.png'
            ],
            [
                'images.php?param=some-value',
                ['src' => 'the-image.png'],
                'images.php?param=some-value&src=the-image.png',
                'images.php/the-2Dimage.png/param=some-2Dvalue'
            ],
            [
                'images-service/',
                ['src' => 'the-image.png'],
                'images-service/?src=the-image.png',
                'images-service/the-2Dimage.png'
            ],
            [
                'images.php?param=value&src=overridden',
                ['src' => 'image.png'],
                'images.php?param=value&src=image.png',
                'images.php/image.png/param=value'
            ]
        ];
    }

    public function testSigning() {
        $signature = new ServiceSignature($this->createMock(Cache::class));
        $signature->setIdentities('some-token');
        $request = (new ServiceRequest())->withParams(['width' => 10, 'src' => 'url'])->sign($signature);


        $this->assertTrue($request->verify($signature));

        $queryFormat = $request->serialize(ServiceRequest::FORMAT_QUERY);
        $get = [];
        parse_str($queryFormat, $get);
        $queryRequest = ServiceRequest::fromHTTPRequest(Request::fromArray($get, []));

        $pathFormat = $request->serialize(ServiceRequest::FORMAT_PATH);
        $pathRequest = ServiceRequest::fromHTTPRequest(Request::fromArray([], ['PATH_INFO' => $pathFormat]));

        $this->assertStringStartsWith('width=10&src=url&token=', $request->serialize(ServiceRequest::FORMAT_QUERY));
        $this->assertStringStartsWith('/url/width=10/token=', $request->serialize(ServiceRequest::FORMAT_PATH));

        $this->assertTrue($queryRequest->verify($signature));
        $this->assertTrue($pathRequest->verify($signature));

        $this->assertArrayNotHasKey('token', $queryRequest->getParams());
        $this->assertArrayNotHasKey('token', $pathRequest->getParams());

        $clonedRequest = $request->withParams(['key' => 'value']);
        $this->assertFalse($clonedRequest->verify($signature));

        $newRequest = new ServiceRequest();
        $this->assertFalse($newRequest->verify($signature));

        $signature2 = new ServiceSignature($this->createMock(Cache::class));
        $signature2->setIdentities('something-else');
        $this->assertFalse($request->verify($signature2));
        $this->assertFalse($queryRequest->verify($signature2));
        $this->assertFalse($pathRequest->verify($signature2));
    }

    public function testGettingSwitchesFromGet() {
        $get = ['phast' => 'images,-webp'];
        $httpRequest = Request::fromArray($get, []);
        $serviceRequest = ServiceRequest::fromHTTPRequest($httpRequest);

        $expected = [
            'images' => true,
            'webp' => false,
            'phast' => true,
            'diagnostics' => false

        ];
        $this->assertEquals($expected, $serviceRequest->getSwitches()->toArray());
    }

    public function testGettingSwitchesFromCookies() {
        $cookie = ['phast' => 'images,-jpeg,diagnostics'];
        $get =    ['phast' => '-images,-webp'];
        $httpRequest = Request::fromArray($get, [], $cookie);
        $switches = ServiceRequest::fromHTTPRequest($httpRequest)->getSwitches();

        $this->assertFalse($switches->isOn('jpeg'));
        $this->assertTrue($switches->isOn('diagnostics'));
        $this->assertFalse($switches->isOn('images'));
        $this->assertFalse($switches->isOn('webp'));
    }

    public function testGettingSwitchesFromPathInfo() {
        $pathInfo = '/switches=-2Ddiagnostics.phast';
        $httpRequest = Request::fromArray([], ['PATH_INFO' => $pathInfo]);
        $serviceRequest = ServiceRequest::fromHTTPRequest($httpRequest);

        $expected = [
            'diagnostics' => false,
            'phast' => true,
        ];
        $this->assertEquals($expected, $serviceRequest->getSwitches()->toArray());
    }

    public function testGettingDefaultSwitches() {
        $httpRequest = Request::fromArray([], []);
        $serviceRequest = ServiceRequest::fromHTTPRequest($httpRequest);
        $this->assertEquals(
            ['phast' => true, 'diagnostics' => false],
            $serviceRequest->getSwitches()->toArray()
        );
    }

    public function testPropagatingSwitches() {
        $httpRequest = Request::fromArray(['phast' => 's1.s2.-s3'], []);
        $serviceRequest = ServiceRequest::fromHTTPRequest($httpRequest);
        $url = $serviceRequest->withUrl(URL::fromString('phast.php?service=images'))
                ->withParams(['k' => 'v'])
                ->serialize();
        $this->assertContains('phast=s1.s2.-s3', $url);
    }

    public function testGeneratingRequestId() {
        $httpRequest = Request::fromArray([], []);
        $id1 = ServiceRequest::fromHTTPRequest($httpRequest)->getDocumentRequestId();
        $id2 = ServiceRequest::fromHTTPRequest($httpRequest)->getDocumentRequestId();

        $this->assertTrue((bool)preg_match('/^\d{1,9}$/', $id1));
        $this->assertTrue((bool)preg_match('/^\d{1,9}$/', $id2));
        $this->assertNotEquals($id1, $id2);
    }

    public function testPreservingRequestId() {
        $httpRequest = Request::fromArray([], []);
        $id1 = ServiceRequest::fromHTTPRequest($httpRequest)->getDocumentRequestId();
        $id2 = (new ServiceRequest())->getDocumentRequestId();
        $this->assertEquals($id1, $id2);
    }

    public function testGettingRequestIdFromHTTPRequest() {
        $httpRequest = Request::fromArray(['documentRequestId' => 'the-id'], []);
        $id = ServiceRequest::fromHTTPRequest($httpRequest)->getDocumentRequestId();
        $this->assertEquals('the-id', $id);
    }

    public function testPropagatingRequestId() {
        $url = URL::fromString('phast.php?service=diagnostics');
        $url1 = (new ServiceRequest())->withUrl($url)->serialize();

        $this->assertNotContains('requestId=', $url1);

        $httpRequest = Request::fromArray(['phast' => 'diagnostics'], []);
        $url2 = ServiceRequest::fromHTTPRequest($httpRequest)
                ->withUrl($url)
                ->serialize();

        $this->assertContains('documentRequestId=', $url2);

        $url3 = (new ServiceRequest())->withUrl($url)->serialize();
        $this->assertContains('documentRequestId=', $url3);

        $httpRequest = Request::fromArray(['phast' => ''], [], ['phast' => 'diagnostics']);
        $url4 = ServiceRequest::fromHTTPRequest($httpRequest)
            ->withUrl($url)
            ->serialize();
        $this->assertContains('documentRequestId=', $url4);
    }

    
    private function checkRequest(ServiceRequest $request, $expectedQuery, $expectedPath) {
        $actualQuery = $request->serialize(ServiceRequest::FORMAT_QUERY);
        $actualPath = $request->serialize(ServiceRequest::FORMAT_PATH);

        $this->assertEquals($expectedQuery, $actualQuery);
        $this->assertEquals($expectedPath, $actualPath);
    }

}