<?php

use Mds\MdsAddress\MdsAddress;
use Mds\MdsColliveryService;

class ModelExtensionShippingMds extends Model
{
    // arrange custom fields according to their relevant position
    // on checkout page -> personal details form
    // town will always comes before suburb
    const SORTED_01_TOWN = '8';
    const SORTED_02_SUBURB = '9';
    const SORTED_03_LOCATION_TYPE = '10';

    private $columns = array(
        'order' => array(
            'collivery_from_address_id',
            'collivery_from_contact_id',
            'collivery_to_address_id',
            'collivery_to_contact_id',
            'collivery_town',
            'collivery_suburb',
            'collivery_location_type',
            'collivery_price_data',
            'collivery_service_type_id',
            'waybill_id'
        ),
        'address' => array(
            'collivery_town',
            'collivery_suburb',
            'collivery_location_type',
        )
    );

    private $locationFields = array(
        self::SORTED_01_TOWN => "Town",
        self::SORTED_02_SUBURB => "Suburb",
        self::SORTED_03_LOCATION_TYPE => "Location Type",
    );

    private $relationship = array(
        'custom_field_description',
        'custom_field_customer_group',
        'custom_field_value_description',
        'custom_field_value',
        'custom_field'
    );

    public function addColumns() {
        foreach ($this->columns as $table => $columns) {
            foreach ($columns as $column) {
                if ($this->exists($this->query($this->getCustomQuery($table, $column)))) {
                    continue;
                }
                $this->addColumn($table, $column);
            }
        }
    }

    private function addColumn($table, $column)
    {
        $this->query("ALTER TABLE `".DB_PREFIX."{$table}` ADD `{$column}` VARCHAR(255) NULL DEFAULT NULL;");
    }

    public function dropColumns() {
        foreach ($this->columns as $table => $columns) {
            foreach ($columns as $column) {
                if ($this->exists($this->query($this->getCustomQuery($table, $column)))) {
                    $this->dropColumn($table, $column);
                }
            }
        }
    }

    private function dropColumn($table, $column)
    {
        $this->query("ALTER TABLE `".DB_PREFIX."{$table}` DROP COLUMN `{$column}`;");
    }

    public function addCustomFields()
    {
        foreach ($this->locationFields as $index => $field) {

            if ($this->doesNotExists($this->selectWhere('custom_field_description', 'name',  $field))) {

                $this->insert('custom_field',[
                    'type' => 'select',
                    'location' => 'address',
                    'status' => 1,
                    'sort_order' => $index
                ]);

                $custom_field_id = $this->db->getLastId();

                $this->insert('custom_field_description',[
                    'custom_field_id' => (int)$custom_field_id,
                    'language_id' => 1,
                    'name' => $field
                ]);

                $this->insert('custom_field_customer_group',[
                    'custom_field_id' => (int)$custom_field_id,
                    'customer_group_id' => 1,
                    'required' => 0
                ]);

            }
        }
    }

    public function dropCustomFields()
    {
        foreach ($this->locationFields as $index => $field) {
            $query = $this->selectWhere('custom_field_description', 'name', $field);

            if ($this->exists($query)) {
                $row = $this->first($query);
                $custom_field_id = $row['custom_field_id'];

                foreach ($this->relationship as $table) {
                    if ($this->exists($this->selectWhere($table, 'custom_field_id', $custom_field_id))) {
                        $this->deleteWhere($table, 'custom_field_id', $custom_field_id);
                    }
                }
                $this->deleteWhere('custom_field_description', 'custom_field_id', $custom_field_id);
            }
        }
    }

    private function first($query)
    {
        return $query->row;
    }

    private function deleteWhere($table, $field, $value)
    {
        return $this->action('DELETE',$table, $field, $value);
    }

    private function selectWhere($table, $field, $value)
    {
        return $this->action("SELECT *",$table, $field, $value);
    }

    private function action($type,$table, $field, $value )
    {
        return $this->query($type . " FROM " . DB_PREFIX.$table . " WHERE " . "`$field`" . " = '" . $this->db->escape($value) . "'");
    }

    private function insert($table, $data)
    {
        $this->query("INSERT INTO `" . DB_PREFIX . $table . "` SET " . $this->implode($data));
    }

    private function implode($data)
    {
        return implode(', ', array_map(
                function ($v, $k) { return sprintf("`%s` = '%s' " , $k, $v); },
                $data, array_keys($data))
        );
    }

    private function  exists($query)
    {
        return $this->count($query) > 0;
    }

    private function  doesNotExists($query)
    {
        return (int) $this->count($query) === 0;
    }

    private function count($query)
    {
        return $query->num_rows;
    }

    private function query($query)
    {
        return $this->db->query($query);
    }

    private function getCustomQuery($table, $column)
    {
        return "SELECT *
                FROM `INFORMATION_SCHEMA`.`COLUMNS`
                WHERE `TABLE_NAME` = '".DB_PREFIX."{$table}'
                AND `TABLE_SCHEMA` = '".DB_DATABASE."'
                AND `COLUMN_NAME` = '{$column}'";
    }

}