<?php
if ( ! defined( 'WP_CLI' ) && WP_CLI ) {
	return;
}

class VIP_Remove_Users_CSV extends WP_WPCOM_CLI_Command {
	/**
	 * Removes users from site based on a CSV of usernames. Requires ID of a user to whom posts can be reassigned. That user will not be removed if it is listed in the CSV.
	 *
	 * ## OPTIONS
	 *
	 * --csv=<csv-file>
	 * : Path to csv list of users to remove.
	 *
	 * --reassign=<reassign>
	 * : Posts will be reassigned to this user.
	 *
	 * [--dry-run]
	 * : List users to be removed but perform no actions. Default true.
	 */
	public function __invoke( $args, $assoc_args ) {
		$dry_run = isset( $assoc_args['dry-run'] ) ? ! ( 'false' === $assoc_args['dry-run'] ) : true;


		if ( empty( $assoc_args['csv'] ) || ! is_readable( $assoc_args['csv'] ) ) {
			WP_CLI::error( 'Missing `csv` or invalid `csv` file' );
		}

		if ( empty( $assoc_args['reassign'] ) ) {
			WP_CLI::error( 'Missing user reassignment argument.' );
		}

		$reassign = absint( $assoc_args['reassign'] );

		if ( ! $reassignment_user = get_user_by( 'ID', $reassign ) ) { // user not valid
			WP_CLI::error( 'User id for post reassignment is not valid. Use `wp user list --role=administrator` to get current admin user IDs.' );
		}

		if ( ! $dry_run ) {
			WP_CLI::confirm( 'Are you sure you want to proceed? You can use the --dry-run option first to make sure.' );
		}

		WP_CLI::line( sprintf( 'Reassigning posts belonging to removed users to %s', $reassignment_user->user_login ) );

		// Get a linecount for the file. SplFileObject::seek(PHP_MAX_INT) will set the cursor at the highest line number in the file without loading the contents into memory.
		$file = new SplFileObject( $assoc_args['csv'], 'r' );
		$file->seek( PHP_INT_MAX );
		$linecount = $file->key();
		$file = null;

		$notify = \WP_CLI\Utils\make_progress_bar( 'Removing...', $linecount );

		foreach ( new \WP_CLI\Iterators\CSV( $assoc_args['csv'] ) as $i => $csv_user ) {
			$csv_user = wp_parse_args( $csv_user, [
				'ID' => '',
				'user_login' => '',
			] );

			$user_login = $csv_user['user_login'];

			$user = get_user_by( 'login', $user_login );

			if ( ! $user ) {
				WP_CLI::warning( sprintf( 'User %s does not exist', $user_login ) );
				continue;
			}

			if ( $user->ID === $reassign ) {
				WP_CLI::warning( sprintf( 'Skipping user %s with ID %d', $user_login, $reassign ) );
				continue;
			}

			if ( ! $dry_run ) {
				$removed = remove_user_from_blog( $user->ID, get_current_blog_id(), $reassign );

				if ( is_wp_error( $removed ) ) {
					WP_CLI::warning( sprintf( 'Failed to remove user (%s): %s', $user_login, $removed->get_error_message() ) );
					continue;
				} elseif ( ! $removed ) {
					WP_CLI::warning( sprintf( 'Failed to remove user (%s)', $user_login ) );
					continue;
				}
			} // End if().

			$notify->tick();

			$dry_run_reminder = $dry_run ? ' (dry run) ' : '';

			WP_CLI::line( sprintf( '%s Removed user (%s)', $dry_run_reminder, $user_login ) );

		} // End foreach().

		$notify->finish();

		WP_CLI::success( 'All done.' );
	}
}

WP_CLI::add_command( 'csv-remove-users', 'VIP_Remove_Users_CSV' );
