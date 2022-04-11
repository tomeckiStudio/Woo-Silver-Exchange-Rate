<?php
defined('ABSPATH') or die('You do not have permissions to this file!');

class Woo_Silver_Exchange_Rate_Backend{

	public function __construct(){
		// Add menu to admin side panel
		add_action('admin_menu', array($this, 'wser_options_page'));

		// Register WSER settings
		add_action('admin_init', array($this, 'wser_settings_init'));
		
		// Add custom fileds to product edit page
		add_action('woocommerce_product_options_general_product_data', array($this, 'wser_product_general_options_add_fields'));

		// Save custom fileds in product edit page
		add_action('woocommerce_process_product_meta', array($this, 'wser_save_product_meta'), 10, 1);
	}

	function wser_options_page() {
		add_menu_page(
			__('Silver Exchange Rate for WooCommerce Options', 'woo-silver-exchange-rate'),
			__('Silver Exchange Rate Options', 'woo-silver-exchange-rate'),
			'manage_options',
			'wser',
			array($this, 'wser_options_page_html'),
		);
	}

	function wser_settings_init() {
		register_setting( 'wser', 'wser_options' );

		add_settings_section(
			'wser_section_options',
			__('Metals-api', 'woo-silver-exchange-rate'), 
			array($this, 'wser_section_options_callback'),
			'wser'
		);

		add_settings_field(
			'wser_api_key', 
			__('Api Key', 'woo-silver-exchange-rate'),
			array($this, 'wser_api_key_callback'),
			'wser',
			'wser_section_options'
		);
	}

	function wser_section_options_callback($args) {
	}

	function wser_api_key_callback($args) {
		$options = get_option('wser_options');
		?>
		<input type="text" id="wser_api_key" name="wser_options[wser_api_key]" value="<?php echo $options['wser_api_key']; ?>">
		<?php
	}

	function wser_options_page_html() {
		if(!current_user_can('manage_options')){
			return;
		}

		if(isset($_GET['settings-updated'])){
			add_settings_error('wser_messages', 'wser_message', __('Settings Saved', 'woo-silver-exchange-rate'), 'updated');

			$api_key = get_option('wser_options')['wser_api_key'];
			$ch = curl_init('https://metals-api.com/api/latest?access_key=' . $api_key . '&base=' . get_woocommerce_currency() . '&symbols=XAG');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			$json = curl_exec($ch);
			curl_close($ch);

			$exchangeRates = json_decode($json);

			$rates = $exchangeRates->rates;
			$silver_price = $rates->XAG;
			
			if(get_woocommerce_currency()=="USD")
				$silver_price = 1/$silver_price;

			if($silver_price > 0){
				global $wpdb;
				$table_name = $wpdb->prefix . "currencies"; 

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
			}else{
				add_settings_error('wser_messages', 'wser_message', __('Unable to get the price from metals-api', 'woo-silver-exchange-rate'), 'error');
			}
		}

		settings_errors('wser_messages');
		?>
		<div class="wrap">
			<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields('wser');
				do_settings_sections('wser');
				submit_button('Save Settings');
				?>
			</form>
		</div>
		<?php
	}

	function wser_product_general_options_add_fields(){
		global $wpdb, $product;
		$table_name = $wpdb->prefix . "currencies";
		$silver_price = 0;

		foreach ($wpdb->get_results("SELECT price, name FROM $table_name") as $retrieved_data){
			if($retrieved_data->name == "silver")
				$silver_price = $retrieved_data->price;
		}

		woocommerce_wp_text_input(array(
			'id' => 'wser_silver_ounce',
			'label' => __('Silver ounces:', 'woo-silver-exchange-rate'),
			'desc_tip' => true,
			'description' => __('Enter the number of ounces of your product.', 'woo-silver-exchange-rate'),
		));
		woocommerce_wp_text_input(array(
			'id' => 'wser_silver_rate',
			'label' => __('Current silver price:', 'woo-silver-exchange-rate'),
			'placeholder' => $silver_price . " " . get_woocommerce_currency(),
			'desc_tip' => false,
			'custom_attributes' => array('readonly' => 'readonly'),
		));
	}

	function wser_save_product_meta($post_id){
		$product = wc_get_product($post_id);
		$product->update_meta_data('wser_silver_ounce', isset($_POST['wser_silver_ounce']) ? sanitize_text_field($_POST['wser_silver_ounce']) : '');
		$product->save();
	}
}
$wser_backend = new Woo_Silver_Exchange_Rate_Backend();
