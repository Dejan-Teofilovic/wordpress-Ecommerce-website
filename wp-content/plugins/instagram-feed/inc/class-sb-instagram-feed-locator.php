<?php
/**
 * Class SB_Instagram_Feed_Locator
 *
 * Locates feeds on the site and logs information about them in the database.
 *
 * @since 5.11
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

class SB_Instagram_Feed_Locator {
	const COLUMN_NAMES = array( 'feed_id', 'post_id', 'html_location', 'shortcode_atts', 'last_update' );

	private $feed_details;

	private $expiration_time;

	private $matching_entries;

	public function __construct( $feed_details ) {
		/**
		 * Example of how $feed_details is structured
		 *
		 * $feed_details = array(
		 *   'feed_id' => $transient_name,
		 *   'atts' => $atts,
		 *   'location' => array(
		 *       'post_id' => get_the_ID(),
		 *       'html' => 'unknown'
		 *   )
		 * );
		 */
		$this->feed_details = $feed_details;

		$this->matching_entries = array();

		$this->expiration_time = time() - 2 * WEEK_IN_SECONDS;
	}

	/**
	 * Returns records that match the post ID and feed ID
	 * of the feed being located
	 *
	 * @return array
	 *
	 * @since 5.11
	 */
	public function retrieve_matching_entries() {
		global $wpdb;
		$feed_locator_table_name = $wpdb->prefix . SBI_INSTAGRAM_FEED_LOCATOR;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"
			SELECT *
			FROM $feed_locator_table_name
			WHERE post_id = %d
		  	AND feed_id = %s",
				$this->feed_details['location']['post_id'],
				$this->feed_details['feed_id']
			),
			ARRAY_A
		);

		return $results;
	}

	/**
	 * Add feed being located to the database
	 *
	 * @since 5.11
	 */
	public function insert_entry() {
		global $wpdb;

		$feed_locator_table_name = $wpdb->prefix . SBI_INSTAGRAM_FEED_LOCATOR;
		$two_minutes_ago = date( 'Y-m-d H:i:s', time() - 120 );

		$results_recent_entries = $wpdb->get_results( $wpdb->prepare("
			SELECT COUNT(*) AS num_entries
            FROM $feed_locator_table_name
            WHERE last_update > %s;
            ", $two_minutes_ago ), ARRAY_A );

		// Only allow 5 new entries within 5 minutes
		if ( isset( $results_recent_entries[0]['num_entries'] ) && (int)$results_recent_entries[0]['num_entries'] > 5 ) {
			return;
		}

		// Only allow 1000 total entries
		$results_total_entries = $wpdb->get_results( "
			SELECT COUNT(*) AS num_entries
            FROM $feed_locator_table_name", ARRAY_A );
		if ( isset( $results_total_entries[0]['num_entries'] ) && (int)$results_total_entries[0]['num_entries'] > 1000 ) {
			$this->delete_oldest_entry();
		}

		$affected = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $feed_locator_table_name
      	(feed_id,
      	post_id,
      	html_location,
      	shortcode_atts,
      	last_update)
      	VALUES (
            %s,
	        %d,
	        %s,
	        %s,
	        %s);",
				$this->feed_details['feed_id'],
				$this->feed_details['location']['post_id'],
				$this->feed_details['location']['html'],
				sbi_json_encode( $this->feed_details['atts'] ),
				date( 'Y-m-d H:i:s' )
			)
		);
	}

	/**
	 * Update a record based on the existing "id" column. Location can change
	 * from "unknown" to one of footer, content, header, or sidebar.
	 *
	 * @param $id
	 * @param $location
	 *
	 * @since 5.11
	 */
	public function update_entry( $id, $location ) {
		global $wpdb;

		$feed_locator_table_name = $wpdb->prefix . SBI_INSTAGRAM_FEED_LOCATOR;

		$query = $wpdb->query(
			$wpdb->prepare(
				"
			UPDATE $feed_locator_table_name
			SET last_update = %s, html_location = %s
			WHERE id = %d;",
				date( 'Y-m-d H:i:s' ),
				$location,
				$id
			)
		);
	}

	/**
	 * Processes a feed being located based on whether or not the record
	 * exists as well as whether or not an unknown location needs to be
	 * updated.
	 *
	 * @since 5.11
	 */
	public function add_or_update_entry() {
		if ( empty( $this->feed_details['feed_id'] ) ) {
			return;
		}

		if ( empty( $this->feed_details['location']['post_id'] ) ) {
			return;
		}

		$this->matching_entries = $this->retrieve_matching_entries();

		if ( empty( $this->matching_entries ) ) {
			$this->insert_entry();
		} else {
			$matching_indices  = array();
			$matched_location  = false;
			$non_unknown_match = false;
			$unknown_match     = false;

			foreach ( $this->matching_entries as $index => $matching_entry ) {
				$details_atts  = is_array( $this->feed_details['atts'] ) ? $this->feed_details['atts'] : array();
				$matching_atts = json_decode( $matching_entry['shortcode_atts'], true );
				if ( ! is_array( $matching_atts ) ) {
					$matching_atts = array();
				}
				$atts_diff = array_diff( $matching_atts, $details_atts ); // determines if the shortcode settings match the shortcode settings of an existing feed
				if ( empty( $atts_diff ) ) {
					$matching_indices[] = $matching_entry['id'];
					if ( $matching_entry['html_location'] === $this->feed_details['location']['html'] ) {
						$matched_location = $index;
						$this->update_entry( $matching_entry['id'], $matching_entry['html_location'] );
					}
					if ( $matching_entry['html_location'] !== 'unknown' ) {
						$non_unknown_match = $index;
					} else {
						$unknown_match = $index;
					}
				}
			}

			if ( false === $matched_location ) {
				// if there is no matched location, there is only one feed on the page, and the feed being checked has an unknown location, update the known location
				if ( count( $matching_indices ) === 1
				     && $this->feed_details['location']['html'] === 'unknown'
				     && false !== $non_unknown_match ) {
					$this->update_entry( $this->matching_entries[ $non_unknown_match ]['id'], $this->matching_entries[ $non_unknown_match ]['html_location'] );
				} else {
					if ( $this->feed_details['location']['html'] !== 'unknown'
					     && false !== $unknown_match ) {
						$this->update_entry( $this->matching_entries[ $unknown_match ]['id'], $this->feed_details['location']['html'] );
					} else {
						$this->insert_entry();
					}
				}
			}
		}
	}

	public function delete_oldest_entry() {
		global $wpdb;

		$feed_locator_table_name = $wpdb->prefix . SBI_INSTAGRAM_FEED_LOCATOR;

		$affected = $wpdb->query(
			"DELETE FROM $feed_locator_table_name
					ORDER BY last_update ASC
					LIMIT 1;" );

	}

	/**
	 * Queries the locator table for feeds by feed_id
	 *
	 * @param $args
	 *
	 * @return array|object|null
	 *
	 * @since 4.0
	 */
	public static function instagram_feed_locator_query( $args ) {
		global $wpdb;
		$feed_locator_table_name = $wpdb->prefix . SBI_INSTAGRAM_FEED_LOCATOR;

		$group_by = isset( $args['group_by'] ) ? self::sanitize_group_by( $args['group_by'] ) : '';

		$location_string = 'content';
		if ( isset( $args['html_location'] ) ) {
			$locations       = array_map( 'esc_sql', $args['html_location'] );
			$location_string = implode( "', '", $locations );
		}

		$page = 0;
		if ( isset( $args['page'] ) ) {
			$page = (int) $args['page'] - 1;
			unset( $args['page'] );
		}

		$offset = max( 0, $page * InstagramFeed\Builder\SBI_Db::RESULTS_PER_PAGE );

		if ( isset( $args['shortcode_atts'] ) ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"
			SELECT *
			FROM $feed_locator_table_name
			WHERE shortcode_atts = %s
		  	AND html_location IN ( '$location_string' )
		  	$group_by
		  	LIMIT %d
			OFFSET %d;",
					$args['shortcode_atts'],
					InstagramFeed\Builder\SBI_Db::RESULTS_PER_PAGE,
					$offset
				),
				ARRAY_A
			);
		} else {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"
			SELECT *
			FROM $feed_locator_table_name
			WHERE feed_id = %s
		  	AND html_location IN ( '$location_string' )
		  	$group_by
		  	LIMIT %d
			OFFSET %d;",
					$args['feed_id'],
					InstagramFeed\Builder\SBI_Db::RESULTS_PER_PAGE,
					$offset
				),
				ARRAY_A
			);
		}

		return $results;
	}

	/**
	 * Queries all legacy feeds that have been located
	 *
	 * @param $args
	 *
	 * @return array|object|null
	 *
	 * @since 4.0
	 */
	public static function legacy_instagram_feed_locator_query( $args ) {
		global $wpdb;
		$feed_locator_table_name = $wpdb->prefix . SBI_INSTAGRAM_FEED_LOCATOR;

		$group_by = isset( $args['group_by'] ) ? self::sanitize_group_by( $args['group_by'] ) : '';

		$location_string = 'content';
		if ( isset( $args['html_location'] ) ) {
			$locations       = array_map( 'esc_sql', $args['html_location'] );
			$location_string = implode( "', '", $locations );
		}

		$page = 0;
		if ( isset( $args['page'] ) ) {
			$page = (int) $args['page'] - 1;
			unset( $args['page'] );
		}

		$offset = max( 0, $page * InstagramFeed\Builder\SBI_Db::RESULTS_PER_PAGE );
		$limit  = InstagramFeed\Builder\SBI_Db::RESULTS_PER_PAGE;

		$results = $wpdb->get_results(
			"
			SELECT *
			FROM $feed_locator_table_name
			WHERE feed_id NOT LIKE '*%'
		  	AND html_location IN ( '$location_string' )
		  	$group_by
		  	LIMIT $limit
			OFFSET $offset;",
			ARRAY_A
		);

		return $results;
	}

	public static function update_legacy_to_builder( $args ) {
		global $wpdb;
		$feed_locator_table_name = $wpdb->prefix . SBI_INSTAGRAM_FEED_LOCATOR;

		$data = array(
			'feed_id'        => '*' . $args['new_feed_id'],
			'shortcode_atts' => '{"feed":"' . $args['new_feed_id'] . '"}',
		);

		$affected = $wpdb->query(
			$wpdb->prepare(
				"UPDATE $feed_locator_table_name
         				SET feed_id = %s, shortcode_atts = %s",
				$data['feed_id'],
				$data['shortcode_atts']
			)
		);

		return $affected;
	}

	/**
	 * Simple count of rows based on args
	 *
	 * @param array $args
	 *
	 * @return int
	 *
	 * @since 4.0
	 */
	public static function count( $args ) {
		global $wpdb;
		$feed_locator_table_name = $wpdb->prefix . SBI_INSTAGRAM_FEED_LOCATOR;

		if ( isset( $args['shortcode_atts'] ) ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"
			SELECT COUNT(*) AS num_entries
            FROM $feed_locator_table_name
            WHERE shortcode_atts = %s
            ",
					$args['shortcode_atts']
				),
				ARRAY_A
			);
		} else {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"
			SELECT COUNT(*) AS num_entries
            FROM $feed_locator_table_name
            WHERE feed_id = %s
            ",
					$args['feed_id']
				),
				ARRAY_A
			);
		}

		if ( isset( $results[0]['num_entries'] ) ) {
			return (int) $results[0]['num_entries'];
		}

		return 0;
	}

	/**
	 * Old feeds are only detected once a day to keep load on the server low.
	 *
	 * @return bool
	 *
	 * @since 5.11
	 */
	public static function should_clear_old_locations() {
		$sbi_statuses_option = get_option( 'sbi_statuses', array() );
		$last_old_feed_check = isset( $sbi_statuses_option['feed_locator']['last_check'] ) ? $sbi_statuses_option['feed_locator']['last_check'] : 0;

		return $last_old_feed_check < time() - DAY_IN_SECONDS;
	}

	/**
	 * Old feeds are removed if they haven't been updated in two weeks.
	 *
	 * @since 5.11
	 */
	public static function delete_old_locations() {
		global $wpdb;

		$feed_locator_table_name = $wpdb->prefix . SBI_INSTAGRAM_FEED_LOCATOR;
		$two_weeks_ago           = date( 'Y-m-d H:i:s', time() - 2 * WEEK_IN_SECONDS );

		$affected = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $feed_locator_table_name WHERE last_update < %s;",
				$two_weeks_ago
			)
		);

		$sbi_statuses_option                               = get_option( 'sbi_statuses', array() );
		$sbi_statuses_option['feed_locator']['last_check'] = time();
		if ( ! isset( $sbi_statuses_option['feed_locator']['initialized'] ) ) {
			$sbi_statuses_option['feed_locator']['initialized'] = time();
		}

		update_option( 'sbi_statuses', $sbi_statuses_option, true );
	}

	/**
	 * Feeds are located with the page load randomly (5% or 1/30 loads)
	 * to decrease load on the server.
	 *
	 * If the locating just started (within 5 minutes) it is run more often
	 * to collect feed locations quickly.
	 *
	 * @return bool
	 *
	 * @since 5.11
	 */
	public static function should_do_locating() {
		if ( is_admin() || isset( $_GET['page'] ) && strpos( $_GET['page'], 'sbi' ) !== false ) {
			return false;
		}
		$sbi_statuses_option = get_option( 'sbi_statuses', array() );
		if ( isset( $sbi_statuses_option['feed_locator']['initialized'] )
		     && $sbi_statuses_option['feed_locator']['initialized'] < ( time() - 300 ) ) {
			$should_do_locating = rand( 1, 10 ) === 10;
		} else {
			$should_do_locating = rand( 1, 30 ) === 30;
		}
		$should_do_locating = apply_filters( 'sbi_should_do_locating', $should_do_locating );

		return $should_do_locating;
	}

	/**
	 * Simliar to the should_do_locating method but will add an additional
	 * database query to see if there is a feed with an unknown location that
	 * matches the details of the feed in question.
	 *
	 * @param $feed_id
	 * @param $post_id
	 *
	 * @return bool
	 *
	 * @since 5.11
	 */
	public static function should_do_ajax_locating( $feed_id, $post_id ) {
		if ( is_admin() || isset( $_GET['page'] ) && strpos( $_GET['page'], 'sbi' ) !== false ) {
			return false;
		}
		$sbi_statuses_option = get_option( 'sbi_statuses', array() );
		if ( isset( $sbi_statuses_option['feed_locator']['initialized'] )
		     && $sbi_statuses_option['feed_locator']['initialized'] < ( time() - 300 ) ) {
			$should_do_locating = rand( 1, 10 ) === 10;
		} else {
			$should_do_locating = rand( 1, 30 ) === 30;
		}
		if ( $should_do_locating ) {
			$should_do_locating = self::entries_need_locating( $feed_id, $post_id );
		}

		$should_do_locating = apply_filters( 'sbi_should_do_ajax_locating', $should_do_locating );

		return $should_do_locating;
	}

	/**
	 * Feeds are located with the page load randomly (1/30 loads)
	 * to decrease load on the server.
	 *
	 * If the locating just started (within 5 minutes) it is run more often
	 * to collect feed locations quickly.
	 *
	 * @param $feed_id
	 * @param $post_id
	 *
	 * @return bool
	 *
	 * @since 5.11
	 */
	public static function entries_need_locating( $feed_id, $post_id ) {
		global $wpdb;
		$feed_locator_table_name = $wpdb->prefix . SBI_INSTAGRAM_FEED_LOCATOR;

		$one_day_ago = date( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"
			SELECT id
			FROM $feed_locator_table_name
			WHERE html_location = 'unknown'
			AND last_update < %s
			AND feed_id = %s
			AND post_id = %d
			LIMIT 1;",
				$one_day_ago,
				$feed_id,
				$post_id
			),
			ARRAY_A
		);

		return isset( $results[0] );
	}

	/**
	 * A custom table stores locations
	 *
	 * @since 5.11
	 */
	public static function create_table() {
		global $wpdb;
		global $sb_instagram_posts_manager;

		$feed_locator_table_name = $wpdb->prefix . SBI_INSTAGRAM_FEED_LOCATOR;

		if ( $wpdb->get_var( "show tables like '$feed_locator_table_name'" ) !== $feed_locator_table_name ) {
			$sql = 'CREATE TABLE ' . $feed_locator_table_name . " (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                feed_id VARCHAR(50) DEFAULT '' NOT NULL,
                post_id BIGINT(20) UNSIGNED NOT NULL,
                html_location VARCHAR(50) DEFAULT 'unknown' NOT NULL,
                shortcode_atts LONGTEXT NOT NULL,
                last_update DATETIME
            );";
			$wpdb->query( $sql );
		}
		$error     = $wpdb->last_error;
		$query     = $wpdb->last_query;
		$had_error = false;
		if ( $wpdb->get_var( "show tables like '$feed_locator_table_name'" ) !== $feed_locator_table_name ) {
			$had_error = true;
			$sb_instagram_posts_manager->add_error( 'database_create', '<strong>' . __( 'There was an error when trying to create the database tables used to locate feeds.', 'instagram-feed' ) . '</strong><br>' . $error . '<br><code>' . $query . '</code>' );
		}

		if ( ! $had_error ) {
			$wpdb->query( "ALTER TABLE $feed_locator_table_name ADD INDEX feed_id (feed_id)" );
			$wpdb->query( "ALTER TABLE $feed_locator_table_name ADD INDEX post_id (post_id)" );

			$sb_instagram_posts_manager->remove_error( 'database_create' );
		}
	}

	/**
	 * Counts the number of unique feeds in the database.
	 *
	 * @return int
	 *
	 * @since 5.11
	 */
	public static function count_unique() {
		global $wpdb;

		$feed_locator_table_name = $wpdb->prefix . SBI_INSTAGRAM_FEED_LOCATOR;
		$results_content         = $wpdb->get_results(
			"
			SELECT COUNT(*) AS num_entries
            FROM $feed_locator_table_name
            WHERE html_location = 'content'
            ",
			ARRAY_A
		);

		$results_other = $wpdb->get_results(
			"
			SELECT COUNT(*) AS num_entries
            FROM $feed_locator_table_name
            WHERE html_location != 'content'
            AND html_location != 'unknown'
            GROUP BY feed_id
            ",
			ARRAY_A
		);

		$total = 0;
		if ( isset( $results_content[0]['num_entries'] ) ) {
			$total += (int) $results_content[0]['num_entries'];
		}
		if ( isset( $results_other[0]['num_entries'] ) ) {
			$total += (int) $results_other[0]['num_entries'];
		}

		return $total;
	}

	/**
	 * Creates a summary of the located feeds in an array
	 *
	 * @return array
	 *
	 * @since 5.11
	 */
	public static function summary() {
		global $wpdb;

		$feed_locator_table_name = $wpdb->prefix . SBI_INSTAGRAM_FEED_LOCATOR;

		$locations = array(
			array(
				'label'          => __( 'Content', 'instagram-feed' ),
				'html_locations' => array( 'content', 'unknown' ),
			),
			array(
				'label'          => __( 'Header', 'instagram-feed' ),
				'html_locations' => array( 'header' ),
				'group_by'       => 'feed_id',
			),
			array(
				'label'          => __( 'Sidebar', 'instagram-feed' ),
				'html_locations' => array( 'sidebar' ),
				'group_by'       => 'feed_id',
			),
			array(
				'label'          => __( 'Footer', 'instagram-feed' ),
				'html_locations' => array( 'footer' ),
				'group_by'       => 'feed_id',
			),
		);

		$one_result_found = false;

		foreach ( $locations as $key => $location ) {
			$in       = implode( "', '", $location['html_locations'] );
			$group_by = isset( $location['group_by'] ) ? self::sanitize_group_by( $location['group_by'] ) : '';
			$results  = $wpdb->get_results(
				"
			SELECT *
			FROM $feed_locator_table_name
			WHERE html_location IN ('$in')
		  	$group_by
		  	ORDER BY last_update ASC",
				ARRAY_A
			);

			if ( isset( $results[0] ) ) {
				$one_result_found = true;
			}

			$locations[ $key ]['results'] = $results;
		}

		if ( ! $one_result_found ) {
			return array();
		}

		return $locations;
	}

	public static function sanitize_group_by( $group_by ) {
		if ( in_array( $group_by, self::COLUMN_NAMES, true ) ) {
			return 'GROUP BY ' . $group_by;
		}

		return '';
	}
}
