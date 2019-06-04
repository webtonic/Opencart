<?php

/**
 * Class ControllerExtensionShippingMds
 *
 * @property ModelSettingEvent $model_setting_event
 */
class ControllerExtensionShippingMds extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/shipping/mds');
        $this->load->model('setting/event');
        $this->load->model('setting/setting');
        $this->document->setTitle($this->language->get('heading_title'));
        if (strtoupper($this->request->server['REQUEST_METHOD']) === 'POST') {
            $this->model_setting_setting->editSetting('shipping_mds', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'], 'SSL'));
        }
        $data             = $this->language->all();

        $services         = $this->collivery->getServices();
        $data['services'] = $services;
        foreach ($services as $key => $service) {
            if (isset($this->request->post['shipping_mds_service_display_name_' . $key])) {
                $data['shipping_mds_service_display_name_' . $key] = $this->request->post['shipping_mds_service_display_name_' . $key];
            } else {
                if ($this->config->get('shipping_mds_service_display_name_' . $key) == "") {
                    $data['shipping_mds_service_display_name_' . $key] = $service;
                } else {
                    $data['shipping_mds_service_display_name_' . $key] = $this->config->get('shipping_mds_service_display_name_' . $key);
                }
            }
            if (isset($this->request->post['shipping_mds_service_surcharge_' . $key])) {
                $data['shipping_mds_service_surcharge_' . $key] = $this->request->post['shipping_mds_service_surcharge_' . $key];
            } else {
                $data['shipping_mds_service_surcharge_' . $key] = $this->config->get('shipping_mds_service_surcharge_' . $key);
            }
        }
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }
        if (isset($this->error['key'])) {
            $data['error_key'] = $this->error['key'];
        } else {
            $data['error_key'] = '';
        }
        if (isset($this->error['markup'])) {
            $data['error_markup'] = $this->error['markup'];
        } else {
            $data['error_markup'] = '';
        }
        if (isset($this->error['username'])) {
            $data['error_username'] = $this->error['username'];
        } else {
            $data['error_username'] = '';
        }
        if (isset($this->error['password'])) {
            $data['error_password'] = $this->error['password'];
        } else {
            $data['error_password'] = '';
        }

        $data['mdsErrors'] = '';

        $data['breadcrumbs']   = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], 'SSL')
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_shipping'),
            'href' => $this->url->link('extension/shipping', 'user_token=' . $this->session->data['user_token'], 'SSL')
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('shipping/mds', 'user_token=' . $this->session->data['user_token'], 'SSL')
        );
        $data['action']        = $this->url->link('extension/shipping/mds', 'user_token=' . $this->session->data['user_token'], 'SSL');
        $data['cancel']        = $this->url->link('extension/shipping', 'user_token=' . $this->session->data['user_token'], 'SSL');
        if (isset($this->request->post['shipping_mds_username'])) {
            $data['shipping_mds_username'] = $this->request->post['shipping_mds_username'];
        } else {
            $data['shipping_mds_username'] = $this->config->get('shipping_mds_username');
        }
        if (isset($this->request->post['shipping_mds_password'])) {
            $data['shipping_mds_password'] = $this->request->post['shipping_mds_password'];
        } else {
            $data['shipping_mds_password'] = $this->config->get('shipping_mds_password');
        }
        if (isset($this->request->post['shipping_mds_markup'])) {
            $data['shipping_mds_markup'] = $this->request->post['shipping_mds_markup'];
        } else {
            $data['shipping_mds_markup'] = $this->config->get('shipping_mds_markup');
        }
        if (isset($this->request->post['shipping_mds_test'])) {
            $data['shipping_mds_test'] = $this->request->post['shipping_mds_test'];
        } else {
            $data['shipping_mds_test'] = $this->config->get('shipping_mds_test');
        }
        if (isset($this->request->post['shipping_mds_insurance'])) {
            $data['shipping_mds_insurance'] = $this->request->post['shipping_mds_insurance'];
        } else {
            $data['shipping_mds_insurance'] = $this->config->get('shipping_mds_insurance');
        }
        if (isset($this->request->post['shipping_mds_status'])) {
            $data['shipping_mds_status'] = $this->request->post['shipping_mds_status'];
        } else {
            $data['shipping_mds_status'] = $this->config->get('shipping_mds_status');
        }

        if (isset($this->request->post['shipping_mds_is_demo'])) {
            $data['shipping_mds_is_demo'] = $this->request->post['shipping_mds_is_demo'];
        } else {
            $data['shipping_mds_is_demo'] = $this->config->get('shipping_mds_is_demo');
        }
        if (isset($this->request->post['shipping_mds_tax_class_id'])) {
            $data['shipping_mds_tax_class_id'] = $this->request->post['shipping_mds_tax_class_id'];
        } else {
            $data['shipping_mds_tax_class_id'] = $this->config->get('shipping_mds_tax_class_id');
        }
        $this->load->model('localisation/tax_class');
        $data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();
        if (isset($this->request->post['shipping_mds_geo_zone_id'])) {
            $data['shipping_mds_geo_zone_id'] = $this->request->post['shipping_mds_geo_zone_id'];
        } else {
            $data['shipping_mds_geo_zone_id'] = $this->config->get('shipping_mds_geo_zone_id');
        }
        if (isset($this->request->post['shipping_mds_geo_zone_id'])) {
            $data['shipping_mds_geo_zone_id'] = $this->request->post['shipping_mds_geo_zone_id'];
        } else {
            $data['shipping_mds_geo_zone_id'] = $this->config->get('shipping_mds_geo_zone_id');
        }

        $data['shipping_mds_is_auto_create_waybill'] = $this->config->get('shipping_mds_is_auto_create_waybill');
        $data['shipping_mds_is_auto_create_address'] = $this->config->get('shipping_mds_is_auto_create_address');

        $data['default_collivery_from_addresses'] = array();
        $data['default_address_id'] = $this->collivery->getDefaultAddressId();
        $data['user_token'] = $this->request->get['user_token'];



        $this->load->model('localisation/geo_zone');
        $data['geo_zones']   = $this->model_localisation_geo_zone->getGeoZones();
        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/shipping/mds', $data));
    }
    protected function validate() {
        if (!$this->user->hasPermission('modify', 'shipping/mds')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        if (!$this->request->post['shipping_mds_username']) {
            $this->error['username'] = $this->language->get('error_username');
        }
        if (!$this->request->post['shipping_mds_password']) {
            $this->error['password'] = $this->language->get('error_password');
        }
        if (($this->request->post['shipping_mds_username'] != $this->config->get('shipping_mds_username')) || ($this->request->post['shipping_mds_password'] != $this->config->get('shipping_mds_password'))) {

            $mdsErrors       = $this->collivery->getErrors();
            if ($mdsErrors) {
                $this->error['warning'] = $this->language->get('error_login');
            }
        }
        return !$this->error;
    }

    public function install() {

        $errors = '';
        if (PHP_VERSION_ID <= 50500) {
            $errors .= 'MDS Collivery requires PHP 5.6 in order to run. Please upgrade before installing.' . PHP_EOL;
        }
        if (!extension_loaded('soap')) {
            $errors .= 'MDS Collivery requires SOAP to be enabled on the server. Please make sure its enabled before installing.' . PHP_EOL;
        }

        if($errors){
            $this->log->write($errors);
            $div = '<div class="col-md-12 alert alert-danger">
                       ' . $errors . '
                   </div>';
            die($div);
        }
        $main_controller = DIR_SYSTEM . 'engine/controller.php';
        if(is_file($main_controller)){
            $opencart_controller = file_get_contents($main_controller);
            $pattern = '/\$.*registry\W.*=.*\$registry;/';
            $replacement = "\$this->registry = \$registry;\n\t\t/** @collivery-config */\n\t\t\$collivery_query = \$registry->get('db')->query(\"select u.value as username, p.value as password, d.value as is_demo from \" . DB_PREFIX . \"setting as p left join \" . DB_PREFIX . \"setting as u on(u.code=p.code) left join \" . DB_PREFIX . \"setting as d on(d.code=p.code) where u.code='shipping_mds' and p.code='shipping_mds' and p.code='shipping_mds_is_demo' and u.key='shipping_mds_username' and p.key='shipping_mds_password'\");\n";
            $replacement .= "\t\t\$demo_auth = array('username' => 'api@collivery.co.za', 'password' => 'api123', 'is_demo' => true);";
            $replacement .="\n\t\tif(\$collivery_query->num_rows && !((bool) \$collivery_query->row['is_demo'])){";
            $replacement .="\n\t\t\tlist('username' => \$username, 'password' => \$password, 'is_demo'=> \$is_demo) = \$collivery_query->row;\n";
            $replacement .="\t\t}else{\n\t\t\tlist('username' => \$username, 'password' => \$password, 'is_demo' => \$is_demo) = \$demo_auth;\n\t\t}\n";
            $replacement .="\t\t\$config = array(\n\t\t\t'log' => \$this->log,\n\t\t\t'app_name' => 'Collivery_net/Opencart',\n\t\t\t'app_version' => '1.0.1',\n\t\t\t'app_host' => 'Opencart ' . VERSION,\n\t\t\t'app_url' => \$_SERVER['HTTP_HOST'],\n";
            $replacement .="\t\t\t'user_email' => \$username ,\n\t\t\t'user_password' => \$password,\n\t\t\t'demo' => (bool) \$is_demo\n\t\t);\n";
            $replacement .="\n\t\tforeach (\$config as \$key => \$value){\n\t\t\t\$this->registry->set('collivery_config_' . \$key, \$value);\n\t\t}\n\t\t\$collivery_library = DIR_SYSTEM . 'library/mds/Collivery.php';\n";
            $replacement .="\t\tif(is_file(\$collivery_library)){\n\t\t\tinclude_once \$collivery_library;\n\t\t\t\$this->collivery = new \Mds\Collivery(\$config);";
            $replacement .="\t\t}\n";
            $replacement .="\t/** @endcollivery-config */";
            $existing = '/\/\*\*.@collivery.*\*\/.*@endcollivery.*\*\//s';
            if(preg_match($existing, $opencart_controller) === 1){
                $opencart_controller = preg_replace($existing, '', $opencart_controller);
            }

            $data = preg_replace($pattern, $replacement, $opencart_controller);
            file_put_contents($main_controller, $data);

            $this->model_setting_event->installColliveryShippingPlugin();
        }
        $this->model_setting_event->installColliveryShippingPlugin();

    }

    public function uninstall(){
        $this->model_setting_event->uninstallColliveryShippingPlugin();
        $main_controller = DIR_SYSTEM . 'engine/controller.php';
        if(is_file($main_controller)) {
            $opencart_controller = file_get_contents($main_controller);
            $existing = '/\/\*\*.@collivery.*\*\/.*@endcollivery.*\*\//s';
            if (preg_match($existing, $opencart_controller) === 1) {
                $opencart_controller = preg_replace($existing, '', $opencart_controller);
            }
            file_put_contents($main_controller, $opencart_controller);
        }

    }
}
