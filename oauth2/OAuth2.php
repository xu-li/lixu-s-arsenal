<?php
include_once(dirname(__FILE__) . "/../utils/HttpRequestSender.php");

class OAuth2
{
    /**
     * Auth methods
     */
    const AUTH_TYPE_URI                 = 0;
    const AUTH_TYPE_AUTHORIZATION_BASIC = 1;
    const AUTH_TYPE_FORM                = 2;

    /**
     * Access token types
     */
    const ACCESS_TOKEN_URI    = 0;
    const ACCESS_TOKEN_BEARER = 1;
    const ACCESS_TOKEN_OAUTH  = 2;
    const ACCESS_TOKEN_MAC    = 3;

    /**
     * Grant types
     */
    const GRANT_TYPE_AUTH_CODE          = 'authorization_code';
    const GRANT_TYPE_PASSWORD           = 'password';
    const GRANT_TYPE_CLIENT_CREDENTIALS = 'client_credentials';
    const GRANT_TYPE_REFRESH_TOKEN      = 'refresh_token';

    /**
     * Endpoints
     */
    const ENDPOINT_CALLBACK = "callback";
    const ENDPOINT_AUTH     = "auth";
    const ENDPOINT_TOKEN    = "token";
    const ENDPOINT_API      = "api";

    /**
     * Create a qq oauth2 instance
     *
     * @see http://wiki.open.t.qq.com/index.php/%E9%A6%96%E9%A1%B5
     */
    public static function qq($client_id, $client_secret, $callback) {
        return new self($client_id, $client_secret, array(
            "callback" => $callback,
            "auth"     => "https://open.t.qq.com/cgi-bin/oauth2/authorize",
            "token"    => "https://open.t.qq.com/cgi-bin/oauth2/access_token",
            "api"      => "https://open.t.qq.com/api/?oauth_version=2.a&oauth_consumer_key={CLIENT_ID}"
        ));
    }

    /**
     * Create a weibo oauth2 instance
     *
     * @see http://open.weibo.com/wiki/%E9%A6%96%E9%A1%B5
     */
    public static function weibo($client_id, $client_secret, $callback) {
        return new self($client_id, $client_secret, array(
            "callback" => $callback,
            "auth"     => "https://api.weibo.com/oauth2/authorize",
            "token"    => "https://api.weibo.com/oauth2/access_token",
            "api"      => "https://api.weibo.com/2/"
        ), array(
            "append_params_for_access_token_request" => true
        ));
    }

    /**
     * Create a renren oauth2 instance
     *
     * @see http://wiki.dev.renren.com/wiki/%E9%A6%96%E9%A1%B5
     */
    public static function renren($client_id, $client_secret, $callback) {
        return new self($client_id, $client_secret, array(
            "callback" => $callback,
            "auth"     => "https://graph.renren.com/oauth/authorize",
            "token"    => "https://graph.renren.com/oauth/token",
            "api"      => "https://api.renren.com/v2/"
        ));
    }

    protected $client_id;
    protected $client_secret;
    protected $scope;

    protected $auth_type = self::AUTH_TYPE_URI;
    protected $grant_type = self::GRANT_TYPE_AUTH_CODE;
    protected $access_token_type = self::ACCESS_TOKEN_URI;
    protected $access_token;

    // because sina requires a empty POST body, with all parameters in the url
    protected $append_params_for_access_token_request = false;

    protected $http_request_sender;

    /**
     * Endpoints
     *
     * An array of urls, possible keys:
     * 1. callback, callback url after authorization
     * 2. auth, authorization url to get the authorization code
     * 3. token, token url to exchange the authorization code
     * 4. api, api url to fetch the protected resource
     */
    protected $endpoints = array();

    public function __construct($client_id, $client_secret, $endpoints, $options = array()) {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->endpoints = $endpoints;

        $this->http_request_sender = new HttpRequestSender();

        foreach ($options as $k => $v) {
            $this->$k = $v;
        }
    }

    /** 
     * Get the authorization url
     *
     * @param array $params An array to override the default parameters
     * @return string
     */
    public function getAuthorizationUrl($params = array()) {
        $url = $this->getEndpoint(self::ENDPOINT_AUTH);
        if (empty($url)) {
            throw new Exception("Authorization url is empty.");
        }

        $default = array(
            'response_type' => 'code',
            'client_id'     => $this->client_id
        );

        if (!empty($this->scope)) {
            $default["scope"] = $this->scope;
        }

        $callback_url = $this->getEndpoint(self::ENDPOINT_CALLBACK);
        if (!empty($callback_url)) {
            $default['redirect_uri'] = $callback_url;
        }

        $params = http_build_query(array_merge($default, $params));
        return $this->buildUrl($url, "", $params);
    }

    /**
     * Exchange the authorization code
     */
    public function getAccessToken($authorization_code = "", $options = array()) {
        $token_url = $this->getEndpoint(self::ENDPOINT_TOKEN);
        if (empty($token_url)) {
            throw new Exception("Token url is empty.");
        }

        if ($authorization_code === "") {
            $authorization_code = isset($_GET["code"]) ? $_GET["code"] : "";
        }

        $params = array(
            "grant_type" => $this->grant_type,
            "code" => $authorization_code
        );

        $callback_url = $this->getEndpoint(self::ENDPOINT_CALLBACK);
        if (!empty($callback_url)) {
            $params['redirect_uri'] = $callback_url;
        }

        $headers = array();
        switch ($this->auth_type) {
            case self::AUTH_TYPE_URI:
            case self::AUTH_TYPE_FORM:
                $params['client_id'] = $this->client_id;
                $params['client_secret'] = $this->client_secret;
                break;
            case self::AUTH_TYPE_AUTHORIZATION_BASIC:
                $params['client_id'] = $this->client_id;
                $headers[] = 'Authorization: Basic ' . base64_encode($this->client_id .  ':' . $this->client_secret);
                break;
            default:
                throw new Exception('Unknown client auth type.');
        }

        if (isset($options["params"]) && is_array($options["params"])) {
            $params = array_merge($params, $options["params"]);
        }

        if (isset($options["headers"]) && is_array($options["headers"])) {
            $headers = array_merge($headers, $options["headers"]);
        }

        if ($this->append_params_for_access_token_request) {
            $url = $this->buildUrl($token_url, "", http_build_query($params));
            $params = null;
        } else {
            $url = $token_url;
        }
        $method = isset($options["method"]) ? $options["method"] : "POST";

        return $this->http_request_sender->request($url, $method, $params, $headers);
    }

    /**
     * Fetch a protected resource
     *
     * @param string $path
     * @param array $options
     * @return string
     */
    public function fetch($path, $options = array()) {
        // default to "GET"
        $method = isset($options["method"]) ? $options["method"] : "GET";

        // prefix the path with api url
        $url = $this->getEndpoint(self::ENDPOINT_API);
        $url = empty($url) ? $path : $this->buildUrl($url, $path);

        $params = isset($options["params"]) ? $options["params"] : array();
        $headers = isset($options["headers"]) ? $options["headers"] : array();

        // add the access token
        switch ($this->access_token_type) {
            case self::ACCESS_TOKEN_URI:
            $params["access_token"] = $this->access_token;
            break;

            case self::ACCESS_TOKEN_BEARER:
            $headers[] = 'Authorization: Bearer ' . $this->access_token;
            break;

            case self::ACCESS_TOKEN_OAUTH:
            $headers[] = 'Authorization: OAuth ' . $this->access_token;
            break;

            case self::ACCESS_TOKEN_MAC:
            throw new Exception("Not implemented.");
        }

        return $this->http_request_sender->request($url, $method, $params, $headers);
    }

    public function setAccessToken($token, $type = self::ACCESS_TOKEN_URI) {
        $this->access_token = $token;
        $this->access_token_type = $type;
    }

    /**
     * get the endpoint url, replace placeholders
     *
     * @param string $key
     * @return string
     */
    protected function getEndpoint($key) {
        if (!isset($this->endpoints[$key])) {
            return "";
        }

        $search = array(
            "{CLIENT_ID}",
            "{CLIENT_SECRET}",
            "{ACCESS_TOKEN}"
        );

        $replace = array(
            $this->client_id,
            $this->client_secret,
            $this->access_token
        );

        return str_replace($search, $replace, $this->endpoints[$key]);
    }

    /**
     * Build a url
     *
     * @param string $url
     * @param string $path
     * @param string $query
     * @return string|bool
     */
    protected function buildUrl($url, $path = "", $query = "") {
        $url = parse_url($url);

        if ($url === false) {
            return false;
        }

        $url["path"] = isset($url["path"]) ? ltrim($url["path"], "/") . $path : $path;
        $url["query"] = isset($url["query"]) ? $url["query"] . '&' . $query: $query;

        return sprintf("%s://%s/%s?%s", $url["scheme"], $url["host"], $url["path"], $url["query"]);
    }
}
