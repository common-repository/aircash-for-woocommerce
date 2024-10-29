<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( class_exists( 'Aircash_Payment_Gateway' ) || ! class_exists( 'WC_Payment_Gateway' ) ) {
	return;
}

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;
use Endroid\QrCode\QrCode;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\PlainTextRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use Ramsey\Uuid\Uuid;

class Aircash_Payment_Gateway extends WC_Payment_Gateway {
	/**
	 * @var WC_Logger|null
	 */
	private static $log;

	/**
	 * @var int[]
	 */
	private $supported_currencies = array(
		'EUR' => 978,
	);

	private $platform_id = 'eea49b69-8326-4acc-85c4-c35d3eab2919';
	private $platform_password_encoded = 'QUAiK34tITVEWHhrcCEscEtxeDh8eTE=';
	private $platform_private_key = 'LS0tLS1CRUdJTiBFTkNSWVBURUQgUFJJVkFURSBLRVktLS0tLQpNSUlKcERCT0Jna3Foa2lHOXcwQkJRMHdRVEFwQmdrcWhraUc5dzBCQlF3d0hBUUlVeitDUURtQjk3a0NBZ2dBCk1Bd0dDQ3FHU0liM0RRSUpCUUF3RkFZSUtvWklodmNOQXdjRUNHVUdKVUtaTUZFa0JJSUpVSUVBMDlsQXo5L2IKRC9GTTVWcGJPK0c0N2tvRTFzOTJwcDAvQjZmaEpIUWs5Skg1bG51SXVyVGY0dkl3NkszUEJxNElmZDd1ZEFaRgpmUitSL2xxUEI3amRMT0NiczlGNTR5dmxkYkszaXkxK1R0SVRDYmlyN0crZGNHNU12NFM0YmRINFZVTTVoS1drCjdoZ21NSUpBakZoVStTbFFKcXk2emVHemZsYm9XZkJTVHkwbEg4YzFhT21keFpBL3V4b0srSytBTE5jaW05VVMKRVlPVHlsU0tlREpCQ280bTVXWkpoeXplWitXMkQ2emN5NVViZEZITjY1ZnMveWdTZDBlODZqV2FvNHh2VUNkdgpFU2duYWNMeGpaS1N1WGY0QjJMREZ4cEMzUmt1SU93MzNya3VkY0JBRVZWZExqM2xROE5ERVBhc0dvc0R0Z3BsClJuYUtHcVhKUmNrYkVWMm5DclRtMnpwL21nSEYxb2dPdEpRRmtQL0tzVmNlVHlBZjlKMXNXSVo3NkFWZ0Q1dkcKSnh5cFRsOW5oMWVGdUtzbGhOZW8rRXhxSkxvVnpiME5laysrYXZrc3RseDBZblV4T014aWVyc2l0NnNCVmF3bApaRTVkWGo1bzNEYmwyUmdHVDVOV3FMbzd5ci81Rzd5c2hCb1MzNUdRcnlqWmhPbTFwZzBJZVh3YXFWMUQ5Vm1PCjZmRTU0R2dqTzM4VW5qWjNLcjZVVVJSaW53L2YzeVV2S2RtRnFjd2huWVBjbDlabzh1OXkrQVAzNEQrRHNpUVUKeWxFZDBaYXdQZlo1aHBKeEUvMndKSHpTUUdBYXJ4c0s2Nm1yT1N0UGJqZHFwcVh2VWlYV3BpU3BaajVYUndCSwpoTjVVM2FSUGZvblJINzlJak5UdVR0aHBGMzN3M1pOQTVGSzBwcnlDNWZ1M0VFc204Zm5zNjhEZmE0cTd1aWEvCm9JQVF0MTc2RVlrNGhwNVV2dk9aTW1QZmxLRmhtYlV4R1dqcGpxcVhGZ1BuaU5vQnZoTEkvVXlndEh1Y0NLQ24KU2ZVN0VqTW1rY3NBa1hnVXdwYWNwdlJqaVlMOEc3UXBsSDY3SWsxQjlXYkZnV3RaWURVdkhWWnpEQ3Iwc2N3MQpxL2FIQVBYZktlK2l1SkdVbmZLSTExTEFXZzZqdmhxTHh6Mm5ZS1FnL3N2dUFucENiZmN0dU1seTdZSDRzMTBxCjlLU2YzeE14K2swZlpsM0dZdE9CcFR0SmFCaWJaaENwNXZpcllackxNTXptNzgrUVZFK0MzY1pQbVo4S2x4QVcKbG9PZGlObXJRWGJxWWRrVysvQThVSFVLVitaakcwWVo2b3RSTlg2OVJhZWtFb21nUXVYdXhHdVRTVURySGROUwordUxiMEM4Z1dIWk12NS9XeHBURktDRlRFSWpSQzN5WmNFbEw3Si9tTk9QWVV4SUM1V3NqcGNWYTNOcHl5cDY5ClgzNnJRSWV5ZUQ4a2ZUR1UvQVNJQVFQcGEwTXU3NFlTR1V6aERwSS9CcHZCNHNGbEdIM2hWK2ZLMEJidENpdVkKb0d2OEVTeGdOTVkyTS9hMGZLVWNoVW1iUndGMEIrck4yVGdoa0NRdGFKM0J1a3E0RnFaRVB0MStVbFhQRzJYagptaHFicDNFT1Z2Nll6Y0ltTlo0bFRLTFdIQnZCNWRFUmxURmxwaDl0N0hraGNoTnFvYmpGeFUreFQ3ai94V1JFCm11Mm1abFdFbCttMStqVXdkTnU1RlYxMEFGbkZBZjFFUFAzRXJ0ZGNhRXlrcVoyenFRZFRDOEs4WUx6WXdSWjcKcmlVQnVWamdNUUs0R2lYendFUkVtV1dXNUJwVkZiZjJsV0Q2OWFsWTV1RVdZUmkzM1RQZ2ZTK2wvYjhlTEgxaApNNEtOR2J4elowOWk4dThNdmFuc29WSzg5a0FEYTRCaUFzZ2kzUWVNK3pwWXd0MEd1Z29lUEp6eVVQNFhjaXNOCjcreVl4UW9sTE5ESmJydkYySXQvYm5xdzhTY3h0Z05IV2tYNlIyNm1oOHJUSTdQL3J3WGZIMzBhMndGZzVxOGYKbDl2RE5jUkNQUXNnTlo4RVB6ejhLa3FpbUdZZDVyV2w1anJUVVhhWk1KNW5ZM3RXOXNvZXh5MGZhNjFqZ0dqNApPWmFCOUptQkhQeUs1N2FaVCtyVEJuZkFBVFU5VFgrbjUwTXNzbzcrVXpoaXU0Zm9ZL2JQSmloKzI2T3BiZzFBCmRKVzNZQUVkcmIwWXZNSjB5Z1NQcmRzaCtxOGdCMjd2aFh6d3Z3eFprUVN4alJVblBUTmovTGY2UDFxazY1OEcKUjFuZy91SitJT21XZkxpMi9pNHdEaWJ5SFlYbXJ2eXc0bEI2RWxDcmJHOEs0cTN5WkRWY0Z5MG9VNFMwWXVZagpOVmRCM2NralZ4UytCNWJGTGhzeTlCNE50RnJXa3UvZW1BU3VtcWJUdXZ1bGNveCtYZlM0MTB4MkhMbXlIZ1VkCjZ5NHIzTWpjT2NmUnR3YkViSGZPQkwyS3Z5c0x0K2xibUFoRnJaQUFmQVI3QTBZUGFxRTg2UDBlWVZxZE56SjMKd3lSNWEwZ1h4SUkrdEg0WVR4WDhaelQwWXV2c3IzMXV5Nzl4cGxlVnJCak5lR0p1eVZJaGx5YTNvcW9oZzk4TgpvcERWd1FDbXJRcVJCdUM5NzFyVTBKeUhBTmtoQnN5eEpaWWJGa2c4ckR2VjJBaTIwRVFMZmxEbnlDNEljc1BIClBKV1g2ekFiY05WYVo2ZnVzblByTzJkTDgwSnJqYkdrcFZmWjIxV2Q2Y1JuUExzb3YvNkRUZzh0N2lrOGtyczAKYityVGRTUUQ0NHhFamJkT09DQXBieG9pMDA1bGxwaFRJbHBXZHdRd0hpdFdKSzg0SE1Oc3NNN3B0eiticWJadwpiWkF1Zlg3akdtbnh5NzNSRng4Nk1pTDdoUFlYRWNUaFhtSFBMZjhKTGgxRHRrYjRaOFlUd0FCeTBaYWtYRFRBCno4L3JIUEtQT1QyVjcyNzhDNnJ3ZlZ4eFZSRk1HQ0s3MVpEdkRXVjh2bUhLREc0RHFDcy9ib285cFA5M1JZVU8KcDErcWlrOWZTNUdnTUhXZ2lsVWlVUzBuKytNMUV5OWV0dGhXdFJvazcvOHBOM0N4TFFrOEphSlRMdnhINkNlRgpqNDJEZUJ5UE5qOFdzVllTRUFnQkdHZnJQYnQ5dVJLN21sRWh5UWlyYTh2SFNRRjZkWkpXVHI1YW9FTmxLeVB2CmwyaE9oN1FDdGdFNElCQ29QUE5FMWR0am9IemdqZEdFVElGUjRDTERsL3ZaUlNhaVZ5Q1cxTHZXeDdxV0RZcXkKZkJlZ3hzTGtpaXh1TldwTERNMDV6WTVIekZzWDBGb3ZhVGtTeHJqbTFKQmlRWjI5N0JacE56Q2hOTVA1emNnQgphUnEwVmFrbVUyeGlsck9LN042TGFwODJoblJKN0xwVVVmUW1oM0JnK1VVTUlWdHdEcEczQXRnL1p2MFN0ellECjRHc2FPaFl6S0E2eStMYXFXdDJDbWVLQ2Q2ZGFuZkJ4Wm91T2hobXVRYWNuVSt6VWMvTWFNT0pDdFd4QWxGMEgKVzdQL2hIUFdoY3piR3hsRGFmeXozOS9YaDlsMmpLUkdOclFJMlhvYzNsMG00THJ1YXpyVVpiMjRhSklrYkg5SwphdFRvcHdqaFZUOWVnRU9CQnlwWW9IUytZVWR5djdZWFBsK3Vid2xtQmd4UnNwb1Nua09mWHoxNnBUYWFwT0dGCmc3ZkRCdHRFK0N2aG1KQldMOTJrMnhvTTROaE1IVDljTFJTNVdBdjU4SlRiSytPNnRkOXlKUmZrMUxGb2ZDYmcKa3J4NmdYQmhUQkF2WUlzbzJyOCtVWldQSmlDVWRqdHAxMlV2Vkx3dlk5aHVZbVptZE12aW5uUlpldXFYMmp3WQpuNGJuT04yQmNEMk81d2g1amFtN1dhVFI1SUc5bmZObllOUU5KaFRkdnNDOXRNbXRTNjRZL25tL1F3cjhMNXNVCmZEVk1GK2xqUTViR3ByNlNuOEt1aU5tWU5MTjU3NDc4TlluUDh0cTU0S2s1L0dNQkxNb3hjZE1FMFFHWDRrbVEKbmlNT3JXZXVQTmNjcEpqRTZQUkZDQXB2VlZiY0Z6YXUzc0FoVlg4N29XMHFEU3lUNysxVXFxWjlDdzVjL05XRApobTJsM2NSQnhVUk5ROEpmYWlUR21Sd2Z2d1NSOHhZZAotLS0tLUVORCBFTkNSWVBURUQgUFJJVkFURSBLRVktLS0tLQo=';

	/**
	 * @var mixed|void
	 */
	private $account_activation_form_fields;
	private $manual_form_fields;

	/**
	 * Initialize settings form and add actions
	 */
	public function __construct() {
		$this->id                 = 'aircash-woocommerce';
		$this->has_fields         = true;
		$this->supports           = array( 'products', 'refunds' );
		$this->method_title       = 'Aircash';
		$this->method_description = 'Aircash';

		$this->init_form_fields();
		$this->init_settings();

		$this->title = 'Aircash';

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array( $this, 'process_admin_options' )
		);
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'display_aircash_payment_page' ) );
		add_action( 'woocommerce_api_aircash', array( $this, 'handle_callback' ) );
		add_filter( 'wp_enqueue_scripts', array( $this, 'add_frontend_scripts' ) );
		add_action( 'aircash_cron_hook', array( $this, 'update_aircash_account_status' ) );
		add_action( 'admin_head', array( $this, 'remove_manual_refund_button_for_aircash_orders' ) );
	}

	private function get_plugin_url(): string {
		return untrailingslashit( plugins_url( '/', realpath( __DIR__ . '/../aircash-woocommerce.php' ) ) );
	}

	/**
	 * Hides the manual refund button
	 *
	 * @return void
	 */
	public function remove_manual_refund_button_for_aircash_orders() {
		global $post;
		if ( ! $post ) {
			return;
		}
		if ( $post->post_type !== 'shop_order' ) {
			return;
		}
		$order = new WC_Order( $post->ID );
		if ( 'aircash-woocommerce' !== $order->get_payment_method() ) {
			return;
		}
		echo '<style>.do-manual-refund {display: none !important;}</style>';
	}

	/**
	 * admin_init hook.
	 */
	public function admin_init() {
		if ( ! is_admin() ) {
			return;
		}
		wc_enqueue_js( file_get_contents( __DIR__ . '/../assets/js/aircash-admin.js' ) );
		wp_enqueue_script( 'aircash', $this->get_plugin_url() . '/assets/js/intlTelInput-jquery.min.js' );
		wp_enqueue_style( 'aircash', $this->get_plugin_url() . '/assets/css/intlTelInput.min.css' );

		// If in test mode, submit the new test account request if not already submitted
		if ( $this->is_in_test_mode() && ! $this->is_test_account_request_sent() && $this->get_test_account_request_status() !== 'approved' ) {
			$this->send_new_test_account_request();
		}
		if ( isset( $_POST['woocommerce_aircash-woocommerce_action'] ) ) {
			$this->send_new_account_request();
		}
		if ( isset( $_GET['aircash'] ) && $_GET['aircash'] == 'reset' ) {
			$this->reset_aircash_configuration();
		}
		if ( isset( $_GET['aircash'] ) && $_GET['aircash'] == 'reset_test' ) {
			$this->reset_aircash_test_configuration();
		}
		if ( isset( $_GET['aircash'] ) && $_GET['aircash'] == 'check_account_status' ) {
			$this->update_aircash_account_status();
		}
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['aircash'] ) && $_POST['aircash'] == 'manual_configuration' ) {
			$this->update_manual_configuration();
		}
		if ( isset( $_GET['aircash'] ) && $_GET['aircash'] == 'new_account_request' ) {
			$this->display_account_request_form();
			die;
		}
		if ( isset( $_GET['aircash'] ) && $_GET['aircash'] == 'manual_configuration' ) {
			$this->display_manual_configuration_form();
			die;
		}
	}

	private function get_new_account_request_url(): string {
		$url = 'admin.php?page=wc-settings&tab=checkout&aircash=new_account_request&width=700&height=540';
		if ( isset( $_GET['aircash-new-account-request-error'] ) ) {
			foreach ( $_GET['aircash-new-account-request-error'] as $error_message ) {
				$url .= '&aircash-new-account-request-error[]=' . urlencode( $error_message );
			}
		}

		return admin_url( $url );
	}

	/**
	 * Adds hidden HTML content to admin
	 */
	public function add_html_to_admin() {
		echo '<div id="aircash-new-account-request-url" data-url="' . esc_attr( $this->get_new_account_request_url() ) . '"></div>';
	}

	/**
	 * Generate certificate key pair and save it to options.
	 *
	 * @param null|string $passphrase Passphrase for private key.
	 * @param bool $test Test mode flag.
	 */
	private function generate_certificates( $passphrase = null, $test = false ) {
		if ( ! $passphrase ) {
			$passphrase = bin2hex( openssl_random_pseudo_bytes( 10 ) );
		}
		$private_key = openssl_pkey_new(
			array(
				'private_key_bits' => 4096,
				'private_key_type' => OPENSSL_KEYTYPE_RSA,
			)
		);
		$csr         = openssl_csr_new( array( 'commonName' => get_bloginfo() ), $private_key, array( 'digest_alg' => 'sha256' ) );
		$x509        = openssl_csr_sign( $csr, null, $private_key, 365 * 10, array( 'digest_alg' => 'sha256' ) );
		openssl_csr_export( $csr, $csrout );
		openssl_pkey_export( $private_key, $private_key_string, $passphrase );
		openssl_x509_export( $x509, $certificate );
		$private_key_details = openssl_pkey_get_details( $private_key );
		$public_key          = $private_key_details['key'];
		$prefix              = ( $test ? 'aircash-test-' : 'aircash-' );
		$this->update_option( $prefix . 'private-key', $private_key_string );
		$this->update_option( $prefix . 'certificate', $certificate );
		$this->update_option( $prefix . 'passphrase', $passphrase );
	}

	/**
	 * Returns plugins asset URL
	 *
	 * @return string
	 */
	private function get_assets_base_url(): string {
		return plugin_dir_url( __DIR__ . '/../aircash-woocommerce.php' ) . '/assets/';
	}

	/**
	 * Payment gateway settings page
	 */
	public function admin_options() {

		add_action( 'admin_enqueue_scripts', 'enqueue_modal_window_assets' );

		echo '<h2>' . esc_html( $this->get_method_title() );
		wc_back_link( __( 'Return to payments', 'woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&' ) );
		echo '</h2>';


		add_thickbox();

		$account_status = $this->get_account_request_status();
		if ( 'approved' !== $account_status ) {
			$url = $this->get_new_account_request_url();
			printf(
				'<a href="%s" id="aircash-connect" class="button button-primary thickbox open-plugin-details-modal" title="%s">%s</a>',
				esc_html( $url ),
				esc_html__( 'Connect with Aircash', 'aircash-for-woocommerce' ),
				esc_html__( 'Connect with Aircash', 'aircash-for-woocommerce' )
			);
		}

		if ( isset( $_GET['aircash'] ) && $_GET['aircash'] == 'show_manual_configuration' ) {
			$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&aircash=manual_configuration&width=700&height=500' );
			printf(
				'&nbsp;<a href="%s" id="aircash-configuration" class="button thickbox open-plugin-details-modal" title="%s">%s</a>',
				esc_html( $url ),
				esc_html__( 'Manual configuration', 'aircash-for-woocommerce' ),
				esc_html__( 'Manual configuration', 'aircash-for-woocommerce' )
			);
		}
		if ( $this->get_aircash_certificate() && $this->get_aircash_private_key() ) {
			$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&aircash=check_configuration&width=700&height=700' );
			printf(
				'&nbsp;<a href="%s" id="aircash-check-configuration" class="button thickbox open-plugin-details-modal" title="%s">%s</a>',
				esc_html( $url ),
				esc_html__( 'Configuration check', 'aircash-for-woocommerce' ),
				esc_html__( 'Check configuration', 'aircash-for-woocommerce' )
			);
		}
		?>

        <table class="form-table">
			<?php $this->generate_settings_html( $this->form_fields ); ?>
        </table>
		<?php
	}

	/**
	 * Message to display when payment method is selected on checkout.
	 */
	public function payment_fields() {
		echo '<p>' . esc_html__( 'After confirming your order, you will be redirected to pay via Aircash', 'aircash-for-woocommerce' ) . '</p>';
	}

	/**
	 * Empties shopping cart after order is processed.
	 *
	 * @param int $order_id
	 *
	 * @return array|null
	 */
	public function process_payment( $order_id ): ?array {
		$order = new WC_Order( $order_id );

		if ( ! $order ) {
			return null;
		}
		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * Check if the Aircash payment is enabled in WooCommerce
	 */
	public function is_enabled(): bool {
		return $this->get_option( 'enabled' ) === 'yes';
	}

	/**
	 * Get test account status from options.
	 *
	 * @return string
	 */
	public function get_test_account_request_status(): string {
		$account_status = $this->get_option( 'aircash-test-account-status' );

		return ( $account_status && '' !== $account_status ) ? $account_status : 'plugin_installed';
	}

	/**
	 * Get production account status from options.
	 *
	 * @return string
	 */
	public function get_account_request_status(): string {
		$account_status = $this->get_option( 'aircash-account-status' );

		return ( $account_status && '' !== $account_status ) ? $account_status : 'plugin_installed';
	}

	/**
	 * Initializes fields for manual settings and new account submission forms.
	 */
	public function init_form_fields() {
		$settings               = array();
		$manual_settings        = array();
		$currencies             = array();
		$woocommerce_currencies = get_woocommerce_currencies();
		foreach ( $this->supported_currencies as $iso => $code ) {
			$currencies[ $code ] = ( $woocommerce_currencies[ $iso ] ?? $iso ) . ' (' . get_woocommerce_currency_symbol( $iso ) . ')';
		}
		$settings['enabled']                    = array(
			'title'    => __( 'Enable/Disable', 'woocommerce' ),
			'type'     => 'checkbox',
			'label'    => __( 'Enable "Aircash for WooCommerce"', 'aircash-for-woocommerce' ),
			'default'  => 'no',
			'desc_tip' => false,
		);
		$manual_settings['aircash-partner-id']  = array(
			'title'    => __( 'Partner ID', 'aircash-for-woocommerce' ),
			'type'     => 'text',
			'required' => true,
			// 'disabled' => $this->get_option('aircash-partner-id') ?? null,
		);
		$manual_settings['aircash-certificate'] = array(
			'title'    => __( 'Public key', 'aircash-for-woocommerce' ),
			'type'     => 'textarea',
			'required' => true,
		);
		$manual_settings['aircash-private-key'] = array(
			'title'    => __( 'Private key', 'aircash-for-woocommerce' ),
			'type'     => 'textarea',
			'required' => true,
		);
		$manual_settings['aircash-passphrase']  = array(
			'title'    => __( 'Passphrase', 'aircash-for-woocommerce' ),
			'type'     => 'password',
			'required' => true,
		);
		$manual_settings['aircash-currency']    = array(
			'title'    => __( 'Currency', 'aircash-for-woocommerce' ),
			'type'     => 'select',
			'required' => true,
			'options'  => array_flip( $this->supported_currencies ),
		);
		$settings['order-status']               = array(
			'title'       => __( 'Paid order status', 'aircash-for-woocommerce' ),
			'type'        => 'select',
			/* translators: %s: Order status */
			'description' => sprintf( __( '(Default status: "%s").', 'aircash-for-woocommerce' ), _x( 'Processing', 'Order status', 'woocommerce' ) ),
			'default'     => 'wc-processing',
			'options'     => wc_get_order_statuses(),
		);
		$settings['in-test-mode']               = array(
			'title'       => __( 'Enable Test mode', 'aircash-for-woocommerce' ),
			'type'        => 'checkbox',
			'label'       => __( 'Test mode', 'aircash-for-woocommerce' ),
			'description' => __( 'Uses Aircash testing environment', 'aircash-for-woocommerce' ),
			'default'     => 'no',
			'desc_tip'    => false,
		);
		$settings['aircash-log-url']            = array(
			'title'       => __( 'Log file', 'aircash-for-woocommerce' ),
			'type'        => 'title',
			'description' => '<code>' . WC_Log_Handler_File::get_log_file_path( 'aircash-for-woocommerce' ) . '</code>',
		);

		$account_activation_settings['aircash-partner-name']  = array(
			'title' => __( 'Partner name', 'aircash-for-woocommerce' ),
			'type'  => 'text',
		);
		$account_activation_settings['aircash-partner-email'] = array(
			'title' => __( 'E-mail', 'aircash-for-woocommerce' ),
			'type'  => 'email',
		);
		$account_activation_settings['aircash-partner-phone'] = array(
			'title' => __( 'Phone', 'aircash-for-woocommerce' ),
			'type'  => 'tel',
			'css'   => 'padding-left: 45px;'
		);
		$account_activation_settings['aircash-currency']      = array(
			'title'   => __( 'Currency', 'aircash-for-woocommerce' ),
			'type'    => 'select',
			'default' => get_woocommerce_currency(),
			'options' => $currencies,
		);
		$account_activation_settings['aircash-partner-note']  = array(
			'title' => __( 'Note', 'aircash-for-woocommerce' ),
			'type'  => 'textarea',
		);
		$account_activation_settings['action']                = array(
			'type' => 'hidden',
		);
		$this->account_activation_form_fields                 = apply_filters( 'wc_aircash_settings', $account_activation_settings );
		$this->form_fields                                    = apply_filters( 'wc_aircash_settings', $settings );
		$this->manual_form_fields                             = apply_filters( 'wc_aircash_settings', $manual_settings );
	}

	/**
	 * Checks if Aircash payment should be available, depending on the web shop currency and installed extensions.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( ! extension_loaded( 'openssl' ) || ( ! extension_loaded( 'gd' ) && ! extension_loaded( 'imagick' ) ) ) {
			return false;
		}

		if ( ! in_array( get_woocommerce_currency(), array_keys( $this->supported_currencies ) ) ) {
			return false;
		}

		return parent::is_available();
	}

	/**
	 * Returns Aircash icon.
	 *
	 * @return mixed|string|void
	 */
	public function get_icon() {
		return apply_filters( 'woocommerce_gateway_icon', '<img src="' . esc_attr( $this->get_assets_base_url() ) . '/images/aircash-red.svg' . '" class="aircash-payment-gateway-icon">', $this->id );
	}

	/**
	 * Signs the array with certificate (private key).
	 *
	 * @param array $parameters
	 */
	private function add_signature( array &$parameters ): void {
		ksort( $parameters );
		$data_to_sign = urldecode( http_build_query( $parameters ) );
		$private_key  = openssl_pkey_get_private( $this->get_aircash_private_key(), $this->get_aircash_passphrase() );
		openssl_sign( $data_to_sign, $signature, $private_key, OPENSSL_ALGO_SHA256 );
		// phpcs:ignore
		$parameters['Signature'] = base64_encode( $signature );
	}


	/**
	 * Returns current WordPress language prefix ("en" for "en-US")
	 * @return string
	 */
	private function get_current_language_prefix(): string {
		$parts = explode( '-', get_bloginfo( 'language' ) );

		return is_array( $parts ) ? $parts[0] : 'en';
	}

	/**
	 * Displays Aircash QR-code and the link for smaller devices.
	 *
	 * @param int $order_id
	 */
	public function display_aircash_payment_page( int $order_id ) {
		$order = new WC_Order( $order_id );
		if ( ! $order ) {
			return;
		}
		if ( ! $order->get_meta( 'aircash_status' ) ) {
			$order->add_meta_data( 'aircash_status', 'pending', true );
			$order->save();
		}
		$location   = preg_replace( '/^https?:\/\//i', '', get_site_url() );
		$parameters = array(
			'Amount'               => (float) number_format( $order->get_total(), 2, '.', '' ),
			'CurrencyID'           => $this->supported_currencies[ $order->get_currency() ],
			'Description'          => $order->get_id(),
			'LocationID'           => $location,
			'PartnerID'            => $this->get_aircash_partner_id(),
			'PartnerTransactionID' => $order->get_id(),
		);
		$this->add_signature( $parameters );
		$aircash_url = $this->get_aircash_base_uri( $this->is_in_test_mode() ) . '/api/AircashPay/GeneratePartnerCode';

		$serialize_precizion = ini_get( 'serialize_precision' );
		// phpcs:ignore
		ini_set( 'serialize_precision', - 1 );
		$body = wp_json_encode( $parameters, JSON_UNESCAPED_SLASHES );
		// phpcs:ignore
		ini_set( 'serialize_precision', $serialize_precizion );
		$this->log( 'Order #' . $order_id . ': Generate code on ' . $aircash_url . ' with request body: ' . $body, 'debug' );
		$response = wp_remote_post(
			$aircash_url,
			array(
				'body'        => $body,
				'headers'     => array( 'Content-type' => 'application/json' ),
				'sslverify'   => false,
				'data_format' => 'body',
			)
		);
		$result   = $error_message = $qr_code_image_data = $qr_code_textual = null;

		$response_code = (int) wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$this->log( 'Order #' . $order_id . ': Response code: ' . $response_code, 200 === $response_code ? 'debug' : 'error' );
		if ( $response_body ) {
			$this->log( 'Order #' . $order_id . ': Response body: ' . $response_body, 200 === $response_code ? 'debug' : 'error' );
		}
		if ( 200 === $response_code &&
		     $result = json_decode( $response_body, true )
		) {
			if ( isset( $result['codeLink'] ) && is_string( $result['codeLink'] ) ) {
				$qr_code_textual = false;
				if ( extension_loaded( 'gd' ) ) {
					$qr_code = new QrCode( $result['codeLink'], new Aircash_Encoding( 'UTF-8' ), new ErrorCorrectionLevelLow(), 400, 10 );
					$qr_code->setRoundBlockSizeMode( new RoundBlockSizeModeMargin() )
						->setForegroundColor( new Color( 0, 0, 0 ) )
						->setBackgroundColor( new Color( 255, 255, 255 ) );
					$writer             = new PngWriter();
					$qr_code_image_data = $writer->write( $qr_code )->getString();
				} elseif ( extension_loaded( 'imagick' ) ) {
					$renderer           = new ImageRenderer( new RendererStyle( 400 ), new ImagickImageBackEnd() );
					$writer             = new Writer( $renderer );
					$qr_code_image_data = $writer->writeString( $result['codeLink'] );

				} else {
					$qr_code_textual    = true;
					$renderer           = new PlainTextRenderer();
					$writer             = new Writer( $renderer );
					$qr_code_image_data = $writer->writeString( $result['codeLink'] );
				}
			} else {
				$error_message = 'Unable to generate QR code';
			}
			$order->add_meta_data( 'aircash_status', 'submitted', true );
			$order->save();
		} else {
			$order->add_meta_data( 'aircash_status', 'failed', true );
			$order->save();
			$error_message = 'An unknown error occurred with Aircash. Please try later.';
		}

		$language = $this->get_current_language_prefix();
		if ( ! file_exists( __DIR__ . '/../assets/images/pay-with-aircash-' . $language . '.svg' ) ) {
			$language = 'en';
		}
		?>
        <div class="aircash-container">
            <img src="<?php
			echo esc_url( $this->get_assets_base_url() ) . '/images/aircash-red.svg';
			?>" alt="Aircash" class="aircash-logo">
			<?php
			if ( $qr_code_image_data ) {
				?>
                <div class="aircash-wrapper">
                    <div class="aircash-payment-button">
                        <a href="<?php echo esc_url( $result['codeLink'] ); ?>">
                            <img src="<?php echo esc_url( $this->get_assets_base_url() . 'images/pay-with-aircash-' . $language . '.svg' ); ?>"
                                 alt="<?php echo esc_html__( 'Pay with Aircash', 'aircash-for-woocommerce' ); ?>">
                        </a>
                    </div>

                    <div class="aircash-qr-code-wrapper">
						<?php
						if ( $qr_code_textual ) {
							?>
                            <pre style="line-height:100%;color:black;font-family:monospace;font-size:11px;background:white;font-weight:100;letter-spacing:-1px;user-select:none;-webkit-user-select:none"><?php
							echo $qr_code_image_data;
							?></pre><?php
						} else {
							?>
                            <img class="aircash-qr-code" src="data:image/png;base64,<?php
							// phpcs:ignore
							echo base64_encode( $qr_code_image_data );
							?>">
							<?php
						}
						?>
                        <ol>
                            <li><?php echo esc_html__( 'Open Aircash application', 'aircash-for-woocommerce' ) ?></li>
                            <li><?php echo esc_html__( 'Select "Scan and Pay"', 'aircash-for-woocommerce' ) ?></li>
                            <li><?php echo esc_html__( 'Point your camera to QR code', 'aircash-for-woocommerce' ) ?></li>
                            <li><?php echo esc_html__( 'Follow instructions in Aircash application', 'aircash-for-woocommerce' ) ?></li>
                        </ol>
                    </div>

                    <div class="aircash-success-wrapper">
                        <h4><?php echo esc_html( __( 'Payment Successful', 'aircash-for-woocommerce' ) ); ?></h4>
                        <div class="aircash-success-checkmark">
                            <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                                <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none"/>
                                <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                            </svg>
                        </div>
                    </div>

                    <p class="aircash-download-label">
						<?php echo esc_html__( 'Download the latest Aircash app:', 'aircash-for-woocommerce' ); ?>
                    </p>
                    <div class="aircash-app-icons">
                        <a href="https://click.google-analytics.com/redirect?tid=UA-83810025-1&url=https%3A%2F%2Fitunes.apple.com%2Fus%2Fapp%2Faircash%2Fid1178612530&aid=com.aircash.app&idfa=%{idfa}&cs=eshops&cm=web&ck=woocommerce">
                            <img src="<?php echo esc_url( $this->get_assets_base_url() ) . '/images/download-button/app-store-' . $language . '.svg'; ?>">
                        </a>
                        <a href="https://play.google.com/store/apps/details?id=com.aircash.aircash&referrer=utm_source%3Deshops%26utm_medium%3Dweb%26utm_campaign%3Dwoocommerce%26utm_content%3D">
                            <img src="<?php echo esc_url( $this->get_assets_base_url() ) . '/images/download-button/google-play-' . $language . '.svg'; ?>">
                        </a>
                        <a href="https://aircash.eu/huawei-app-gallery/?utm_campaign=woocommerce&utm_source=eshops&utm_medium=web&utm_term=&utm_content=">
                            <img src="<?php echo esc_url( $this->get_assets_base_url() ) . '/images/download-button/app-gallery-' . $language . '.svg'; ?>">
                        </a>
                    </div>
                </div>
				<?php
			} else {
				echo esc_html( $error_message );
			}
			?>
        </div>
		<?php
	}

	/**
	 * Returns Aircash web service base URL.
	 *
	 * @param bool $test_mode
	 *
	 * @return string
	 */
	private function get_aircash_base_uri( bool $test_mode = true ): string {
		return $test_mode ? 'https://staging-m3.aircash.eu' : 'https://m3.aircash.eu';
	}

	/**
	 * Handles the request made by Aircash web service when the payment is completed.
	 *
	 * @return int|void
	 */
	public function handle_callback() {
		$body = file_get_contents( 'php://input' );
		$this->log( 'Callback called: ' . $body, 'debug' );
		$result = json_decode( $body, true );
		if ( $body && $result ) {
			$signature              = $result['signature'] ?? null;
			$partner_id             = $result['partnerID'] ?? null;
			$partner_transaction_id = $result['partnerTransactionID'] ?? null;
			$amount                 = $result['amount'] ?? null;
			$currency_id            = $result['currencyID'] ?? null;
			$aircash_transaction_id = $result['aircashTransactionID'] ?? null;

			$order = wc_get_order( $partner_transaction_id );
			if ( ! $order ) {
				/* translators: %s: Order ID */
				$this->callback_error( sprintf( __( 'Order with ID "%s" is not found', 'aircash-for-woocommerce' ), $partner_transaction_id ) );
				$this->log( 'Order #' . $partner_transaction_id . ': Order not found', 'error' );
			}

			// phpcs:ignore
			$signature          = base64_decode( $signature );
			$data_to_sign       = urldecode(
				http_build_query(
					array(
						'AircashTransactionID' => $aircash_transaction_id,
						'Amount'               => (float) $amount,
						'CurrencyID'           => $currency_id,
						'PartnerID'            => $partner_id,
						'PartnerTransactionID' => $partner_transaction_id,
					)
				)
			);
			$aircash_public_key = 'aircash-public-key.pem';
			if ( $this->is_in_test_mode() ) {
				$aircash_public_key = 'aircash-public-key-test.pem';
			}

			if ( ! file_exists( __DIR__ . DIRECTORY_SEPARATOR . $aircash_public_key ) || ! is_readable( __DIR__ . DIRECTORY_SEPARATOR . $aircash_public_key ) ) {
				$this->log( 'Order #' . $partner_transaction_id . ': Aircash public certificate file not found or unreadable', 'error' );
				$this->callback_error( __( 'Aircash public certificate file not found or unreadable', 'aircash-for-woocommerce' ) );
			}
			$aircash_certificate = openssl_x509_read( file_get_contents( __DIR__ . DIRECTORY_SEPARATOR . $aircash_public_key ) );
			$verification_result = openssl_verify( $data_to_sign, $signature, $aircash_certificate, OPENSSL_ALGO_SHA256 );
			if ( ! $verification_result ) {
				/* translators: %s: OpenSSL signature verification error message */
				$error_message = openssl_error_string();
				$this->log( 'Order #' . $partner_transaction_id . ': Unable to verify signature: ' . $error_message . ' (data: ' . $data_to_sign . ')', 'error' );
				$this->callback_error( sprintf( __( 'Unable to verify signature: %s', 'aircash-for-woocommerce' ), $error_message ) );
			}

			if ( ! isset( array_flip( $this->supported_currencies )[ $currency_id ] ) ) {
				$this->log( 'Order #' . $partner_transaction_id . ': Unsupported currency ' . $currency_id, 'error' );
				/* translators: %s: Currency code */
				$this->callback_error( sprintf( __( 'Currency code %s is not supported.', 'aircash-for-woocommerce' ), $currency_id ) );
			}
			if ( $order->get_currency() !== array_flip( $this->supported_currencies )[ $currency_id ] ) {
				$this->log( 'Order #' . $partner_transaction_id . ': Different currency ' . $currency_id, 'error' );
				/* translators: 1: Order currency ISO, 2: Aircash transaction currency code */
				$this->callback_error( sprintf( __( 'Order has different currency (%1$s) from Aircash payment (%2$s)', 'aircash-for-woocommerce' ), $order->get_currency(), array_flip( $this->supported_currencies )[ $currency_id ] ) );
			}

			if ( $order->get_total() != $amount ) {
				$this->log( 'Order #' . $partner_transaction_id . ': Different amount ' . $amount, 'error' );
				/* translators: 1: Order amount, 2: Aircash transaction amount */
				$this->callback_error( sprintf( __( 'Order has different amount (%1$s) from Aircash payment (%2$s)', 'aircash-for-woocommerce' ), $order->get_amount(), $amount ) );
			}
			$target_status = preg_replace( '/^wc-/iu', '', $this->get_option( 'order-status' ) );
			$order->set_status( $target_status );
			$order->add_meta_data( 'aircash_transaction_id', $aircash_transaction_id, true );
			$order->add_meta_data( 'aircash_status', 'success', true );
			if ( $this->is_in_test_mode() ) {
				$order->add_meta_data( 'aircash_test', 1 );
			}
			$order->add_meta_data( 'aircash_partner_id', $partner_id );
			$order->save();
			$this->log( 'Order #' . $partner_transaction_id . ': Successful transaction ' . $aircash_transaction_id, 'debug' );
			echo wp_json_encode(
				array(
					'status'  => 0,
					'message' => 'Success',
				)
			);
			die;
		}
		$this->log( 'Unable to parse request', 'debug' );

		return 1;
	}

	/**
	 * Check if Aircash payment test mode is enabled.
	 *
	 * @return bool
	 */
	public function is_in_test_mode(): bool {
		return $this->get_option( 'in-test-mode' ) === 'yes';
	}

	/**
	 * Iin case of payment error on the callback, return JSON content with a message to display to the customer.
	 *
	 * @param $message
	 * @param int $code
	 */
	private function callback_error( $message, $code = 500 ) {
		status_header( $code );
		header( 'Content-Type: application/json; charset=utf-8' );
		wp_die(
			wp_json_encode(
				array(
					'status'  => - 1,
					'message' => $message,
				)
			)
		);
	}

	/**
	 * Timeout to finish the payment.
	 *
	 * @return int
	 */
	private function get_timeout() {
		$timeout = get_option( 'woocommerce_hold_stock_minutes' );
		if ( ! $timeout ) {
			$timeout = 30;
		}

		return $timeout;
	}

	/**
	 * Adds JS (+jQuery) and CSS to the Aircash payment page.
	 */
	public function add_frontend_scripts() {
		if ( ! is_checkout_pay_page() ) {
			return;
		}
		$order_id = get_query_var( 'order-pay' );
		$order    = new WC_Order( $order_id );
		if ( ! $order ) {
			return;
		}
		if ( 'aircash-woocommerce' !== $order->get_payment_method() ) {
			return;
		}
		$plugin_data = get_file_data( __DIR__ . '/../aircash-woocommerce.php', array( 'Version' => 'Version' ), false );
		$version     = $plugin_data['Version'] ?? 1;
		wp_enqueue_script( 'aircash', plugin_dir_url( __DIR__ . '/../aircash-woocommerce.php' ) . '/../assets/js/aircash.js', array( 'jquery' ), $version, true );
		wp_enqueue_style( 'aircash', plugin_dir_url( __DIR__ . '/../aircash-woocommerce.php' ) . '/../assets/css/aircash.css', array(), $version );
		wp_localize_script(
			'aircash',
			'aircash',
			array(
				'url'     => admin_url( 'admin-ajax.php' ),
				'order'   => $order->get_id(),
				'nonce'   => wp_create_nonce( 'aircash_order_check' ),
				'timeout' => $this->get_timeout(),
			)
		);
	}

	/**
	 * This function is periodically run to check if payment is completed.
	 */
	public function check_order() {
		check_ajax_referer( 'aircash_order_check' );
		$order          = new WC_Order( filter_input( INPUT_POST, 'order', FILTER_SANITIZE_NUMBER_INT ) );
		$aircash_status = $order->get_meta( 'aircash_status' );
		$response       = array(
			'aircash_status' => $aircash_status,
		);
		if ( 'success' === $aircash_status ) {
			$response['url'] = $order->get_checkout_order_received_url();
		}
		status_header( 200 );
		header( 'Content-Type: application/json; charset=utf-8' );
		wp_die( wp_json_encode( $response ) );
	}

	/**
	 * Adds a entry to the log file.
	 *
	 * @param $message
	 * @param string $level
	 */
	public static function log( $message, $level = 'info' ) {
		if ( empty( self::$log ) ) {
			self::$log = wc_get_logger();
		}
		self::$log->log( $level, $message, array( 'source' => 'aircash' ) );
	}

	/**
	 * Calls the Aircash refund API.
	 *
	 * @param int $order_id
	 * @param null $amount
	 * @param string $reason
	 *
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = new WC_Order( $order_id );
		if ( ! $order ) {
			return false;
		}
		if ( ! $order->get_payment_method() === 'aircash-woocommerce' ) {
			return false;
		}

		$this->log( 'Order #' . $order_id . ': Refund requested', '`debug`' );
		$parameters = array(
			'PartnerID'                  => $this->get_aircash_partner_id(),
			'PartnerTransactionID'       => $order->get_id(),
			'RefundPartnerTransactionID' => ( Uuid::uuid4() )->toString(),
			'Amount'                     => (float) number_format( $amount, 2, '.', '' ),
		);
		$this->add_signature( $parameters );
		$serialize_precizion = ini_get( 'serialize_precision' );
		// phpcs:ignore
		ini_set( 'serialize_precision', - 1 );
		$body = wp_json_encode( $parameters, JSON_UNESCAPED_SLASHES );
		// phpcs:ignore
		ini_set( 'serialize_precision', $serialize_precizion );
		$aircash_url = $this->get_aircash_base_uri( $this->is_in_test_mode() ) . '/api/AircashPay/RefundTransaction';
		$this->log( 'Order #' . $order_id . ': Refund ' . $aircash_url . ' with request body: ' . $body, 'debug' );
		$response      = wp_remote_post(
			$aircash_url,
			array(
				'body'        => $body,
				'headers'     => array( 'Content-type' => 'application/json' ),
				'sslverify'   => false,
				'data_format' => 'body',
			)
		);
		$response_code = (int) wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$this->log( 'Order #' . $order_id . ': Status code: ' . $response_code . ', Response body: ' . $response_body, 200 === $response_code ? 'debug' : 'error' );

		return ( 200 === $response_code );
	}

	/**
	 * Checks Aircash partner status and updates option entry.
	 *
	 * @param false $test
	 *
	 * @return false|mixed|string
	 */
	public function update_aircash_account_status( $test = false ) {
		$prefix = ( $test ? 'aircash-test-' : 'aircash-' );
		$this->update_option( $prefix . 'account-request-check-time', time() );
		if ( $test ) {
			$account_status = $this->fetch_test_account_request_status();
		} else {
			$account_status = $this->fetch_account_request_status();
		}
		// $this->update_option( $prefix . 'account-status', 'plugin_installed' );
		if ( $account_status === '"Pending"' ) {
			$this->update_option( $prefix . 'account-status', 'sent' );
		}
		if ( $account_status === '"Activated"' ) {
			$this->update_option( $prefix . 'account-status', 'approved' );
		}
		if ( $account_status === '"Rejected"' ) {
			$this->update_option( $prefix . 'account-status', 'declined' );
		}

		return $account_status;
	}

	/**
	 * Calls Aircash CheckPartner API endpoint to determine if the account is activated.
	 *
	 * @return false|string
	 */
	private function fetch_account_request_status() {
		$aircash_partner_id = $this->get_option( 'aircash-partner-id' );
		if ( ! $aircash_partner_id ) {
			return false;
		}
		$response      = wp_remote_get( 'https://m3.aircash.eu/api/AircashPay/CheckPartner?partnerId=' . $aircash_partner_id );
		$response_code = (int) wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$this->log( 'Fetch account request status: Status code: ' . $response_code . ', Response body: ' . $response_body, 200 === $response_code ? 'debug' : 'error' );

		return $response_body;

	}

	/**
	 * Calls Aircash CheckPartner API endpoint on testing environment to determine if the test account is activated.
	 *
	 * @return false|string
	 */
	private function fetch_test_account_request_status() {
		$aircash_partner_id = $this->get_option( 'aircash-test-partner-id' );
		if ( ! $aircash_partner_id ) {
			return false;
		}
		$response      = wp_remote_get( 'https://staging-m3.aircash.eu/api/AircashPay/CheckPartner?partnerId=' . $aircash_partner_id );
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$this->log( 'Fetch test account request status: Status code: ' . $response_code . ', Response body: ' . $response_body, $response_code == 200 ? 'debug' : 'error' );
		if ( $response_code == 200 ) {
			return $response_body;
		}

		return false;
	}

	/**
	 * Sends a new Aircash account request.
	 *
	 * @param $partner_id
	 * @param $partner_name
	 * @param $email
	 * @param $phone
	 * @param $currency
	 * @param $callback_url
	 * @param $certificate
	 * @param false $test
	 * @param string $note
	 *
	 * @return bool
	 */
	private function send_activate_partner_request( $partner_id, $partner_name, $email, $phone, $currency, $callback_url, $certificate, bool $test = false, string $note = '' ): bool {

		$prefix                                = ( $test ? 'aircash-test-' : 'aircash-' );
		$aircash_callback_url_option           = $prefix . 'callback-url';
		$aircash_partner_email_option          = $prefix . 'partner-email';
		$aircash_partner_name_option           = $prefix . 'partner-name';
		$aircash_partner_phone_option          = $prefix . 'partner-phone';
		$aircash_currency_id_option            = $prefix . 'currency';
		$aircach_account_request_status_option = $prefix . 'account-status';

		$certificate = preg_replace( '/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\n/iu', '', $certificate );
		$parameters  = array(
			'PlatformID'            => $this->platform_id,
			'PartnerID'             => $partner_id,
			'PartnerName'           => $partner_name,
			'PublicKey'             => $certificate,
			'CurrencyID'            => $currency,
			'ConfirmTransactionURL' => $callback_url,
			'ContactEmail'          => $email,
			'PhoneNumber'           => $phone,
			'Note'                  => $note,
		);

		ksort( $parameters );
		$data_to_sign = urldecode( http_build_query( $parameters ) );
		// phpcs:ignore
		$private_key = openssl_pkey_get_private( base64_decode( $this->platform_private_key ), base64_decode( $this->platform_password_encoded ) );
		openssl_sign( $data_to_sign, $signature, $private_key, OPENSSL_ALGO_SHA256 );
		// phpcs:ignore
		$parameters['Signature'] = base64_encode( $signature );

		$this->update_option( $aircash_partner_email_option, $email );
		$this->update_option( $aircash_partner_name_option, $partner_name );
		$this->update_option( $aircash_partner_phone_option, $phone );
		$this->update_option( $aircash_currency_id_option, $currency );
		$this->update_option( $aircash_callback_url_option, $callback_url );

		$body          = wp_json_encode( $parameters, JSON_UNESCAPED_SLASHES ^ JSON_UNESCAPED_LINE_TERMINATORS ^ JSON_PRETTY_PRINT );
		$response      = wp_remote_post(
			'https://' . ( $test ? 'staging-m3.aircash.eu' : 'm3.aircash.eu' ) . '/api/AircashPay/ActivatePartner',
			array(
				'body'        => $body,
				'headers'     => array( 'Content-type' => 'application/json' ),
				'sslverify'   => false,
				'data_format' => 'body',
			)
		);
		$response_code = (int) wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$this->log( 'Send new account request: Status code: ' . $response_code . ', Response body: ' . $response_body . ', Request body: ' . $body, $response_code == 200 ? 'debug' : 'error' );
		if ( 200 === $response_code ) {
			$this->update_option( $aircach_account_request_status_option, 'sent' );

			return true;
		} else {
			$this->update_option( $aircach_account_request_status_option, 'error_sending' );

			return false;
		}
	}

	/**
	 * After "Connect with Aircash" form is submitted, create partner ID and certificates and submit new account request.
	 */
	public function send_new_account_request() {

		$post_data          = $this->get_post_data();
		$aircash_partner_id = $this->get_option( 'aircash-partner-id' );
		if ( ! $aircash_partner_id ) {
			$aircash_partner_id = ( Uuid::uuid4() )->toString();
			$this->update_option( 'aircash-partner-id', $aircash_partner_id );
		}

		$errors                = null;
		$aircash_partner_email = trim( $post_data[ $this->get_field_key( 'aircash-partner-email' ) ] ) ?? null;
		$aircash_partner_name  = trim( $post_data[ $this->get_field_key( 'aircash-partner-name' ) ] ) ?? null;
		$aircash_currency_id   = $post_data[ $this->get_field_key( 'aircash-currency' ) ] ?? null;

		$aircash_partner_phone = $post_data[ $this->get_field_key( 'aircash-partner-phone' ) ] ?? null;
		$aircash_partner_note  = $post_data[ $this->get_field_key( 'aircash-partner-note' ) ] ?? null;
		$aircash_callback_url  = WC()->api_request_url( 'aircash' );

		$generate_certificates = ( ! $this->get_option( 'aircash-private-key' ) ) ||
		                         ( ! $this->get_option( 'aircash-certificate' ) ) ||
		                         ( ! $this->get_option( 'aircash-passphrase' ) );

		if ( ! $aircash_partner_name ) {
			$errors [] = __( 'Partner name is required.', 'aircash-for-woocommerce' );
		}
		if ( ! $aircash_partner_email ) {
			$errors [] = __( 'E-mail address is required.', 'aircash-for-woocommerce' );
		} else if ( ! is_email( $aircash_partner_email ) ) {
			$errors [] = __( 'E-mail address is invalid.', 'aircash-for-woocommerce' );
		}
		if ( ! $aircash_partner_phone ) {
			$errors[] = __( 'Phone number is requred.', 'aircash-for-woocommerce' );
		}
		$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=aircash-woocommerce' );
		if ( $errors ) {
			foreach ( $errors as $error_message ) {
				$url .= '&aircash-new-account-request-error[]=' . urlencode( $error_message );
			}
			$url .= '#aircash-new-account-request';
			wp_safe_redirect( $url );
		}

		if ( $generate_certificates ) {
			$this->generate_certificates();
		}
		$certificate                     = $this->get_option( 'aircash-certificate' );
		$activate_partner_request_result = $this->send_activate_partner_request(
			$aircash_partner_id,
			$aircash_partner_name,
			$aircash_partner_email,
			$aircash_partner_phone,
			$aircash_currency_id,
			$aircash_callback_url,
			$certificate,
			false,
			$aircash_partner_note
		);
		if ( $activate_partner_request_result ) {
			$url .= '#';
		} else {
			// Error sending request, append hash to URL to open popup
			$url .= '#aircash-new-account-request';
		}
		wp_safe_redirect( $url );
	}

	/**
	 * Send new test account request.
	 */
	public function send_new_test_account_request() {
		$aircash_partner_id = $this->get_option( 'aircash-test-partner-id' );
		if ( ! $aircash_partner_id ) {
			$aircash_partner_id = ( Uuid::uuid4() )->toString();
			$this->update_option( 'aircash-test-partner-id', $aircash_partner_id );
		}
		$aircash_callback_url  = WC()->api_request_url( 'aircash' );
		$generate_certificates = ( ! $this->get_option( 'aircash-test-private-key' ) ) ||
		                         ( ! $this->get_option( 'aircash-test-certificate' ) ) ||
		                         ( ! $this->get_option( 'aircash-test-passphrase' ) );

		if ( $generate_certificates ) {
			$this->generate_certificates( null, true );
		}
		$certificate  = $this->get_option( 'aircash-test-certificate' );
		$currency_iso = get_woocommerce_currency();
		$currency     = $this->supported_currencies[ $currency_iso ] ?? 191;
		$this->send_activate_partner_request( $aircash_partner_id, 'Test', 'test@test.com', 'Test', $currency, $aircash_callback_url, $certificate, true );

	}

	/**
	 * Check if test account request is already sent.
	 *
	 * @return bool
	 */
	private function is_test_account_request_sent(): bool {
		return $this->get_test_account_request_status() === 'sent';
	}

	/**
	 * Delete Aircash configuration and redirect to Aircash payment settings page.
	 */
	public function reset_aircash_configuration() {

		$this->update_option( 'aircash-partner-id', '' );
		$this->update_option( 'aircash-currency', '' );
		$this->update_option( 'aircash-certificate', '' );
		$this->update_option( 'aircash-private-key', '' );
		$this->update_option( 'aircash-passphrase', '' );
		$this->update_option( 'aircash-account-status', '' );
		wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=aircash-woocommerce' ) );
		exit;
	}

	/**
	 * Delete Aircash test configuration and redirect to Aircash payment settings page.
	 */
	public function reset_aircash_test_configuration() {

		$this->update_option( 'aircash-test-partner-id', '' );
		$this->update_option( 'aircash-test-currency', '' );
		$this->update_option( 'aircash-test-certificate', '' );
		$this->update_option( 'aircash-test-private-key', '' );
		$this->update_option( 'aircash-test-passphrase', '' );
		$this->update_option( 'aircash-test-account-status', '' );
		wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=aircash-woocommerce' ) );
		exit;
	}

	/**
	 * Display the "Connect with Aircash" modal form
	 */
	private function display_account_request_form() {
		$this->init_form_fields();
		$account_status = $this->get_account_request_status();
		if ( $account_status === 'sent' ) {
			?>
            <div class="notice notice-warning" style="margin-left: 0">
                <p>
					<?php echo __( 'Account request already submitted.<br>Please wait until we process your request or contact our support.', 'aircash-for-woocommerce' ); ?>
                </p>
            </div>
			<?php
		} elseif ( $account_status === 'error_sending' ) {
			?>
            <div class="notice notice-error" style="margin-left: 0">
                <p>
					<?php echo __( '<b>Error</b> - there was an error submitting new account request. Check the log file for details.', 'aircash-for-woocommerce' ); ?>
                </p>
            </div>
			<?php
		}

		if ( isset( $_REQUEST['aircash-new-account-request-error'] ) && is_array( $_REQUEST['aircash-new-account-request-error'] ) ) {
			foreach ( $_REQUEST['aircash-new-account-request-error'] as $error_message ) {
				?>
                <div class="notice notice-error" style="margin-left: 0">
                    <p>
						<?php echo esc_html( $error_message ); ?>
                    </p>
                </div>

				<?php
			}
		}

		?>
        <form method="post" action="#aircash-new-account-request" id="form-new-account" class="woocommerce">
            <input type="hidden" name="aircash" value="send">
            <img src="<?php echo esc_url( $this->get_assets_base_url() . '/images/aircash-red.svg' ); ?>" alt="Aircash"
                 style="width: 150px; margin: 1rem">
            <table class="form-table">
				<?php $this->generate_settings_html( $this->account_activation_form_fields ); ?>
            </table>
            <button name="save" class="button-primary" type="submit">
				<?php
				echo esc_html__( 'Send new account request', 'aircash-for-woocommerce' )
				?>
            </button>
        </form>
        <script type="text/javascript">jQuery("#woocommerce_aircash-woocommerce_aircash-partner-phone").intlTelInput({nationalMode: false});</script>
		<?php
		die;
	}

	/**
	 * Display the "Manual configuration" modal form
	 */
	private function display_manual_configuration_form() {
		$this->init_form_fields();
		?>
        <form method="post" class="woocommerce">

            <input type="hidden" name="aircash" value="manual_configuration">
            <img src="<?php echo esc_url( $this->get_assets_base_url() . '/images/aircash-red.svg' ); ?>" alt="Aircash"
                 style="width: 150px; margin: 1rem">

			<?php
			if ( $this->get_account_request_status() === 'approved' ) {
				?>
                <div class="notice notice-warning" style="margin-left: 0">
                    <p>
						<?php echo __( '<b>Warning</b> - changing this values may cause Aircash payment to stop working properly.', 'aircash-for-woocommerce' ); ?>
                    </p>
                </div>
				<?php
			}
			?>

            <table class="form-table">
				<?php $this->generate_settings_html( $this->manual_form_fields ); ?>
            </table>
            <button name="save" class="button-primary"
                    type="submit"><?php echo __( 'Save configuration', 'aircash-for-woocommerce' ); ?></button>
        </form>
		<?php
	}

	/**
	 * Update options after "Manual configuration" form is submitted.
	 */
	private function update_manual_configuration() {
		$post_data = $this->get_post_data();
		$this->update_option( 'aircash-partner-id', $post_data['woocommerce_aircash-woocommerce_aircash-partner-id'] );
		$this->update_option( 'aircash-private-key', $post_data['woocommerce_aircash-woocommerce_aircash-private-key'] );
		$this->update_option( 'aircash-certificate', $post_data['woocommerce_aircash-woocommerce_aircash-certificate'] );
		$this->update_option( 'aircash-passphrase', $post_data['woocommerce_aircash-woocommerce_aircash-passphrase'] );
	}

	/**
	 * "Check configuration" modal content and logic.
	 * If account status is requested and partner ID is deinfed, checks for the production accont status and certificates.
	 * If test mode is enabled checks all the test account status and certificates.
	 */
	public function check_configuration() {
		if ( ! is_admin() ) {
			return;
		}

		if ( ( $_GET['aircash'] ?? null ) !== 'check_configuration' ) {
			return;
		}
		?>
        <img src="<?php echo esc_url( $this->get_assets_base_url() . '/images/aircash-red.svg' ); ?>" alt="Aircash"
             class="aircash-logo" style="width: 150px; margin: 1rem">
        <br>
		<?php
		// Production settings
		$account_status = $this->get_account_request_status();
		$partner_id     = $this->get_option( 'aircash-partner-id' );
		if ( $account_status && $partner_id ) {
			$remote_account_status = $this->update_aircash_account_status();
			$last_check            = $this->get_option( 'aircash-account-request-check-time' );
			$account_status        = $this->get_account_request_status();
			?>
            <h4><?php echo esc_html__( 'Account status', 'aircash-for-woocommerce' ); ?></h4>
            <table class="wp-list-table widefat striped">
                <tr>
                    <td style="white-space: nowrap"><?php echo esc_html__( 'Account status', 'aircash-for-woocommerce' ); ?></td>
                    <td><?php
						if ( $account_status === 'approved' ) {
							?><span id="aircash-account-approved-icon" class="dashicons dashicons-yes-alt"
                                    style="color: #00a32a"></span><?php
						} elseif ( $account_status === 'error_sending' ) {
							?><span class="dashicons dashicons-warning" style="color: #d63638"></span>&nbsp;<?php
						}
						?><b><?php echo esc_html( $account_status ); ?></b></td>
                </tr>
                <tr>
                    <td><?php echo esc_html__( 'Remote check', 'aircash-for-woocommerce' ); ?></td>
                    <td><?php echo esc_html( $remote_account_status ); ?></td>
                </tr><?php
				if ( $last_check ) {
					?>
                    <tr>
                    <td><?php echo esc_html__( 'Last check', 'aircash-for-woocommerce' ); ?></td>
                    <td><?php echo esc_html( date( 'Y-m-d H:i:s', $last_check ) ); ?></td></tr><?php
				}

				?></table>
            <h4><?php
				echo esc_html__( 'Configuration', 'aircash-for-woocommerce' );
				?></h4>

            <table class="wp-list-table widefat striped">
                <tr>
                    <td><?php echo esc_html__( 'Partner ID', 'aircash-for-woocommerce' ); ?></td>
                    <td><code><?php echo esc_html( $partner_id ) ?></code></td>
                </tr>
                <tr>
                    <td><?php echo esc_html__( 'Partner name', 'aircash-for-woocommerce' ); ?></td>
                    <td><?php echo esc_html( $this->get_option( 'aircash-partner-name' ) ); ?></td>
                </tr>
                <tr>
                    <td><?php echo esc_html__( 'E-mail', 'aircash-for-woocommerce' ); ?></td>
                    <td><?php echo esc_html( $this->get_option( 'aircash-partner-email' ) ); ?></td>
                </tr>
                <tr>
                    <td><?php echo esc_html__( 'Public key', 'aircash-for-woocommerce' ); ?></td>
                    <td>
						<?php
						$certificate = $this->get_option( 'aircash-certificate' );
						list( $certificate_first_part, $certificate_rest ) = array(
							substr( $certificate, 0, 157 ),
							substr( $certificate, 157, strlen( $certificate ) - 156 ),
						);
						?>
                        <pre style="margin: 0; font-size: 9px"><?php echo esc_html( $certificate_first_part ); ?></pre>
                        <a href="#"
                           onclick="this.nextElementSibling.style.display='block';this.style.display='none';return false;">
							<?php echo esc_html__( 'Show complete public key', 'aircash-for-woocommerce' ); ?>
                        </a>
                        <pre style="margin: 0; font-size: 9px; display: none"><?php echo esc_html( $certificate_rest ); ?></pre>
                    </td>
                </tr>
                <tr>
                    <td><?php echo esc_html__( 'Currency', 'aircash-for-woocommerce' ); ?></td>
                    <td><?php echo esc_html( $this->get_option( 'aircash-currency' ) ); ?></td>
                </tr>
                <tr>
                    <td><?php echo esc_html__( 'Callback URL', 'aircash-for-woocommerce' ); ?></td>
                    <td><?php echo esc_html( $this->get_option( 'aircash-callback-url' ) ); ?></td>
                </tr>
            </table>
            <h4><?php echo esc_html__( 'Certificate check', 'aircash-for-woocommerce' ); ?></h4><?php
			$private_key = openssl_pkey_get_private( $this->get_option( 'aircash-private-key' ), $this->get_option( 'aircash-passphrase' ) );
			if ( ! $private_key ) {
				?>
                <div class="notice notice-error" style="margin: 0;">
                <p><?php echo esc_html__( 'Private key error', 'aircash-for-woocommerce' ); ?>
                    :<br><code><?php echo esc_html( openssl_error_string() ); ?></code></p></div><?php
			} else {
				$certificate = $this->get_option( 'aircash-certificate' );
				if ( ! $certificate ) {
					?>
                    <div class="notice notice-error" style="margin: 0;">
                    <p><?php echo esc_html__( 'Public certificate not set', 'aircash-for-woocommerce' ); ?></p>
                    </div><?php
				}
				$data_to_sign = 'funky';
				openssl_sign( $data_to_sign, $signed_data, $private_key, OPENSSL_ALGO_SHA256 );
				$verification = @openssl_verify( $data_to_sign, $signed_data, $certificate, OPENSSL_ALGO_SHA256 );
				if ( $verification ) {
					?>
                    <div class="notice notice-success" style="margin: 0"><p><span class="dashicons dashicons-yes-alt"
                                                                                  style="color: #00a32a"></span><?php
						echo esc_html__( 'It\'s all good.', 'aircash-for-woocommerce' ); ?></p></div><?php

				} else {
					?>
                    <div class="notice notice-error" style="margin: 0;"><p><?php
						echo esc_html__( 'Error verifying - check public certificate/passphrase.', 'aircash-for-woocommerce' );
						?><br><code><?php
							echo esc_html( openssl_error_string() ); ?></code></p></div><?php

				}
			}
		}
		if ( $this->is_in_test_mode() ) {
			?>
            <div class="notice notice-warning" style="margin: 0">
                <p><?php echo esc_html__( 'Test mode enabled', 'aircash-for-woocommerce' ); ?></p></div>
            <h3><?php echo esc_html__( 'Test mode configuration', 'aircash-for-woocommerce' ); ?></h3><?php

			$test_account_status        = $this->get_test_account_request_status();
			$test_remote_account_status = $this->update_aircash_account_status( true );
			?>
            <table class="wp-list-table widefat striped">
                <tr>
                    <td style="white-space: nowrap"><?php echo esc_html__( 'Test account status', 'aircash-for-woocommerce' ); ?></td>
                    <td><?php
						if ( $test_account_status === 'approved' ) {
							?><span class="dashicons dashicons-yes-alt" style="color: #00a32a"></span><?php
						} elseif ( $test_account_status === 'error_sending' ) {
							?><span class="dashicons dashicons-warning" style="color: #d63638"></span>&nbsp;<?php
						}
						?><b><?php echo esc_html( $test_account_status ); ?></b></td>
                </tr>
                <tr>
                    <td><?php echo esc_html__( 'Test remote account check', 'aircash-for-woocommerce' ); ?></td>
                    <td><?php echo esc_html( $test_remote_account_status ); ?></td>
                </tr>
            </table>
            <h4><?php echo esc_html__( 'Test configuration', 'aircash-for-woocommerce' ); ?></h4>
            <table class="wp-list-table widefat striped">
                <tr>
                    <td><?php echo esc_html__( 'Test partner ID', 'aircash-for-woocommerce' ); ?></td>
                    <td><code><?php echo esc_html( $this->get_option( 'aircash-test-partner-id' ) ); ?></code></td>
                </tr>
                <tr>
                    <td><?php echo esc_html__( 'Test public key', 'aircash-for-woocommerce' ); ?></td>
                    <td>
						<?php
						$certificate = $this->get_option( 'aircash-test-certificate' );
						list( $certificate_first_part, $certificate_rest ) = array(
							substr( $certificate, 0, 157 ),
							substr( $certificate, 157, strlen( $certificate ) - 156 ),
						);
						?>
                        <pre style="margin: 0; font-size: 9px"><?php echo esc_html( $certificate_first_part ); ?></pre>
                        <a href="#"
                           onclick="this.nextElementSibling.style.display='block';this.style.display='none';return false;">
							<?php echo esc_html__( 'Show complete public key', 'aircash-for-woocommerce' ); ?>
                        </a>
                        <pre style="margin: 0; font-size: 9px; display: none"><?php echo esc_html( $certificate_rest ); ?></pre>
                    </td>
                </tr>
                <tr>
                    <td><?php echo esc_html__( 'Test currency', 'aircash-for-woocommerce' ); ?></td>
                    <td><?php echo esc_html( $this->get_option( 'aircash-test-currency' ) ); ?></td>
                </tr>
                <tr>
                    <td><?php echo esc_html__( 'Test callback URL', 'aircash-for-woocommerce' ); ?></td>
                    <td><?php echo esc_html( $this->get_option( 'aircash-test-callback-url' ) ); ?></td>
                </tr>
            </table>
            <h4><?php echo esc_html__( 'Test certificate check', 'aircash-for-woocommerce' ); ?></h4><?php
			$private_key = openssl_pkey_get_private( $this->get_option( 'aircash-test-private-key' ), $this->get_option( 'aircash-test-passphrase' ) );
			if ( ! $private_key ) {
				?>
                <div class="notice notice-error" style="margin: 0;">
                <p><?php echo esc_html__( 'Private key error', 'aircash-for-woocommerce' ); ?>
                    :<br><code><?php echo esc_html( openssl_error_string() ) ?></code></p></div><?php
			} else {
				$certificate = $this->get_option( 'aircash-test-certificate' );
				if ( ! $certificate ) {
					?>
                    <div class="notice notice-error" style="margin: 0;">
                    <p><?php echo esc_html__( 'Public certificate not set', 'aircash-for-woocommerce' ); ?></p>
                    </div><?php
				}
				$data_to_sign = 'funky';
				openssl_sign( $data_to_sign, $signed_data, $private_key, OPENSSL_ALGO_SHA256 );
				$verification = @openssl_verify( $data_to_sign, $signed_data, $certificate, OPENSSL_ALGO_SHA256 );
				if ( $verification ) {
					?>
                    <div class="notice notice-success" style="margin: 0">
                    <p><span class="dashicons dashicons-yes-alt"
                             style="color: #00a32a"></span> <?php
						echo esc_html__( 'It\'s all good.', 'aircash-for-woocommerce' ); ?></p></div><?php
				} else {
					?>
                    <div class="notice notice-error" style="margin: 0;">
                    <p><?php echo esc_html__( 'Error verifying - check public certificate/passphrase.', 'aircash-for-woocommerce' ); ?>
                        <br>
                        <code><?php
							echo esc_html( openssl_error_string() );
							?></code>
                    </p>
                    </div><?php
				}
			}
		}


		$qr_code_error = false;
		if ( extension_loaded( 'gd' ) ) {
			$qr_code            = new QrCode( 'Test QR code string', new Aircash_Encoding( 'UTF-8' ), new ErrorCorrectionLevelLow(), 200, 10 );
			$writer             = new PngWriter();
			$qr_code_image_data = $writer->write( $qr_code )->getString();


		} elseif ( extension_loaded( 'imagick' ) ) {
			$renderer           = new ImageRenderer( new RendererStyle( 200 ), new ImagickImageBackEnd() );
			$writer             = new Writer( $renderer );
			$qr_code_image_data = $writer->writeString( 'Test QR code string' );

		} else {
			$qr_code_error = true;
		}

		?><h4><?php echo esc_html__( 'QR code generation check', 'aircash-for-woocommerce' ); ?></h4><?php
		if ( extension_loaded( 'gd' ) ) {
			echo esc_html__( 'Renderer', 'aircash-for-woocommerce' ) . ': GD';
		} elseif ( extension_loaded( 'imagick' ) ) {
			echo esc_html__( 'Renderer', 'aircash-for-woocommerce' ) . ': Imagick';
		} else {
			?>
            <div class="notice notice-error" style="margin: 0;"><p><?php
				echo esc_html__( 'No renderer available. Please install GD or Imagick PHP extension.', 'aircash-for-woocommerce' );
				?></p></div><?php
		}

		if ( ! $qr_code_error ) {
			?><br><img class="aircash-qr-code" src="data:image/png;base64,<?php
			// phpcs:ignore
			echo base64_encode( $qr_code_image_data );
			?>" alt="QR Code"><?php
		}
		?>
        <script>aircashCheckConfiguration();</script><?php
		die;
	}

	/**
	 * Get the private key from options, test mode sensitive.
	 *
	 * @return string|null
	 */
	private function get_aircash_private_key(): ?string {
		if ( $this->is_in_test_mode() ) {
			return $this->get_option( 'aircash-test-private-key' );
		} else {
			return $this->get_option( 'aircash-private-key' );
		}

	}

	/**
	 * Get private key passphrase from options.
	 * @return string|null
	 */
	private function get_aircash_passphrase(): ?string {
		if ( $this->is_in_test_mode() ) {
			return $this->get_option( 'aircash-test-passphrase' );
		} else {
			return $this->get_option( 'aircash-passphrase' );
		}
	}

	/**
	 * Get X.509 certificate from options.
	 *
	 * @return string|null
	 */
	private function get_aircash_certificate(): ?string {
		if ( $this->is_in_test_mode() ) {
			return $this->get_option( 'aircash-test-certificate' );
		} else {
			return $this->get_option( 'aircash-certificate' );
		}

	}

	/**
	 * Get Aircash partner ID from options.
	 *
	 * @return string|null
	 */
	private function get_aircash_partner_id(): ?string {
		return $this->is_in_test_mode() ?
			$this->get_option( 'aircash-test-partner-id' ) :
			$this->get_option( 'aircash-partner-id' );
	}

}
