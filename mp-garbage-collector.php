<?php

/**
 * Plugin Name:       MP Garbage Collector
 * Description:       Cleans up database rows of MailPoet
 * Version:           0.0.1
 * Requires at least: 5.7
 * Requires PHP:      7.2
 * Author:            Sebastian Gärtner
 * Author URI:        https://gaertner-webentwicklung.de
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mp-garbage-collector
 * Domain Path:       /languages
 */

if ( ! isset( $mailpoetPlugin )  ) {
	add_action( 'admin_notices', function() {
		$class = 'notice notice-error';
		$message = __( 'Plugin <strong>MailPoet Garbage Handling</strong> is only useful if Plugin <strong>MailPoet</strong> is present.', 'mp-garbage-collection' );
		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $message );
	});
}


class MpGarbageCollector {

	function __construct() {
		/* Add admin notice */
		add_action( 'admin_notices', [ $this, 'handle_admin_notice'] );
		add_action( 'cron_mp_garbage_collection_daily', [ $this, 'garbage_collection' ] );
		$this->garbage_collection();
	}

	function activate() {
		if ( ! current_user_can('activate_plugins') ) {
			return;
		}
		$plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
		check_admin_referer( "activate-plugin_{$plugin}" );

		// schedule for 1am
		$schedule = wp_schedule_event( strtotime('1:00:00'), 'daily', 'cron_mp_garbage_collection_daily' );

		if ( ! is_wp_error( $schedule ) ) {
			set_transient( 'ms-garbage-collector-admin-notice-success', __( 'MP Garbage Collector cron job activated', 'mp-garbage-collection' ) , 5 );
		} else {
			set_transient( 'ms-garbage-collector-admin-notice-error', __( 'MP Garbage Collector cron job activation failed', 'mp-garbage-collection' ) , 5 );
		}
	}

	function deactivate() {
		if ( ! current_user_can('activate_plugins')) {
			return;
		}
		$plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
		check_admin_referer( "deactivate-plugin_{$plugin}" );

		wp_clear_scheduled_hook('cron_mp_garbage_collection_daily');
	}

	function garbage_collection() {
		global $wpdb;

		$date = date('Y-m-d', strtotime('-3 Months'));

		$wpdb->query( $wpdb->prepare( "DELETE FROM " . MP_SCHEDULED_TASK_SUBSCRIBERS_TABLE . " WHERE updated_at < %s", $date ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM " . MP_SCHEDULED_TASKS_TABLE . " WHERE created_at < %s", $date ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM " . MP_STATISTICS_OPENS_TABLE . " WHERE created_at < %s", $date ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM " . MP_STATISTICS_NEWSLETTERS_TABLE . " WHERE sent_at < %s", $date ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM " . MP_STATISTICS_CLICKS_TABLE . " WHERE updated_at < %s", $date ) );
	}


	function handle_admin_notice(){

		/* Check transient, if available display notice */
		if ( $message = get_transient( 'ms-garbage-collector-admin-notice-success' ) ){
			$message = get_transient( 'ms-garbage-collector-admin-notice-success' )
			?>
			<div class="updated notice is-dismissible">
				<p><?= $message ?></p>
			</div>
			<?php
			/* Delete transient, only display this notice once. */
			delete_transient( 'ms-garbage-collector-admin-notice-success' );
		}

		if ( get_transient( 'ms-garbage-collector-admin-notice-error' ) ){
			$message = get_transient( 'ms-garbage-collector-admin-notice-error' );
			?>
			<div class="notice notice-error is-dismissible">
				<p><?= $message ?></p>
			</div>
			<?php
			/* Delete transient, only display this notice once. */
			delete_transient( 'ms-garbage-collector-admin-notice-error' );
		}
	}

}

$mpGarbageCollector = new MpGarbageCollector;

register_activation_hook( __FILE__, [ 'MpGarbageCollector', 'activate'] );

register_deactivation_hook( __FILE__, [ 'MpGarbageCollector', 'deactivate'] );
