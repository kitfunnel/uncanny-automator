<?php

namespace Uncanny_Automator;

/**
 * Uncanny Toolkit - Trigger: A user is imported to {{a LearnDash Course}}
 */
class UT_USER_IMPORTED_IN_COURSE {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'UNCANNYTOOLKIT';

	/**
	 * Trigger Code
	 *
	 * @var string
	 */
	private $trigger_code;
	/**
	 * Trigger Meta
	 *
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		if ( ! defined( 'LEARNDASH_VERSION' ) || ! defined( 'UNCANNY_TOOLKIT_PRO_VERSION' ) ) {
			return;
		}
		$this->trigger_code = 'UTUSERIMPORTEDCOURSE';
		$this->trigger_meta = 'UOUSERIMPORTEDCOURSE';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/uncanny-toolkit/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'meta'                => $this->trigger_meta,
			/* translators: Logged-in trigger - Uncanny Toolkit */
			'sentence'            => sprintf( esc_attr__( 'A user is imported to {{a LearnDash course:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Uncanny Toolkit */
			'select_option_name'  => esc_attr__( 'A user is imported to {{a LearnDash course}}', 'uncanny-automator' ),
			'action'              => 'uo_after_user_row_imported',
			'priority'            => 20,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'a_user_is_imported' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @return array[]
	 */
	public function load_options() {
		$all_courses = Automator()->helpers->recipe->learndash->options->all_ld_courses( null, $this->trigger_meta );
		if ( isset( $all_courses['relevant_tokens'] ) ) {
			unset( $all_courses['relevant_tokens'] );
		}

		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					$all_courses,
				),
			)
		);
	}

	/**
	 * Running an actual function on the trigger
	 *
	 * @param $user_id
	 * @param $csv_data
	 * @param $csv_header
	 * @param $key_location
	 */
	public function a_user_is_imported( $user_id, $csv_data, $csv_header, $key_location ) {

		if ( ! is_numeric( $user_id ) ) {
			return;
		}

		$meta_value = Uncanny_Toolkit_Helpers::build_token_data( $csv_data, $csv_header, $key_location, $user_id );
		$recipes    = Automator()->get->recipes_from_trigger_code( $this->trigger_code );

		if ( empty( $recipes ) ) {
			return;
		}

		$required_course = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );

		if ( empty( $required_course ) ) {
			return;
		}

		if ( ! isset( $meta_value['learndash_course_ids'] ) ) {
			return;
		}

		if ( empty( $meta_value['learndash_course_ids'] ) ) {
			return;
		}

		foreach ( $meta_value['learndash_course_ids'] as $course_id ) {
			foreach ( $recipes as $recipe_id => $recipe ) {
				foreach ( $recipe['triggers'] as $trigger ) {
					$trigger_id = $trigger['ID'];//return early for all products
					if ( ! isset( $required_course[ $recipe_id ] ) ) {
						continue;
					}
					if ( ! isset( $required_course[ $recipe_id ][ $trigger_id ] ) ) {
						continue;
					}
					if ( intval( '-1' ) === intval( $required_course[ $recipe_id ][ $trigger_id ] ) || (int) $required_course[ $recipe_id ][ $trigger_id ] === (int) $course_id ) {
						$args = array(
							'code'             => $this->trigger_code,
							'meta'             => $this->trigger_meta,
							'ignore_post_id'   => true,
							'user_id'          => $user_id,
							'is_signed_in'     => true,
							'recipe_to_match'  => $recipe_id,
							'trigger_to_match' => $trigger_id,
						);

						$this->complete_trigger( $meta_value, $args, $course_id );
					}
				}
			}
		}
	}

	/**
	 * Running an actual function on the trigger
	 *
	 * @param $meta_value
	 * @param $args
	 * @param $course_id
	 */
	public function complete_trigger( $meta_value, $args, $course_id ) {

		$results                              = Automator()->process->user->maybe_add_trigger_entry( $args, false );
		$meta_value['learndash_course_id']    = $course_id;
		$meta_value['learndash_course_title'] = get_the_title( $course_id );
		$serialized                           = maybe_serialize( $meta_value );
		if ( empty( $results ) ) {
			return;
		}
		foreach ( $results as $rr ) {
			if ( ! $rr['result'] ) {
				continue;
			}
			$trigger_id     = (int) $rr['args']['trigger_id'];
			$user_id        = (int) $rr['args']['user_id'];
			$trigger_log_id = (int) $rr['args']['trigger_log_id'];
			$run_number     = (int) $rr['args']['run_number'];
			$token_args     = array(
				'user_id'        => $user_id,
				'trigger_id'     => $trigger_id,
				'run_number'     => $run_number, //get run number
				'trigger_log_id' => $trigger_log_id,
			);

			Automator()->db->trigger->add_token_meta( 'imported_row', $serialized, $token_args );

			Automator()->process->user->maybe_trigger_complete( $rr['args'] );
		}
	}

}
