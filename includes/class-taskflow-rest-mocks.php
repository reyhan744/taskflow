<?php
/**
 * REST controller for the MOCK integrations: payment gateway and e-mail.
 * Payment routes require taskflow_manage; mail routes are open to editors.
 * Every response is explicitly labeled MOCK.
 *
 * @package TaskFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskFlow_Rest_Mocks
 */
class TaskFlow_Rest_Mocks {

	/**
	 * Route registration.
	 *
	 * @return void
	 */
	public static function register() {
		$ns = TASKFLOW_REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/mock/payments',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_payments' ),
					'permission_callback' => array( 'TaskFlow_REST', 'perm_manage' ),
					'args'                => array(
						'status' => array(
							'default'           => '',
							'sanitize_callback' => 'sanitize_key',
						),
						'search' => array(
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'page' => array(
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page' => array(
							'default'           => 20,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_payment' ),
					'permission_callback' => array( 'TaskFlow_REST', 'perm_manage' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/mock/payments/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_payment' ),
					'permission_callback' => array( 'TaskFlow_REST', 'perm_manage' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/mock/payments/(?P<id>\d+)/finalize',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'finalize_payment' ),
					'permission_callback' => array( 'TaskFlow_REST', 'perm_manage' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/mock/email/outbox',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'outbox' ),
					'permission_callback' => array( 'TaskFlow_REST', 'perm_manage_or_edit_others' ),
					'args'                => array(
						'search' => array(
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'page' => array(
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page' => array(
							'default'           => 20,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/mock/email/send',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'send_email' ),
					'permission_callback' => array( 'TaskFlow_REST', 'perm_manage_or_edit_others' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/mock/email/inbox',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'inbox' ),
					'permission_callback' => array( 'TaskFlow_REST', 'perm_manage_or_edit_others' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'simulate_incoming' ),
					'permission_callback' => array( 'TaskFlow_REST', 'perm_manage_or_edit_others' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/mock/email/inbox/(?P<id>\d+)/read',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'inbox_read' ),
					'permission_callback' => array( 'TaskFlow_REST', 'perm_manage_or_edit_others' ),
				),
			)
		);
	}

	/**
	 * Mark a payload as mock.
	 *
	 * @param array<string,mixed> $data Payload.
	 * @return array<string,mixed>
	 */
	private static function with_mock_flag( $data ) {
		$data['is_mock'] = true;

		return $data;
	}

	/**
	 * GET /mock/payments.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response
	 */
	public static function list_payments( $request ) {
		$result = ( new TaskFlow_Mock_Payment() )->all(
			array(
				'status'   => sanitize_key( (string) $request->get_param( 'status' ) ),
				'search'   => sanitize_text_field( (string) $request->get_param( 'search' ) ),
				'page'     => max( 1, absint( $request->get_param( 'page' ) ) ),
				'per_page' => min( 100, max( 1, absint( $request->get_param( 'per_page' ) ) ) ),
			)
		);

		$items = array();

		foreach ( $result['items'] as $row ) {
			$items[] = self::with_mock_flag( $row );
		}

		return rest_ensure_response(
			array(
				'items'  => $items,
				'total'  => $result['total'],
				'totals' => ( new TaskFlow_Mock_Payment() )->totals(),
				'currency' => TaskFlow_Settings::get( 'currency', 'USD' ),
			)
		);
	}

	/**
	 * POST /mock/payments – create a simulated transaction.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_payment( $request ) {
		$amount = TaskFlow_Validation::amount( $request->get_param( 'amount' ) );

		if ( is_wp_error( $amount ) ) {
			return TaskFlow_REST::bad_request( $amount );
		}

		$simulate = sanitize_key( (string) $request->get_param( 'simulate' ) );

		if ( ! in_array( $simulate, array( 'success', 'failed', 'pending' ), true ) ) {
			return TaskFlow_REST::bad_request( __( 'The simulated outcome must be success, failed or pending.', 'taskflow' ) );
		}

		$payment = ( new TaskFlow_Mock_Payment() )->create(
			$amount,
			sanitize_text_field( (string) $request->get_param( 'currency' ) ) ?: TaskFlow_Settings::get( 'currency', 'USD' ),
			$simulate,
			(string) $request->get_param( 'order_ref' )
		);

		( new TaskFlow_Activity() )->log(
			null,
			'mock_payment',
			array(
				'ref'    => $payment['gateway_ref'],
				'status' => $payment['status'],
			),
			sprintf(
				/* translators: 1: gateway reference, 2: status */
				__( 'Created a MOCK payment (%1$s) with status "%2$s".', 'taskflow' ),
				$payment['gateway_ref'],
				$payment['status']
			)
		);

		$response = rest_ensure_response(
			array(
				'item'    => self::with_mock_flag( $payment ),
				'message' => __( 'A MOCK transaction was created.', 'taskflow' ),
			)
		);
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * GET /mock/payments/{id}.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_payment( $request ) {
		$payment = ( new TaskFlow_Mock_Payment() )->find( absint( $request['id'] ) );

		if ( ! $payment ) {
			return TaskFlow_REST::not_found();
		}

		return rest_ensure_response( array( 'item' => self::with_mock_flag( $payment ) ) );
	}

	/**
	 * POST /mock/payments/{id}/finalize – resolve a pending transaction.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function finalize_payment( $request ) {
		$result = sanitize_key( (string) $request->get_param( 'result' ) );

		if ( ! in_array( $result, array( 'success', 'failed' ), true ) ) {
			return TaskFlow_REST::bad_request( __( 'The final result must be success or failed.', 'taskflow' ) );
		}

		$payment = ( new TaskFlow_Mock_Payment() )->finalize( absint( $request['id'] ), $result );

		if ( ! $payment ) {
			return TaskFlow_REST::not_found();
		}

		return rest_ensure_response(
			array(
				'item'    => self::with_mock_flag( $payment ),
				'message' => __( 'The MOCK transaction was updated.', 'taskflow' ),
			)
		);
	}

	/**
	 * GET /mock/email/outbox.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response
	 */
	public static function outbox( $request ) {
		$result = ( new TaskFlow_Mock_Mailer() )->outbox(
			array(
				'search'   => sanitize_text_field( (string) $request->get_param( 'search' ) ),
				'page'     => max( 1, absint( $request->get_param( 'page' ) ) ),
				'per_page' => min( 100, max( 1, absint( $request->get_param( 'per_page' ) ) ) ),
			)
		);

		$items = array();

		foreach ( $result['items'] as $row ) {
			$items[] = self::with_mock_flag( $row );
		}

		return rest_ensure_response(
			array(
				'items' => $items,
				'total' => $result['total'],
			)
		);
	}

	/**
	 * POST /mock/email/send – simulate an outgoing e-mail.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function send_email( $request ) {
		$to = TaskFlow_Validation::email( $request->get_param( 'to' ) );

		if ( is_wp_error( $to ) ) {
			return TaskFlow_REST::bad_request( $to );
		}

		$subject = sanitize_text_field( (string) $request->get_param( 'subject' ) );

		if ( '' === $subject ) {
			return TaskFlow_REST::bad_request( __( 'The subject is required.', 'taskflow' ) );
		}

		$mailer = new TaskFlow_Mock_Mailer();
		$sent   = $mailer->send( $to, '[MOCK] ' . $subject, sanitize_textarea_field( (string) $request->get_param( 'body' ) ) );

		return rest_ensure_response(
			array(
				'item'    => self::with_mock_flag( $sent ),
				'message' => __( 'The MOCK e-mail was stored in the Outbox.', 'taskflow' ),
			)
		);
	}

	/**
	 * GET /mock/email/inbox.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response
	 */
	public static function inbox( $request ) {
		$result = ( new TaskFlow_Mock_Mailer() )->inbox(
			array(
				'page'     => max( 1, absint( $request->get_param( 'page' ) ) ),
				'per_page' => min( 100, max( 1, absint( $request->get_param( 'per_page' ) ) ) ),
			)
		);

		$items = array();

		foreach ( $result['items'] as $row ) {
			$items[] = self::with_mock_flag( $row );
		}

		return rest_ensure_response(
			array(
				'items' => $items,
				'total' => $result['total'],
			)
		);
	}

	/**
	 * POST /mock/email/inbox – simulate an INCOMING e-mail.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function simulate_incoming( $request ) {
		$from = TaskFlow_Validation::email( $request->get_param( 'from_email' ) );

		if ( is_wp_error( $from ) ) {
			return TaskFlow_REST::bad_request( $from );
		}

		$subject = sanitize_text_field( (string) $request->get_param( 'subject' ) );

		if ( '' === $subject ) {
			return TaskFlow_REST::bad_request( __( 'The subject is required.', 'taskflow' ) );
		}

		$received = ( new TaskFlow_Mock_Mailer() )->simulate_incoming(
			$from,
			sanitize_text_field( (string) $request->get_param( 'from_name' ) ),
			'[MOCK] ' . $subject,
			sanitize_textarea_field( (string) $request->get_param( 'body' ) )
		);

		return rest_ensure_response(
			array(
				'item'    => self::with_mock_flag( $received ),
				'message' => __( 'A MOCK incoming e-mail was stored in the Inbox.', 'taskflow' ),
			)
		);
	}

	/**
	 * POST /mock/email/inbox/{id}/read.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function inbox_read( $request ) {
		$mailer = new TaskFlow_Mock_Mailer();

		if ( ! $mailer->find_inbox( absint( $request['id'] ) ) ) {
			return TaskFlow_REST::not_found();
		}

		$mailer->mark_inbox_read( absint( $request['id'] ) );

		return rest_ensure_response(
			array(
				'item'    => $mailer->find_inbox( absint( $request['id'] ) ),
				'message' => __( 'Marked as read.', 'taskflow' ),
			)
		);
	}
}
