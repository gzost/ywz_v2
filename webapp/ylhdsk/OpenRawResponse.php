<?php

namespace YunLianHui;

class OpenRawResponse
{

    protected $headers;

    protected $body;


    protected $httpResponseCode;


    public function __construct($headers, $body, $httpStatusCode = null)
    {
        if (is_numeric($httpStatusCode)) {
            $this->httpResponseCode = (int)$httpStatusCode;
        }

        if (is_array($headers)) {
            $this->headers = $headers;
        } else {
            $this->setHeadersFromString($headers);
        }

        $this->body = $body;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function getHttpResponseCode()
    {
        return $this->httpResponseCode;
    }

    public function setHttpResponseCodeFromHeader($rawResponseHeader)
    {
        preg_match('|HTTP/\d\.\d\s+(\d+)\s+.*|', $rawResponseHeader, $match);
        $this->httpResponseCode = (int)$match[1];
    }

    protected function setHeadersFromString($rawHeaders)
    {
        $rawHeaders = str_replace("\r\n", "\n", $rawHeaders);
        $headerCollection = explode("\n\n", trim($rawHeaders));
        $rawHeader = array_pop($headerCollection);

        $headerComponents = explode("\n", $rawHeader);
        foreach ($headerComponents as $line) {
            if (strpos($line, ': ') === false) {
                $this->setHttpResponseCodeFromHeader($line);
            } else {
                list($key, $value) = explode(': ', $line, 2);
                $this->headers[$key] = $value;
            }
        }
    }
}