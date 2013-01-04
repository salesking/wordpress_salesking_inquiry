<?php
/**
* Plugin Name: SkInquiry
* Plugin URI: http://github.com/salesking/SkInquiryWP
* Description: This plugin allows you to create a form for Salesking inquiries on you page
* Version: %%PLUGINVERSION%%
* Author: David Jardin
* Author URI: http://www.djumla.de
* License: GPL3
* Copyright 2012  David Jardin  (email : d.jardin@djumla.de)

* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License, version 3, as
* published by the Free Software Foundation.

* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.

* You should have received a copy of the GNU General Public License
* along with this program; if not, write to the Free Software
* Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class SkInquiry {

    private $options = null;

    private $api = null;

    private $products = null;


    public function __construct()
    {
        $this->options = get_option('skinquiry_options');
        $this->api = $this->initLibrary();

        add_action( 'init', array($this, 'frontendInit') );
        add_shortcode( 'skinquiry', array($this, 'frontendDisplay') );

        add_action( 'admin_init', array($this, 'adminInit') );
        add_action( 'admin_menu', array($this, 'adminMenu') );
    }

    public function adminInit()
    {
        register_setting( 'skinquiry_options', 'skinquiry_options', array($this, 'validateSettings') );
        add_settings_section('skinquiry_main', 'Main Settings', null , 'skinquiry');
        add_settings_field('skinquiry_sk_url', 'SalesKing URL', array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_sk_url"));
        add_settings_field('skinquiry_sk_username', 'SalesKing Username', array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_sk_username"));
        add_settings_field('skinquiry_sk_password', 'SalesKing Password', array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_sk_password"));
        add_settings_field('skinquiry_products_tag', 'Products Tag', array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_products_tag"));
        add_settings_field('skinquiry_client_tags', 'Client Tags', array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_client_tags"));
        add_settings_field('skinquiry_estimate_tags', 'Estimate Tags', array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_estimate_tags"));
        add_settings_field('skinquiry_notes_before', 'Notes Before', array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_notes_before"));
        add_settings_field('skinquiry_notes_after', 'Notes After', array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_notes_after"));

    }

    public function frontendInit() {
        die("init");
        var_dump($_POST);
        if($_POST['skinquiry_sentform'] == 1) {
            if($this->validate()) {
                $this->send();
            }
        }
    }

    public function generateInputs($args)
    {
        switch($args['id']) {
            case "skinquiry_sk_url":
                echo '<input id="skinquiry_sk_url" name="skinquiry_options[sk_url]" size="40" type="text" value="'.$this->options['sk_url'].'" />';
                break;

            case "skinquiry_sk_username":
                echo '<input id="skinquiry_sk_username" name="skinquiry_options[sk_username]" size="40" type="text" value="'.$this->options['sk_username'].'" />';
                break;

            case "skinquiry_sk_password":
                echo '<input id="skinquiry_sk_password" name="skinquiry_options[sk_password]" size="40" type="password" value="'.$this->options['sk_password'].'" />';
                break;

            case "skinquiry_products_tag":
                echo '<input id="skinquiry_products_tag" name="skinquiry_options[products_tag]" size="40" type="text" value="'.$this->options['products_tag'].'" />';
                break;

            case "skinquiry_client_tags":
                echo '<input id="skinquiry_client_tags" name="skinquiry_options[client_tags]" size="40" type="text" value="'.$this->options['client_tags'].'" />';
                break;

            case "skinquiry_estimate_tags":
                echo '<input id="skinquiry_estimate_tags" name="skinquiry_options[estimate_tags]" size="40" type="text" value="'.$this->options['estimate_tags'].'" />';
                break;

            case "skinquiry_notes_before":
                echo '<textarea id="skinquiry_notes_before" name="skinquiry_options[notes_before]" rows="5" cols="40" type="text">'.$this->options['notes_before'].'</textarea>';
                break;

            case "skinquiry_notes_after":
                echo '<textarea id="skinquiry_notes_after" name="skinquiry_options[notes_after]" rows="5" cols="40" type="text">'.$this->options['notes_after'].'</textarea>';
                break;
        }
    }

    public function frontendDisplay() {
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'skinquiry', plugins_url('js/skinquiry.js', __FILE__ ));
        wp_enqueue_style( 'skinquiry', plugins_url('css/skinquiry.css', __FILE__ ));

        $products = $this->fetchProducts();

        if(!count($products)) {
            $placeholder = new stdClass();
            $placeholder->id = "";
            $placeholder->name = "-- No Products found --";
            $placeholder->price = "";
            $placeholder->tax = "";
            $placeholder->number = "";

            $products = array(
                $placeholder
            );
        }

        $content = '<select id="skinquiry_products" aria-hidden="true" tabindex="-1">';

        foreach($products as $product) {
            $content .= '<option value="'.$product->id.'" data-price="'.$product->price.'" data-tax="'.$product->tax.'" data-number="'.$product->number.'">'.$product->name.'</option>';
        }

        $content .= '</select>
        <form method="post" id="skinquiry_form">
            <fieldset id="skinquiry_clientdetails">
                <legend>Your Details</legend>
                <label for="skinquiry_client_last_name">Last name</label>
                <input name="skinquiry_client_last_name" type="text" id="skinquiry_client_last_name" />
                <label for="skinquiry_client_first_name">First name</label>
                <input name="skinquiry_client_first_name" type="text" id="skinquiry_client_first_name" />
                <label for="skinquiry_client_organisation_name">Company</label>
                <input name="skinquiry_client_organisation_name" type="text" id="skinquiry_client_organisation_name" />
                <label for="skinquiry_client_email">E-Mail</label>
                <input name="skinquiry_client_email" type="text" id="skinquiry_client_email" />
                <label for="skinquiry_client_phone">Phone</label>
                <input name="skinquiry_client_phone" type="text" id="skinquiry_client_phone" />
                <label for="skinquiry_client_address1">Address</label>
                <input name="skinquiry_client_address1" type="text" id="skinquiry_client_address1" />
                <label for="skinquiry_client_zip">Zip</label>
                <input name="skinquiry_client_zip" type="text" id="skinquiry_client_zip" />
                <label for="skinquiry_client_city">City</label>
                <input name="skinquiry_client_city" type="text" id="skinquiry_client_city" />
                <label for="skinquiry_client_country">Country</label>
                <input name="skinquiry_client_country" type="text" id="skinquiry_client_country" />
            </fieldset>
            <fieldset>
                <legend>Products</legend>
                <div id="skinquiry_productlist">
                    <div class="skinquiry_product" data-rowid="0">
                        <label for="skinquiry_products_0_product">Product</label>
                        <select id="skinquiry_products_0_product" name="skinquiry_products[0][product]">';

        foreach($products as $product) {
            $content .= '<option value="'.$product->id.'">'.$product->name.'</option>';
        }

        $content .= '   </select>
                        <label for="skinquiry_products_0_quantity">Quantity</label>
                        <input id="skinquiry_products_0_quantity" name="skinquiry_products[0][quantity]" value="1" type="text" />
                        <span class="skinquiry_delete">Remove</span>
                    </div>
                </div>
                <button id="skinquiry_addproduct">Add line item</button>
            </fieldset>
            <fieldset>
                <legend>Comment</legend>
                <label for="skinquiry_comment">Your message for us</label>
                <textarea id="skinquiry_comment"></textarea>
                <input type="submit" value="Request Inquiry" />
            </fieldset>
            <input type="hidden" id="skinquiry_rowid" name="skinquiry_rowid" value="0" />
            <input type="hidden" id="skinquiry_sentform" name="skinquiry_sentform" value="1" />
        </form>';

        return $content;

    }

    public function adminDisplay() {
        ?>
            <div class="wrap">
                <?php screen_icon(); ?>
                <h2>SalesKing Inquiry Plugin</h2>
                <form action="options.php" method="post">
                    <?php settings_fields('skinquiry_options'); ?>
                    <?php do_settings_sections('skinquiry'); ?>

                    <input name="Submit" type="submit" class="button button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />
                </form>
            </div>
        <?php
    }

    public function validateSettings($input) {
        //@todo proper input validation and check for correct credentials

        return $input;
    }

    public function adminMenu() {
        add_options_page('SalesKing Inquiry Settings', 'SkInquiry', 'manage_options', 'skinquiry', array($this, 'adminDisplay'));
    }

    public function initLibrary() {
        if(empty($this->options['sk_username']) || empty($this->options['sk_password']) || empty($this->options['sk_url'])) {
            return false;
        }

        if(!in_array('curl', get_loaded_extensions())) {
            return false;
        }

        if(!file_exists(dirname(__FILE__).'/lib/salesking/salesking.php')) {
            return false;
        }

        require_once dirname(__FILE__).'/lib/salesking/salesking.php';

        $config = array(
            "sk_url" => $this->options['sk_url'],
            "user" => $this->options['sk_username'],
            "password" => $this->options['sk_password']
        );

        return new Salesking($config);
    }

    public function fetchProducts() {
        if($this->products == null) {
            $products = $this->api->getCollection(array(
                    'type' => 'product',
                    'autoload' => true
                ));

            try {
                $this->products = $products->tags($this->options['products_tag'])->load()->getItems();
            }
            catch (SaleskingException $e) {
                return false;
            }
        }

        return $this->products;
    }

    public function send() {
        //@todo make api calls
        $address = $this->api->getObject('address');

        try {
        $address->bind($_POST, array(
                "skinquiry_client_address1" => "address1",
                "skinquiry_client_city" => "city",
                "skinquiry_client_zip" => "zip",
                "skinquiry_client_country" => "country"
            ));
        }
        catch (SaleskingException $e) {
            return false;
        }

        die(print_r($address->getData()));

        return true;
    }

    public function validate() {
        //@todo proper frontend validation

        return true;
    }
}

add_action( 'init', function() {
    $SkInquiry = new SkInquiry();
} );


