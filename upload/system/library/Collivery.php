<?php

class Collivery
{
    private $errors = [];
    private $accessToken = null;

    const API_BASE_URL = 'https://api.collivery.co.za/v3/';
    const VERSION = 'v3';
    const APP_PLUGIN_NAME = 'collivery.net opencart plugin';

    const SANDBOX_USERNAME = 'demo@collivery.co.za';
    const SANDBOX_PASSWORD = 'demo';

    const ENDPOINT_LOGIN = 'login';
    const ENDPOINT_DEFAULT_ADDRESS = 'default_address';
    const ENDPOINT_SERVICE_TYPES = 'service_types';
    const ENDPOINT_TOWNS = 'towns';
    const ENDPOINT_SUBURBS = 'towns';
    const ENDPOINT_LOCATION_TYPES = 'location_types';

    private $userName = null;

    private $registry;
    private $config;

    private $attempts = 0;

    private $isAuthError = true;


    /**
     * Collivery constructor.
     * @param $registry
     */
    public function __construct($registry)
    {
        $this->registry = $registry;
        $this->config = $registry->get('config');

        $this->setToken();

    }

    /** get instance of MdsHttpRequest
     * @return MdsHttpRequest
     */
    private function getMdsHttpRequestInstance()
    {
        // using singleton return error when more than one request is made
        return (new MdsHttpRequest(self::API_BASE_URL))
            ->setHeader('Content-type', 'application/json')
            ->setHeader('X-App-Name', self::APP_PLUGIN_NAME)
            ->setHeader('X-App-Version', self::VERSION)
            ->setHeader('X-App-Host', $_SERVER['HTTP_HOST'])
            ->setHeader('X-App-Lang', 'php')
            ->setHeader('Accept', 'application/json');
    }

    /** Send Request
     * @param $type
     * @param $endpoint
     * @param $data
     * @return object|null
     */
    private function sendRequest($type, $endpoint, $data)
    {
        $this->attempts += 1;

        $result = null;
        $instance = $this->getMdsHttpRequestInstance();

        // parse json
        $instance->setJsonDecoder();

        switch ($type) {
            case 'post' :
                $result = $instance->post($endpoint, $data);
                break;
            default:
                $result = $instance->get($endpoint, $data);
                break;
        }

        if ($instance->isCurlError()) {
            $this->errors['curl'] = $instance->getCurlErrorMessage();
        }

        if ($instance->isHttpError() && $result->error) {
            $this->errors['http'] = $result->error->message;
        }

        return $result;

    }

    /**
     * @param $endpoint
     * @param array $data
     * @return object|null
     */
    private function fetch($endpoint, $data = [])
    {
        $data = array_merge(['api_token' => $this->token()], $data);
        return $this->sendRequest('get',$endpoint, $data);
    }

    /** get authentication token
    /** get authentication token
     * @return string|null
     */
    public function token()
    {
        return $this->accessToken;
    }

    /**
     * set authentication token
     */
    private function setToken()
    {
        if ($data = $this->attempt()) {
            $this->accessToken = $data->api_token;
            $this->userName = $data->email_address;
            $this->isAuthError = false;
        }
    }

    /**
     * attempt to retrieve user access token
     * https://collivery.net/integration/api/v3/authentication
     * @return object|null
     */
    private function attempt()
    {
        if ($data = $this->sendRequest('post', self::ENDPOINT_LOGIN, $this->UserCredential())) {
            if ($d = $data->data) {
                return $d;
            }
        }
    }

    /**
     * Determine if authentication to api passed
     * @return bool
     */
    public function isAuthError()
    {
        return $this->isAuthError;
    }

    /**
     * get user api login credential
     * @return array
     */
    private function UserCredential()
    {
        $userName = self::SANDBOX_USERNAME;
        $password = self::SANDBOX_PASSWORD;
        if ($n =$this->getVal('shipping_mds_username')) {
            $userName = $n;
        }

        if ($p = $this->getVal('shipping_mds_password')) {
            $password = $p;
        }

        return ['email' => $userName, 'password' => $password];
    }

    /**
     * @param $val
     * @return string|null
     */
    private function getVal($val)
    {
        if ($this->config->has($val)) {
            return trim($this->config->get($val));
        }

    }

    /**
     * get towns
     * @return array
     */
    public function towns()
    {
        $result = [];
        $data = $this->fetch(self::ENDPOINT_TOWNS);
        if ($this->hasResults($data)) {
            foreach ($data->data as $index => $town) {
                $result[$town->id] = $town->name;
            }
        }
        return $result;
    }

    /**
     * get location types
     * @return array
     */
    public function locationTypes()
    {
        $result = [];
        $data = $this->fetch(self::ENDPOINT_LOCATION_TYPES);
        if ($this->hasResults($data)) {
            foreach ($data->data as $index => $locationType) {
                $result[$locationType->id] = $locationType->name;
            }
        }
        return $result;
    }

    /**
     * get suburbs
     * @return array
     */
    public function suburbs()
    {
        $result = [];
        $data = $this->fetch(self::ENDPOINT_SUBURBS);
        if ($this->hasResults($data)) {
            foreach ($data->data as $index => $suburb) {
                $result[$suburb->id] = $suburb->name;
            }
        }
        return $result;
    }

    /**
     * get service-types form api
     * https://collivery.net/integration/api/v3/#11-service-types
     * @return array
     */
    public function services()
    {
        $result = [];
        if ($data = $this->fetch(self::ENDPOINT_SERVICE_TYPES)) {
            foreach ($data->data as $index => $service) {
                $result[$service->id] = $service->text;
            }
        }
        return $result;
    }

    /**
     * get default_address form api
     * https://collivery.net/integration/api/v3/#2-addresses
     * @return string
     */
    public function defaultAddress()
    {
        $result = '';
        if ($data = $this->fetch(self::ENDPOINT_DEFAULT_ADDRESS)) {
            $result = $data;
        }
        return $result;
    }

    public function getuserName()
    {
        return $this->userName;
    }




}

require_once(dirname(__FILE__) . '/mds/MdsHttpRequest.php');