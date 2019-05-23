<?php namespace Mds\MdsAddress;
use Mds\Collivery;

use Controller;
 /* Class MdsAddress
 *
 * @package Mds
 */
class MdsAddress extends Controller {

//private $db;
public $registry;
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


	public function __construct($settings) {

		include_once ('Collivery.php');


		$this->collivery = new Collivery($settings);

	}

	public function getLocations(){

		$locations = $this->collivery->getLocationTypes();

		return $locations;
	}

	public function getTowns(){
		$towns = $this->collivery->getTowns();
		return $towns;
	}
	public function getSuburbs(){
		$suburbs = $this->collivery->getSuburbs('');
		return $suburbs;
	}
	public function searchSuburbs($townId){
		$suburbs = $this->collivery->getSuburbs($townId);
		return $suburbs;
	}

}
