<?php

namespace Kibo\Phast\HTTP;

use Kibo\Phast\Exceptions\RuntimeException;
use Kibo\Phast\HTTP\Exceptions\HTTPError;
use Kibo\Phast\HTTP\Exceptions\NetworkError;
use Kibo\Phast\ValueObjects\URL;

class CURLClient implements Client {

    public function __construct() {
        if (!function_exists('curl_init')) {
            throw new RuntimeException("cURL must be installed to use RemoteCURLBackend");
        }
    }

    public function get(URL $url, array $headers = []) {
        return $this->request($url, $headers);
    }

    public function post(URL $url, $data, array $headers = []) {
        return $this->request($url, $headers, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data
        ]);
    }

    private function request(URL $url, array $headers = [], array $opts = []) {
        $ch = curl_init((string)$url);
        curl_setopt_array($ch, $opts + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->makeHeaders($headers),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_HEADER => true
        ]);
        $responseText = @curl_exec($ch);
        if ($responseText === false) {
            throw new NetworkError(curl_error($ch), curl_errno($ch));
        }
        $info = curl_getinfo($ch);
        if (!preg_match('/^2/', $info['http_code'])) {
            throw new HTTPError($info['http_code']);
        }
        $response = $this->parseResponse($responseText);
        $response->setCode($info['http_code']);
        return $response;
    }

    private function makeHeaders(array $headers) {
        $result = [];
        foreach ($headers as $k => $v) {
            $result[] = "$k: $v";
        }
        return $result;
    }

    private function parseResponse($responseText) {
        // When status 100 curl returns two lines with status (\r\n\r\n x2)
        $responseParts = explode("\r\n\r\n", $responseText);
        $body = array_pop($responseParts);
        $headersText = array_pop($responseParts);
        $response = new Response();
        $response->setContent($body);
        foreach (explode("\r\n", $headersText) as $idx => $header) {
            if ($idx === 0) {
                continue;
            }
            list ($name, $value) = explode(':', $header);
            $response->setHeader($name, $value);
        }
        return $response;
    }

}