<?php namespace Mds\MdsOrder;
use Mds\Collivery;
use Mds\MdsColliveryService;

/**
 * Class MdsOrder
 *
 * @package Mds
 */
class MdsOrder {

	/**
	 * @type
	 */
	var $mdsService;
	/**
	 * @type
	 */
	var $collivery;

	/**
	 * @type
	 */
	var $validated_data;

	/**
	 * @type
	 */
	var $settings;

	public function __construct($settings)
	{

		include_once('Collivery.php');
		include_once('MdsService.php');


		$this->collivery = new Collivery($settings);
		$this->mdsService = new MdsColliveryService($settings);

	}


	public function getQuote($collectionAddress, $deliveryAddress, $parcel,$cover,$service)
	{


		$orderParams = $this->buildColliveryControlDataArray($collectionAddress, $deliveryAddress, $parcel);

		$orderParams['cover'] = $cover;

		$orderParams['service'] = $service;

		if ($orderParams['service'] == 5) {

			$orderParams['collection_time'] = time()+(48*60*60);
		}

		if ($orderParams['service'] == 3) {

			$orderParams['collection_time'] = time()+(72*60*60);
		}

		$price = $this->getShippingCost($orderParams);

		return $price;


	}

	/**
	 * @param $params
	 *
	 * @return mixed
	 */
	public function buildColliveryControlDataArray($collectionAddress, $deliveryAddress, $parcel)
	{

		$colliveryAddressTo = $this->addControlColliveryAddress($deliveryAddress);
		$colliveryAddressFrom = $this->addControlColliveryAddress($collectionAddress);

		$colliveryParams['collivery_to'] = $colliveryAddressTo['address_id'];
		$colliveryParams['contact_to'] = $colliveryAddressTo['contact_id'];
		$colliveryParams['collivery_from'] = $colliveryAddressFrom['address_id'];
		$colliveryParams['contact_from'] = $colliveryAddressFrom['contact_id'];
		$colliveryParams['collivery_type'] = '2';

		foreach ($parcel as $key =>$colliveryProduct) {
				$colliveryParams['parcels'][] = array(
					'weight' => $colliveryProduct['weight'],
					'height' => $colliveryProduct['height'],
					'width'  => $colliveryProduct['width'],
					'length' => $colliveryProduct['length']
				);
		}

		return $colliveryParams;
	}



	public function addControlColliveryAddress($address)
	{

		$addressString = $address['address_1'] . $address['town_id'] . $address['suburb_id'] . $address['postcode'] . $address['firstname'] . " " . $address['lastname'];
		$hash = hash('md5', $addressString);
		$hash = substr($hash, 0, 15);
		$address['custom_field'] = $hash . " | " . $address['address_id'];

		$colliveryParams = $this->setColliveryParamsArray($address);

		$colliveryParams['custom_id'] = $address['custom_field'] ;

		try {

			return $this->mdsService->addColliveryAddress($colliveryParams);
		} catch (Exception $e) {

			die($e->getMessage());
		}

	}

	function setColliveryParamsArray($addressRow)
	{
		$colliveryParams['company_name'] = $addressRow['company'];
		$colliveryParams['building'] = $addressRow['address_2'];
		$colliveryParams['street'] = $addressRow['address_1'];
		$colliveryParams['location_type'] = $addressRow['location_type'];
		$colliveryParams['suburb'] = $addressRow['suburb_id'];
		$colliveryParams['town'] = $addressRow['town_id'];
		$colliveryParams['zip_code'] = $addressRow['postcode'];
		$colliveryParams['full_name'] = $addressRow['firstname'] . $addressRow['lastname'];
		$colliveryParams['phone'] = $addressRow['phone'];
		$colliveryParams['cellphone'] = $addressRow['phone'];
		$colliveryParams['email'] = $addressRow['email'];
		$colliveryParams['custom_id'] = $addressRow['custom_id'];

		return $colliveryParams;
	}

	/**
	 * @param $orderParams
	 *
	 * @return mixed
	 */
	public function getShippingCost($orderParams)
	{
		$validate = $this->mdsService->validateCollivery($orderParams);

		return $validate;
	}

}