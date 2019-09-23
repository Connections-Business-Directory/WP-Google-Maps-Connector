<?php
/**
 * An Connector for Connections Pro and WP Google maps. Adds an "Update all points" option
 *
 * @package   Connections Pro to WP Google Maps Connector
 * @category  Extension
 * @author    Paul Warren
 * @license   GPL-2.0+
 * @link      http://paradime.com
 * @copyright 2014 Paradime Inc.
 *
 * @wordpress-plugin
 * Plugin Name:       Connections Pro to WP Google Maps Connector
 * Plugin URI:        http://paradime.com/wordpress/connections-wpmap-connector/
 * Description:       Connections Pro and WP Google Maps Connector. CSV import is not supported as the address doesn't exist at the time of an available hook. 
 * Version:           2.0
 * Author:            Paul Warren
 * Author URI:        http://paradime.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       connections_wpgm_connector
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists('Connections_WPGM_Connector') ) {

	class Connections_WPGM_Connector {
		private static $mapRecordID = null;
		private static $permlink = null;

		public function __construct() {
			self::defineConstants();
			
			self::prepare_wpgm();
			
			self::registerHooks();
			
			// self::setMapRecord();
		}

		/**
		 * Define the constants.
		 *
		 * @access  private
		 * @since  1.0
		 * @return void
		 */
		private static function defineConstants() {
			define( 'CNKML_CURRENT_VERSION', '1.0' );
			define( 'CNKML_DIR_NAME', plugin_basename( dirname( __FILE__ ) ) );
			define( 'CNKML_BASE_NAME', plugin_basename( __FILE__ ) );
			define( 'CNKML_PATH', plugin_dir_path( __FILE__ ) );
			define( 'CNKML_URL', plugin_dir_url( __FILE__ ) );
		}
		
		private static function prepare_wpgm() {
			return;
			/* This should be an "on activate" hook */
			global $wpdb;
			$table_name = $wpdb->prefix . "wpgmza";
			$results = $wpdb->get_results("DESC $table_name");
			$found = 0;
			foreach ($results as $row ) {
				if ($row->Field == "connections_recordid") {
					$found++;
				}
			}
			if ($found == 0) { $wpdb->query("ALTER TABLE $table_name ADD `connections_recordid` BIGINT"); }
			
			/* This is broken and should be fixed */
			global $wpdb;
			$table_name = $wpdb->prefix . "wpgmza_categories";
			$results = $wpdb->get_results("DESC $table_name");
			$found = 0;
			foreach ($results as $row ) {
				if ($row->Field == "connections_recordid") {
					$found++;
				}
			}
			if ($found == 0) { $wpdb->query("ALTER TABLE $table_name ADD `sortorder` INT"); }
		}

		private static function registerHooks() {
			//       cn_post_process_
			add_action( 'cn_post_process_add-entry', array( __CLASS__, 'saveMarker' ), 99, 4 );
			add_action( 'cn_post_process_update-entry', array( __CLASS__, 'updateMarker' ), 99, 4 );
			add_action( 'cn_post_process_save-entry', array( __CLASS__, 'saveMarker' ), 99, 4 );
			add_action( 'cn_post_process_delete-entry', array( __CLASS__, 'deleteMarker' ), 99, 4 );
			
			// Bulk Actions
			// add_action( 'cn_manage_actions', array( __CLASS__, 'bulkMarker' ), 99, 4 );
			add_action( 'cn_process_cache-entry', array( __CLASS__, 'bulkMarker' ), 99, 4 );
			
			// CSV import doesn't have an address and therefore will not work.
			// add_action( 'cn_saved-entry', array( __CLASS__, 'saveMarker' ), 99, 4 );
			
			
			add_action( 'wpgmza_change_link', array( __CLASS__, 'editLink' ), 99, 4 );
		}
		
		public static function editLink( $link_url, $record ) {
			if (self::$permlink == null) self::setPermLink();
			
			global $connections, $wpdb, $wp_rewrite;
			$connections_base = get_option( 'connections_permalink' );
			
			if ($record->connections_recordid) {
				// Must get the record from the database directly as the connections plugin doesn't have a method to get the record based on ID and not the entry_id.
				$table_name = $wpdb->prefix . "connections_address";
				$entry_id = $wpdb->get_var( $wpdb->prepare("SELECT `entry_id` FROM $table_name WHERE `id` = %d", $record->connections_recordid) );
				
				
				$entry = $connections->retrieve->entry( $entry_id );
				// $link_url = "http://google.ca/".$entry->slug;
				if ( $wp_rewrite->using_permalinks() ) {
					$link_url = trailingslashit( self::$permlink . $connections_base['name_base'] . '/' . urlencode( $entry->slug ) );
				} else {
					$link_url = add_query_arg( array( 'cn-entry-slug' => $entry->slug , 'cn-view' => 'detail' ) , self::$permlink );
				}
			}
			
			return $link_url;
		}
		private static function setPermLink() {
			global $wp_rewrite;
			$homeID = cnSettingsAPI::get( 'connections', 'connections_home_page', 'page_id' );
			// Create the permalink base based on context where the entry is being displayed.
			if ( in_the_loop() && is_page() ) {
				// Only slash it when using pretty permalinks.
				$permalink = $wp_rewrite->using_permalinks() ? trailingslashit( get_permalink( $homeID ) ) : get_permalink( $homeID );
			} else {
				// If using pretty permalinks get the directory home page ID and slash it, otherwise just add the page_id to the query string.
				if ( $wp_rewrite->using_permalinks() ) {
					$permalink = trailingslashit( get_permalink( $homeID ) );
				} else {
					$permalink = add_query_arg( array( 'page_id' => $homeID, 'p' => FALSE  ), get_permalink() );
				}
			}
			self::$permlink = $permalink;
		}
		
		private static function setMapRecord() {
			
			self::$mapRecordID = $mapID;
		}
		private static function getMapRecord($county) {
			global $wpdb;
			$table_name = $wpdb->prefix . "wpgmza_maps";
			
			$county = strtolower($county);
			$mapID = $wpdb->get_var(
				"
				SELECT id
				FROM $table_name
				WHERE map_title like '%[$county]%'
				"
			);
			
			if (!$mapID)  {
				$mapID = $wpdb->get_var(
					"
					SELECT id
					FROM $table_name
					WHERE map_title like '%[default]%'
					"
				);
			}
			return $mapID;
			//return self::$mapRecordID;
		}
		
		public static function updateMarker( $entry ) { self::saveMarker( $entry, true ); }
		public static function saveMarker( $entry, $update = false ) {
		
			global $connections, $wp_rewrite, $wpdb;
			if (self::$permlink == null) self::setPermLink();
			
			/*
			*/
			$entryID = (int) $entry->getId();
			$entry->set( $entryID );
			
			
			$record = new stdClass;
			
			/*
			*/
			$custom_fields = method_exists('cnEntry','getMeta');
			if ($custom_fields) {
				// Query the entry meta.
				/*
				$meta_results = $results[$key]->getMeta( array( 'key' => 'kml_generator', 'single' => TRUE ) );
				$metadata[$key]->title = $meta_results['kml_title'];
				$metadata[$key]->description = $meta_results['kml_description'];
				*/
				$meta_results = $entry->getMeta( array( 'key' => 'county', 'single' => TRUE ) );
				$county = $meta_results;
			} else {
				$county = '';
				/*
				$metadata[$key]->title = trim($results[$key]->getName( array( 'return' => true ) ));
				$metadata[$key]->description = $results[$key]->getBio();
				*/
			}
			
			
			if ( $entry->getVisibility() != 'public' or $entry->getStatus() != 'approved') {
				self::deleteMarker( $entry );
				return;
			}
			
			$record->title = trim($entry->getName( array( 'return' => true ) ));
			$record->description = $entry->getBio();
			$record->coords = array();
			$record->id = $entry->getId();
			$record->link = null;
			
		
			$connections_base = get_option( 'connections_permalink' );
			$entry_full = $connections->retrieve->entry( $entryID );
			if ( $wp_rewrite->using_permalinks() ) {
				$link_url = trailingslashit( self::$permlink . $connections_base['name_base'] . '/' . urlencode( $entry_full->slug ) );
			} else {
				$link_url = add_query_arg( array( 'cn-entry-slug' => $entry_full->slug , 'cn-view' => 'detail' ) , self::$permlink );
			}
			
			$record->link = $link_url;
			/*
			$websites = $entry->getWebsites();
			if (isset($websites[0])) $record->link = $websites[0]->url;
			*/
			
// this is a hack to allow for the searching of categories. Currently, WP Google Maps only supports a single category per marker, yet Connections Pro allows for many-to-many. 
// This puts each category into the description as an html comment which can be searched on through the WP Google Maps plugin's search box. 
// Until the plugin supports multiple categories per marker, I've done it this way.

			# $categories = self::entryCategories( $entryID );
			$table_name = $wpdb->prefix . "wpgmza_categories";
			$sql = $wpdb->prepare( "SELECT wpc.id FROM " . CN_TERMS_TABLE . " AS t INNER JOIN " . CN_TERM_TAXONOMY_TABLE . " AS tt ON t.term_id = tt.term_id INNER JOIN " . CN_TERM_RELATIONSHIP_TABLE . " AS tr ON tt.term_taxonomy_id = tr.term_taxonomy_id INNER JOIN ".$table_name." as wpc ON wpc.category_name = t.name WHERE tt.taxonomy = 'category' AND tr.entry_id = %d ORDER BY wpc.sortorder DESC", $entryID );
			$categories = $wpdb->get_results( $sql );
			
			$catnum = array();
			foreach($categories as $category) {
				$catnum[] = $category->id;
			}
			// This hack moves the combined maple/honey category to the front so the point uses that icon instead.
			if (in_array(5,$catnum) && in_array(6,$catnum)) {
				$index = array_search(11,$catnum);
				unset($catnum[$index]);
				array_unshift($catnum,11);
			}

			
			
			
			$addresses = $entry->getAddresses( array(), false );
			foreach($addresses as $address) {
				$coords = (object) array(
					'addressid' => $address->id,
					'latitude' => $address->latitude,
					'longitude' => $address->longitude,
					'address' => $address->line_1 . ', ' . $address->city . ', ' . $address->state . ', ' . $address->zipcode
				);
				$record->coords[] = $coords;
			}
			
			
			/*
			echo self::getMapRecord($county);
			
			print_r($coords);
			print_r($record);
				echo 'test';
			die();
			*/
			
			if ( empty( $record->coords )) return;
			
			
			$table_name = $wpdb->prefix . "wpgmza";
			foreach( $record->coords as $key => $coord ) {
				$recordID = $wpdb->get_var(
					$wpdb->prepare(
						"
						SELECT id
						FROM $table_name
						WHERE connections_recordid = %d
						",
						$coord->addressid
					)
				);
				// $coord->recordid = $recordID;
				
				if ($recordID) {
					$wpdb->update(
						$table_name, 
						array(
							'address' => $coord->address,
							'description' => $record->description,
							'lat' => $coord->latitude,
							'lng' => $coord->longitude,
							'title' => $record->title,
							'link' => $record->link,
							'category' => join(',',$catnum),
							'map_id' => self::getMapRecord($county)
						), 
						array( 'id' => $recordID ),
						array(
							'%s', '%s', '%s', '%s' ,'%s'
						),
						array( '%d' )
					);
				} else {
					$wpdb->insert(
						$table_name, 
						array(
							'address' => $coord->address,
							'description' => $record->description,
							'lat' => $coord->latitude,
							'lng' => $coord->longitude,
							'title' => $record->title,
							'link' => $record->link,
							'category' => join(',',$catnum),
							'connections_recordid' => $coord->addressid,
							'map_id' => self::getMapRecord($county)
						), 
						array(
							'%s', '%s', '%s', '%s' ,'%s', '%d'
						)
					);
				}
			}
			
				
			
		}
		
		public static function deleteMarker( $entry ) {
			global $wpdb;
			$table_name = $wpdb->prefix . "wpgmza";
			
			$addresses = $entry->getAddresses( array(), FALSE );
			foreach($addresses as $address) {
				$wpdb->delete(
					$table_name, 
					array(
						'connections_recordid' => $address->id
					),
					array( '%d' )
				);
			}
		}
		
		public static function bulkMarker( $action, $ids = array()) {
			global $connections;
			foreach( $ids as $id ) {
				$entry = $connections->retrieve->entry( $id );
				if ($entry) {
					$entry = new cnEntry($entry);
					self::saveMarker( $entry );
				}
			}
		}
		
		// Retrieve the categories.
		public static function entryCategories( $id ) {
			global $wpdb;

			$sql = $wpdb->prepare( "SELECT t.*, tt.* FROM " . CN_TERMS_TABLE . " AS t INNER JOIN " . CN_TERM_TAXONOMY_TABLE . " AS tt ON t.term_id = tt.term_id INNER JOIN " . CN_TERM_RELATIONSHIP_TABLE . " AS tr ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.taxonomy = 'category' AND tr.entry_id = %d ", $id );
			$results = $wpdb->get_results( $sql );
			
			if ( ! empty( $results ) ) {
				usort( $results, array( __CLASS__, 'sortTermsByName' ) );
			}

			return $results;
		}
		
		/**
		 * Sorts terms by name.
		 *
		 * @param object  $a
		 * @param object  $b
		 * @return integer
		 */
		public static function sortTermsByName( $a, $b ) {
			return strcmp( $a->name, $b->name );
		}
	}

	/**
	 * Start up the extension.
	 *
	 * @access public
	 * @since 1.0
	 *
	 * @return mixed (object)|(bool)
	 */
	function Connections_WPGM_Connector() {
	
		$return_flag = true;
		$die_message = array();
		
		if ( !class_exists('connectionsLoad') ) {
			$die_message[] = 'Connections must be installed and active in order use Connections Pro to WP Google Maps Connector.';
			add_action(
				'admin_notices',
				 create_function(
					 '',
					'echo \'<div id="message" class="error"><p><strong>ERROR:</strong> Connections must be installed and active in order use Connections Pro to WP Google Maps Connector.</p></div>\';'
					)
			);
			$return_flag = false;
		}

		if ( !function_exists('wpgmaps_activate') ) {
			$die_message[] = 'WP Google Maps must be installed and active in order use Connections Pro to WP Google Maps Connector.';
			add_action(
				'admin_notices',
				 create_function(
					 '',
					'echo \'<div id="message" class="error"><p><strong>ERROR:</strong> WP Google Maps must be installed and active in order use Connections Pro to WP Google Maps Connector.</p></div>\';'
					)
			);
			$return_flag = false;
		}
		
		if (!$return_flag) { return false; }
		
		return new Connections_WPGM_Connector();
		
	}
	
	/**
	 * Since Connections loads at default priority 10, and this extension is dependent on Connections,
	 * we'll load with priority 11 so we know Connections will be loaded and ready first.
	 */
	add_action( 'plugins_loaded', 'Connections_WPGM_Connector', 11 );
}
