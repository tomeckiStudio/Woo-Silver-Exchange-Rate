<?php
defined('ABSPATH') or die('You do not have permissions to this file!');

class Woo_Silver_Exchange_Rate_Frontend{

	public function __construct(){
		// Get exchange rate from metals-api
		add_action('init', array($this, 'wser_get_metals_exchange_rate'));
		
		// Add silver exchange rate to price
		add_filter('woocommerce_get_price_html', array($this, 'wser_get_price_html'), 20, 2);
		
		// Display margin from spot on single page
		add_filter('woocommerce_before_add_to_cart_form', array($this, 'wser_before_add_to_cart_form'));
		
		// Display margin from spot on shop page
		add_filter('woocommerce_after_shop_loop_item', array($this, 'wser_after_shop_loop_item'), 5);
		
		// Add silver exchange rate to price in cart
		add_filter('woocommerce_cart_item_price', array($this, 'wser_cart_item_price'), 110, 3);
		
		// Calculate total with silver exchange rate
		add_action('woocommerce_before_calculate_totals', array($this, 'wser_before_calculate_totals'), 20);
		
		// Display current exchange rate on page
		add_action('wp_footer', array($this, 'wser_exchange_rate_note'));
	}

	function wser_get_metals_exchange_rate(){
		if(isset($_REQUEST['metals-cron'])){
			if($_REQUEST['metals-cron'] == 'active'){
				global $wpdb;
				$table_name = $wpdb->prefix . "currencies"; 

				$api_key = get_option('wser_options')['wser_api_key'];
				$ch = curl_init('https://metals-api.com/api/latest?access_key=' . $api_key . '&base=' . get_woocommerce_currency() . '&symbols=XAG');
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

				$json = curl_exec($ch);
				curl_close($ch);

				$exchangeRates = json_decode($json);

				$rates = $exchangeRates->rates;
				$silver_price = $rates->XAG;

				if(!($silver_price > 0)){
					foreach ($wpdb->get_results("SELECT price FROM $table_name WHERE name='silver'") as $retrieved_data){
						$priceFromDB = $retrieved_data->price;
					}
					$silver_price = $priceFromDB * 1.10;
				}

				$wpdb->update(
					$table_name,
					array(
						'price' => $silver_price,
						'date' => date("Y-m-d H:i:s")
					),
					array(
						'name'=>'silver'
					)
				);
			}
		}
	}

	function wser_get_price_html($price_html, $product){
		if($product->get_regular_price()=="") return $price_html;	

		$product_id = $product->get_id();

		if(get_post_meta($product_id, 'wser_silver_ounce', true) > 0){
			$price_html = $this->wser_get_format_price($product_id, $product);
		}

		return $price_html;
	}

	function wser_before_add_to_cart_form(){
		global $product;

		if ($product->get_regular_price()=="") return;

        $product_id = $product->get_id();
		if(get_post_meta($product_id, 'wser_silver_ounce', true)>0){
			echo __('Percentage margin on spot price:', 'woo-silver-exchange-rate') . " " . round($this->wser_get_margin_from_spot($product_id, $product), 2) . "%<br>";
		}
	}

	function wser_after_shop_loop_item(){
		global $product;

		if ($product->get_regular_price()=="") return;

        $product_id = $product->get_id();
		if(get_post_meta($product_id, 'wser_silver_ounce', true)>0){
			echo "<div class='woocommerce-loop-product__wser_additional_info'>";
			    echo __('Margin from spot:', 'woo-silver-exchange-rate') . " " . round($this->wser_get_margin_from_spot($product_id, $product), 2) . "%<br>";
			echo "</div>";
		}
	}

    function wser_get_margin_from_spot($product_id, $product){
        global $wpdb;
        $table_name = $wpdb->prefix . "currencies"; 

        $silver_price = 0;
        foreach ($wpdb->get_results("SELECT price, name FROM $table_name") as $retrieved_data){
            if($retrieved_data->name == "silver")
                $silver_price = $retrieved_data->price;
        }

        $margin_from_spot = 0;

        if($product->is_on_sale()){
            $margin_from_spot = ($product->get_sale_price() / ($silver_price * get_post_meta($product_id, 'wser_silver_ounce', true)))*100;
        }else{
            $margin_from_spot = ($product->get_regular_price() / ($silver_price * get_post_meta($product_id, 'wser_silver_ounce', true)))*100;
        }

        return $margin_from_spot;
    }

	function wser_cart_item_price($price_html, $cart_item, $cart_item_key){
		$product = $cart_item['data'];
		if($product->get_price()=="") return $price_html;	

		$product_id = $product->get_id();
		if(get_post_meta($product_id, 'wser_silver_ounce', true)>0){
            $price_html = $this->wser_get_format_price($product_id, $product);
		}
		return $price_html;
	}

    function wser_get_format_price($product_id, $product){
        global $wpdb;
        $table_name = $wpdb->prefix . "currencies"; 

        $silver_price = 0;
        foreach ($wpdb->get_results("SELECT price, name FROM $table_name") as $retrieved_data){
            if($retrieved_data->name == "silver")
                $silver_price = $retrieved_data->price;
        }

        $product_price = $product->get_regular_price();
        $product_price = $product_price + ($silver_price * (get_post_meta($product_id, 'wser_silver_ounce', true)));
        $price_html = wc_price($product_price);

        if($product->is_on_sale()){
            $sale_price = $product->get_sale_price() + ($silver_price * get_post_meta($product_id, 'wser_silver_ounce', true));

            $price_html = wc_format_sale_price($product_price, $sale_price);
        }

        return $price_html;
    }

	function wser_before_calculate_totals($cart){
		global $wpdb;
		$table_name = $wpdb->prefix . "currencies"; 

		$silver_price = 0;
		foreach ($wpdb->get_results("SELECT price, name FROM $table_name") as $retrieved_data){
			if($retrieved_data->name == "silver")
				$silver_price = $retrieved_data->price;
		}

		foreach ($cart->get_cart() as $cart_item_key => $cart_item){
			$product = wc_get_product($cart_item['product_id']);
			$product_id = $product->get_id();
			if(get_post_meta($product_id, 'wser_silver_ounce', true)>0){
				$product_price = $product->get_price();
				$cart_item['data']->set_price($product_price + ($silver_price * (get_post_meta($product_id, 'wser_silver_ounce', true))));
			}
		}
	}
	
	public function wser_exchange_rate_note(){
		global $wpdb;
		$table_name = $wpdb->prefix . "currencies"; 
		$silver_price = 0;
		
		foreach ($wpdb->get_results("SELECT price, name FROM $table_name") as $retrieved_data){
			if($retrieved_data->name == "silver")
				$silver_price = $retrieved_data->price;
		}

		echo "<div class='currentSilverRate'>" . __('Current spot silver price:', 'woo-silver-exchange-rate') . " " . wc_price($silver_price) . "</div>";
		echo "<style>";
			echo ".currentSilverRate{position:fixed;bottom:0;left:0;background:#C0C0C0;color:#000;z-index:100;padding:5px 20px;font-weight:400;font-size:15px;}";
		echo "</style>";
	}
	
}
$wser_frontend = new Woo_Silver_Exchange_Rate_Frontend();
