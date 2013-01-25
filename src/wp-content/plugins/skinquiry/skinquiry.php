<?php
/**
* Plugin Name: SkInquiry
* Plugin URI: http://github.com/salesking/SkInquiryWP
* Description: This plugin allows you to create a form for Salesking docs on you page
* Version: 1.0.0
* Author: David Jardin
* Author URI: http://www.djumla.de
* Text Domain: skinquiry
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

    private $apis = array();

    private $products = null;

    private $pdfTemplates = null;

    private $emailTemplates = null;

    private $apiStates = array();

    private $systemMessage = null;

    /**
     * constructor, gets fired on every application start
     */
    public function __construct()
    {
        $this->options = get_option('skinquiry_options');
        load_plugin_textdomain( 'skinquiry', false, basename(dirname(__FILE__)).'/languages/' );

        // determine current application context
        if (is_admin()) {
            if ($_GET['page'] == 'skinquiry') {
                if (!$this->systemCheck()) {
                    add_action('admin_notices', array($this, 'setSystemMessage'));
                }
            }

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
     * checks current system status
     * @return bool
     */
    private function systemCheck() {
        if (!version_compare( PHP_VERSION, '5.3.0', '>=' )) {
            $this->systemMessage = __('Outdated PHP Version', 'skinquiry' );
            return false;
        }

        if (!in_array('curl', get_loaded_extensions())) {
            $this->systemMessage = __('curl extension not available', 'skinquiry' );
            return false;
        }

        if (!$this->getApiStatus()) {
            $this->systemMessage = __('Invalid SalesKing credentials', 'skinquiry' );
            return false;
        }

        return true;
    }

    /**
     *  callback function to output a system message in admin area
     */
    public function setSystemMessage() {
        echo '<div class="error">'.$this->systemMessage.'</div>';
    }

    /***
     * add admin menu item
     */
    public function adminMenu() {
        add_options_page(__('SalesKing Inquiry Settings', 'skinquiry' ), 'SkInquiry', 'manage_options', 'skinquiry', array($this, 'adminDisplay'));
    }

    /**
     * gets executed when viewing the admin panel and sets up the plugin options
     */
    public function adminInit()
    {
        // set up plugin options using wp options API
        register_setting( 'skinquiry_options', 'skinquiry_options', array($this, 'validateSettings') );
        add_settings_section('skinquiry_main', __('Main Settings', 'skinquiry' ), array($this, 'adminSettingsDisplay') , 'skinquiry');
        add_settings_field('skinquiry_sk_url', __('SalesKing URL', 'skinquiry' ), array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_sk_url"));
        add_settings_field('skinquiry_sk_username', __('SalesKing Username', 'skinquiry' ), array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_sk_username"));
        add_settings_field('skinquiry_sk_password', __('SalesKing Password', 'skinquiry' ), array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_sk_password"));
        add_settings_field('skinquiry_document_type', __('Document Type', 'skinquiry' ), array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_document_type"));

        // hide all other settings as long as the api is not ready
        if ($this->getApiStatus() && $this->options['sk_url'] && $this->options['sk_password'] && $this->options['sk_username']) {
            add_settings_field('skinquiry_contact_type', __('Contact Type', 'skinquiry' ), array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_contact_type"));
            add_settings_field('skinquiry_products_tag', __('Products Tag', 'skinquiry' ), array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_products_tag"));
            add_settings_field('skinquiry_contact_tags', __('Contact Tags', 'skinquiry' ), array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_contact_tags"));
            add_settings_field('skinquiry_document_tags', __('Document Tags', 'skinquiry' ), array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_document_tags"));
            add_settings_field('skinquiry_redirect_url', __('Redirect URL', 'skinquiry' ), array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_redirect_url"));
            add_settings_field('skinquiry_use_captcha', __('Use Captcha', 'skinquiry' ), array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_use_captcha"));
            add_settings_field('skinquiry_emailtemplate', __('E-Mail Template', 'skinquiry' ), array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_emailtemplate"));
            add_settings_field('skinquiry_pdftemplate', __('PDF Template', 'skinquiry' ), array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_pdftemplate"));
            add_settings_field('skinquiry_notes_before', __('Notes Before', 'skinquiry' ), array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_notes_before"));
            add_settings_field('skinquiry_notes_after', __('Notes After', 'skinquiry' ), array($this, 'generateInputs'), 'skinquiry', 'skinquiry_main', array("id" => "skinquiry_notes_after"));
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
                echo '<div class="description">'.__('The URL of your SalesKing installation, i.e. https://company.salesking.eu', 'skinquiry').'</div>';
                break;

            // username
            case "skinquiry_sk_username":
                echo '<input id="skinquiry_sk_username" name="skinquiry_options[sk_username]" size="40" type="text" value="'.$this->options['sk_username'].'" />';
                echo '<div class="description">'.__('Your SalesKing username', 'skinquiry').'</div>';
                break;

            // password
            case "skinquiry_sk_password":
                echo '<input id="skinquiry_sk_password" name="skinquiry_options[sk_password]" size="40" type="password" value="'.$this->options['sk_password'].'" />';
                echo '<div class="description">'.__('Your SalesKing password', 'skinquiry').'</div>';
                break;

            // document type
            case "skinquiry_document_type":
                echo '<select id="skinquiry_document_type" name="skinquiry_options[document_type]" >';

                $checked = ($this->options['document_type'] == 'estimate') ? 'selected="selected"' : '';
                echo '<option '.$checked.' value="estimate">'.__('Estimate', 'skinquiry' ).'</option>';

                $checked = ($this->options['document_type'] == 'order') ? 'selected="selected"' : '';
                echo '<option '.$checked.' value="order">'.__('Order', 'skinquiry' ).'</option>';

                $checked = ($this->options['document_type'] == 'invoice') ? 'selected="selected"' : '';
                echo '<option '.$checked.' value="invoice">'.__('Invoice', 'skinquiry' ).'</option>';

                echo '</select>';
                echo '<div class="description">'.__('Select which type of document should be created in your SalesKing Account', 'skinquiry').'<br />
                '.__('NOTICE: If you change the document type, please make sure that you also choose a matching email and pdf template!', 'skinquiry').'</div>';
                break;

            // contact type
            case "skinquiry_contact_type":
                echo '<select id="skinquiry_contact_type" name="skinquiry_options[contact_type]" >';

                $checked = ($this->options['contact_type'] == 'Lead') ? 'selected="selected"' : '';
                echo '<option '.$checked.' value="Lead">'.__('Lead', 'skinquiry' ).'</option>';

                $checked = ($this->options['contact_type'] == 'Client') ? 'selected="selected"' : '';
                echo '<option '.$checked.' value="Client">'.__('Client', 'skinquiry' ).'</option>';

                $checked = ($this->options['contact_type'] == 'Supplier') ? 'selected="selected"' : '';
                echo '<option '.$checked.' value="Supplier">'.__('Supplier', 'skinquiry' ).'</option>';

                echo '</select>';
                break;

            // products tag
            case "skinquiry_products_tag":
                echo '<input id="skinquiry_products_tag" name="skinquiry_options[products_tag]" size="40" type="text" value="'.$this->options['products_tag'].'" />';
                echo '<div class="description">'.__('Enter a comma-separated list of tags that will be used to filter the displayed products in the form', 'skinquiry').'</div>';
                break;

            // contact tags
            case "skinquiry_contact_tags":
                echo '<input id="skinquiry_contact_tags" name="skinquiry_options[contact_tags]" size="40" type="text" value="'.$this->options['contact_tags'].'" />';
                echo '<div class="description">'.__('Enter a comma-separated list of tags that will be added to the new contact in the SalesKing', 'skinquiry').'</div>';
                break;

            // document tags
            case "skinquiry_document_tags":
                echo '<input id="skinquiry_document_tags" name="skinquiry_options[document_tags]" size="40" type="text" value="'.$this->options['document_tags'].'" />';
                echo '<div class="description">'.__('Enter a comma-separated list of tags that will be added to the new document in the SalesKing', 'skinquiry').'</div>';
                break;

            // redirect url
            case "skinquiry_redirect_url":
                echo '<input id="skinquiry_redirect_url" name="skinquiry_options[redirect_url]" size="40" type="text" value="'.$this->options['redirect_url'].'" />';
                echo '<div class="description">'.__('The URL where the user gets redirected after successfully submitting the form', 'skinquiry').'</div>';
                break;

            // use captcha
            case "skinquiry_use_captcha":
                if (class_exists('ReallySimpleCaptcha')) {
                    $checked = ($this->options['use_captcha'] == 1) ? 'checked="checked"'  : '';
                    echo '<input id="skinquiry_use_captcha" name="skinquiry_options[use_captcha]" type="checkbox" value="1" '.$checked.' />';
                    echo '<div class="description">'.__('Display a captcha image to increase security', 'skinquiry').'</div>';
                }
                else {
                    echo __('Please install the plugin "ReallySimpleCaptcha" to enable this function', 'skinquiry' );
                    echo '<input id="skinquiry_use_captcha" name="skinquiry_options[use_captcha]" type="hidden" value="0" />';
                }

                break;

            // emailtemplate select list
            case "skinquiry_emailtemplate":
                echo '<select id="skinquiry_emailtemplate" name="skinquiry_options[emailtemplate]" >';

                // fetch email templates
                $templates = $this->fetchEmailTemplates();

                // error while fetching, create default value
                if ($templates == false) {
                    echo '<option value="">-- '.__('Could not fetch Templates', 'skinquiry' ).' --</value>';
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
                echo '<div class="description">'.__('Select an e-mail template that is used for the mail to the contact', 'skinquiry').'</div>';
                break;

            // pdftemplate select list
            case "skinquiry_pdftemplate":
                echo '<select id="skinquiry_pdftemplate" name="skinquiry_options[pdftemplate]" >';

                // fetch email templates
                $templates = $this->fetchPdfTemplates();

                // error while fetching, create default value
                if ($templates == false) {
                        echo '<option value="">-- '.__('Could not fetch Templates', 'skinquiry' ).' --</value>';
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
                echo '<div class="description">'.__('Select a PDF template that is used for the document generation', 'skinquiry').'</div>';
                break;

            // document notes before
            case "skinquiry_notes_before":
                echo '<textarea id="skinquiry_notes_before" name="skinquiry_options[notes_before]" rows="5" cols="40" type="text">'.$this->options['notes_before'].'</textarea>';
                echo '<div class="description">'.__('Enter a text that will be displayed above the line items in the generated document', 'skinquiry').'</div>';
                break;

            // document notes after
            case "skinquiry_notes_after":
                echo '<textarea id="skinquiry_notes_after" name="skinquiry_options[notes_after]" rows="5" cols="40" type="text">'.$this->options['notes_after'].'</textarea>';
                echo '<div class="description">'.__('Enter a text that will be displayed below the line items in the generated document', 'skinquiry').'</div>';
                break;
        }
    }

    /**
     * generate settings form
     */
    public function adminDisplay() {
        // add required javascripts
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'skinquiryAdmin', plugins_url('js/skinquiry.admin.js', __FILE__ ));

        // find out which button text we should use for the submit button
        $buttonText = ($this->getApiStatus() && $this->options['sk_url'] && $this->options['sk_password'] && $this->options['sk_username']) ? __('Save Changes', 'skinquiry' ) : __('Connect', 'skinquiry' );
        ?>
            <div class="wrap">
                <?php screen_icon(); ?>
                <h2><?php echo __('SalesKing Inquiry Plugin', 'skinquiry' ); ?></h2>

                <form action="options.php" method="post">
                    <?php settings_fields('skinquiry_options'); ?>
                    <?php do_settings_sections('skinquiry'); ?>

                    <input name="Submit" type="submit" class="button button-primary" value="<?php echo $buttonText; ?>" />
                </form>
            </div>
        <?php
    }

    /**
     * generate settings header
     * @return null
     */
    public function adminSettingsDisplay() {
        if ($this->options['message'] && $_GET['settings-updated'] == "true") {
            ?>
            <div class="error"><?php echo $this->options['message']; ?></div>
            <?php
        }
    }

    /**
     * @param $input array
     *
     * @return mixed
     */
    public function validateSettings($input) {
        // delete cached products
        delete_transient('skinquiry_products');

        if ($input['sk_url'] OR $input['sk_username'] OR $input['sk_password']) {
            if (!$this->getApiStatus($input['sk_url'], $input['sk_username'], $input['sk_password'])) {
                $input['sk_url'] = '';
                $input['sk_username'] = '';
                $input['sk_password'] = '';

                $input['message'] = __('Invalid credentials', 'skinquiry' );
            }
        }

        return $input;
    }

    /**
     * set up Salesking PHP library
     * @param $sk_url 
     * @param $sk_username
     * @param $sk_password
     * @return bool|Salesking
     */
    private function getApi($sk_url = null, $sk_username = null, $sk_password = null) {
        // check if credentials are provided and switch to default values
        $sk_url = ($sk_url == null) ? $this->options['sk_url'] : $sk_url;
        $sk_username = ($sk_username == null) ? $this->options['sk_username'] : $sk_username;
        $sk_password = ($sk_password == null) ? $this->options['sk_password'] : $sk_password;

        // create a unique instance for every credential combination
        $hash = md5($sk_url.$sk_username.$sk_password);

        if (!array_key_exists($hash, $this->apis)) {
            // make sure that curl is available
            if (!in_array('curl', get_loaded_extensions())) {
                return false;
            }

            // make sure that curl is available
            if (!in_array('curl', get_loaded_extensions())) {
                return false;
            }

            require_once dirname(__FILE__).'/lib/salesking/salesking.php';

            // set up object
            $config = array(
                "sk_url" => $sk_url,
                "user" => $sk_username,
                "password" => $sk_password
            );

            $this->apis[$hash] = new Salesking($config);
        }

        return $this->apis[$hash];


    }

    /**
     *
     * fetch current api status
     * @param $sk_url string
     * @param $sk_username string
     * @param $sk_password string
     * @return bool
     */
    private function getApiStatus($sk_url = null, $sk_username = null, $sk_password = null) {
        // check if credentials are provided and switch to default values
        $sk_url = ($sk_url == null) ? $this->options['sk_url'] : $sk_url;
        $sk_username = ($sk_username == null) ? $this->options['sk_username'] : $sk_username;
        $sk_password = ($sk_password == null) ? $this->options['sk_password'] : $sk_password;

        // create a unique instance for every credential combination
        $hash = md5($sk_url.$sk_username.$sk_password);

        if (!array_key_exists($hash, $this->apiStates))
        {
            if ($sk_url && $sk_username && $sk_password) {
                try {
                    $response = $this->getApi($sk_url, $sk_username, $sk_password)->request("/api/users/current");
                }
                catch (SaleskingException $e) {
                    $this->apiStates[$hash] = false;
                }

                if ($response['code'] == '200') {
                    if (property_exists($response['body'],'user')) {
                        $this->apiStates[$hash] = true;
                    }
                }
                else
                {
                    $this->apiStates[$hash] = false;
                }
            }
            else
            {
                $this->apiStates[$hash] = true;
            }
        }

        return $this->apiStates[$hash];
    }

    /**
     * return products from caching layers
     * @return bool|null
     */
    private function getProducts() {
        // cached in memory?
        if ($this->products == null) {
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
    protected function fetchProducts() {
        $products = $this->getApi()->getCollection(array(
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
    protected function fetchPdfTemplates() {
        if ($this->pdfTemplates == null) {
            $templates = $this->getApi()->getCollection(array(
                    'type' => 'pdf_template',
                    'autoload' => true
                ));

            try {
                $this->pdfTemplates = $templates->kind($this->options['document_type'])->load()->getItems();
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
    protected function fetchEmailTemplates() {
        if ($this->emailTemplates == null) {

            $templates = $this->getApi()->getCollection(array(
                    'type' => 'email_template',
                    'autoload' => true
                ));

            try {
                $this->emailTemplates = $templates->kind($this->options['document_type'])->load()->getItems();
            }
            catch (SaleskingException $e) {
                return false;
            }
        }

        return $this->emailTemplates;
    }

    /**
     * initialize frontend
     */
    public function frontendInit() {
        if ($_POST['skinquiry_sentform'] == 1) {
            if ($this->validate()) {
                $this->send();
            }
        }
    }

    /**
     * @return string html output
     */
    public function frontendDisplay() {
        if (!$this->getApiStatus() OR !$this->options['sk_username'] OR !$this->options['sk_url'] OR !$this->options['sk_password']) {
            return null;
        }

        // load jquery and
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'skinquiry', plugins_url('js/skinquiry.js', __FILE__ ));
        wp_enqueue_script( 'jquery_validation', plugins_url('js/jquery.validate.min.js', __FILE__ ));
        wp_enqueue_style( 'skinquiry', plugins_url('css/skinquiry.css', __FILE__ ));
        wp_localize_script( 'skinquiry', 'objectL10n', array(
            'product' => __( 'Product', 'skinquiry' ),
            'remove' => __( 'Remove', 'skinquiry' ),
            'quantity' => __( 'Quantity', 'skinquiry' ),
        ) );

        // fetch products from api
        $products = $this->getProducts();

        // no products found so we have to create a dummy
        if (!count($products)) {
            $placeholder = new stdClass();
            $placeholder->id = "";
            $placeholder->name = "-- ".__('No Products found', 'skinquiry' )." --";
            $placeholder->price = "";
            $placeholder->tax = "";
            $placeholder->number = "";

            $products = array(
                $placeholder
            );
        }

        // fetch captcha
        $captcha = false;
        if ($this->options['use_captcha'] == 1 AND class_exists('ReallySimpleCaptcha')) {
            $captcha = new ReallySimpleCaptcha();
            $captcha->tmp_dir = dirname( __FILE__ ) . '/tmp/';
            $captchaPrefix = mt_rand();
            $captchaImage = $captcha->generate_image( $captchaPrefix, $captcha->generate_random_word() );
        }

        // generate hidden select list which is used by the javascript
        $content = '<select id="skinquiry_products" aria-hidden="true" tabindex="-1">';

        foreach($products as $product) {
            $content .= '<option value="'.$product->id.'" data-price="'.$product->price.'" data-tax="'.$product->tax.'" data-number="'.$product->number.'">'.$product->name.'</option>';
        }

        // generate form
        $content .= '</select>
        <form method="post" id="skinquiry_form">
            <fieldset id="skinquiry_contactdetails">
                <legend>'.__('Your Details', 'skinquiry' ).'</legend>
                <label for="skinquiry_contact_last_name">'.__('Last name', 'skinquiry' ).'*</label>
                <input name="skinquiry_contact_last_name" type="text" id="skinquiry_contact_last_name" required="required" />
                <label for="skinquiry_contact_first_name">'.__('First name', 'skinquiry' ).'*</label>
                <input name="skinquiry_contact_first_name" type="text" id="skinquiry_contact_first_name" required="required" />
                <label for="skinquiry_contact_organisation_name">'.__('Company', 'skinquiry' ).'</label>
                <input name="skinquiry_contact_organisation_name" type="text" id="skinquiry_contact_organisation_name" />
                <label for="skinquiry_contact_email">'.__('E-Mail', 'skinquiry' ).'*</label>
                <input name="skinquiry_contact_email" type="text" id="skinquiry_contact_email" required="required" />
                <label for="skinquiry_contact_phone">'.__('Phone', 'skinquiry' ).'</label>
                <input name="skinquiry_contact_phone" type="text" id="skinquiry_contact_phone" />
                <label for="skinquiry_contact_address1">'.__('Address', 'skinquiry' ).'*</label>
                <input name="skinquiry_contact_address1" type="text" id="skinquiry_contact_address1" required="required" />
                <label for="skinquiry_contact_zip">'.__('Zip', 'skinquiry' ).'*</label>
                <input name="skinquiry_contact_zip" type="text" id="skinquiry_contact_zip" required="required" />
                <label for="skinquiry_contact_city">'.__('City', 'skinquiry' ).'*</label>
                <input name="skinquiry_contact_city" type="text" id="skinquiry_contact_city" required="required" />
                <label for="skinquiry_contact_country">'.__('Country', 'skinquiry' ).'*</label>
                <input name="skinquiry_contact_country" type="text" id="skinquiry_contact_country" required="required" />
            </fieldset>
            <fieldset>
                <legend>'.__('Products', 'skinquiry' ).'</legend>
                <div id="skinquiry_productlist">
                    <div class="skinquiry_product" data-rowid="0">
                        <label for="skinquiry_products_0_product">'.__('Product', 'skinquiry' ).'</label>
                        <select id="skinquiry_products_0_product" name="skinquiry_products[0][product]">';

                        foreach($products as $product) {
                            $content .= '<option value="'.$product->id.'">'.$product->name.'</option>';
                        }

                        $content .= '</select>
                        <label for="skinquiry_products_0_quantity">'.__('Quantity', 'skinquiry' ).'</label>
                        <input id="skinquiry_products_0_quantity" name="skinquiry_products[0][quantity]" value="1" type="text" />
                        <span class="skinquiry_delete">'.__('Remove', 'skinquiry' ).'</span>
                    </div>
                </div>
                <button id="skinquiry_addproduct">'.__('Add line item', 'skinquiry' ).'</button>
            </fieldset>
            <fieldset>
                <legend>'.__('Comment', 'skinquiry' ).'</legend>
                <label for="skinquiry_comment">'.__('Your message for us', 'skinquiry' ).'</label>
                <textarea id="skinquiry_comment" name="skinquiry_comment"></textarea>
            </fieldset>';

        // captcha
        if ($captcha) {
            $content .= '
            <fieldset>
                <legend>'.__('Security Check', 'skinquiry' ).'</legend>
                <img src="'.plugins_url('tmp/'.$captchaImage, __FILE__ ).'" alt="captcha" /><br />
                <label for="skinquiry_captcha_word">'.__('Please enter the displayed word', 'skinquiry' ).'</label>
                <input type="text" required="required" id="skinquiry_captcha_word" name="skinquiry_captcha_word" value="" />
                <input type="hidden" id="skinquiry_captcha_prefix" name="skinquiry_captcha_prefix" value="'.$captchaPrefix.'" />
            </fieldset>';
        }

        $content .= '<input type="submit" value="'.__('Request Document', 'skinquiry' ).'" />
            <input type="hidden" id="skinquiry_rowid" name="skinquiry_rowid" value="0" />
            <input type="hidden" id="skinquiry_sentform" name="skinquiry_sentform" value="1" />
        </form>';

        return $content;

    }

    /**
     * submit entered information to salesking api
     * @return bool
     */
    private function send() {
        // set up objects
        $address = $this->getApi()->getObject('address');
        $contact = $this->getApi()->getObject('contact');
        $document = $this->getApi()->getObject($this->options['document_type']);
        $comment = $this->getApi()->getObject('comment');

        // bind address data
        try {
            $address->bind($_POST, array(
                "skinquiry_contact_address1" => "address1",
                "skinquiry_contact_city" => "city",
                "skinquiry_contact_zip" => "zip",
                "skinquiry_contact_country" => "country"
            ));
        }
        catch (SaleskingException $e) {
            return false;
        }

        // bind contact data
        try {
            $contact->bind($_POST, array(
                "skinquiry_contact_organisation_name" => "organisation",
                "skinquiry_contact_last_name" => "last_name",
                "skinquiry_contact_first_name" => "first_name",
                "skinquiry_contact_email" => "email",
                "skinquiry_contact_phone" => "phone_office"
            ));

            // set tags, type and address
            $contact->type = $this->options['contact_type'];
            $contact->tag_list = $this->options['contact_tags'];
            $contact->addresses = array($address->getData());
        }
        catch (SaleskingException $e) {
            return false;
        }

        // save contact
        try {
            $contact->save();
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
                $item = $this->getApi()->getObject("line_item");
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

        // set all the other options for the document
        try {
            $document->contact_id = $contact->id;
            $document->tag_list = $this->options['document_tags'];
            $document->notes_before = $this->options['notes_before'];
            $document->notes_after = $this->options['notes_after'];
            $document->status = "open";
            $document->line_items = $items;
        }
        catch (SaleskingException $e) {
            die(print_r($e));
        }

        // save document
        try {
            $document->save();
        }
        catch (SaleskingException $e) {
            return false;
        }

        // set up comment
        if (isset($_POST['skinquiry_comment']) AND trim($_POST['skinquiry_comment']) != "") {
            try {
                $comment->text = $_POST['skinquiry_comment'];
                $comment->related_object_type = 'Document';
                $comment->related_object_id = $document->id;
                $comment->save();
            }
            catch (SaleskingException $e) {
                return false;
            }

        }

        // generate pdf
        try {
            $this->getApi()->request('/api/'.$this->options['document_type'].'s/'.$document->id.'/print','POST',
                json_encode(array(
                    "template_id" => $this->options['pdftemplate']
                ))
            );
        }
        catch (SaleskingException $e) {
            return false;
        }

        // send mail to contact
        try {
            $mail = new stdClass();
            $mail->email = new stdClass();
            $mail->email->to_addr = $_POST['skinquiry_contact_email'];
            $mail->email->from_addr = "";

            $mail->send = true;
            $mail->archived_pdf = true;
            $mail->template_id = $this->options['emailtemplate'];

            // create mail and send to contact
            $this->getApi()->request('/api/'.$this->options['document_type'].'s/'.$document->id.'/emails','POST',
                json_encode($mail)
            );
        }
        catch (SaleskingException $e) {
            return false;
        }

        if ($this->options['redirect_url']) {
            wp_redirect($this->options['redirect_url']);
            exit();
        }

        return true;
    }

    /**
     * validate frontend inputs
     * @return bool
     */
    private function validate() {

        // last name is set
        if (!isset($_POST['skinquiry_contact_last_name'])) {
            return false;
        }

        // first name is set
        if (!isset($_POST['skinquiry_contact_first_name'])) {
            return false;
        }

        // email is set
        if (!isset($_POST['skinquiry_contact_email'])) {
            return false;
        }

        // email is valid
        if (!preg_match("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$^", $_POST['skinquiry_contact_email'])) {
            return false;
        }

        // address is set
        if (!isset($_POST['skinquiry_contact_address1'])) {
            return false;
        }

        // zip is set
        if (!isset($_POST['skinquiry_contact_zip'])) {
            return false;
        }

        // city is set
        if (!isset($_POST['skinquiry_contact_city'])) {
            return false;
        }

        // country is set
        if (!isset($_POST['skinquiry_contact_country'])) {
            return false;
        }

        // we have at least one product
        if (!isset($_POST['skinquiry_products'])) {
            return false;
        }

        // all products have an id and a quantity
        foreach($_POST['skinquiry_products'] as $product) {
            if (!isset($product['product']) OR !isset($product['quantity'])) {
                return false;
            }
        }

        // check if the captcha is active
        if ($this->options['use_captcha'] == 1 AND class_exists('ReallySimpleCaptcha')) {
            $captcha = new ReallySimpleCaptcha();
            $captcha->tmp_dir = dirname( __FILE__ ) . '/tmp/';

            if (!$captcha->check( $_POST['skinquiry_captcha_prefix'], $_POST['skinquiry_captcha_word'] )) {
                $captcha->remove( $_POST['skinquiry_captcha_prefix'] );
                return false;
            }

            $captcha->remove( $_POST['skinquiry_captcha_prefix'] );
        }

        return true;
    }
}

if (!function_exists('skinquiry_init_function')) {
    function skinquiry_init_function() {
        new SkInquiry();
    }
}

register_activation_hook( __FILE__, array( 'SkInquiry', 'activationHook' ) );
add_action( 'init', 'skinquiry_init_function');


