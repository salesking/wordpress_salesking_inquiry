<?php
/**
* Plugin Name: SkInquiry
* Plugin URI: http://github.com/salesking/SkInquiryWP
* Description: This plugin allows you to create a form for Salesking inquiries on you page
* Version: 1.0.0
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

/**
 * wrapper class for all methods of the SkInquiry plugin
 */
class SkInquiry {

    private $options = null;

    private $api = null;

    private $products = null;

    private $pdfTemplates = null;

    private $emailTemplates = null;

    private $apiStatus = null;

    /**
     * constructor, gets fired on every application start
     */
    public function __construct()
    {
        $this->options = get_option('skinquiry_options');
        $this->api = $this->initApi();

        // determine current application context
        if(is_admin()) {
            // call backend code
            add_action( 'admin_init', array($this, 'adminInit') );
            add_action( 'admin_menu', array($this, 'adminMenu') );
        }
        else {
            // call frontend code
            $this->frontendInit();
            add_shortcode( 'skinquiry', array($this, 'frontendDisplay') );
        }
    }

    /**
     * make some pre activation checks for system requirements
     */
    public static function activationHook() {
        if (!version_compare( PHP_VERSION, '5.3.0', '>=' )) {
            deactivate_plugins( __FILE__ );
            wp_die( wp_sprintf( __( 'Sorry, This plugin has taken a bold step in requiring PHP 5.3.0+. Your server is currently running PHP %2s, Please bug your host to upgrade to a recent version of PHP which is less bug-prone.', 'skinquiry' ), PHP_VERSION ) );
        }

        if (!in_array('curl', get_loaded_extensions())) {
            deactivate_plugins( __FILE__ );
            wp_die( __( 'Sorry, This plugin requires the curl extension for PHP which isn\'t available on you server. Please contact your host.', 'skinquiry' ));
        }
    }

    /**
     * gets executed when viewing the admin panel and sets up the plugin options
     */
    public function adminInit()
    {
        // set up plugin options using wp options API
        register_setting( 'skinquiry_options', 'skinquiry_options', array($this, 'validateSettings') );
        add_settings_section('skinquiry_main', 'Main Settings', array($this, 'adminSettingsDisplay') , 'skinquiry');
        add_settings_field('skinquiry_sk_url', 'SalesKing URL', array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_sk_url"));
        add_settings_field('skinquiry_sk_username', 'SalesKing Username', array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_sk_username"));
        add_settings_field('skinquiry_sk_password', 'SalesKing Password', array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_sk_password"));
        add_settings_field('skinquiry_products_tag', 'Products Tag', array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_products_tag"));
        add_settings_field('skinquiry_client_tags', 'Client Tags', array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_client_tags"));
        add_settings_field('skinquiry_estimate_tags', 'Estimate Tags', array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_estimate_tags"));
        add_settings_field('skinquiry_emailtemplate', 'E-Mail Template', array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_emailtemplate"));
        add_settings_field('skinquiry_pdftemplate', 'PDF Template', array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_pdftemplate"));
        add_settings_field('skinquiry_notes_before', 'Notes Before', array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_notes_before"));
        add_settings_field('skinquiry_notes_after', 'Notes After', array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_notes_after"));

        // display error message if necessary
        if($this->api == false) {
            add_action('admin_notices', array($this, 'outputCurlMessage'));
        }

        // display

    }

    public function outputCurlMessage() {
        echo '<div class="error">Could not SalesKing API - Please make sure that all system requirements (curl) are meet</div>';
    }

    /**
     *
     */
    public function frontendInit() {
        if($_POST['skinquiry_sentform'] == 1) {
            if($this->validate()) {
                $this->send();
            }
        }
    }

    /**
     * generate backend form inputs
     * @param $args
     */
    public function generateInputs($args)
    {
        switch($args['id']) {
            // url
            case "skinquiry_sk_url":
                echo '<input id="skinquiry_sk_url" name="skinquiry_options[sk_url]" size="40" type="text" value="'.$this->options['sk_url'].'" />';
                break;

            // username
            case "skinquiry_sk_username":
                echo '<input id="skinquiry_sk_username" name="skinquiry_options[sk_username]" size="40" type="text" value="'.$this->options['sk_username'].'" />';
                break;

            // password
            case "skinquiry_sk_password":
                echo '<input id="skinquiry_sk_password" name="skinquiry_options[sk_password]" size="40" type="password" value="'.$this->options['sk_password'].'" />';
                break;

            // products tag
            case "skinquiry_products_tag":
                echo '<input id="skinquiry_products_tag" name="skinquiry_options[products_tag]" size="40" type="text" value="'.$this->options['products_tag'].'" />';
                break;

            // client tags
            case "skinquiry_client_tags":
                echo '<input id="skinquiry_client_tags" name="skinquiry_options[client_tags]" size="40" type="text" value="'.$this->options['client_tags'].'" />';
                break;

            // estimate tags
            case "skinquiry_estimate_tags":
                echo '<input id="skinquiry_estimate_tags" name="skinquiry_options[estimate_tags]" size="40" type="text" value="'.$this->options['estimate_tags'].'" />';
                break;

            // emailtemplate select list
            case "skinquiry_emailtemplate":
                echo '<select id="skinquiry_emailtemplate" name="skinquiry_options[emailtemplate]" />';

                // fetch email templates
                $templates = $this->fetchEmailTemplates();

                // error while fetching, create default value
                if($templates == false) {
                    echo '<option value="">-- Could not fetch Templates --</value>';
                }
                else
                {
                    //generate an option for each template
                    foreach($templates as $template) {
                        $selected = ($template->id == $this->options['emailtemplate']) ? 'selected="selected"' : '';
                        echo '<option '.$selected.' value="'.$template->id.'">'.$template->name.'</option>';
                    }
                }

                echo '</select>';
                break;

            // pdftemplate select list
            case "skinquiry_pdftemplate":
                echo '<select id="skinquiry_pdftemplate" name="skinquiry_options[pdftemplate]" />';

                // fetch email templates
                $templates = $this->fetchPdfTemplates();

                // error while fetching, create default value
                if($templates == false) {
                        echo '<option value="">-- Could not fetch Templates --</value>';
                }
                else
                {
                    // generate an option for each template
                    foreach($templates as $template) {
                        $selected = ($template->id == $this->options['pdftemplate']) ? 'selected="selected"' : '';
                        echo '<option '.$selected.' value="'.$template->id.'">'.$template->name.'</option>';
                    }
                }

                echo '</select>';
                break;

            // estimate notes before
            case "skinquiry_notes_before":
                echo '<textarea id="skinquiry_notes_before" name="skinquiry_options[notes_before]" rows="5" cols="40" type="text">'.$this->options['notes_before'].'</textarea>';
                break;

            // estimate notes after
            case "skinquiry_notes_after":
                echo '<textarea id="skinquiry_notes_after" name="skinquiry_options[notes_after]" rows="5" cols="40" type="text">'.$this->options['notes_after'].'</textarea>';
                break;
        }
    }

    /**
     * @return string html output
     */
    public function frontendDisplay() {
        // make sure that the api is alright
        if($this->api == false) {
            return;
        }

        // load jquery and
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'skinquiry', plugins_url('js/skinquiry.js', __FILE__ ));
        wp_enqueue_script( 'jquery_validation', plugins_url('js/jquery.validate.min.js', __FILE__ ));
        wp_enqueue_style( 'skinquiry', plugins_url('css/skinquiry.css', __FILE__ ));

        // fetch products from api
        $products = $this->getProducts();

        // no products found so we have to create a dummy
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

        // generate hidden select list which is used by the javascript
        $content = '<select id="skinquiry_products" aria-hidden="true" tabindex="-1">';

        foreach($products as $product) {
            $content .= '<option value="'.$product->id.'" data-price="'.$product->price.'" data-tax="'.$product->tax.'" data-number="'.$product->number.'">'.$product->name.'</option>';
        }

        // generate form
        $content .= '</select>
        <form method="post" id="skinquiry_form">
            <fieldset id="skinquiry_clientdetails">
                <legend>Your Details</legend>
                <label for="skinquiry_client_last_name">Last name*</label>
                <input name="skinquiry_client_last_name" type="text" id="skinquiry_client_last_name" required="required" />
                <label for="skinquiry_client_first_name">First name*</label>
                <input name="skinquiry_client_first_name" type="text" id="skinquiry_client_first_name" required="required" />
                <label for="skinquiry_client_organisation_name">Company</label>
                <input name="skinquiry_client_organisation_name" type="text" id="skinquiry_client_organisation_name" />
                <label for="skinquiry_client_email">E-Mail*</label>
                <input name="skinquiry_client_email" type="text" id="skinquiry_client_email" required="required" />
                <label for="skinquiry_client_phone">Phone</label>
                <input name="skinquiry_client_phone" type="text" id="skinquiry_client_phone" />
                <label for="skinquiry_client_address1">Address*</label>
                <input name="skinquiry_client_address1" type="text" id="skinquiry_client_address1" required="required" />
                <label for="skinquiry_client_zip">Zip*</label>
                <input name="skinquiry_client_zip" type="text" id="skinquiry_client_zip" required="required" />
                <label for="skinquiry_client_city">City*</label>
                <input name="skinquiry_client_city" type="text" id="skinquiry_client_city" required="required" />
                <label for="skinquiry_client_country">Country*</label>
                <input name="skinquiry_client_country" type="text" id="skinquiry_client_country" required="required" />
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

                        $content .= '</select>
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
                <textarea id="skinquiry_comment" name="skinquiry_comment"></textarea>
                <input type="submit" value="Request Inquiry" />
            </fieldset>
            <input type="hidden" id="skinquiry_rowid" name="skinquiry_rowid" value="0" />
            <input type="hidden" id="skinquiry_sentform" name="skinquiry_sentform" value="1" />
        </form>';

        return $content;

    }

    /**
     * generate settings form
     */
    public function adminDisplay() {
        ?>
            <div class="wrap">
                <?php screen_icon(); ?>
                <h2>SalesKing Inquiry Plugin</h2>
                <?php if($this->api != false): ?>

                <form action="options.php" method="post">
                    <?php settings_fields('skinquiry_options'); ?>
                    <?php do_settings_sections('skinquiry'); ?>

                    <input name="Submit" type="submit" class="button button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />
                </form>

                <?php endif; ?>
            </div>
        <?php
    }

    /**
     * generate settings header
     * @return null
     */
    public function adminSettingsDisplay() {
        return null;
    }

    /**
     * @param $input
     *
     * @return mixed
     */
    public function validateSettings($input) {
        // delete cached products
        delete_transient('skinquiry_products');

        return $input;
    }

    /***
     * add admin menu item
     */
    public function adminMenu() {
        add_options_page('SalesKing Inquiry Settings', 'SkInquiry', 'manage_options', 'skinquiry', array($this, 'adminDisplay'));
    }

    /**
     * set up Salesking PHP library
     * @return bool|Salesking
     */
    public function initApi() {
        // make sure that curl is available
        if(!in_array('curl', get_loaded_extensions())) {
            return false;
        }

        require_once dirname(__FILE__).'/lib/salesking/salesking.php';

        // set up object
        $config = array(
            "sk_url" => $this->options['sk_url'],
            "user" => $this->options['sk_username'],
            "password" => $this->options['sk_password']
        );

        return new Salesking($config);
    }

    /**
     * fetch current api status
     * @return bool
     */
    public function getApiStatus() {
        if($this->apiStatus == null && $this->options['sk_url'] && $this->options['sk_username'] && $this->options['sk_password'])
        {
            try {
                $response = $this->api->request("/api/users/current");
            }
            catch (SaleskingException $e) {
                $this->apiStatus = false;
                return self::$apiStatus;
            }

            if($response['code'] = '200' AND property_exists($response['body'],'user')) {
                $this->apiStatus = true;
            }
            else
            {
                $this->apiStatus = false;
            }
        }
    }

    /**
     * return products from caching layers
     * @return bool|null
     */
    public function getProducts() {
        // cached in memory?
        if($this->products == null) {
            // cached in db?
            if ( false === ( $products = get_transient( 'skinquiry_products' ) ) ) {
                // fetch from api and cache in db
                $products = $this->fetchProducts();
                set_transient('skinquiry_products', $products, 60*60);
            }

            // cache in memory
            $this->products = $products;
        }

        return $this->products;
    }

    /**
     * fetch products from api
     * @return bool
     */
    public function fetchProducts() {
        $products = $this->api->getCollection(array(
                'type' => 'product',
                'autoload' => true
            ));

        // filter products for specific tags
        try {
            $this->products = $products->tags($this->options['products_tag'])->load()->getItems();
        }
        catch (SaleskingException $e) {
            return false;
        }

        return $this->products;
    }

    /**
     * fetch pdf templates
     * @return bool|SaleskingCollection
     */
    public function fetchPdfTemplates() {
        if($this->pdfTemplates == null) {
            $templates = $this->api->getCollection(array(
                    'type' => 'pdf_template',
                    'autoload' => true
                ));

            try {
                $this->pdfTemplates = $templates->load()->getItems();
            }
            catch (SaleskingException $e) {
                return false;
            }
        }

        return $this->pdfTemplates;
    }

    /**
     * fetch email templates
     * @return bool|SaleskingCollection
     *
     */
    public function fetchEmailTemplates() {
        if($this->emailTemplates == null) {

            $templates = $this->api->getCollection(array(
                    'type' => 'email_template',
                    'autoload' => true
                ));

            try {
                $this->emailTemplates = $templates->load()->getItems();
            }
            catch (SaleskingException $e) {
                return false;
            }
        }

        return $this->emailTemplates;
    }

    /**
     * submit entered information to salesking api
     * @return bool
     */
    public function send() {
        // set up objects
        $address = $this->api->getObject('address');
        $client = $this->api->getObject('client');
        $estimate = $this->api->getObject('estimate');
        $comment = $this->api->getObject('comment');

        // bind address data
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

        // bind client data
        try {
            $client->bind($_POST, array(
                "skinquiry_client_organisation_name" => "organisation",
                "skinquiry_client_last_name" => "last_name",
                "skinquiry_client_first_name" => "first_name",
                "skinquiry_client_email" => "email",
                "skinquiry_client_phone" => "phone_office"
            ));

            // set tags and address
            $client->tag_list = $this->options['client_tags'];
            $client->addresses = array($address->getData());
        }
        catch (SaleskingException $e) {
            return false;
        }

        // save client
        try {
            $client->save();
        }
        catch (SaleskingException $e) {
            return false;
        }

        // generate line items
        $i = 1;
        $items = array();

        // generate a line_item object for each product
        foreach($_POST['skinquiry_products'] as $product) {
            try {
                $item = $this->api->getObject("line_item");
                $item->position = $i;
                $item->product_id = $product['product'];
                $item->use_product = true;
                $item->quantity = $product['quantity'];

                $items[] = $item->getData();
                $i++;
            }
            catch (SaleskingException $e) {
                return false;
            }
        }

        // set all the other options for the estimate
        try {
            $estimate->client_id = $client->id;
            $estimate->tag_list = $this->options['estimate_tags'];
            $estimate->notes_before = $this->options['notes_before'];
            $estimate->notes_after = $this->options['notes_after'];
            $estimate->status = "open";
            $estimate->line_items = $items;
        }
        catch (SaleskingException $e) {
            return false;
        }

        // save estimate
        try {
            $estimate->save();
        }
        catch (SaleskingException $e) {
            return false;
        }

        // set up comment
        if(isset($_POST['skinquiry_comment']) AND trim($_POST['skinquiry_comment']) != "") {
            try {
                $comment->text = $_POST['skinquiry_comment'];
                $comment->related_object_type = 'Document';
                $comment->related_object_id = $estimate->id;
                $comment->save();
            }
            catch (SaleskingException $e) {
                return false;
            }

        }

        // generate pdf
        try {
            $this->api->request('/api/estimates/'.$estimate->id.'/print','POST',
                json_encode(array(
                    "template_id" => $this->options['pdftemplate']
                ))
            );
        }
        catch (SaleskingException $e) {
            return false;
        }

        // send mail to client
        try {
            $mail = new stdClass();
            $mail->email = new stdClass();
            $mail->email->to_addr = $_POST['skinquiry_client_email'];
            $mail->email->from_addr = "";

            $mail->send = true;
            $mail->archived_pdf = true;
            $mail->template_id = $this->options['emailtemplate'];

            // create mail and send to client
            $this->api->request('/api/estimates/'.$estimate->id.'/emails','POST',
                json_encode($mail)
            );
        }
        catch (SaleskingException $e) {
            return false;
        }

        return true;
    }

    /**
     * validate frontend inputs
     * @return bool
     */
    public function validate() {

        // last name is set
        if(!isset($_POST['skinquiry_client_last_name'])) {
            return false;
        }

        // first name is set
        if(!isset($_POST['skinquiry_client_first_name'])) {
            return false;
        }

        // email is set
        if(!isset($_POST['skinquiry_client_email'])) {
            return false;
        }

        // email is valid
        if(!preg_match("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$^", $_POST['skinquiry_client_email'])) {
            return false;
        }

        // address is set
        if(!isset($_POST['skinquiry_client_address1'])) {
            return false;
        }

        // zip is set
        if(!isset($_POST['skinquiry_client_zip'])) {
            return false;
        }

        // city is set
        if(!isset($_POST['skinquiry_client_city'])) {
            return false;
        }

        // country is set
        if(!isset($_POST['skinquiry_client_country'])) {
            return false;
        }

        // we have at least one product
        if(!isset($_POST['skinquiry_products'])) {
            return false;
        }

        // all products have an id and a quantity
        foreach($_POST['skinquiry_products'] as $product) {
            if(!isset($product['product']) OR !isset($product['quantity'])) {
                return false;
            }
        }

        return true;
    }
}

if(!function_exists('skinquiry_init_function')) {
    function skinquiry_init_function() {
        new SkInquiry();
    }
}

register_activation_hook( __FILE__, array( 'SkInquiry', 'activationHook' ) );
add_action( 'init', 'skinquiry_init_function');


