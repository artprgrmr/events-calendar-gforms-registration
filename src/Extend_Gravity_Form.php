<?php

declare(strict_types=1);
/**
 * Extend a Gravity Forms form.
 *
 * @package     ForwardJump\ECGF_Registration
 * @author      Tim Jensen <info@forwardjump.com>
 * @license     GNU General Public License 2.0+
 * @link        https://www.timjensen.us
 * @since       0.1.0
 */

namespace ForwardJump\ECGF_Registration;

/**
 * Class Extend_Gravity_Form
 *
 * @package ForwardJump\ECGF_Registration
 */
class Extend_Gravity_Form {

	/**
	 * The event id field number key used in the database.
	 *
	 * @var string
	 */
	const EVENT_ID_DB_KEY = 'event_id';

	/**
	 * Post ID.
	 *
	 * @var int|bool
	 */
	protected $post_id = false;

	/**
	 * Event form id.
	 *
	 * @var string|bool
	 */
	protected $event_form_id = false;

	/**
	 * Event form settings.
	 *
	 * @var array
	 */
	protected $event_form_settings = [];

	/**
	 * Maximum number of reservations.
	 *
	 * @var array
	 */
	protected $max_reservations = [];

	/**
	 * Number of booked reservations.
	 *
	 * @var array
	 */
	protected $booked_reservations = [];

	/**
	 * Number of available reservations.
	 *
	 * @var array
	 */
	protected $available_reservations = [];

	/**
	 * Extend_Gravity_Form constructor.
	 */
	public function __construct() {
	}

	/**
	 * Hooks into Gravity Forms to modify the form.
	 *
	 * @return bool|void
	 */
	public function init() {
		add_action( 'wp', [ $this, 'set_properties' ], 5 );
		add_action( 'wp', [ $this, 'gform_hooks' ], 7 );
	}

	/**
	 * Sets the class properties.
	 *
	 * @return bool
	 */
	public function set_properties() {
		if (
			function_exists( 'tribe_is_event' ) && ! tribe_is_event() ||
			function_exists( 'tribe_is_past_event' ) && tribe_is_past_event()
		) {
			return false;
		}

		$this->post_id             = $this->get_post_id();
		$this->event_form_id       = $this->get_event_form_id();
		$this->event_form_settings = $this->get_event_form_settings();

		if ( empty( $this->event_form_id ) || empty( $this->event_form_settings ) ) {
			return false;
		}

		$this->max_reservations       = $this->get_max_reservations();
		$this->booked_reservations    = $this->get_booked_reservations();
		$this->available_reservations = $this->get_available_reservations();

		return true;
	}

	/**
	 * Get the post_id variable.
	 *
	 * @return null|int
	 */
	public function get_post_id() {
		if ( ! $this->post_id ) {
			$this->post_id = get_the_ID();
		}

		return $this->post_id;
	}

	/**
	 * Get the event Gravity Form id.
	 *
	 * @return null|string
	 */
	public function get_event_form_id() {
		if ( ! $this->event_form_id ) {
			$this->set_event_form_id();
		}

		return $this->event_form_id;
	}

	/**
	 * Set the $event_form_id property.
	 *
	 * @return string|bool
	 */
	protected function set_event_form_id() {
		$event_form_id = get_post_meta( $this->get_post_id(), 'ecgf_form_id', true );

		$this->event_form_id = ( 0 === (int) $event_form_id ) ? false : $event_form_id;

		return $this->event_form_id;
	}

	/**
	 * Get the form settings.
	 *
	 * @return null|array
	 */
	public function get_event_form_settings() {
		if ( ! $this->event_form_settings ) {
			$this->event_form_settings = get_post_meta( $this->get_post_id(), 'ecgf_form_settings', true );
		}

		return $this->event_form_settings;
	}

	/**
	 * Get the maximum reservations for each of the selected fields.
	 *
	 * @return array
	 */
	public function get_max_reservations() {
		if ( ! empty( $this->max_reservations ) ) {
			return $this->max_reservations;
		}

		$this->max_reservations = $this->transform_array( $this->event_form_settings, 'field_id', 'max_reservations' );

		return $this->max_reservations;
	}

	/**
	 * Returns the number of participants for the current event.
	 *
	 * @return array
	 */
	public function get_booked_reservations() {

		if ( $this->booked_reservations ) {
			return $this->booked_reservations;
		}

		$meta_key  = self::EVENT_ID_DB_KEY;
		$field_ids = array_column(
			(array) $this->event_form_settings,
			'field_id'
		);

		if ( empty( $field_ids ) ) {
			return [];
		}

		$field_placeholders = implode( ', ', array_fill( 0, count( $field_ids ), '%s' ) );
		$sql_variables      = array_merge( (array) $meta_key, (array) $this->get_post_id(), (array) $field_ids );

		global $wpdb;

		$sql_statement
			= "			
			SELECT ld.field_number as field_id, COUNT(ld.value) AS booked_reservations
			FROM {$wpdb->prefix}rg_lead_detail AS ld
			INNER JOIN {$wpdb->prefix}rg_lead_meta AS lm
			ON lm.lead_id = ld.lead_id
			   AND lm.meta_key IN ( %s )
			       AND lm.meta_value IN ( %s )
			INNER JOIN {$wpdb->prefix}rg_lead AS l
			ON l.id = ld.lead_id
			   AND l.status = 'active'
			WHERE CAST( ld.field_number AS DECIMAL(6,2) ) IN ( {$field_placeholders} )
			GROUP BY ld.field_number
		";

		$booked_reservations = $wpdb->get_results( $wpdb->prepare( $sql_statement, $sql_variables ) );

		$this->booked_reservations = $this->transform_array( $booked_reservations, 'field_id', 'booked_reservations' );

		return $this->booked_reservations;
	}

	/**
	 * Returns the number of available registration slots for the given event.
	 *
	 * @return array
	 */
	public function get_available_reservations() {

		if ( $this->available_reservations ) {
			return $this->available_reservations;
		}

		if ( empty( $this->get_max_reservations() ) ) {
			return [];
		}

		$booked_reservations = $this->get_booked_reservations();

		foreach ( $this->get_max_reservations() as $index => $max ) {
			$booked                                 = isset( $this->get_booked_reservations()[ $index ] ) ? $this->get_booked_reservations()[ $index ] : 0;
			$this->available_reservations[ $index ] = max( 0, ( $max - $booked ) );
		}

		return $this->available_reservations;
	}

	/**
	 * Hook into the appropriate Gravity Form.
	 *
	 * @return bool
	 */
	public function gform_hooks() {
		if (
			function_exists( 'tribe_is_event' ) && ! tribe_is_event() ||
			function_exists( 'tribe_is_past_event' ) && tribe_is_past_event()
		) {
			return false;
		}

		$form_id = $this->get_event_form_id();

		if ( ! $form_id ) {
			return false;
		}

		add_filter( "gform_pre_render_{$form_id}", [ $this, 'insert_registration_notice' ] );

		$field_ids = array_column( $this->get_event_form_settings(), 'field_id' );

		foreach ( (array) $field_ids as $field ) {
			$field_id = floor( $field );
			add_filter( "gform_field_validation_{$form_id}_{$field_id}", [ $this, 'validate_reservation_request' ], 10, 4 );
		}

		add_filter( 'gform_entry_meta', [ $this, 'add_entry_meta' ], 10, 2 );

		add_filter( 'gform_replace_merge_tags', [ $this, 'replace_event_info_merge_tag' ], 10, 3 );
	}

	/**
	 * Inserts an event registration notice above the appropriate form field.
	 *
	 * @param array $form The current form to be filtered.
	 *
	 * @return array
	 */
	public function insert_registration_notice( $form ) {
		$form_meta = get_post_meta( $this->get_post_id(), 'ecgf_form_meta', true );
		$notice    = empty( $form_meta[0]['notice'] ) ? '' : $form_meta[0]['notice'];

		$insert_above_field = isset( $this->get_event_form_settings()[0]['field_id'] ) ? $this->get_event_form_settings()[0]['field_id'] : null;

		/**
		 * Filter the position of the form notice.
		 *
		 * @param string $insert_above_field The ID of the field where the notice will be displayed.
		 * @param array  $form               Gravity Form object.
		 * @param string $notice             The notice that will be displayed.
		 *
		 * @return string
		 */
		$insert_above_field = apply_filters( 'ecgf_form_notice_position', $insert_above_field, $form, $notice );

		if ( ! $insert_above_field || ! $notice ) {
			return $form;
		}

		$form_field_ids = array_column( (array) $form['fields'], 'id' );

		$splice_position = array_search( (int) $insert_above_field, (array) $form_field_ids, true );

		$notice = $this->update_registration_notice( $notice, $this->get_event_form_settings() );

		$new_field = \GF_Fields::create(
			[
				'id'       => 'ecgf_registration_notice',
				'type'     => 'html',
				'content'  => $notice,
				'cssClass' => 'ecgf_registration_notice',
			]
		);

		array_splice( $form['fields'], $splice_position, 0, [ $new_field ] );

		return $form;
	}

	/**
	 * Validate the reservation request to make sure the max number is not
	 * exceeded.
	 *
	 * @param array  $result Validation result.
	 * @param string $value  Form field value.
	 * @param array  $form   Form The current form to be filtered.
	 * @param object $field  Form field data.
	 *
	 * @return mixed
	 */
	public function validate_reservation_request( $result, $value, $form, $field ) {

		if ( isset( $result['is_valid'] ) && false === (bool) $result['is_valid'] || empty( $value ) ) {
			return $result;
		}

		$available_reservations = null;

		// Handle complex GF fields.
		if ( is_array( $value ) ) {
			$value = array_filter( $value );

			if ( ! empty( $value ) ) {
				$keys                   = array_keys( $value );
				$available_reservations = isset( $this->get_available_reservations()[ $keys[0] ] ) ? $this->get_available_reservations()[ $keys[0] ] : null;
			}
		} else {
			$available_reservations = isset( $this->get_available_reservations()[ $field->id ] ) ? $this->get_available_reservations()[ $field->id ] : null;
		}

		if ( is_null( $available_reservations ) ) {
			return $result;
		}

		$result['is_valid'] = ( (int) $value <= $available_reservations );

		if ( ! $result['is_valid'] ) {

			if ( 0 === (int) $available_reservations ) {
				$result['message'] = 'Sorry, there are no more seats available.';
			} else {
				$result['message'] = "Please enter a value up to {$available_reservations}";
			}
		}

		/**
		 * Filter the validation result.
		 *
		 * @param array  $result                 Validation result.
		 * @param int    $available_reservations Number of available reservations.
		 * @param string $value                  Form field value.
		 * @param array  $form                   Form The current form to be filtered.
		 * @param object $field                  Form field data.
		 *
		 * @return array
		 */
		$result = apply_filters( 'ecgf_field_validation_result', $result, $available_reservations, $value, $form, $field );

		return $result;
	}

	/**
	 * Adds event_id meta to the entry.
	 *
	 * @param array $entry_meta Entry meta.
	 * @param int   $form_id    Form ID.
	 *
	 * @return array
	 */
	public function add_entry_meta( $entry_meta, $form_id ) {
		if ( (int) $form_id !== (int) $this->get_event_form_id() ) {
			return $entry_meta;
		}

		$entry_meta['event_id'] = [
			'label'                      => 'Event Info',
			'is_numeric'                 => true,
			'update_entry_meta_callback' => function () {
				return $this->get_post_id();
			},
			'is_default_column'          => true,
		];

		return $entry_meta;
	}

	/**
	 * Replaces the merge tag {event_info}.
	 *
	 * @param string $text  The current text in which merge tags are being replaced.
	 * @param array  $form  The current form.
	 * @param array  $entry The current entry.
	 *
	 * @return string
	 */
	function replace_event_info_merge_tag( $text, $form, $entry ) {

		if ( empty( $entry ) || (int) $form['id'] !== (int) $this->get_event_form_id() ) {
			return $text;
		}

		$custom_merge_tag = '{event_info}';

		if ( false === strpos( $text, $custom_merge_tag ) ) {
			return $text;
		}

		$event_id = gform_get_meta( $entry['id'], 'event_id' );

		if ( ! $event_id ) {
			return $text;
		}

		$event_info = sprintf(
			'<a href="%s">%s</a>', get_the_permalink( $event_id ),
			$event_id . ' - ' . get_the_title( $event_id )
		);

		/**
		 * Change the default text that replaces the {event_info} merge tag.
		 *
		 * @param string $event_info Text that will replace the {event_info} merge tag.
		 * @param string $event_id   Event ID.
		 *
		 * @return string
		 */
		$event_info = apply_filters( 'ecgf_event_info_merge_tag', $event_info, $event_id );

		$text = str_replace( $custom_merge_tag, $event_info, $text );

		return $text;
	}

	/**
	 * Converts the key value pair for the input array.
	 *
	 * @param array  $array Input array to be transformed.
	 * @param string $key   Array value that should be converted to the key.
	 * @param string $value Array value in the new key value pair.
	 *
	 * @return array
	 */
	protected function transform_array( $array, $key, $value ) {
		$keys   = array_column( (array) $array, $key );
		$values = array_column( (array) $array, $value );

		// Array combine requires arrays of equal size.
		if ( count( (array) $keys ) !== count( (array) $values ) ) {
			return null;
		}

		$transformed_array = array_combine( $keys, $values );

		return $transformed_array;
	}

	/**
	 * Update the event registration notice.
	 *
	 * @param string $message Message to be displayed.
	 * @param array  $fields  Field data.
	 *
	 * @return string
	 */
	protected function update_registration_notice( $message, $fields ) {
		$remaining_slots  = array_values( (array) $this->get_available_reservations() );
		$number_of_fields = count( $remaining_slots );

		$search_replace = [];
		for ( $i = 0; $i < $number_of_fields; $i ++ ) {
			$search                    = '{field_' . ( $i + 1 ) . '}';
			$replace                   = $remaining_slots[ $i ];
			$search_replace[ $search ] = $replace;
		}

		$updated_message = str_replace(
			array_keys( (array) $search_replace ),
			array_values( (array) $search_replace ),
			$message
		);

		/**
		 * Change the form notice text.
		 *
		 * @param string $updated_message Form notice that will be displayed to the user.
		 * @param string $message         Original notice before it was updated.
		 * @param array  $search_replace  Array of search replace pairs.
		 *
		 * @return string
		 */
		$updated_message = apply_filters( 'ecgf_form_notice_text', $updated_message, $message, $search_replace );

		/**
		 * Filter the notice markup.
		 *
		 * @param array Opening and Closing markup for the form notice.
		 *
		 * @return array
		 */
		$markup = apply_filters( 'ecgf_form_notice_markup',
			[
				'open'  => '<span>',
				'close' => '</span>',
			]
		);

		return $markup['open'] . $updated_message . $markup['close'];
	}
}
