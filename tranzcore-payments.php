<?php
/**
* Plugin Name: TranzCore Payments
* Plugin URI: http://tranzcore.com
* Description: Easy integration of TranzCore Payments.
* Version: 1.1
* Author: TranzCore Co Ltd
* Author URI: http://www.tranzcore.com
* Copyright: © 2018 TranzCore Co, Ltd.
* License: GNU General Public License v3.0
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
* WC requires at least: 3.0.0
* WC tested up to: 3.5.0
**/

if ( ! defined( 'ABSPATH' ) )
	exit;

add_action('plugins_loaded', 'woocommerce_TRANZCORE_init');

function woocommerce_TRANZCORE_init() {
	
	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
	
	/**
	 * Gateway class       
	*/
	class WC_Gateway_TranzCore_Payments extends WC_Payment_Gateway {
		
		public function __construct() {
			
			$this->id				= 'tranzcore';
			$this->method_title     = __( 'TranzCore', 'woocommerce' );
			$this->icon 			= plugins_url( 'images/logo.png' , __FILE__ );
			$this->method_description = 'TranzCore redirects customers to TranzCore to enter their payment information.';
			$this->has_fields 		= false;
			
			$this->init_form_fields();
			$this->init_settings();
			
			$this->title 			= $this->settings['title'];
			$this->description      = $this->settings['description'];
			$this->tranzcoreid   	= $this->settings['tranzcoreid'];
			$this->liveorsanbox     = $this->settings['liveorsanbox'];

			$this->notify_url       = home_url( '/wc-api/payment_tranzcore' );

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
						
			add_action('woocommerce_thankyou_'.$this->id, array( $this, 'thankyou_page' ) );
			add_action('woocommerce_receipt_'.$this->id, array( $this, 'receipt_page' ));	

			//IPN action
			add_action( 'woocommerce_api_payment_'.$this->id, array( $this, 'check_TRANZCORE_response' ) );

			if($this->liveorsanbox  =='yes'){
				$this->_url ='https://secure.tranzcore.com/checkout';
			}
			else{
				$this->_url ='https://sandbox.tranzcore.com/checkout';
			}
		
		} 
		
		function init_form_fields() {
			
			$this->form_fields = array(
				'enabled' => array(
								'title' => __( 'Enable/Disable', 'woocommerce' ), 
								'type' => 'checkbox', 
								'label' => __( 'Enable TranzCore', 'woocommerce' ), 
								'default' => 'yes'
							), 
				'title' => array(
								'title' => __( 'Title', 'woocommerce' ), 
								'type' => 'text', 
								'description' => __( 'This controls the title which the user sees during checkout', 'woocommerce' ), 
								'default' => __( 'TranzCore Payments', 'woocommerce' )
				),
				'tranzcoreid' => array(
								'title' => __( 'Merchant Key', 'woocommerce' ), 
								'type' => 'text', 
								'default' => ''
							),
			    'liveorsanbox' => array(
								'title' => __( 'Live or Sandbox', 'woocommerce' ), 
								'type' => 'checkbox', 
								'label' => __( 'Live Mode', 'woocommerce' ),
                                'default' => 'yes'
							),
				'description' => array(
								'title' => __( 'Description', 'woocommerce' ),
								'type' => 'textarea',
								'default' => __( 'Pay via TranzCore: you can pay with MTN Mobile Money, Orange Money or Nexttel Possa.', 'woocommerce' )
					)
			);
		}
		
		/**
		 * Admin Panel Options
		 * - Options for bits like 'title' and availability on a country-by-country basis
		**/
		public function admin_options() {
		
			echo '<h3>'.__('TranzCore Payments', 'woocommerce').'</h3>';
			echo '<table class="form-table">';
			
			$this->generate_settings_html();
			echo '</table>';
		}
		
		/**
		 * There are no payment fields for TRANZCORE, but we want to show the description if set.
		**/
		function payment_fields() {
			if ($this->description) echo wpautop(wptexturize($this->description));
		}
		
		/**
		 * Receipt Page
		**/
		function receipt_page( $order ) {
			echo '<p>'.__('Thank you for your order, please click the button below to pay with TranzCore.', 'woocommerce').'</p>';
			echo $this->generate_TRANZCORE_form( $order );
		}
		
		/**
		 * Process the payment and return the result
		**/
		function process_payment( $order_id ) {
			$order = new WC_Order( $order_id );
			return array(
				'result' 	=> 'success',
				//'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
				'redirect' => $order->get_checkout_payment_url( true )
			);
		}
		

		function thankyou_page() {
			if ($this->description) {
				echo wpautop(wptexturize($this->description));
			}
			
			echo '<h2>'.__('Our Details', 'woocommerce').'</h2>';
			echo '<ul class="order_details ppay_details">';
			$fields = array(
				'ppay_number'=> __('TRANZCORE', 'woocommerce')
			);
			
			foreach ($fields as $key=>$value) :
				if(!empty($this->$key)) :
					echo '<li class="'.$key.'">'.$value.': <strong>'.wptexturize($this->$key).'</strong></li>';
				endif;
			endforeach;
			echo '</ul>';
		}
		
		function generate_TRANZCORE_form( $order_id ) {
			global $woocommerce;
			$order = new WC_Order($order_id);
			$form = "";
			$params = array('apreceiver' => $this->tranzcoreid  ,
							'apcurrency' => get_woocommerce_currency(),
							'apinvoice' => $order_id+1000,
							'item_1_quantity' => '1',							
							'item_1_name'=> 'Order #'.$order_id.'',
							'item_1_price' =>$order->get_total(),
							'apemail' =>$order->get_billing_email(),
							'apstreet' =>$order->get_billing_address_1(),
							'apcity' =>$order->get_billing_city(),
							'apregion' =>$order->get_billing_state(),
							'apcountry' =>$order->get_billing_country(),
							'apphone' =>$order->get_billing_phone(),
							'apipn' =>  $this->notify_url,
							'apfname' =>$order->get_billing_first_name(),
							'aplname' => $order->get_billing_last_name(),
							'apzip' => $order->get_billing_postcode(),
							'apreturnsuccess' => $this->get_return_url( $order ),
							'apreturnfail' => $order->get_cancel_order_url()
						);
			
			$invoice_args_array = array();
			foreach($params as $key => $value){
				$invoice_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
			}
			
			$form .= '<form id="tranzcoresubmit"  action="'.esc_url($this->_url).'" method="POST" name="process" target="_top">';
			$form .= implode(PHP_EOL, $invoice_args_array);
			$form .= '<input type="submit" class="button-alt" id="submit_tranzcore_payment_form" value="'.__('Pay via TranzCore', 'woocommerce').'" />';
			$form .= '<a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__('Cancel order &amp; restore cart', 'woocommerce').'</a>';
			$form .= '</form>';
			
			wc_enqueue_js( '
				$.blockUI({
					message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to TranzCore to make payment.', 'woocommerce' ) ) . '",
					baseZ: 99999,
					overlayCSS:
					{
						background: "#fff",
						opacity: 0.6
					},
					css: {
						padding:        "20px",
						zindex:         "9999999",
						textAlign:      "center",
						color:          "#555",
						border:         "3px solid #aaa",
						backgroundColor:"#fff",
						cursor:         "wait",
						lineHeight:     "24px",
					}
				});
				jQuery("#submit_tranzcore_payment_form").click();
			' );
			
			return $form;
		}

		function check_TRANZCORE_response(){
			global $woocommerce;

			if ( ! isset( $_POST['apstatus'] ) ) {
               wp_die( 'apstatus is a required parameter for this endpoint' );
             }

          foreach ($_POST as $key => $value) 
            {
              $postFields .="&$key=".urlencode($value);    
            }

          $url = 'https://secure.tranzcore.com/verify';
          $args = array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
            'body' => $postFields,
            'cookies' => array()
             );

          $response = wp_remote_post( $url, $args );
          $content  = wp_remote_retrieve_body($response);

          $apstatus  = sanitize_text_field($_POST['apstatus']); 
          $order_id  = sanitize_text_field($_POST['apinvoice']);          
         
          if($content == "VERIFIED" && $apstatus == "Completed"){
          global $woocommerce;
          if( !empty($order_id) ){
          $order    = new WC_Order($order_id-1000);
          if ('processing' || 'Pending payment' == $order->status) {
          $order->update_status('completed');
		  $order->add_order_note('TranzCore payment successful<br/>Transaction Number: '.$transid);
		  $order->add_order_note("Thank you for shopping with us. Your account has been debited and your transaction is successful. We will be shipping your order to you soon.");
		  $woocommerce->cart->empty_cart();
          }
          }
         return;
         }
        }
    }
	
	    /**
	   * Add the TRANZCORE gateway to WooCommerce
	   **/
	   function woocommerce_add_TRANZCORE_gateway($methods) {
		$methods[] = 'WC_Gateway_TranzCore_Payments';
		return $methods;
	   }
	
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_TRANZCORE_gateway' );
}

/**
 * Debug
**/
function tranzcore_debug($var){
    echo '<pre style="background:white;border:1px dashed red;position:absolute;top:200px;left:200px;">';
    print_r($var);
    echo '</pre>';
}
?>