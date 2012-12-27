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

        add_action( 'init', array($this, 'send') );
        add_action( 'admin_init', array($this, 'adminInit') );
        add_action( 'admin_menu', array($this, 'adminMenu') );
        add_shortcode( 'skinquiry', array($this, 'display') );
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
                echo '<textarea id="skinquiry_notes_before" name="skinquiry_options[notes_before]" rows="5" cols="40" type="text" value="'.$this->options['notes_before'].'"></textarea>';
                break;

            case "skinquiry_notes_after":
                echo '<textarea id="skinquiry_notes_after" name="skinquiry_options[notes_after]" rows="5" cols="40" type="text" value="'.$this->options['notes_after'].'"></textarea>';
                break;
        }
    }

    public function fetchProducts() {
        $products = $this->api->getCollection(array(
                'type' => 'product',
                'autoload' => true
            ));

        try {
            $products->load();
        }
        catch (SaleskingException $e) {
            return false;
        }

        return $products;
    }

    public function send() {

    }

    public function validate() {

    }

    public function display() {
        return "dass";
    }

    public function validateSettings($input) {
        return $input;
    }

    public function settings() {
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

    public function adminMenu() {
        add_options_page('SalesKing Inquiry Settings', 'SkInquiry', 'manage_options', 'skinquiry', array($this, 'settings'));
    }
}

add_action( 'init', function() {
    $SkInquiry = new SkInquiry();
} );


