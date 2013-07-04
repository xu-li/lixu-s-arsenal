<?php
if (!function_exists('curl_version')) {
    throw new Exception("You have to install curl extension.");
}

/**
 * A generic Request Sender class
 * 
 * 
 * <code>
 * $sender = new RequestSender(array("cookie" => true));
 * $sender->get("http://httpbin.org/cookies/set?key2=value2");
 * 
 * echo "Request headers: \r\n";
 * echo "===================\r\n";
 * echo $sender->getLastRequestHeaders() . "\r\n\r\n";
 * echo "Response headers: \r\n";
 * echo "===================\r\n";
 * echo $sender->getLastResponseHeaders() . "\r\n\r\n";
 * echo "Body: \r\n";
 * echo "===================\r\n";
 * echo $sender->getLastResponseBody();
 * </code>
 */
class RequestSender
{
    const METHOD_GET  = "GET";
    const METHOD_POST = "POST";

    const HEADER_UA_MAC_CHROME = "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.110 Safari/537.36";

    protected $curlOptions = array(
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_ENCODING       => "gzip,deflate",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLINFO_HEADER_OUT    => true
    );

    protected $defaultHeaders = array(
        "Accept-Language: en-US,en;q=0.8",
        "Accept: */*",
        "Cache-Control: max-age=0",
        "Connection: keep-alive",
        self::HEADER_UA_MAC_CHROME
    );

    protected $lastResponse = "";
    protected $lastResponseInfo = array();
    protected $lastErrorCode = "";
    protected $lastErrorMessage = "";

    public function __construct($options = array()) {
        if (isset($options["curl"]) && is_array($options["curl"])) {
            $this->curlOptions = $this->curlOptions + $options["curl"];
        }

        if (isset($options["cookie"])) {
            $this->useCookie($options["cookie"]);
        }
    }

    /**
     * Whether to use the cookie
     *
     * @param string|bool $file If file is false, it will disable the cookie file
     *                          If file is a empty string or true, a random file will be used
     *                          If file is a normal string, it will be used as the cookie file
     */
    public function useCookie($file = "") {
        if ($file === "" || $file === true) {
            $file = tempnam(sys_get_temp_dir(), __CLASS__);
        } else if ($file === false) {
            unset($this->curlOptions[CURLOPT_COOKIEJAR], $this->curlOptions[CURLOPT_COOKIEFILE]);
            return ;
        }

        $this->curlOptions[CURLOPT_COOKIEJAR] = $file;
        $this->curlOptions[CURLOPT_COOKIEFILE] = $file;
    }

    /**
     * Helper method to send a "GET" request
     */
    public function get($url, $params = array(), $headers = array()) {
        return $this->request($url, self::METHOD_GET, $params, $headers);
    }

    /**
     * Helper method to send a "POST" request
     */
    public function post($url,$params = array(), $headers = array()) {
        return $this->request($url, self::METHOD_POST, $params, $headers);
    }

    /**
     * Send a request
     *
     * @param string $url
     * @param string $method
     * @param array|string $params
     * @param array $headers
     * @return string
     */
    public function request($url, $method = self::METHOD_GET, $params = array(), $headers = array()) {
        $method = strtoupper($method);
        $method = $method === self::METHOD_GET ? self::METHOD_GET : self::METHOD_POST;
        $headers = array_merge($this->defaultHeaders, $headers);

        $options = array();
        if ($method === self::METHOD_POST) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $params;
        } else {
            if (is_array($params)) {
                $params = http_build_query($params);
            }

            if (strpos($url, '?') === false) {
                $url = $url . '?' . $params;
            } else {
                $url = $url . '&' . $params;
            }

        }
        $options[CURLOPT_URL] = $url;
        $options[CURLOPT_HTTPHEADER] = $headers;

        $options = $this->curlOptions + $options;

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);

        $this->lastResponse = $response;
        $this->lastResponseInfo = $info;
        if ($response === false) {
            $this->lastErrorCode = curl_errno($ch); 
            $this->lastErrorMessage = curl_error($ch);
        } else {
            $this->lastErrorCode = CURLE_OK;
            $this->lastErrorMessage = "";
        }

        curl_close($ch);
        return $response;
    }

    public function getLastErrorCode() {
        return $this->lastErrorCode;
    }

    public function getLastErrorMessage() {
        return $this->lastErrorMessage;
    }

    public function getLastRequestHeaders() {
        if (!empty($this->lastResponseInfo)) {
            return $this->lastResponseInfo["request_header"];
        }
        return array();
    }

    public function getLastResponseHeaders() {
        if ($this->lastErrorCode === CURLE_OK && !empty($this->lastResponseInfo)) {
            $header_length = $this->lastResponseInfo["header_size"];
            return substr($this->lastResponse, 0, $header_length);
        }
        return "";
    }

    public function getLastResponseBody() {
        if ($this->lastErrorCode === CURLE_OK && !empty($this->lastResponseInfo)) {
            $header_length = $this->lastResponseInfo["header_size"];
            return substr($this->lastResponse, $header_length);
        }
        return "";
    }

    public function getLastResponseInfo() {
        return $this->lastResponseInfo;
    }
}
