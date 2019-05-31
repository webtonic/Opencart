<?php
use Mds\Collivery;
use Mds\MdsAddress\MdsAddress;
use Mds\MdsColliveryService;
/**
 * Class ModelExtensionShippingMds
 */
class ModelExtensionShippingMds extends Model
{
    /**
     * @var MdsAddress
     */
    /**
     * ModelExtensionShippingMds constructor.
     *
     * @param $registry
     */
    public function __construct($registry)
    {
        parent::__construct($registry);

        $this->load->model('setting/setting');
        $this->load->language('shipping/mds');
        $data['mdsErrors'] = array();
    }
    /**
     * @param $address
     *
     * @return array|bool
     */
    function getQuote($address)
    {
        if(isset($address['address_id']) && $address['address_id']){
            $address = $this->db->query("select * from " . DB_PREFIX . "address where address_id={$address['address_id']}")->row;
        }
        $parcel                   = $this->cart->getProducts();
        $data['parcels']          = $this->cart->getProducts();
        $data['cover']            = $this->config->get('shipping_mds_insurance') ? 1 : 0;
        $data['rica']             = 0;
        $quote_data = array();
        foreach ($this->collivery->getServices() as $key => $service) {
            $data = $this->buildColliveryControlData( $address, $parcel);
            $data['service'] = $key;
            $add_days        = '';
            if ($key == 5) {
                $add_days = '+1 days'; //add 1 day
            } elseif ($key == 3) {
                $add_days = '+2 days'; //add 2 day
            }
            $data['collection_time']   = strtotime(date('d-m-Y 8:00', strtotime($add_days)));
            $service_display_name      = $this->config->get('shipping_mds_service_display_name_' . $key);
            $service_markup_percentage = $this->config->get('shipping_mds_service_surcharge_' . $key);



            if ($this->config->get('shipping_mds_insurance')) {
                $data['cover'] = 1;
            } else {
                $data['cover'] = 0;
            }

            if ($this->config->get('shipping_mds_rica')) {
                $data['rica'] = 1;
            } else {
                $data['rica'] = 0;
            }
            $price                     = $this->getShippingCost($data);
            $price_including_vat       = (int) $price['price']['inc_vat'];


            switch (true) {
                case ($service_markup_percentage >= 0 && $service_markup_percentage <= 1):
                    break;
                case ($service_markup_percentage >= 1 && $service_markup_percentage <= 100):
                    $service_markup_percentage = (float) ($service_markup_percentage / 100);
                    break;
                default:
                    $service_markup_percentage = false;
                    break;
            }
            /**
             * Display Price Formula: A = P(1+m)
             * A = $display_price
             * P = total price including VAT except client markup fee
             * m = Markup percentage
             */
            $display_price    = $service_markup_percentage ? round($price_including_vat * (1 + $service_markup_percentage), 2) : $price_including_vat;
            $quote_data[$key] = array(
                'code' => 'mds.' . $key,
                'title' => $service_display_name,
                'cost' => $display_price,
                'tax_class_id' => 0,
                'text' => 'R' . $display_price . ' VAT inclusive'
            );
        }

        $error = '';
        if ($quote_data) {
            $method_data = array(
                'code' => 'mds',
                'title' => 'MDS Collivery.net',
                'quote' => $quote_data,
                'sort_order' => 1,
                'error' => $error
            );
            return $method_data;
        } else {
            Echo "Fail";
            return false;
        }
    }
    /**
     * @param $address
     * @param $parcel
     *
     * @return mixed
     */
    public function buildColliveryControlData($address, $parcel)
    {
        $collivery_address_from               = $this->collivery->getDefaultAddress();
        $collivery_params['to_town_id']       = $address['collivery_town'] ;
        $collivery_params['to_location_type'] = $address['collivery_location_type'];
        $collivery_params['contact_to']       = 0;
        $collivery_params['collivery_from']   = $collivery_address_from['address']['address_id'];
        $collivery_params['contact_from']     = current($collivery_address_from['contacts'])['address_id'];
        $collivery_params['collivery_type']   = '2';
        foreach ($parcel as $key => $collivery_product) {
            for ($i = 0; $i < $collivery_product['quantity']; $i++) {
                $collivery_params['parcels'][$key] = array(
                    'weight' => $collivery_product['weight'] / $collivery_product['quantity'],
                    'height' => $collivery_product['height'],
                    'width' => $collivery_product['width'],
                    'length' => $collivery_product['length']
                );
            }
        }
        return $collivery_params;
    }
    /**
     * @param $address
     *
     * @return array
     */
    public function add_control_collivery_address_to($address)
    {
        $addressString                 = '';
        $hash                          = hash('md5', $addressString);
        $hash                          = substr($hash, 0, 15);
        $address['custom_field']       = $hash . " | "; // . $address['address_id'];
        $collivery_params              = $this->setColliveryParams($address);
        $collivery_params['custom_id'] = $address['custom_field'];
        try {
            return $this->collivery->addAddress($collivery_params);
        }
        catch (Exception $e) {
            die($e->getMessage());
        }
    }
    /**
     * @param $address_row
     *
     * @return mixed
     */
    function setColliveryParams($address_row)
    {
        $collivery_params['company_name'] = $address_row['company'];
        $collivery_params['building']     = $address_row['address_2'];
        $collivery_params['street']       = $address_row['address_1'];
        $collivery_params['collivery_to'] = $address_row['collivery_to'];
        $collivery_params['zip_code']     = $address_row['postcode'];
        $collivery_params['full_name']    = $address_row['firstname'] . $address_row['lastname'];
        $collivery_params['phone']        = $this->customer->getTelephone();
        $collivery_params['cellphone']    = $this->customer->getTelephone();
        $collivery_params['email']        = $address_row['email'];
        return $collivery_params;
    }
    public function getShippingCost($order_params)
    {
        return $this->collivery->getPrice($order_params);
    }
}


