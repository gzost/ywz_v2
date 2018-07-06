<?php
namespace YunLianHui;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class Request
{

    protected $guzzleClient;

    public function __construct(Client $guzzleClient = null)
    {
        $this->guzzleClient = $guzzleClient ?: new Client();
    }

	public function send($url, $method, $body, array $headers, $timeOut=300)
	{
        $options = [
            'timeout' => $timeOut,
            'connect_timeout' => 10,
            'verify' => __DIR__ . '/certs/yunlianhui.pem',
        ];
        $request = new Psr7Request($method,$url,$headers,$body);

        try {
            $rawResponse = $this->guzzleClient->send($request,$options);

        } catch (RequestException $e) {
            $rawResponse = $e->getResponse();
            if ($e->getPrevious() instanceof TransferException || !$rawResponse instanceof RequestException) {
                throw new ApiException($rawResponse->getBody(),$rawResponse->getStatusCode());
            }
        }

        $rawHeaders = $this->getHeadersAsString($rawResponse);
        $rawBody = $rawResponse->getBody();
        $httpStatusCode = $rawResponse->getStatusCode();

        return new OpenRawResponse($rawHeaders, $rawBody, $httpStatusCode);
	}
    public function getHeadersAsString(ResponseInterface $response)
    {
        $headers = $response->getHeaders();
        $rawHeaders = [];
        foreach ($headers as $name => $values) {
            $rawHeaders[] = $name . ": " . implode(", ", $values);
        }

        return implode("\r\n", $rawHeaders);
    }


	public function get($url = null, $requestBody = array())
	{
        $headers = [
            'User-Agent' => 'ylh-php-sdk',
            'Accept-Encoding' => '*',
        ];
		return $this->send($url,'GET',$requestBody,$headers);
	}

	public function post($url = null, $requestBody = array())
	{
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'User-Agent' => 'ylh-php-sdk',
            'Accept-Encoding' => '*',
        ];
		return $this->send($url, 'POST',$requestBody,$headers);
	}


}
