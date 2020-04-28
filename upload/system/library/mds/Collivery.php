<?php

namespace Mds;

use Exception;
use mdsHttpRequest;

class Collivery
{

    private $registry;
    private $config;
    private $request;

    const API_BASE_URL = 'https://api.collivery.co.za/v3/';
    const VERSION = 'v3';
    const APP_PLUGIN_NAME = 'collivery.net opencart plugin';
    const SANDBOX_USERNAME = 'demo@collivery.co.za';
    const SANDBOX_PASSWORD = 'demo';

    const ENDPOINT_LOGIN = 'login';
    const ENDPOINT_DEFAULT_ADDRESS = 'default_address';
    const ENDPOINT_SERVICE_TYPES = 'service_types';
    const ENDPOINT_LOCATION_TYPES = 'location_types';
    const ENDPOINT_SUBURBS = 'suburbs';
    const ENDPOINT_TOWNS = 'towns';
    const ENDPOINT_ADDRESS = 'address';

    // Client Auth Credential
    private $clientId = null;
    private $fullName = null;
    private $emailAddress = null;
    private $accessToken = null;

    private $isSandBocAcc = true;

    private $isAuthError = false;
    private $errors = [];



    /**
     * Collivery constructor.
     * @param $registry
     * @throws Exception
     */
    public function __construct($registry)
    {
        $this->registry = $registry;
        $this->config = $registry->get('config');
        $this->request = $registry->get('request');

        $this->getAuthenticationToken();

        $this->getServices();
    }

    /**
     * set authentication token
     * @throws Exception
     */
    private function getAuthenticationToken()
    {
        $client_login = $this->getUserCredential();
        $data = $this->sendRequest('post', self::ENDPOINT_LOGIN, $client_login);

        if (isset($data->data)) {
            $this->clientId = $data->data->id;
            $this->emailAddress = $data->data->email_address;
            $this->accessToken = $data->data->api_token;
            $this->fullName = $data->data->full_name;

            $this->isSandBocAcc = $this->emailAddress === self::SANDBOX_USERNAME;
        } else {
            $this->isAuthError = true;
        }

    }

    /**
     * determine if error occurred during authentication
     */
    private function isAuthError()
    {
        return $this->isAuthError;
    }

    /**
     * filter user credential
     * @return array
     */
    private function getUserCredential()
    {
        $arr = ['email' => self::SANDBOX_USERNAME,'password' => self::SANDBOX_PASSWORD];

        if (filter_var($email = trim($this->config->get('shipping_mds_username')), FILTER_VALIDATE_EMAIL)) {
            $arr['email'] = $email;
            $arr['password'] =trim($this->config->get('shipping_mds_password'));
        }

        return  $arr;
    }

    /** Send Request
     * @param $type
     * @param $endpoint
     * @param $data
     * @return object|null
     * @throws Exception
     */
    private function sendRequest($type, $endpoint, $data)
    {

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

    /** get instance of MdsHttpRequest
     * @return MdsHttpRequest
     * @throws Exception
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

    /** get authentication token
     *
     * @return string|null
     */
    public function token()
    {
        return $this->accessToken;
    }

    /**
     * @param $endpoint
     * @param array $data
     * @return object|null
     * @throws Exception
     */
    private function get($endpoint, $data = [])
    {
        return $this->sendRequest('get',$endpoint, array_merge(['api_token' => $this->token()], $data));
    }

    /**
     * get all suburbs
     * @return array
     * @throws Exception
     */
    public function getAllSuburbs()
    {
        $result = [];
        $suburbs = $this->get(self::ENDPOINT_SUBURBS, ['country' => 'ZAF']);
        if ($suburbs = $suburbs->data) {
            foreach ($suburbs as  $suburb) {
                $result[$suburb->id] =  $suburb->name;
            }
        }

        return $result;
    }

    /**
     * get a suburb
     * @param $suburb_id
     * @return array
     * @throws Exception
     */
    public function getSuburb($suburb_id = 0)
    {
        $result = [];
        $suburbs = $this->get(self::ENDPOINT_SUBURBS, ["town_id" => $suburb_id,"country" => "ZAF"]);

        if (isset($suburbs->data)) {
            foreach ($suburbs->data as  $suburb) {
                $result[$suburb->id] =  $suburb->name;
            }
        }
        return $result;
    }

    /**
     * get towns
     * @param string $country
     * @return array
     * @throws Exception
     */
    public function getTowns($country = "ZAF" )
    {
        $result = [];
        $towns = $this->get(self::ENDPOINT_TOWNS, ["country" => $country, "per_page" => 1000000]);

        if (isset($towns->data)) {
            foreach ($towns->data as  $town) {
                $result[$town->id] =  $town->name;
            }
        }

        return $result;
    }

    /**
     * get location types
     * @throws Exception
     */
    public function getLocationTypes()
    {
        $result = [];
        $locationTypes = $this->get(self::ENDPOINT_LOCATION_TYPES);

        if (isset($locationTypes->data)) {
            foreach ($locationTypes->data as $locationType) {
                $result[$locationType->id] = $locationType->name;
            }
        }

        return $result;
    }

    /**
     * get client Default address
     * @throws Exception
     */
    public function getDefaultAddress()
    {
        $defaultAddress = $this->get(self::ENDPOINT_DEFAULT_ADDRESS);
        if (!isset($defaultAddress->data)) {
            return [];
        }

        $defaultAddressId = $defaultAddress->data->id;
        $clientAddress = $this->getAddress($defaultAddressId);
        $address = [];
        $contacts = [];


        if ($clientAddress) {
            $contacts = $clientAddress->contacts;
            $address['address_id'] =  $clientAddress->id;
            $address['custom_id'] =  $clientAddress->custom_id;
            $address['client_id'] =  $this->clientId;
            $address['suburb_id'] =  $clientAddress->suburb_id;
            $address['nice_address'] =  $clientAddress->short_text;
        }

        return  [
            'address' => $address,
            'default_address_id' => $defaultAddressId,
            'contacts' => $contacts
        ];


    }

    /**
     * get client address
     * @param $defaultAddressId
     * @return array
     * @throws Exception
     */
    public function getAddress($defaultAddressId)
    {
        $address = $this->get(self::ENDPOINT_ADDRESS . '/' . $defaultAddressId);

        if (isset($address->data)) {
            return $address->data;
        }

        return [];

    }

    /**
     * get Services
     * @return array
     * @throws Exception
     */
    public function getServices()
    {
        $result = [];
        $services = $this->get(self::ENDPOINT_SERVICE_TYPES );

        if (isset($services->data)) {
            foreach ($services->data as $service) {
                $result[$service->id] =  $service->text;
            }
        }
        return $result;

    }

}

require_once(dirname(__FILE__) . '/MdsHttpRequest.php');
