<?php

namespace Uncanny_Automator;

/**
 * Class WC_VIEWPRODUCT
 *
 * @package Uncanny_Automator
 */
class WC_VIEWPRODUCT {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WC';

	/**
	 * @var string
	 */
	private $trigger_code;
	/**
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * SetAutomatorTriggers constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'VIEWWOOPRODUCT';
		$this->trigger_meta = 'WOOPRODUCT';
		$this->define_trigger();
	}

	/**
	 *
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name(),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/woocommerce/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - WooCommerce */
			'sentence'            => sprintf( esc_attr__( 'A user views {{a product:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - WooCommerce */
			'select_option_name'  => esc_attr__( 'A user views {{a product}}', 'uncanny-automator' ),
			'action'              => 'template_redirect',
			'priority'            => 90,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'view_woo_product' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @return array
	 */
	public function load_options() {
		Automator()->helpers->recipe->woocommerce->options->load_options = true;

		$options = array(
			'options' => array(
				Automator()->helpers->recipe->woocommerce->options->all_wc_view_products(),
				Automator()->helpers->recipe->options->number_of_times(),
			),
		);

		return Automator()->utilities->keep_order_of_options( $options );
	}

	/**
	 *
	 */
	public function view_woo_product() {

		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( 'product' !== $post->post_type ) {
			return;
		}

		$user_id = get_current_user_id();
		$args    = array(
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => $post->ID,
			'user_id' => $user_id,
		);

		$arr = Automator()->maybe_add_trigger_entry( $args, false );

		if ( $arr ) {
			foreach ( $arr as $result ) {
				if ( true === $result['result'] ) {
					$trigger_meta = array(
						'user_id'        => $user_id,
						'trigger_id'     => $result['args']['trigger_id'],
						'trigger_log_id' => $result['args']['trigger_log_id'],
						'run_number'     => $result['args']['run_number'],
					);

					// product_id Token
					Automator()->db->token->save( 'product_id', $post->ID, $trigger_meta );

					Automator()->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}

}
