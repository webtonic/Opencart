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

    private $registry;
    private $config;
    private $request;

    private $attempts = 0;
    private $isSandBocAcc = true;

    private $isAuthError = false;

    private $defaultAddressId = null;


    /**
     * Collivery constructor.
     * @param $registry
     */
    public function __construct($registry)
    {
        $this->registry = $registry;
        $this->config = $registry->get('config');
        $this->request = $registry->get('request');
        $this->refreshToken();
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
    private function refreshToken()
    {
        $client_login = $this->filterUserCredential();
        $data = $this->sendRequest('post', self::ENDPOINT_LOGIN, $client_login);
        if (isset($data->data)) {
            $this->accessToken = $data->data->api_token;
            $this->isSandBocAcc = $client_login['email'] === self::SANDBOX_USERNAME;
        } else {
            $this->isAuthError = false;
        }

    }

    /**
     * filter user credential
     * @return array
     */
    private function filterUserCredential()
    {
        $email = trim($this->config->get('shipping_mds_username'));

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['email' => $email,'password' => $this->config->get('shipping_mds_password')];
        }

        return  ['email' => self::SANDBOX_USERNAME,'password' => self::SANDBOX_PASSWORD];
    }

    /**
     * Determine if authentication to api passed
     * @return bool
     */
    public function isAuthError()
    {
        return $this->isAuthError;
    }

    private function hasResults($data)
    {
        return isset($data->data) && count($data->data);
    }

    /**
     * get service-types form api
     * @return array
     */
    public function services()
    {
        $result = [];
        $data = $this->fetch(self::ENDPOINT_SERVICE_TYPES);
        if ($this->hasResults($data)) {
            foreach ($data->data as $index => $service) {
                $result[$service->id] = $service->text;
            }
        }
        return $result;
    }

    /**
     * get default_address form api
     * @return string
     */
    public function defaultAddress()
    {
        $data = $this->fetch(self::ENDPOINT_DEFAULT_ADDRESS);
        if ($this->hasResults($data)) {
            $this->defaultAddressId = $data->data->id;
            return $data->data;
        }
    }

    /**
     * get default_address form api
     * @return string
     */
    public function defaultAddressId()
    {
        $result = '';
        if ($data = $this->fetch(self::ENDPOINT_DEFAULT_ADDRESS)) {
            $result = $data;
        }
        return $result;
    }

    /** get client username
     * @return string
     */
    public function getClientDefaultName()
    {
        return self::SANDBOX_USERNAME;
    }

    /** get client default password
     * @return string
     */
    public function getClientDefaultPassword()
    {
        return self::SANDBOX_PASSWORD;
    }

    /** Determine client is using testing account
     * @return string
     */
    public function isSandBoxAcc()
    {
        return $this->isSandBocAcc;
    }

    /** get default address
     * @return int|null
     */
    public function getdefaultAddress()
    {
        if ($this->defaultAddressId === null) {
            $this->defaultAddress();
        }

        return $this->defaultAddressId;
    }


}


require_once(dirname(__FILE__) . '/mds/MdsHttpRequest.php');
