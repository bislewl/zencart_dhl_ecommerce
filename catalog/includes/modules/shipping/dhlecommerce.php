<?php

/**
 * @package shippingMethod
 * @copyright Copyright 2003-2009 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: dhlxmlservices.php 20141012 bislewl
 */

/**
 * DHL Shipping Module class
 *
 */
class dhlecommerce extends base {

    /**
     * Declare shipping module alias code
     *
     * @var string
     */
    var $code;

    /**
     * Shipping module display name
     *
     * @var string
     */
    var $title;

    /**
     * Shipping module display description
     *
     * @var string
     */
    var $description;

    /**
     * Shipping module icon filename/path
     *
     * @var string
     */
    var $icon;

    /**
     * Shipping module status
     *
     * @var boolean
     */
    var $enabled;

    /**
     * Shipping module list of supported countries (unique to USPS/DHL)
     *
     * @var array
     */
    var $types;

    /**
     * Constructor
     *
     * @return dhl
     */
    function dhlecommerce() {
        global $order, $db, $template, $current_page_base;

        $this->code = 'dhlecommerce';
        $this->title = MODULE_SHIPPING_DHL_ECOMM_TEXT_TITLE;
        $this->description = MODULE_SHIPPING_DHL_ECOMM_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_SHIPPING_DHL_ECOMM_SORT_ORDER;
        $this->icon = $template->get_template_dir('shipping_dhl.gif', DIR_WS_TEMPLATE, $current_page_base, 'images/icons') . '/' . 'shipping_dhl.gif';
        $this->tax_class = MODULE_SHIPPING_DHL_ECOMM_TAX_CLASS;
        $this->tax_basis = MODULE_SHIPPING_DHL_ECOMM_TAX_BASIS;
        $this->ecomm_methods = array('BMA', 'BML', 'BMP', 'BMS', 'PKA', 'PKD', 'PKL', 'PKT', 'PKY', 'PLD', 'PLT', 'PLX', 'PLY');
        $this->ecomm_gb_zones = array('PLT');
        $this->ecomm_ca_zones = array('PLD', 'PLT', 'PLX', 'PLY');


        // disable only when entire cart is free shipping
        if (zen_get_shipping_enabled($this->code)) {
            $this->enabled = ((MODULE_SHIPPING_DHL_ECOMM_STATUS == 'True') ? true : false);
        }
        if ($this->enabled && IS_ADMIN_FLAG) {
            $new_version_details = plugin_version_check_for_updates(0, '1.1.0');
            if ($new_version_details !== FALSE) {
                $this->title .= '<span class="alert">' . ' - NOTE: A NEW VERSION OF THIS PLUGIN IS AVAILABLE. <a href="' . $new_version_details['link'] . '" target="_blank">[Details]</a>' . '</span>';
            }
        }

        if (($this->enabled == true) && ((int) MODULE_SHIPPING_DHL_ECOMM_ZONE > 0)) {
            $check_flag = false;
            $check = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_SHIPPING_DHL_ECOMM_ZONE . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id");
            while (!$check->EOF) {
                if ($check->fields['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check->fields['zone_id'] == $order->delivery['zone_id']) {
                    $check_flag = true;
                    break;
                }
                $check->MoveNext();
            }

            if ($check_flag == false) {
                $this->enabled = false;
            }
        }
    }

    /**
     * @param string $method
     * @return array of quotation results
     */
    function quote($method = '') {
        global $_POST, $order, $shipping_weight, $shipping_num_boxes, $sniffer, $db;


        $methods = array();
        foreach ($this->ecomm_methods as $ecomm_method) {
            if (constant('MODULE_SHIPPING_DHL_ECOMM_ENABLED_' . $ecomm_method) == 'Y') {
                $column = $order->delivery['country']['iso_code_2'];
                if (in_array($ecomm_method, $this->ecomm_ca_zones) && $order->delivery['country']['iso_code_2'] == 'CA') {
                    //@todo add postal code lookup here
                    // Use $order->delivery['postcode']
                    $column = 'CA-1';
                }
                if (in_array($ecomm_method, $this->ecomm_ca_zones) && $order->delivery['country']['iso_code_2'] == 'GB') {
                    //@todo add postal code lookup here
                    $column = 'GB-1';
                }
                if ($sniffer->field_exists(constant('TABLE_SHIPPING_DHL_' . $ecomm_method), $column)) {
                    $rate_query = $db->Execute("SELECT * FROM " . constant('TABLE_SHIPPING_DHL_' . $ecomm_method) . " WHERE LBS >= " . (float) $shipping_weight . " ORDER BY LBS LIMIT 1");
                    if ($rate_query->RecordCount() > 0) {
                        $methods[] = array('id' => 'DHL_' . $ecomm_method,
                            'title' => constant('MODULE_SHIPPING_DHL_ECOMM_TEXT_' . $ecomm_method),
                            'cost' => ($rate_query->fields[$column] + (MODULE_SHIPPING_DHL_ECOMM_HANDLING_METHOD == 'Box' ? MODULE_SHIPPING_DHL_ECOMM_HANDLING * $shipping_num_boxes : MODULE_SHIPPING_DHL_ECOMM_HANDLING)));
                    }
                }
            }
        }




        if ((is_array($methods)) && (sizeof($methods) > 0)) {
            switch (SHIPPING_BOX_WEIGHT_DISPLAY) {
                case (0):
                    $show_box_weight = '';
                    break;
                case (1):
                    $show_box_weight = ' (' . $shipping_num_boxes . ' ' . TEXT_SHIPPING_BOXES . ')';
                    break;
                case (2):
                    $show_box_weight = ' (' . number_format($shipping_weight * $shipping_num_boxes, 2) . TEXT_SHIPPING_WEIGHT . ')';
                    break;
                default:
                    $show_box_weight = ' (' . $shipping_num_boxes . ' x ' . number_format($shipping_weight, 2) . TEXT_SHIPPING_WEIGHT . ')';
                    break;
            }
            $this->quotes = array('id' => $this->code,
                'module' => $this->title . $show_box_weight);



            $this->quotes['methods'] = $methods;

            if ($this->tax_class > 0) {
                $this->quotes['tax'] = zen_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
            }
        } else {
            $this->quotes = array('module' => $this->title,
                'error' => 'We are unable to obtain a rate quote for DHL shipping.<br />Please contact the store if no other alternative is shown.');
        }

        if (zen_not_null($this->icon))
            $this->quotes['icon'] = zen_image($this->icon, $this->title);

        return $this->quotes;
    }

    /**
     * check status of module
     *
     * @return boolean
     */
    function check() {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SHIPPING_DHL_ECOMM_STATUS'");
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }

    /**
     * Install this module
     *
     */
    function install() {
        global $db;
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable DHL eCommerce Shipping', 'MODULE_SHIPPING_DHL_ECOMM_STATUS', 'True', 'Do you want to offer DHL eCommerce shipping?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('DHL eCommerce Version', 'MODULE_SHIPPING_DHL_ECOMM_VERSION', '1.1.1', '', '6', '0', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_SHIPPING_DHL_ECOMM_SORT_ORDER', '4', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Handling Per Order or Per Box', 'MODULE_SHIPPING_DHL_ECOMM_HANDLING_METHOD', 'Box', 'Do you want to charge Handling Fee Per Order or Per Box?', '6', '3', 'zen_cfg_select_option(array(\'Order\', \'Box\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tax Class', 'MODULE_SHIPPING_DHL_ECOMM_TAX_CLASS', '0', 'Use the following tax class on the shipping fee.', '6', '4', 'zen_get_tax_class_title', 'zen_cfg_pull_down_tax_classes(', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Tax Basis', 'MODULE_SHIPPING_DHL_ECOMM_TAX_BASIS', 'Shipping', 'On what basis is Shipping Tax calculated. Options are<br />Shipping - Based on customers Shipping Address<br />Billing Based on customers Billing address<br />Store - Based on Store address if Billing/Shipping Zone equals Store zone', '6', '5', 'zen_cfg_select_option(array(\'Shipping\', \'Billing\', \'Store\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Dutiable?', 'MODULE_SHIPPING_DHL_ECOMM_DUTIABLE', 'Y', 'Is your shipments subject to duty?', '6', '6', 'zen_cfg_select_option(array(\'Y\', \'N\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Processing Terminal', 'MODULE_SHIPPING_DHL_ECOMM_TERMINAL', 'LAX', 'Your Processing Terminal, if you do not know contact your Account Rep.', '6', '7', 'zen_cfg_select_option(array(\'LAX\', \'EWS\', \'ORD\'), ', now())");
        //Shipping Method Status
        define('MODULE_SHIPPING_DHL_ECOMM_TEXT_BMA', 'GlobalMail Business IPA');
        define('MODULE_SHIPPING_DHL_ECOMM_TEXT_BML', 'GlobalMail Business ISAL');
        define('MODULE_SHIPPING_DHL_ECOMM_TEXT_BMP', 'GlobalMail Business Priority');
        define('MODULE_SHIPPING_DHL_ECOMM_TEXT_BMS', 'GlobalMail Business Standard');
        define('MODULE_SHIPPING_DHL_ECOMM_TEXT_PKA', 'GlobalMail Packet Standard');
        define('MODULE_SHIPPING_DHL_ECOMM_TEXT_PKD', 'GlobalMail Packet IPA');
        define('MODULE_SHIPPING_DHL_ECOMM_TEXT_PKL', 'GlobalMail Packet ISAL');
        define('MODULE_SHIPPING_DHL_ECOMM_TEXT_PKT', 'GlobalMail Packet Plus Priority');
        define('MODULE_SHIPPING_DHL_ECOMM_TEXT_PKY', 'GlobalMail Packet Priority');
        define('MODULE_SHIPPING_DHL_ECOMM_TEXT_PLD', 'GlobalMail Parcel Standard');
        define('MODULE_SHIPPING_DHL_ECOMM_TEXT_PLT', 'GlobalMail Parcel Direct');
        define('MODULE_SHIPPING_DHL_ECOMM_TEXT_PLX', 'GlobalMail Parcel Express');
        define('MODULE_SHIPPING_DHL_ECOMM_TEXT_PLY', 'GlobalMail Parcel Priority');
        $ecomm_titles = array(
            'BMA' => MODULE_SHIPPING_DHL_ECOMM_TEXT_BMA,
            'BML' => MODULE_SHIPPING_DHL_ECOMM_TEXT_BML,
            'BMP' => MODULE_SHIPPING_DHL_ECOMM_TEXT_BMP,
            'BMS' => MODULE_SHIPPING_DHL_ECOMM_TEXT_BMS,
            'PKA' => MODULE_SHIPPING_DHL_ECOMM_TEXT_PKA,
            'PKD' => MODULE_SHIPPING_DHL_ECOMM_TEXT_PKD,
            'PKL' => MODULE_SHIPPING_DHL_ECOMM_TEXT_PKL,
            'PKT' => MODULE_SHIPPING_DHL_ECOMM_TEXT_PKT,
            'PKY' => MODULE_SHIPPING_DHL_ECOMM_TEXT_PKY,
            'PLD' => MODULE_SHIPPING_DHL_ECOMM_TEXT_PLD,
            'PLT' => MODULE_SHIPPING_DHL_ECOMM_TEXT_PLT,
            'PLX' => MODULE_SHIPPING_DHL_ECOMM_TEXT_PLX,
            'PLY' => MODULE_SHIPPING_DHL_ECOMM_TEXT_PLY
        );
        $ecomm_method_sort = 10;
        foreach ($this->ecomm_methods as $ecomm_method) {
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('" . $ecomm_titles[$ecomm_method] . "', 'MODULE_SHIPPING_DHL_ECOMM_ENABLED_" . $ecomm_method . "', 'Y', 'Do you want to offer shipping via " . $ecomm_titles[$ecomm_method] . "?', '6', '" . $ecomm_method_sort . "', 'zen_cfg_select_option(array(\'Y\', \'N\'), ', now())");
            $ecomm_method_sort++;
        }
    }

    /**
     * Remove this module
     *
     */
    function remove() {
        global $db;
        $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key like 'MODULE\_SHIPPING\_DHL\_ECOMM%' ");
    }

    /**
     * Build array of keys used for installing/managing this module
     *
     * @return array
     */
    function keys() {
        $keys = array('MODULE_SHIPPING_DHL_ECOMM_STATUS', 'MODULE_SHIPPING_DHL_ECOMM_VERSION', 'MODULE_SHIPPING_DHL_ECOMM_HANDLING_METHOD', 'MODULE_SHIPPING_DHL_ECOMM_TAX_CLASS', 'MODULE_SHIPPING_DHL_ECOMM_TAX_BASIS', 'MODULE_SHIPPING_DHL_ECOMM_DUTIABLE','MODULE_SHIPPING_DHL_ECOMM_TERMINAL','MODULE_SHIPPING_DHL_ECOMM_SORT_ORDER');
        foreach ($this->ecomm_methods as $ecomm_method) {
            $keys[] = 'MODULE_SHIPPING_DHL_ECOMM_ENABLED_' . $ecomm_method;
        }
        return $keys;
    }

}

if (!function_exists('plugin_version_check_for_updates')) {

    function plugin_version_check_for_updates($fileid = 0, $version_string_to_check = '') {
        if ($fileid == 0) {
            return FALSE;
        }
        $new_version_available = FALSE;
        $lookup_index = 0;
        $url = 'http://www.zen-cart.com/downloads.php?do=versioncheck' . '&id=' . (int) $fileid;
        $data = json_decode(file_get_contents($url), true);
        if (!$data || !is_array($data))
            return false;
        // compare versions
        if (version_compare($data[$lookup_index]['latest_plugin_version'], $version_string_to_check) > 0) {
            $new_version_available = TRUE;
        }
        // check whether present ZC version is compatible with the latest available plugin version
        if (!in_array('v' . PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR, $data[$lookup_index]['zcversions'])) {
            $new_version_available = FALSE;
        }
        if ($version_string_to_check == true) {
            return $data[$lookup_index];
        } else {
            return FALSE;
        }
    }

}