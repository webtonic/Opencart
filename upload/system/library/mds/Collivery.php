<?php

namespace Mds;

use SoapClient;
use SoapFault;

class Collivery
{
    const ENDPOINT = 'http://ops.collivery.local/webservice.php?wsdl';
    const DEMO_ACCOUNT = ['user_email' => 'api@collivery.co.za', 'user_password' => 'api123'];
    const CACHE_PREFIX = 'collivery_net.';
    protected $token;
    protected $client;
    protected $config;
    protected $errors = [];
    protected $cacheEnabled = true;
    protected $default_address_id;
    protected $client_id;
    protected $user_id;
    protected $log;
    protected $cache;

    /**
     * Setup class with basic Config
     *
     * @param array $config Configuration Array
     * @param       $cache
     */
    public function __construct(array $config = [], $cache = null)
    {

        $config['cache_dir'] = isset($config['cache_dir']) ? $config['cache_dir'] : basename(__DIR__);
        $this->cache = $cache ?: new Cache($config['cache_dir']);
        $this->cacheEnabled = (bool)(isset($config['enable_cache']) ? $config['enable_cache'] : $this->cacheEnabled);
        $this->log = isset($config['log']) ? $config['log'] : new Log;

        if ((isset($config['demo']) && $config['demo']) || !$config['user_email']) {
            $config = array_merge($config, self::DEMO_ACCOUNT);
        }

        $this->config = (object)$config;
    }
    /**
     * Any method called from this class goes via the magic call
     * We want to make sure that we clear all errors that were generated
     * before before calling another method, we also want to ensure that
     * Soap client is initialized before calling any method from it
     * We also want to make sure that a collivery client is authenticated
     *if the conditions above do not succeed, return errors stating reason
     * for failure for each
     *
     * @param $method
     * @param $args
     *
     * @return array|mixed
     * @throws \ReflectionException
     */
    public function __call($method, $args)
    {
        $this->clearErrors(); //clear errors

        !$this->client && $this->init();

        !$this->token && $this->authenticate();

        return $this->getErrors() ?: call_user_func_array([$this, $method], $args);
    }
    /**
     * @return $this
     */
    public function init()
    {
        if (!$this->client) {
            try {
                $this->client = new SoapClient( // Setup the soap client
                    self::ENDPOINT, // URL to WSDL File
                    ['cache_wsdl' => WSDL_CACHE_NONE] // Don't cache the WSDL file
                );
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);
            }
        }

        return $this;
    }
    /**
     * @return $this
     */
    public function authenticate()
    {
        $cacheKey = 'auth';
        $auth = $this->getCache($cacheKey);

        $isSameUser = $auth &&
            isset($auth['user_email'], $this->config->user_email) &&
            !strcasecmp($this->config->user_email, $auth['user_email']);

        if ($isSameUser) {
            $this->default_address_id = $auth['default_address_id'];
            $this->client_id = $auth['client_id'];
            $this->user_id = $auth['user_id'];
            $this->token = $auth['token'];

            return $this;
        }

        $user_email = strtolower($this->config->user_email);
        $user_password = $this->config->user_password;

        $config = [
            'name'    => $this->config->app_name,
            'version' => $this->config->app_version,
            'host'    => $this->config->app_host,
            'url'     => $this->config->app_url,
            'lang'    => 'PHP ' . PHP_VERSION,
        ];

        !$this->client && $this->init();
        if ($this->hasErrors()) {
            return $this;
        }

        try {

            $authenticate = $this->client->authenticate($user_email, $user_password, $this->token, $config);

            if ($authenticate) {

                if ($this->cacheEnabled) {
                    $this->setCache($cacheKey, $authenticate);
                }

                $this->default_address_id = $authenticate['default_address_id'];
                $this->client_id = $authenticate['client_id'];
                $this->user_id = $authenticate['user_id'];
                $this->token = $authenticate['token'];

                return $this;
            }
        } catch (SoapFault $exception) {
            $this->catchSoapFault($exception);
        } catch (\ReflectionException $e) {
            die($e->getMessage());
        }

        return $this;
    }
    /**
     * @return bool
     */
    public function isAuthError()
    {
        return array_key_exists('auth_error', $this->getErrors());
    }
    /**
     * @return $this
     */
    public function clearErrors()
    {
        $this->errors = [];

        return $this;
    }
    /**
     * @param SoapFault $e
     */
    public function catchSoapFault(SoapFault $e)
    {
        $this->setError($e->faultcode, $e->faultstring);
    }
    /**
     * @param        $id
     * @param string $text
     *
     * @return $this
     */
    public function setError($id, $text = '')
    {
        if (is_array($id) && !empty($id)) {
            foreach ($id as $key => $val) {
                $this->setError($key, $val);
            }
        } elseif (is_string($id) || is_int($id)) {
            $this->errors[$id] = $text;
            $json = json_encode(['id' => $id, 'message' => $text]);
            $this->log($json);
        }

        return $this;
    }
    /**
     * @param $message
     */
    public function log($message)
    {
        if (is_array($message)) {
            foreach ($message as $value) {
                $this->log($value);
            }

            return;
        }
        if (property_exists($this, 'log')) {

            try {
                $reflectionClass = new \ReflectionClass($this->log);
                $message = "collivery_net_error: {$message}"; //message to be logged

                foreach (['write', 'message'] as $method) {
                    if ($reflectionClass->hasMethod($method)) {
                        $this->log->{$method}($message);
                        break; //exit
                    }
                }
            } catch (\ReflectionException $e) {
                die($e->getMessage());
            }
        }
    }
    /**
     * @param $key
     *
     * @return mixed|null
     */
    public function getCache($key)
    {
        $key = $this->getCacheKey($key);

        if ($this->cacheEnabled && $this->cache->has($key)) {
            return $this->cache->get($key);
        }

        return null;
    }
    /**
     * @param $key
     *
     * @return string
     */
    public function getCacheKey($key)
    {
        return md5(strtolower(self::CACHE_PREFIX . str_replace(self::CACHE_PREFIX, '', $key)));
    }
    /**
     * @return bool
     */
    public function hasErrors()
    {
        return !empty($this->getErrors());
    }
    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
    /**
     * @param     $key
     * @param     $value
     * @param int $ttl
     *
     * @return mixed
     */
    public function setCache($key, $value, $ttl = 10080)
    {
        $key = $this->getCacheKey($key);

        return $this->cache->put($key, $value, $ttl);
    }
    /**
     * @return array
     */
    public function getClient()
    {
        !$this->client && $this->init();

        return $this->errorsOrResponse($this->client);
    }
    /**
     * @param $key
     *
     * @return bool
     */
    public function removeCache($key)
    {
        if ($this->getCache($key)) {
            $this->cache->forget($key);
        }

        return !$this->getCache($key);
    }
    /**
     * @return $this
     */
    public function disableCache()
    {
        $this->cacheEnabled = false;

        return $this;
    }
    /**
     * @return $this
     */
    public function enableCache()
    {
        $this->cacheEnabled = true;

        return $this;
    }
    /**
     * @param $results
     *
     * @return array
     */
    public function errorsOrResponse($results = null)
    {
        if (!$results || $this->hasErrors()) {
            return $this->getErrors();
        }

        return $results;
    }
    /**
     * @param       $method
     * @param array $params
     *
     * @return array
     */
    public function sendSoapRequest($method, array $params = [])
    {
        //We need to pass a token to the methods in the client
        if (!in_array($this->token, $params, true)) {
            $params[] = $this->token;
        }

        //initialize client and check for errors
        !$this->client && $this->init();

        //Is there anything wrong with the client?
        if ($this->hasErrors()) {
            return $this->errorsOrResponse();
        }

        //Nothing wrong with soap then lets authenticate
        $this->authenticate();

        //Is there anything wrong with the credentials?
        if ($this->hasErrors()) {
            return $this->errorsOrResponse();
        }

        //No errors, lets try calling a method from our client
        try {
            return $this->client->{trim($method)}(...$params);
        } catch (SoapFault $e) {
            //oops, something went wrong from the client, what is it?
            $this->catchSoapFault($e);
        }

        //What was wrong with the soap client?
        return $this->errorsOrResponse();
    }
    /**
     * @param null $town_id
     *
     * @return array|mixed|null
     */
    private function getAllSuburbs($town_id = null)
    {
        return $this->getSuburbs($town_id);
    }
    /**
     * @param $town_id
     *
     * @return array|mixed|null
     */
    private function getSuburbs($town_id)
    {
        $cacheKey = 'suburbs.' . ($town_id ?: '0');
        $suburbs = $this->getCache($cacheKey);

        if ($suburbs) {
            return $suburbs;
        }

        $result = $this->sendSoapRequest('get_suburbs', [$town_id]);
        if (isset($result['error_id'])) {
            $this->setError($result['error_id'], $result['error']);
        }

        if (!isset($result['suburbs'])) {
            $this->setError('no_results', 'No suburbs returned by the server');
        }

        if ($this->hasErrors()) {
            return $this->getErrors();
        }

        $suburbs = $result['suburbs'];
        if ($this->cacheEnabled) {
            $this->setCache($cacheKey, $suburbs);
        }

        return $this->errorsOrResponse($suburbs);
    }
    /**
     * @param array $filter
     *
     * @return array|mixed|null
     */
    private function getAddresses(array $filter = [])
    {
        $cacheKey = 'addresses.' . $this->client_id;
        $addresses = $this->getCache($cacheKey);
        if ($addresses) {
            return $addresses;
        }

        $result = $this->sendSoapRequest('get_addresses', [$this->token, $filter]);

        if (isset($result['error_id'])) {
            $this->setError($result['error_id'], $result['error']);
        }

        if (!isset($result['addresses'])) {
            $this->setError('result_unexpected', 'No address_id returned.');
        }

        $addresses = $result['addresses'];

        if ($this->cacheEnabled && empty($filter)) {
            $this->setCache($cacheKey, $addresses);
        }

        return $this->errorsOrResponse($addresses);
    }
    /**
     * @param $colliveryId
     *
     * @return array|mixed|null
     * @throws \ReflectionException
     */
    private function getPod($colliveryId)
    {
        $cacheKey = 'pod.' . $this->client_id . '.' . $colliveryId;
        $pod = $this->getCache($cacheKey);
        if ($pod) {
            return $pod;
        }

        $result = $this->sendSoapRequest('get_pod', [$colliveryId]);

        if (isset($result['error_id'])) {
            $this->setError($result['error_id'], $result['error']);
        }

        if ($this->hasErrors()) {
            return $this->errorsOrResponse();
        }

        if (!isset($result['pod'])) {
            $this->setError('no_pod', 'No POD data returned');

            return $this->errorsOrResponse();
        }

        $pod = $result['pod'];

        if ($this->cacheEnabled) {
            $this->setCache($cacheKey, $pod);
        }

        return $this->errorsOrResponse($pod);
    }
    /**
     * Returns the status tracking detail of a given Waybill number.
     * If the collivery is still active, the estimated time of delivery
     * will be provided. If delivered, the time and receivers name (if available)
     * with returned.
     *
     * @param int $colliveryId Collivery ID
     *
     * @return bool|array                 Collivery Status Information
     */
    private function getStatus($colliveryId)
    {
        $cacheKey = 'status.' . $this->client_id . $colliveryId;
        $status = $this->getCache($cacheKey);
        if ($status) {
            return $status;
        }

        $result = $this->sendSoapRequest('get_collivery_status', [$colliveryId]);

        if (isset($result['error_id'])) {
            $this->setError($result['error_id'], $result['error']);
        }

        if (!isset($result['status_id'])) {
            $this->setError('result_unexpected', 'No result returned.');
        }

        $result = $result['status_id'];

        if ($this->cacheEnabled) {
            $this->setCache($cacheKey, $result);
        }

        return $this->errorsOrResponse($result);
    }
    /**
     * @param array $data
     *
     * @return array
     * @throws \ReflectionException
     */
    private function addAddress(array $data)
    {
        $this->errors = [];
        $location_types = $this->getLocationTypes();
        $towns = $this->getTowns();
        $suburbs = $this->getSuburbs($data['town_id']);

        if (!isset($data['location_type'])) {
            $this->setError('missing_data', 'location_type not set.');
        } elseif (!isset($location_types[$data['location_type']])) {
            $this->setError('invalid_data', 'Invalid location_type.');
        }

        if (!isset($data['town_id'])) {
            $this->setError('missing_data', 'town_id not set.');
        } elseif (!isset($towns[$data['town_id']])) {
            $this->setError('invalid_data', 'Invalid town_id.');
        }

        if (!isset($data['suburb_id'])) {
            $this->setError('missing_data', 'suburb_id not set.');
        } elseif (!isset($suburbs[$data['suburb_id']])) {
            $this->setError('invalid_data', 'Invalid suburb_id.');
        }

        if (!isset($data['street'])) {
            $this->setError('missing_data', 'street not set.');
        }

        if (!isset($data['full_name'])) {
            $this->setError('missing_data', 'full_name not set.');
        }

        if (!isset($data['phone']) and !isset($data['cellphone'])) {
            $this->setError('missing_data', 'Please supply ether a phone or cellphone number...');
        }

        if (!$this->hasErrors()) {
            $result = $this->sendSoapRequest('add_address', [$data]);

            if (isset($result['error_id'])) {
                $this->setError($result['error_id'], $result['error']);

                return $this->errorsOrResponse();
            }

            if (!isset($result['address_id'])) {
                $error = json_encode($result);
                $this->setError('error_adding_address',
                    "The address could not be added, data validated successfully, error ID not set $error");

                return $this->errorsOrResponse();
            }

            $addressId = $result['address_id'];
            if (isset($result['contact_id'])) {
                list('contact_id' => $contactId) = current($this->getContacts($addressId));

                return $this->errorsOrResponse(['address_id' => $addressId, 'contact_id' => $contactId]);
            }

            $this->setError('contacts_not_found', "The contacts for address '{$addressId}' not found");

        }

        return $this->errorsOrResponse();
    }
    /**
     * @return array|mixed|null
     */
    private function getLocationTypes()
    {
        $cacheKey = 'location_types';
        $locationTypes = $this->getCache($cacheKey);
        if ($locationTypes) {
            return $locationTypes;
        }

        $result = $this->sendSoapRequest('get_location_types');

        if (isset($result['error_id'])) {
            $this->setError($result['error_id'], $result['error']);
        }

        if (!isset($result['results'])) {
            $this->setError('no_results', 'No results returned');
        }

        $locationTypes = $result['results'];

        if ($this->cacheEnabled) {
            $this->setCache($cacheKey, $locationTypes);
        }

        return $this->errorsOrResponse($locationTypes);
    }
    /**
     * @param string $country
     * @param null   $province
     *
     * @return array|mixed|null
     */
    private function getTowns($country = 'zaf', $province = null)
    {
        $cacheKey = 'towns.' . strtolower($country . ($province ? ".{$province}" : ''));

        $towns = $this->getCache($cacheKey);

        if ($towns) {
            return $towns;
        }

        $result = $this->sendSoapRequest('get_towns', [$this->token, $country, $province]);

        if (isset($result['error_id'])) {
            $this->setError($result['error_id'], $result['error']);
        }

        if (!isset($result['towns'])) {
            $this->setError('no_results', 'No towns returned by the server');
        }

        $towns = $result['towns'];

        if ($this->cacheEnabled) {
            $this->setCache($cacheKey, $towns);
        }

        return $this->errorsOrResponse($towns);
    }
    /**
     * @param $address_id
     *
     * @return array|mixed|null
     * @throws \ReflectionException
     */
    private function getContacts($address_id)
    {
        $cacheKey = 'contacts.' . $this->client_id . '.' . $address_id;
        $contacts = $this->getCache($cacheKey);
        if ($contacts) {
            return $contacts;
        }

        $result = $this->sendSoapRequest('get_contacts', [$address_id]);

        if (isset($result['error_id'])) {
            $this->setError($result['error_id'], $result['error']);
        }

        if ($this->hasErrors()) {
            return $this->getErrors();
        }

        $contacts = $result['contacts'];
        if ($this->cacheEnabled) {
            $this->setCache($cacheKey, $contacts);
        }

        return $this->errorsOrResponse($contacts);
    }
    /**
     * @param array $data
     *
     * @return $this|array
     * @throws \ReflectionException
     */
    private function addContact(array $data)
    {
        if (!isset($data['address_id'])) {
            $this->setError('missing_data', 'address_id not set.');
        } elseif (!is_array($this->getAddress($data['address_id']))) {
            $this->setError('invalid_data', 'Invalid address_id.');
        }

        if (!isset($data['full_name'])) {
            $this->setError('missing_data', 'full_name not set.');
        }

        if (!isset($data['phone']) && !isset($data['cellphone'])) {
            $this->setError('missing_data', 'Please supply ether a phone or cellphone number...');
        }

        if (!isset($data['email'])) {
            $this->setError('missing_data', 'email not set.');
        }

        $result = $this->sendSoapRequest('add_contact', [$data]);

        $this->removeCache('addresses.' . $this->client_id);

        if (isset($result['error_id'])) {
            $this->setError($result['error_id'], $result['error']);
        }

        if ($this->hasErrors()) {
            return $this->getErrors();
        }

        return $this;
    }
    /**
     * @param $address_id
     *
     * @return array|mixed|null
     * @throws \ReflectionException
     */
    private function getAddress($address_id)
    {
        $cacheKey = 'address.' . $this->client_id . '.' . ($address_id ?: '0');
        $address = $this->getCache($cacheKey);
        if ($address) {
            return $address;
        }

        $result = $this->sendSoapRequest('get_address', [$address_id]);

        if (isset($result['error_id'])) {
            $this->setError($result['error_id'], $result['error']);
        }

        if ($this->hasErrors()) {
            return $this->getErrors();
        }

        list('address' => $address) = $result;
        if ($this->cacheEnabled) {
            $this->setCache($cacheKey, $address);
        }

        return $this->errorsOrResponse($address);
    }
    /**
     * @return array
     * @throws \ReflectionException
     */
    private function getDefaultAddress()
    {
        $default_address_id = $this->getDefaultAddressId();

        return [
            'address'            => $this->getAddress($default_address_id),
            'default_address_id' => $default_address_id,
            'contacts'           => $this->getContacts($default_address_id),
        ];
    }
    /**
     * Returns the clients default address
     *
     * @return int Address ID
     */
    private function getDefaultAddressId()
    {
        return $this->default_address_id;
    }
    /**
     * Validate Collivery
     * Returns the validated data array of all details pertaining to a
     * This process validates the information based on services, time frames and parcel information.
     * Dates and times may be altered during this process based on the collection and delivery towns service parameters.
     * Certain towns are only serviced on specific days and between certain times.
     * This function automatically alters the values.
     * The parcels volumetric calculations are also done at this time.
     * It is important that the data is first validated before a collivery can be added.
     *
     * @param array $data Properties of the new Collivery
     *
     * @return array         The validated data
     * @throws \ReflectionException
     */
    private function validate(array $data)
    {
        $contacts_from = $this->getContacts($data['collivery_from']);
        $contacts_to = $this->getContacts($data['collivery_to']);
        $parcel_types = $this->getParcelTypes();
        $services = $this->getServices();

        if (!isset($data['collivery_from'])) {
            $this->setError('missing_data', 'collivery_from not set.');
        } elseif (!is_array($this->getAddress($data['collivery_from']))) {
            $this->setError('invalid_data', 'Invalid Address ID for: collivery_from.');
        }

        if (!isset($data['contact_from'])) {
            $this->setError('missing_data', 'contact_from not set.');
        } elseif (!isset($contacts_from[$data['contact_from']])) {
            $this->setError('invalid_data', 'Invalid Contact ID for: contact_from.');
        }

        if (!isset($data['collivery_to'])) {
            $this->setError('missing_data', 'collivery_to not set.');
        } elseif (!is_array($this->getAddress($data['collivery_to']))) {
            $this->setError('invalid_data', 'Invalid Address ID for: collivery_to.');
        }

        if (!isset($data['contact_to'])) {
            $this->setError('missing_data', 'contact_to not set.');
        } elseif (!isset($contacts_to[$data['contact_to']])) {
            $this->setError('invalid_data', 'Invalid Contact ID for: contact_to.');
        }

        if (!isset($data['collivery_type'])) {
            $this->setError('missing_data', 'collivery_type not set.');
        } elseif (!isset($parcel_types[$data['collivery_type']])) {
            $this->setError('invalid_data', 'Invalid collivery_type.');
        }

        if (!isset($data['service'])) {
            $this->setError('missing_data', 'service not set.');
        } elseif (!isset($services[$data['service']])) {
            $this->setError('invalid_data', 'Invalid service.');
        }

        if ($this->hasErrors()) {
            return $this->errorsOrResponse();
        }
        $result = $this->sendSoapRequest('validate_collivery', [$data]);

        if (isset($result['error_id'])) {
            $this->setError($result['error_id'], $result['error']);
        }

        if (!$result) {
            $this->setError('result_unexpected', 'No result returned.');
        }

        return $this->errorsOrResponse($result);
    }
    /**
     * @return array|null
     */
    private function getParcelTypes()
    {
        $cacheKey = 'parcel_types';
        $parcelType = $this->getCache($cacheKey);
        if ($this->cacheEnabled && $parcelType) {
            return $parcelType;
        }

        $result = $this->sendSoapRequest('get_parcel_types');

        if (isset($result['error_id'])) {
            $this->setError($result['error_id'], $result['error']);
        }

        if (is_array($result) && $this->cacheEnabled) {
            $this->setCache($cacheKey, $result);
        }

        return $this->errorsOrResponse($result);
    }
    /**
     * @return array
     * @throws \ReflectionException
     */
    private function getServices()
    {
        $cacheKey = 'services';
        $services = $this->getCache($cacheKey);

        if ($services) {
            return $this->errorsOrResponse($services);
        }

        $result = $this->sendSoapRequest('get_services');
        if (!isset($result['services'])) {
            $this->setError('No data return', 'Could not get services');

            return $this->errorsOrResponse();
        }

        list('services' => $services) = $result;
        if ($this->cacheEnabled) {
            $this->setCache($cacheKey, $services);
        }

        return $this->errorsOrResponse($services);
    }
    /**
     * @param array $data
     *
     * @return array
     */
    private function getPrice(array $data = [])
    {
        if ($this->validateGetPriceData($data)) {
            return $this->sendSoapRequest('get_price', [$data]);
        }

        return $this->getErrors();
    }
    /**
     * @param array $data
     *
     * @return bool
     */
    private function validateGetPriceData(array $data = [])
    {
        if (!isset($data['collivery_from']) && !isset($data['from_town_id'], $data['from_location_type'])) {
            $this->setError('missing_data', 'Please set collection address');
        }
        if (!isset($data['collivery_to']) && !isset($data['to_town_id'], $data['to_location_type'])) {
            $this->setError('missing_data', 'Please set delivery address');
        }
        if (!isset($data['collivery_type'])) {
            $this->setError('missing_data', 'Please set the collivery type');
        }
        if (!isset($data['rica'])) {
            $this->setError('missing_data', 'Please set rica');
        }
        if (!isset($data['parcels'])) {
            $this->setError('missing_data', 'Parcel data is required to get a price');
        }
        if (!isset($data['service'])) {
            $this->setError('missing_data', 'Service not set.');
        }

        return empty($this->errors);
    }
    /**
     * @param array $data
     *
     * @return array|mixed
     * @throws \ReflectionException
     */
    private function addCollivery(array $data)
    {
        $contacts_from = $this->getContacts($data['collivery_from']);
        $contacts_to = $this->getContacts($data['collivery_to']);
        $parcel_types = $this->getParcelTypes();
        $services = $this->getServices();
        $errors = [];

        if (!isset($data['collivery_from'])) {
            $errors['missing_data'] = 'collivery_from not set.';
        } elseif (!is_array($this->getAddress($data['collivery_from']))) {
            $errors['invalid_data'] = 'Invalid Address ID for: collivery_from.';
        }

        if (!isset($data['contact_from'])) {
            $errors['missing_data'] = 'contact_from not set.';
        } elseif (!isset($contacts_from[$data['contact_from']])) {
            $errors['invalid_data'] = 'Invalid Contact ID for: contact_from.';
        }

        if (!isset($data['collivery_to'])) {
            $errors['missing_data'] = 'collivery_to not set.';
        } elseif (!is_array($this->getAddress($data['collivery_to']))) {
            $errors['invalid_data'] = 'Invalid Address ID for: collivery_to.';
        }

        if (!isset($data['contact_to'])) {
            $errors['missing_data'] = 'contact_to not set.';
        } elseif (!isset($contacts_to[$data['contact_to']])) {
            $errors['invalid_data'] = 'Invalid Contact ID for: contact_to.';
        }

        if (!isset($data['collivery_type'])) {
            $errors['missing_data'] = 'collivery_type not set.';
        } elseif (!isset($parcel_types[$data['collivery_type']])) {
            $errors['invalid_data'] = 'Invalid collivery_type.';
        }

        if (!isset($data['service'])) {
            $errors['missing_data'] = 'service not set.';
        } elseif (!isset($services[$data['service']])) {
            $errors['invalid_data'] = 'Invalid service.';
        }
        if ($errors) {
            return $this->setError($errors);
        }

        $result = $this->sendSoapRequest('add_collivery', [$data]);

        if (isset($result['error_id'])) {
            return $this->setError($result['error_id'], $result['error']);
        }

        return $result['collivery_id'];
    }
    /**
     * @param $colliveryId
     *
     * @return array
     */
    private function acceptCollivery($colliveryId)
    {
        $result = $this->sendSoapRequest('accept_collivery', [$colliveryId]);

        if (isset($result['error_id'])) {
            $this->setError($result['error_id'], $result['error']);
        }

        if (isset($result['error_id'])) {
            $this->setError($result['error_id'], $result['error']);
        } else {
            $this->setError('result_unexpected', 'No result returned.');
        }

        if ($this->hasErrors()) {
            return $this->getErrors();
        }

        return ['status' => $result['result']];
    }
    /**
     * @param $waybillId
     *
     * @return array
     */
    private function getColliveryStatus($waybillId)
    {
        return $this->sendSoapRequest('get_collivery_status', [$waybillId]);
    }
}

/**
 * Class Cache
 *
 * @package Mds
 */
class Cache
{
    private $cache_dir;
    private $cache;

    public function __construct($cache_dir = null)
    {
        $this->cache_dir = $cache_dir;
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function has($name)
    {
        $cache = $this->load($name);

        return is_array($cache) && ($cache['valid'] - 30) > time();
    }

    /**
     * @param $name
     *
     * @return mixed
     */
    protected function load($name)
    {
        if (!isset($this->cache[$name])) {
            if (file_exists($this->cache_dir . $name) && $content = file_get_contents($this->cache_dir . $name)) {
                $this->cache[$name] = json_decode($content, true);

                return $this->cache[$name];
            }

            $this->create_dir($this->cache_dir);
        } else {
            return $this->cache[$name];
        }
    }

    protected function create_dir($dir_array)
    {
        if (!is_array($dir_array)) {
            $dir_array = explode('/', $this->cache_dir);
        }
        array_pop($dir_array);
        $dir = implode('/', $dir_array);

        if ($dir !== '' && !is_dir($dir)) {
            $this->create_dir($dir_array);
            if (!mkdir($dir) && !is_dir($dir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $dir));
            }
        }
    }

    /**
     * @param $name
     *
     * @return mixed|null
     */
    public function get($name)
    {
        $cache = $this->load($name);
        if (is_array($cache) && $cache['valid'] > time()) {
            return $cache['value'];
        }

        return null;
    }

    /**
     * @param     $name
     * @param     $value
     * @param int $time
     *
     * @return bool
     */

    public function put($name, $value, $time = 1440)
    {
        $cache = json_encode(['value' => $value, 'valid' => time() + ($time * 60)]);
        if (file_put_contents($this->cache_dir . $name, $cache)) {
            $this->cache[$name] = $cache;

            return true;
        }

        return false;
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function forget($name)
    {
        $cache = json_encode(['value' => '', 'valid' => 0]);
        if (file_put_contents($this->cache_dir . $name, $cache)) {
            $this->cache[$name] = $cache;

            return true;
        }

        return false;
    }
}

/**
 * Class Log
 *
 * @package Mds
 */
class Log
{
    private $logPath;

    public function __construct()
    {
        $this->logPath = basename(__DIR__);
    }

    /**
     * @param $message
     */
    public function message($message)
    {
        chmod($this->logPath, 0777);
        file_put_contents($this->logPath . '/collivery.net_error_logs' . date('Ymd') . '.log', "{$message}\n",
            FILE_APPEND);
    }

}


