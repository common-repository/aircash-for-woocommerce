<?php

/**
 * Plugin Name:     Aircash for WooCommerce
 * Plugin URI:      https://aircash.eu/aircash-woocommerce/
 * Description:     Aircash - for quick and simple payments
 * Version:         1.0.6
 * Author:          Aircash
 * Requires PHP:    7.1
 * Author URI:      https://aircash.eu/
 * Text Domain:     aircash
 * Domain Path:     /languages
 * License:         GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'aircash_load_textdomain' );

function aircash_load_textdomain() {
	load_plugin_textdomain( 'aircash-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
if ( ! class_exists( 'WC_Aircash' ) ) {

	class WC_Aircash {

		public static $instance;

		private $gateway;

		public function __construct() {
			include_once __DIR__ . '/vendor/autoload.php';
			include_once __DIR__ . '/includes/class-aircash-encoding.php';
			include_once __DIR__ . '/includes/class-aircash-payment-gateway.php';
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_payment_gateway' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_settings_link' ) );
            add_filter( 'kses_allowed_protocols', array($this, 'update_allowed_protocols'), 10, 1 );
			add_action( 'wp_ajax_nopriv_aircash_order_check', array( $this, 'aircash_order_check' ) );
			add_action( 'wp_ajax_aircash_order_check', array( $this, 'aircash_order_check' ) );
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			if ( is_admin() ) {
				add_action( 'admin_init', array( $this->get_aircash_payment_gateway(), 'admin_init' ) );
				add_action( 'admin_init', array( $this->get_aircash_payment_gateway(), 'check_configuration' ) );
				add_action( 'in_admin_footer', array( $this->get_aircash_payment_gateway(), 'add_html_to_admin' ) );
			}
		}

		public function admin_init() {
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'wp_ajax_aircash_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
			wp_enqueue_script( 'jquery-ui-tabs' );
		}

		private function get_aircash_payment_gateway() {
			if ( ! isset( $this->gateway ) ) {
				$this->gateway = new Aircash_Payment_Gateway();
			}

			return $this->gateway;
		}

		private function get_admin_notices(): array {

			add_thickbox();
			$gateway     = $this->get_aircash_payment_gateway();
			$notices     = [];
			$fatal_error = false;
			if ( ! extension_loaded( 'openssl' ) && $gateway->get_option( 'enabled' ) != 'no' ) {
				$fatal_error = true;
				$notice      = array(
					'class'          => 'notice-error',
					'text'           => __( '"Aircash for WooCommerce" gateway is disabled because OpenSSL extension is not installed.', 'aircash-for-woocommerce' ) . '<br>' .
					                    __( 'Please <a href="https://www.php.net/manual/en/openssl.installation.php" target="_blank">install OpenSSL PHP extension</a> in order to use "Aircash for WooCommerce".', 'aircash-for-woocommerce' ) . '<br>' .
					                    __( 'Contact your web server administrator or your web hosting provider if not sure how to install PHP extensions.', 'aircash-for-woocommerce' ),
					'is_dismissable' => false,
					'dismiss_action' => 'dismiss_notice_openssl_extension_missing',
				);
				if ( ! $notice['is_dismissable'] ) {
					$notices[] = $notice;
				} else {
					if ( get_option( $notice['dismiss_action'] ) !== 'no' ) {
						$notices[] = $notice;
					}
				}
			}
			if ( ( ! extension_loaded( 'imagick' ) && ! extension_loaded( 'gd' ) ) && $gateway->get_option( 'enabled' ) != 'no' ) {
				$fatal_error = true;
				$notice      = array(
					'class'          => 'notice-error',
					'text'           => __( '"Aircash for WooCommerce" gateway is disabled because neither GD or Imagick extension are not installed.', 'aircash-for-woocommerce' ) . '<br>' .
					                    __( 'Please install <a href="https://www.php.net/manual/en/image.installation.php" target="_blank">GD</a> or  <a href="https://www.php.net/manual/en/imagick.setup.php" target="_blank">Imagick</a> PHP extension in order to use "Aircash for WooCommerce".', 'aircash-for-woocommerce' ) . '<br>' .
					                    __( 'Contact your web server administrator or your web hosting provider if not sure how to install PHP extensions.', 'aircash-for-woocommerce' ),
					'is_dismissable' => false,
					'dismiss_action' => 'dismiss_notice_gd_imagick_extension_missinzg',
				);
				if ( ! $notice['is_dismissable'] ) {
					$notices[] = $notice;
				} else {
					if ( get_option( $notice['dismiss_action'] ) !== 'no' ) {
						$notices[] = $notice;
					}
				}
			}

			if ( $gateway->is_in_test_mode() ) {

				$notice         = array(
					'class'          => 'notice-warning',
					'text'           => __( '"Aircash for WooCommerce" is in <b>test mode.</b>', 'aircash-for-woocommerce' ),
					'is_dismissable' => true,
					'dismiss_action' => 'dismiss_notice_aircash_test_mode',
				);
				$notices[]      = $notice;
				$account_status = $gateway->get_test_account_request_status();

			} else {
				$account_status = $gateway->get_account_request_status();
			}

			if ( ! $fatal_error ) {

				if ( $account_status == 'plugin_installed' ) {
					$notice    = array(
						'class'          => 'notice-info',
						'text'           =>
							__( '"Aircash for WooCommerce" is almost ready.', 'aircash-for-woocommerce' ) . '<br>' .
							sprintf( __( '<a href="%s" class="thickbox open-plugin-details-modal">Connect with Aircash</a> to submit a new account request or configure Aircash settings <a href="%s" class="thickbox open-plugin-details-modal">manually</a>.', 'aircash-for-woocommerce' ),
								get_admin_url( null, 'admin.php?page=wc-settings&tab=checkout&aircash=new_account_request&width=700' ),
								get_admin_url( null, 'admin.php?page=wc-settings&tab=checkout&aircash=manual_configuration&width=700&height=700' )
							) . '<br>' .
							sprintf( __( 'If you want to test "Aircash for WooCommerce", enable the &quot;Test mode&quot; in the <a href="%s">payment gateway settings</a>.', 'aircash-for-woocommerce' ),
								get_admin_url( null, 'admin.php?page=wc-settings&tab=checkout&section=aircash-woocommerce' )
							),
						'is_dismissable' => false,
					);
					$notices[] = $notice;
				} elseif ( $account_status == 'sent' ) {
					$notice    = array(
						'class'          => 'notice-info',
						'text'           =>
							__( '"Aircash for WooCommerce" is almost ready.', 'aircash-for-woocommerce' ) . '<br>' .
							sprintf( __( 'You have successfully submitted a new account request. Please wait until Aircash verifies your account and then update account status request by <a class="thickbox open-plugin-details-modal" href="%s">checking the configuration</a>.', 'aircash-for-woocommerce' ),
								get_admin_url( null, 'admin.php?page=wc-settings&tab=checkout&aircash=check_configuration&width=700&height=700' )
							) . '<br>' .
							'',
						'is_dismissable' => true,
						'dismiss_action' => 'dismiss_notice_aircash_account_request_sent'
					);
					$notices[] = $notice;
					#} elseif ( $account_status == 'declined' ) {
				} elseif ( $account_status == 'error_sending' ) {
					$notice    = array(
						'class'          => 'notice-error',
						'text'           => sprintf( __( 'There has been an error while sending new account request to Aircash. Please check the <a href="%s">log file</a> for details.', 'aircash-for-woocommerce' ),
							get_admin_url( null, 'admin.php?page=wc-settings&tab=checkout&section=aircash-woocommerce' ) ),
						'is_dismissable' => true,
						'dismiss_action' => 'dismiss_notice_aircash_account_request_error_sending',
					);
					$notices[] = $notice;

				}
			}

			return $notices;
		}

		public function admin_notices() {
			foreach ( $this->get_admin_notices() as $notice ) {
				$show = true;
				if ( $notice['is_dismissable'] ) {
					$dismissable_class = 'is-dismissible';
					if ( isset( $notice['dismiss_action'] ) && get_option( $notice['dismiss_action'] ) ) {
						$show = false;
					}
				} else {
					$dismissable_class = '';
				}
				if ( ! $show ) {
					continue;
				}

				?>
                <div class="notice notice-warning <?php echo esc_attr( $dismissable_class ); ?> <?php echo esc_attr( $notice['class'] ); ?>">
                    <p>
						<?php
						echo wp_kses(
							$notice['text'],
							array(
								'br'     => array(),
								'a'      => array(
									'href'        => array(),
									'title'       => array(),
									'class'       => array(),
									'data-toggle' => array(),
								),
								'strong' => array(),
								'em'     => array(),
							)
						);
						?>
                    </p>
                    <script type="application/javascript">
                        (function ($) {
                            $('.<?php echo esc_js( $notice['class'] ); ?>').on('click', '.notice-dismiss', function () {
                                jQuery.post("<?php echo admin_url( 'admin-ajax.php' ); ?>", {
                                    action: "aircash_dismiss_notice",
                                    dismiss_action: "<?php echo isset( $notice['dismiss_action'] ) ? esc_js( $notice['dismiss_action'] ) : null; ?>",
                                    nonce: "<?php echo esc_js( wp_create_nonce( 'aircash_dismiss_notice' ) ); ?>"
                                });
                            });
                        })(jQuery);
                    </script>
                </div>
				<?php
			}
		}

		public function ajax_dismiss_notice() {
			check_ajax_referer( 'aircash_dismiss_notice', 'nonce' );
			$dismissible_notices = array(
				'dismiss_notice_aircash_test_mode',
				'dismiss_notice_openssl_extension_missing',
				'dismiss_notice_gd_imagick_extension_missing',
				'dismiss_notice_aircash_account_request_sent',
				'dismiss_notice_aircash_account_request_error_sending',
			);
			foreach ( $dismissible_notices as $dismiss_notice ) {
				if ( isset( $_POST['dismiss_action'] ) && $dismiss_notice === $_POST['dismiss_action'] ) {
					update_option( $dismiss_notice, 'no' );
					break;
				}
			}
			wp_die();
		}


		public function add_payment_gateway( $methods ) {
			$methods[] = 'Aircash_Payment_Gateway';

			return $methods;
		}

		public function add_settings_link( $links ) {

			$url           = get_admin_url( null, 'admin.php?page=wc-settings&tab=checkout&section=aircash-woocommerce' );
			$settings_link = '<a href="' . $url . '">' . __( 'Settings', 'aircash-for-woocommerce' ) . '</a>';
			array_unshift( $links, $settings_link );

			return $links;

		}

		/**
		 * @return WC_Aircash
		 */
		public static function get_instance(): self {
			if ( is_null( self::$instance ) ) {
				self::$instance = new WC_Aircash();
			}

			return self::$instance;
		}

		public static function on_install() {
		}

		public static function on_uninstall() {

			delete_option( 'woocommerce_aircash-woocommerce_settings' );
		}

		public static function on_deactivate() {
		}


		public function aircash_order_check() {
			$this->get_aircash_payment_gateway()->check_order();
		}

		public function deactivate() {
		}

		/**
         * "data" is added to allowed protocols because Jetpack plugin breaks images with data URLs
         *
		 * @param array $protocols
		 *
		 * @return array
		 */
		function update_allowed_protocols( $protocols ) {
			$protocols[] = 'data';
			return $protocols;
		}
	}


}

register_activation_hook( __FILE__, array( 'WC_Aircash', 'on_install' ) );
register_uninstall_hook( __FILE__, array( 'WC_Aircash', 'on_uninstall' ) );
register_deactivation_hook( __FILE__, array( 'WC_Aircash', 'on_deactivate' ) );
add_action( 'plugins_loaded', array( 'WC_Aircash', 'get_instance' ), 0 );