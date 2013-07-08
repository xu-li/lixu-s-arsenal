<?php
include_once(dirname(__FILE__) . "/../utils/HttpRequestSender.php");
class HttpRequestSenderTest extends PHPUnit_Framework_TestCase
{
    const URL_HTTP_BIN = "http://httpbin.org/";

    public function testGetUsingArray() {
        $sender = new HttpRequestSender();
        $url = self::URL_HTTP_BIN . "get?test=1";
        $params = array("x" => 1, "y" => 2);
        $sender->get($url, $params);

        $response = json_decode($sender->getLastResponseBody(), true);
        $response_params = $response['args'];

        $params["test"] = 1;
        $this->assertEquals($response_params, $params);
    }

    public function testGetUsingQueryString() {
        $sender = new HttpRequestSender();
        $params = array("x" => 1, "y" => 2);
        $url = self::URL_HTTP_BIN . "get?" . http_build_query($params);
        $sender->get($url, null);

        $response = json_decode($sender->getLastResponseBody(), true);
        $response_params = $response['args'];

        $this->assertEquals($response_params, $params);
    }

    public function testPostUsingMultiPart() {
        $sender = new HttpRequestSender();
        $url = self::URL_HTTP_BIN . "post";
        $params = array("x" => 1, "y" => 2);
        $sender->post($url, $params);

        $response = json_decode($sender->getLastResponseBody(), true);
        $response_params = $response['form'];

        $this->assertEquals($response_params, $params);
    }

    public function testPostUsingForm() {
        $sender = new HttpRequestSender();
        $url = self::URL_HTTP_BIN . "post";
        $params = array("x" => 1, "y" => 2);
        $sender->post($url, http_build_query($params));

        $response = json_decode($sender->getLastResponseBody(), true);
        $response_params = $response['form'];

        $this->assertEquals($response_params, $params);
    }

    public function testCookies() {
        $sender = new HttpRequestSender();
        $sender->useCookie();

        $cookies = array("x" => 1, "y" => 2);
        $url = self::URL_HTTP_BIN . "cookies/set?" . http_build_query($cookies);
        $sender->get($url);

        // get back the cookies, second request
        $url = self::URL_HTTP_BIN . "cookies";
        $sender->get($url);
        $response = json_decode($sender->getLastResponseBody(), true);
        $response_params = $response['cookies'];

        $this->assertEquals($response_params, $cookies);

    }

    public function testUserAgent() {
        $ua = 'test-user-agent';
        $sender = new HttpRequestSender(array(
            'user-agent' => $ua
        ));

        $url = self::URL_HTTP_BIN . "user-agent";
        $sender->get($url);
        $response = json_decode($sender->getLastResponseBody(), true);
        $response_params = $response['user-agent'];

        $this->assertEquals($response_params, $ua);
    }

    public function testHeaders() {
        $sender = new HttpRequestSender();
        $url = self::URL_HTTP_BIN . "headers";
        $headers = array("Custom-Header: This is a custom header");
        $sender->get($url, null, $headers);
        $response = json_decode($sender->getLastResponseBody(), true);
        $response_params = $response['headers'];
        
        $this->assertNotEmpty($response_params);
        $this->assertArrayHasKey("Custom-Header", $response_params);
    }
}
