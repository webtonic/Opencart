<?php

class ControllerExtensionShippingFree extends Controller {

    private $customFields = array(
        'town' => "Town",
        'suburb' => "Suburb",
        'location_type' => "Location Type",
    );

    public function __construct($registry)
    {
        parent::__construct($registry);

        $this->load->library('Collivery');
    }

    public function addData(&$route, &$data, &$output)
    {
        foreach ($output as $index => &$item) {
            $field =  strtolower(preg_replace('/\s+/', '_', trim($v =$output[$index]['name'])));
            if (isset($field, $this->customFields)) {
                $this->fillCustomFieldValue($item, $field);
            }
        }

        return $output;

    }

    private function fillCustomFieldValue(&$customField, $field)
    {
        $data = [];
        switch ($field) {
            case 'town':
                 $data = $this->Collivery->towns();
                break;
            case 'suburb':
                 $data = $this->Collivery->suburbs();
                break;
            default:
                 $data = $this->Collivery->locationTypes();
                break;
        }

        foreach ($data as $id => $name) {
            $customField['custom_field_value'][] = ['custom_field_value_id' => $id, 'name' => $name];
        }
    }

}