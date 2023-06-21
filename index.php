<?php
/*
Plugin Name: Hit dnia
Description: Wtyczka wybiera losowy produkt codziennie o godzinie 8:00 i zwraca jego id.
Version: 1.0
Author: Grzegorz Czarny
*/

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WC_Daily_Random_Product')) {

    class WC_Daily_Random_Product {

        public function __construct() {
            if (!wp_next_scheduled('wc_daily_random_product_event')) {
                wp_schedule_event(strtotime('08:00:00'), 'daily', 'wc_daily_random_product_event');
            }
        
            add_action('wc_daily_random_product_event', array($this, 'select_random_product'));
            add_action('init', array($this, 'manual_select_random_product'));
            add_action('init', array($this, 'register_shortcodes'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        }

        public function select_random_product() {
            $args = array(
                'post_type' => array('product', 'product_variation'),
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
            );

            $product_ids = get_posts($args);
            $random_product_id = $product_ids[array_rand($product_ids)];

            if ($random_product_id) {
                $previous_product_id = get_option('wc_daily_random_product', 0);

                if ($previous_product_id) {
                    $previous_product = wc_get_product($previous_product_id);
                    $previous_product->set_sale_price('');
                    $previous_product->save();
                }

                $product = wc_get_product($random_product_id);
                $regular_price = (float) $product->get_regular_price();  
                $sale_price = $regular_price * 0.93;  
                $product->set_sale_price($sale_price);
                $product->save();

                update_option('wc_daily_random_product', $random_product_id);
            }
        }

        public function manual_select_random_product() {
            if (isset($_GET['select_random_product'])) {
                $this->select_random_product();

                $product_id = $this->get_daily_random_product();

                echo 'Wylosowano produkt o ID: ' . $product_id;
                die();
            }
        }

        public function get_daily_random_product() {
            return get_option('wc_daily_random_product', 0);
        }

        public function register_shortcodes() {
            add_shortcode('hit-dnia', array($this, 'show_daily_product'));
        }

        public function show_daily_product() {
            $product_id = $this->get_daily_random_product();
            $product = wc_get_product($product_id);

            if(!$product) {
                return 'Produkt dnia nie jest dostępny.';
            }

            $savings = $this->calculate_savings($product);
            $time_until_eight_am = $this->get_time_until_eight_am();

            $output = '<div class="daily-deal">';
            $thumbnail_id = $product->get_image_id();
            if($thumbnail_id) {
                $thumbnail_url = wp_get_attachment_image_src($thumbnail_id, 'thumbnail');
                if($thumbnail_url) {
                    $output .= '<img src="' . $thumbnail_url[0] . '" alt="' . $product->get_name() . '">';
                }
            }
            $output .= '<h3>' . $product->get_name() . '</h3>';
            $output .= '<p>' . $product->get_description() . '</p>';
            $output .= '<p>Cena: ' . wc_price($product->get_price()) . ' Oszczędzasz: ' . wc_price($savings) . '</p>';
            $output .= '<p>Następna okazja: ' . $time_until_eight_am . '</p>';
            $output .= '<a rel="nofollow"href="' . $product->get_permalink() . '">Zobacz produkt</a>';
            $output .= '</div>';

            return $output;
        }

        public function enqueue_styles() {
            wp_enqueue_style('wc_daily_random_product', plugin_dir_url(__FILE__) . 'style.css');
        }

        public function get_time_until_eight_am() {
            $now = new DateTime();
            $eight_am = new DateTime('08:00:00');
            if ($now > $eight_am) {
                $eight_am->modify('+1 day');
            }

            return $now->diff($eight_am)->format('%H godz. %I min. %S sek.');
        }

        public function calculate_savings($product) {
            $regular_price = $product->get_regular_price();
            $sale_price = $product->get_sale_price();
            return $regular_price - $sale_price;
        }
    }

    $wc_daily_random_product = new WC_Daily_Random_Product();
}
