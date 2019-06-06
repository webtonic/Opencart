<?php namespace Mds;

use SoapClient; // Use PHP Soap Client
use SoapFault;  // Use PHP Soap Fault
use Cache;

class Collivery
{
    protected $token;
    protected $client;
    protected $config;
    protected $errors = [];
    protected $check_cache = true;
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
        include_once 'Cache.php';
        if ($cache === null) {
            $cache_dir = array_key_exists('cache_dir', $config) ? $config['cache_dir'] : null;
            $this->cache = new \Mds\Cache($cache_dir);
        } else {
            $this->cache = $cache;
        }

        foreach ($config as $key => $value) {
            if ($key === 'log') {
                $this->log = $value;
                continue;
            }

            if ($key && $value) {
                $this->{$key} = $value;
            }
        }

        if ($this->demo) {
            $this->user_email = 'api@collivery.co.za';
            $this->user_password = 'api123';
        }
    }

    /**
     * @param $method
     * @param $args
     *
     * @return array|mixed
     */
    public function __call($method, $args)
    {
        $this->clearErrors();

        if (!$this->client) {
            $this->init();
        }

        if (!$this->token) {
            $this->authenticate();
        }

        if ($this->errors) {
            return $this->errors;
        }

        return call_user_func_array([$this, $method], $args);
    }

    /**
     * @return $this
     */
    private function clearErrors()
    {
        $this->errors = [];

        return $this;
    }

    /**
     * @return Collivery
     */
    protected function init()
    {
        if (!$this->client) {
            try {
                $this->client = new SoapClient( // Setup the soap client
                    'http://ops.collivery.local/webservice.php?wsdl', // URL to WSDL File
                    ['cache_wsdl' => WSDL_CACHE_NONE] // Don't cache the WSDL file
                );
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);
            }
        }

        return $this;
    }

    /**
     * @param SoapFault $e
     *
     * @return array
     */
    protected function catchSoapFault(SoapFault $e)
    {
        return $this->setError($e->faultcode, $e->faultstring . ' -> ' . $e->getMessage());
    }

    /**
     * @param $id
     * @param $text
     *
     * @return array
     */
    protected function setError($id, $text = '')
    {
        if(is_array($id) && !empty($id)){
            foreach ($id as $key => $val){
                if(!next($id)){
                    return $this->setError($key, $val);
                }
                $this->setError($key, $val);
            }
        }
        $this->errors[$id] = $text;
        $this->log("{id : $id, message : $text}");

        return $this->errors;
    }

    /**
     * @param string $message
     */
    private function log($message = '')
    {
        if (property_exists($this, 'log')) {
            $this->log->write('Collivery Error : ' . $message);
        }
    }

    /**
     * @return bool
     */
    private function authenticate()
    {
        $cacheKey = 'collivery.auth';
        $auth = $this->getCache($cacheKey);
        if($this->check_cache && $auth && $auth['user_email'] === strtolower($this->user_email)){
            $this->default_address_id = $auth['default_address_id'];
            $this->client_id = $auth['client_id'];
            $this->user_id = $auth['user_id'];
            $this->token = $auth['token'];
            return true;
        }


        if (!$this->init()) {
            return false;
        }

        $user_email = strtolower($this->user_email);
        $user_password = $this->user_password;
        $config = array(
            'name'    => $this->app_name . ' mds/collivery/class',
            'version' => $this->app_version,
            'host'    => $this->app_host,
            'url'     => $this->app_url,
            'lang'    => 'PHP ' . PHP_VERSION,
        );
        $authenticate = $this->sendSoapRequest('authenticate', array($user_email, $user_password, $this->token, $config));
        if (is_array($authenticate) && isset($authenticate['token'])) {
            if ($this->check_cache) {
                $this->setCache($cacheKey, $authenticate, 50);
            }

            $this->default_address_id = $authenticate['default_address_id'];
            $this->client_id = $authenticate['client_id'];
            $this->user_id = $authenticate['user_id'];
            $this->token = $authenticate['token'];

            return true;
        }

        return false;
    }


    /**
     * Checks if the Soap Client has been set, and returns it.
     *
     * @return  SoapClient  Webserver Soap Client
     */
    protected function client()
    {
        if (!$this->client) {
            $this->init();
        }

        if (!$this->token) {
            $this->authenticate();
        }

        return $this->client;
    }

    /**
     * Returns all the suburbs of a town.
     *
     * @param int $town_id ID of the Town to return suburbs for
     *
     * @return array|bool
     */
    private function getAllSuburbs($town_id = null)
    {

        if ($this->check_cache && $this->cache->has('collivery.suburbs.' . $town_id)) {
            return $this->cache->get('collivery.suburbs.' . $town_id);
        }

        try {
            $result = $this->client()->get_all_suburbs($town_id, $this->token);
        } catch (SoapFault $e) {
            $this->catchSoapFault($e);

            return false;
        }

        if (isset($result['suburbs'])) {
            if ($this->check_cache) {
                $this->cache->put('collivery.suburbs.' . $town_id, $result['suburbs'], 10080);
            }

            return $result['suburbs'];
        }

        if (isset($result['error_id'])) {
            $this->setError($result['error_id'], $result['error']);
        } else {
            $this->setError('result_unexpected', 'No result returned.');
        }

        return false;
    }

    /**
     * Returns all the addresses belonging to a client.
     *
     * @param array $filter Filter Addresses
     *
     * @return array|bool
     */
    private function getAddresses(array $filter = [])
    {
        $cacheKey = 'collivery.addresses.' . $this->client_id;
        $addresses = $this->getCache($cacheKey);
        if($addresses){
            return $addresses;
        }



        try {
            $result = $this->client()->get_addresses($this->token, $filter);
        } catch (SoapFault $e) {
            $this->catchSoapFault($e);

            return false;
        }

        if (isset($result['addresses'])) {
            if (empty($filter)) {
                $this->cache->put('collivery.addresses.' . $this->client_id, $result['addresses'], 60 * 24);
            }

            return $result['addresses'];
        }

        if (isset($result['error_id'])) {
            $this->setError($result['error_id'], $result['error']);
        } else {
            $this->setError('result_unexpected', 'No address_id returned.');
        }

        return false;
    }

    /**
     * Returns the POD image for a given Waybill Number.
     *
     * @param int $collivery_id Collivery waybill number
     *
     * @return array|bool
     */
    private function getPod($collivery_id)
    {
        if ($this->check_cache && $this->cache->has('collivery.pod.' . $this->client_id . '.' . $collivery_id)) {
            return $this->cache->get('collivery.pod.' . $this->client_id . '.' . $collivery_id);
        }

        try {
            $result = $this->client()->get_pod($collivery_id, $this->token);
        } catch (SoapFault $e) {
            $this->catchSoapFault($e);

            return false;
        }

        if (isset($result['pod'])) {
            if (isset($result['error_id'])) {
                $this->setError($result['error_id'], $result['error']);
            } elseif ($this->check_cache) {
                $this->cache->put(
                    'collivery.pod.' . $this->client_id . '.' . $collivery_id,
                    $result['pod'],
                    1440
                );
            }

            return $result['pod'];
        }

        if (isset($result['error_id'])) {
            $this->setError($result['error_id'], $result['error']);
        } else {
            $this->setError('result_unexpected', 'No result returned.');
        }

        return false;
    }

    /**
     * Returns a list of avaibale parcel images for a given Waybill Number.
     *
     * @param int $collivery_id Collivery waybill number
     *
     * @return array|bool
     */
    private function getParcelImageList($collivery_id)
    {
        if ($this->check_cache && $this->cache->has(
                'collivery.parcel_image_list.' . $this->client_id . '.' . $collivery_id
            )
        ) {
            return $this->cache->get('collivery.parcel_image_list.' . $this->client_id . '.' . $collivery_id);
        }

        try {
            $result = $this->client()->get_parcel_image_list($collivery_id, $this->token);
        } catch (SoapFault $e) {
            $this->catchSoapFault($e);

            return false;
        }

        if (isset($result['images'])) {
            if (isset($result['error_id'])) {
                $this->setError($result['error_id'], $result['error']);
            } elseif ($this->check_cache) {
                $this->cache->put(
                    'collivery.parcel_image_list.' . $this->client_id . '.' . $collivery_id,
                    $result['images'],
                    60 * 12
                );
            }

            return $result['images'];
        }

        if (isset($result['error_id'])) {
            $this->setError($result['error_id'], $result['error']);
        } else {
            $this->setError('result_unexpected', 'No result returned.');
        }

        return false;
    }

    /**
     * Returns the image of a given parcel-id of a waybill.
     * If the Waybill number is 54321 and there are 3 parcels, they would
     * be referenced by id's 54321-1, 54321-2 and 54321-3.
     *
     * @param string $parcel_id Parcel ID
     *
     * @return bool|array               Array containing all the information
     *                             about the image including the image
     *                             itself in base64
     */
    private function getParcelImage($parcel_id)
    {
        if ($this->check_cache && $this->cache->has(
                'collivery.parcel_image.' . $this->client_id . '.' . $parcel_id
            )
        ) {
            return $this->cache->get('collivery.parcel_image.' . $this->client_id . '.' . $parcel_id);
        }

        try {
            $result = $this->client()->get_parcel_image($parcel_id, $this->token);
        } catch (SoapFault $e) {
            $this->catchSoapFault($e);

            return false;
        }

        if (isset($result['image'])) {
            if (isset($result['error_id'])) {
                $this->setError($result['error_id'], $result['error']);
            } elseif ($this->check_cache) {
                $this->cache->put(
                    'collivery.parcel_image.' . $this->client_id . '.' . $parcel_id,
                    $result['image'],
                    1440
                );
            }

            return $result['image'];
        }

        if (isset($result['error_id'])) {
            $this->setError($result['error_id'], $result['error']);
        } else {
            $this->setError('result_unexpected', 'No result returned.');
        }

        return false;
    }

    /**
     * Returns the status tracking detail of a given Waybill number.
     * If the collivery is still active, the estimated time of delivery
     * will be provided. If delivered, the time and receivers name (if availble)
     * with returned.
     *
     * @param int $collivery_id Collivery ID
     *
     * @return bool|array                 Collivery Status Information
     */
    private function getStatus($collivery_id)
    {
        if ($this->check_cache && $this->cache->has('collivery.status.' . $this->client_id . '.' . $collivery_id)) {
            return $this->cache->get('collivery.status.' . $this->client_id . '.' . $collivery_id);
        }

        try {
            $result = $this->client()->get_collivery_status($collivery_id, $this->token);
        } catch (SoapFault $e) {
            $this->catchSoapFault($e);

            return false;
        }

        if (isset($result['status_id'])) {
            if (isset($result['error_id'])) {
                $this->setError($result['error_id'], $result['error']);
            } elseif ($this->check_cache) {
                $this->cache->put(
                    'collivery.status.' . $this->client_id . '.' . $collivery_id,
                    $result,
                    60 * 12
                );
            }

            return $result;
        }

        if (isset($result['error_id'])) {
            $this->setError($result['error_id'], $result['error']);
        } else {
            $this->setError('result_unexpected', 'No result returned.');
        }

        return false;
    }

    /**
     * @param array $data
     *
     * @return array
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
            try {
                $result = $this->client()->add_address($data, $this->token);
                $this->cache->forget('collivery.addresses.' . $this->client_id);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);

                return ['soap_exception' => $e->getMessage(), 'errors' => $this->errors];
            }

            if (isset($result['address_id'])) {
                return $result;
            }

            if (isset($result['error_id'])) {
                $this->setError($result['error_id'], $result['error']);
            } else {
                $this->setError('result_unexpected', 'No address_id returned.');
            }

            return $this->errors;
        }
    }

    /**
     * Returns the type of Address Locations.
     * Certain location type incur a surcharge due to time spent during
     * delivery.
     *
     * @return array
     */
    private function getLocationTypes()
    {
        if ($this->check_cache && $this->cache->has('collivery.location_types')) {
            return $this->cache->get('collivery.location_types');
        }

        try {
            $result = $this->client()->get_location_types($this->token);
        } catch (SoapFault $e) {
            $this->catchSoapFault($e);

            return false;
        }

        if (isset($result['results'])) {
            if ($this->check_cache) {
                $this->cache->put('collivery.location_types', $result['results'], 10080);
            }

            return $result['results'];
        } else {
            if (isset($result['error_id'])) {
                $this->setError($result['error_id'], $result['error']);
            } else {
                $this->setError('result_unexpected', 'No results returned.');
            }

            return false;
        }
    }

    /**
     * Returns a list of towns and their ID's for creating new addresses.
     * Town can be filtered by country of province (ZAF Only).
     *
     * @param string $country  Filter towns by Country
     * @param string $province Filter towns by South African Provinces
     *
     * @return array            List of towns and their ID's
     */
    private function getTowns($country = "ZAF", $province = null)
    {
        if (($this->check_cache) && is_null($province) && $this->cache->has('collivery.towns.' . $country)) {
            return $this->cache->get('collivery.towns.' . $country);
        } elseif (($this->check_cache) && !is_null($province) && $this->cache->has(
                'collivery.towns.' . $country . '.' . $province
            )
        ) {
            return $this->cache->get('collivery.towns.' . $country . '.' . $province);
        } else {
            try {
                $result = $this->client()->get_towns($this->token, $country, $province);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);

                return false;
            }

            if (isset($result['towns'])) {
                if (is_null($province)) {
                    if ($this->check_cache) {
                        $this->cache->put('collivery.towns.' . $country, $result['towns'], 60 * 24);
                    }
                } else {
                    if ($this->check_cache) {
                        $this->cache->put('collivery.towns.' . $country . '.' . $province, $result['towns'], 60 * 24);
                    }
                }

                return $result['towns'];
            } else {
                if (isset($result['error_id'])) {
                    $this->setError($result['error_id'], $result['error']);
                } else {
                    $this->setError('result_unexpected', 'No result returned.');
                }

                return false;
            }
        }
    }

    /**
     * Returns all the suburbs of a town.
     *
     * @param int $town_id ID of the Town to return suburbs for
     *
     * @return array
     */
    private function getSuburbs($town_id)
    {
        //api compatibility
        if (($this->check_cache) && $this->cache->has('collivery.suburbs.' . $town_id)) {
            return $this->cache->get('collivery.suburbs.' . $town_id);
        } else {
            try {
                $result = $this->client()->get_suburbs($town_id, $this->token);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);

                return false;
            }

            if (isset($result['suburbs'])) {
                if ($this->check_cache) {
                    $this->cache->put('collivery.suburbs.' . $town_id, $result['suburbs'], 10080);
                }

                return $result['suburbs'];
            } else {
                if (isset($result['error_id'])) {
                    $this->setError($result['error_id'], $result['error']);
                } else {
                    $this->setError('result_unexpected', 'No result returned.');
                }

                return false;
            }
        }
    }

    /**
     * @return bool
     */
    private function hasErrors()
    {
        return !empty($this->errors);
    }

    /**
     * Add's a contact person for a given Address ID
     *
     * @param array $data New Contact Data
     *
     * @return int           New Contact ID
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

        if (!isset($data['phone']) and !isset($data['cellphone'])) {
            $this->setError('missing_data', 'Please supply ether a phone or cellphone number...');
        }

        if (!isset($data['email'])) {
            $this->setError('missing_data', 'email not set.');
        }

        if (!$this->hasErrors()) {
            try {
                $result = $this->client()->add_contact($data, $this->token);
                $this->cache->forget('collivery.addresses.' . $this->client_id);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);

                return false;
            }

            if (isset($result['contact_id'])) {
                return $result;
            } else {
                if (isset($result['error_id'])) {
                    $this->setError($result['error_id'], $result['error']);
                } else {
                    $this->setError('result_unexpected', 'No contact_id returned.');
                }

                return false;
            }
        }
    }

    /**
     * Returns the available Parcel Type ID and value array for use in adding a collivery.
     *
     * @param int $address_id The ID of the address you wish to retrieve.
     *
     * @return array               Address
     */
    private function getAddress($address_id)
    {
        $cacheKey = 'collivery.address.' . $this->client_id . '.' . $address_id;
        $address = $this->getCache($cacheKey);
        if($address){
            return $address;
        }


        try {
            $result = $this->client()->get_address($address_id, $this->token);

            if (isset($result['address'])) {
                list('address' => $address) = $result['address'];
                if ($this->check_cache) {
                    $this->setCache($cacheKey, $address);
                }

                return $address;
            }

            if (isset($result['error_id'])) {
                $this->setError($result['error_id'], $result['error']);
            } else {
                $this->setError('result_unexpected', 'No address_id returned.');
            }

            return false;
        } catch (SoapFault $e) {
            $this->catchSoapFault($e);

            return false;
        }


    }

    /**
     * @return array
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
        if (!$this->default_address_id) {
            $this->authenticate();
        }

        return $this->default_address_id;
    }

    /**
     * Returns the Contact people of a given Address ID.
     *
     * @param int $address_id Address ID
     *
     * @return array
     */
    private function getContacts($address_id)
    {
        if (($this->check_cache) && $this->cache->has(
                'collivery.contacts.' . $this->client_id . '.' . $address_id
            )
        ) {
            return $this->cache->get('collivery.contacts.' . $this->client_id . '.' . $address_id);
        } else {
            try {
                $result = $this->client()->get_contacts($address_id, $this->token);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);

                return false;
            }

            if (isset($result['contacts'])) {
                if ($this->check_cache) {
                    $this->cache->put(
                        'collivery.contacts.' . $this->client_id . '.' . $address_id,
                        $result['contacts'],
                        60 * 24
                    );
                }

                return $result['contacts'];
            } else {
                if (isset($result['error_id'])) {
                    $this->setError($result['error_id'], $result['error']);
                } else {
                    $this->setError('result_unexpected', 'No result returned.');
                }

                return false;
            }
        }
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

        return !empty($this->errors);
    }

    /**
     * Validate Collivery
     * Returns the validated data array of all details pertaining to a collivery.
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

        if (!$this->hasErrors()) {
            try {
                $result = $this->client()->validate_collivery($data, $this->token);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);

                return false;
            }

            if (is_array($result)) {
                if (isset($result['error_id'])) {
                    $this->setError($result['error_id'], $result['error']);
                }

                return $result;
            } else {
                if (isset($result['error_id'])) {
                    $this->setError($result['error_id'], $result['error']);
                } else {
                    $this->setError('result_unexpected', 'No result returned.');
                }

                return false;
            }
        }
    }

    /**
     * Returns the available Parcel Type ID and value array for use in adding a collivery.
     *
     * @return array  Parcel  Types
     */
    private function getParcelTypes()
    {
        if (($this->check_cache) && $this->cache->has('collivery.parcel_types')) {
            return $this->cache->get('collivery.parcel_types');
        } else {
            try {
                $result = $this->client()->get_parcel_types($this->token);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);

                return false;
            }

            if (is_array($result)) {
                if ($this->check_cache) {
                    $this->cache->put('collivery.parcel_types', $result, 10080);
                }

                return $result;
            } else {
                if (isset($result['error_id'])) {
                    $this->setError($result['error_id'], $result['error']);
                } else {
                    $this->setError('result_unexpected', 'No results returned.');
                }

                return false;
            }
        }
    }

    /**
     * @param $name
     *
     * @return null
     */
    private function getCache($name)
    {
        if ($this->cache->has($name)) {
            return $this->cache->get($name);
        }

        return null;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    private function getPrice(array $data = [])
    {
        return $this->sendSoapRequest('get_price', array($data));
    }

    /**
     * @param       $method
     * @param array $params
     *
     * @return array
     */
    private function sendSoapRequest($method, array $params = array()){
        $params[] = $this->token;
        try{
            return  $this->client()->{$method}(...$params);
        }catch (SoapFault $e){
            return $this->catchSoapFault($e);
        }
    }

    /**
     * @param     $key
     * @param     $value
     * @param int $ttl
     *
     * @return mixed
     */
    private function setCache($key, $value, $ttl = 10080)
    {
        return $this->cache->put($key, $value, $ttl);
    }

    /**
     * @return null
     */
    private function getServices()
    {
        $services = $this->getCache('collivery.services');
        if ($services) {
            return $services;
        }

        $result = $this->sendSoapRequest('get_services');
        if (isset($result['services'])) {
            list('services' => $services) = $result['services'];
            if ($this->check_cache) {
                $this->setCache('collivery.services', $services);
            }

            return $services;
        }
        return $result;
    }


    private function addCollivery(array $data)
    {
        $contacts_from = $this->getContacts($data['collivery_from']);
        $contacts_to = $this->getContacts($data['collivery_to']);
        $parcel_types = $this->getParcelTypes();
        $services = $this->getServices();
        $errors = array();

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
           $errors['invalid_data']  = 'Invalid collivery_type.';
        }

        if (!isset($data['service'])) {
           $errors['missing_data'] = 'service not set.';
        } elseif (!isset($services[$data['service']])) {
           $errors['invalid_data'] = 'Invalid service.';
        }
        if($errors){
            return $this->setError($errors);
        }



        if (!$this->hasErrors()) {
            try {
                $result = $this->client()->add_collivery($data, $this->token);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);

                return false;
            }

            if (isset($result['collivery_id'])) {
                if (isset($result['error_id'])) {
                    $this->setError($result['error_id'], $result['error']);
                }

                return $result['collivery_id'];
            }

            if (isset($result['error_id'])) {
                $this->setError($result['error_id'], $result['error']);
            } else {
                $this->setError('result_unexpected', 'No result returned.');
            }
        }

        return false;
    }

    /**
     * Accepts the newly created Collivery, moving it from Waiting Client Acceptance
     * to Accepted so that it can be processed.
     *
     * @param int $collivery_id ID of the Collivery you wish to accept
     *
     * @return boolean                 Has the Collivery been accepted
     */
    private function acceptCollivery($collivery_id)
    {
        try {
            $result = $this->client()->accept_collivery($collivery_id, $this->token);
        } catch (SoapFault $e) {
            $this->catchSoapFault($e);

            return false;
        }

        if (isset($result['result'])) {
            if (isset($result['error_id'])) {
                $this->setError($result['error_id'], $result['error']);
            }

            return strtolower($result['result']) === 'accepted';
        }

        if (isset($result['error_id'])) {
            $this->setError($result['error_id'], $result['error']);
        } else {
            $this->setError('result_unexpected', 'No result returned.');
        }

        return false;
    }

    /**
     * @return array
     */
    private function getErrors()
    {
        return $this->errors;
    }

    /**
     * Disable Cached completely and retrieve data directly from the webservice
     */
    private function disableCache()
    {
        $this->check_cache = false;
    }

    /**
     * Ignore Cached data and retrieve data directly from the webservice
     * Save returned data to Cache
     */
    private function ignoreCache()
    {
        $this->check_cache = 1;
    }

    /**
     * Check if cache exists before querying the webservice
     * If webservice was queried, save returned data to Cache
     */
    private function enableCache()
    {
        $this->check_cache = 2;
    }

    /**
     * @param $waybillId
     *
     * @return bool
     */
    private function getColliveryStatus($waybillId)
    {
        $this->errors = [];

        try {
            return $this->client()->get_collivery_status($waybillId, $this->token);
        } catch (SoapFault $e) {
            $this->catchSoapFault($e);
        }

        return false;
    }

}

