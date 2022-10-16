<?php
include_once("partials/notion-content-admin-functions.php"); //Include functions to parse json to html
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://patrickchang.com
 * @since      1.0.0
 *
 * @package    Notion_Content
 * @subpackage Notion_Content/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Notion_Content
 * @subpackage Notion_Content/admin
 * @author     Patrick Chang <patrick@patrickchang.com>
 */
class Notion_Content_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Notion_Content_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Notion_Content_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/notion-content-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/notion-content-admin.js', array( 'jquery' ), $this->version, false );
	}




	public function add_plugin_admin_menu() {
		// add_options_page( 'Email Press Settings', 'Email Press Release', 'manage_options', $this->plugin_name, array($this, 'display_plugin_setup_page'));
		add_menu_page('Notion Content Settings', 'Notion Content', 'edit_pages', 'notion-content', array($this,'display_page_content_setup'));
		add_submenu_page('notion-content', 'Notion Page Content', 'Page Content', 'edit_pages', 'notion-content', array($this, 'display_page_content_setup'));
		add_submenu_page('notion-content', 'Notion Content Setup', 'Setup', 'edit_pages', 'notion-content-setup', array($this, 'display_plugin_setup_page'));
		register_setting("notion_content_plugin", "notion_api_key");
		register_setting("notion_content_plugin", "notion_content_database");
		register_setting("notion_content_plugin", "notion_refresh_interval");
	}

	public function display_plugin_setup_page() {
		global $post;
		include_once("partials/notion-content-setup-display.php");
	}

	private function refresh_notion_page_list() {
		global $wpdb;
		$table_name = $wpdb->prefix . "notion_content";
		settings_fields( 'notion_content_plugin' );
		$api = esc_attr( get_option('notion_api_key'));
		$url = esc_attr( get_option('notion_content_database'));
		$dID = explode("?v=", $url)[0];
		//$database_id = substr($dID, 0, 8)."-".substr($dID, 8, 4)."-".substr($dID, 12, 4)."-".substr($dID, 16, 4)."-".substr($dID, 20, 12);
		$url = "https://api.notion.com/v1/databases/$dID/query";
	
		$response = wp_remote_post($url , array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api,
				'Content-Type' => 'application/json',
				'Notion-Version' => '2021-08-16'
			)
		));
		
		//MB added check for error in response
		if( is_wp_error( $response ) ) {
			error_log(print_r($response, true));
			return false; // Stop processing here on error
		}
		$body = wp_remote_retrieve_body( $response );
		$arrResult = json_decode($body, true);
		$wpdb->update($table_name, array('status' => 'inactive'), array('status' => 'Active'));
		foreach($arrResult["results"] AS $row) {
			$page_id = $row["id"];
			$page_name = $row["properties"]["Name"]["title"][0]["plain_text"];

	
			if($wpdb->get_row("SELECT * FROM $table_name WHERE page_id='$page_id'")) {
				$wpdb->update($table_name, array('page_name' => $page_name, 'status' => 'Active'), array('page_id' => $page_id));
			}
			else {
				// Insert into db
				$time = date("Y-m-d H:i:s");
				$wpdb->insert($table_name, array('time'=> $time, 'page_id' => $page_id, 'page_name' => $page_name));
			}

					//getbody content by
			$this->refresh_notion_content($page_id);
		}
	}

	private function display_content($page_id = 0) {
		global $wpdb;
		$table_name = $wpdb->prefix . "notion_content";
		$my_content = $wpdb->get_row( "SELECT * FROM $table_name WHERE page_id='$page_id'" );
		$text = $my_content->page_content;
		return $text;
	}


	private function refresh_notion_content($page_id, $return_content = false) {
		global $wpdb;
		$table_name = $wpdb->prefix . "notion_content";
		if (!function_exists('settings_fields')) {
			$my_content = $wpdb->get_row( "SELECT * FROM " . $wpdb->prefix . "options WHERE option_name = 'notion_api_key'");
			$api = $my_content->option_value;
		}
		else {
			settings_fields( 'notion_content_plugin' );
			$api = esc_attr( get_option('notion_api_key'));
		}
		$ch = curl_init();
		$page_content = "";
		$pID = str_replace("-", "", $page_id);
		//MB added call to wordpress function wp_remote_post()
		$data = ['page_size'=>100]; //array
		$url = "https://api.notion.com/v1/blocks/$pID/children";
		// $url .= (strpos($url , '?') !== false ? '&' : '?') . http_build_query($data);
		
		$data = array('page_size' => 100);
		$query_url = $url.'?'.http_build_query($data);
		$response = wp_remote_get($query_url , array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api,
				'Content-Type' => 'application/json',
				'Notion-Version' => '2022-06-28'
			)
		));
		

		//MB added check for error in response
		if( is_wp_error( $response ) ) {
			error_log(print_r($response, true));
			return false; // Stop processing here on error
		}
		$body = wp_remote_retrieve_body( $response );
	
		$arrResult = json_decode(	$body , true);
		$arrAnnotations = array( "bold" => "strong", "italic" => "i", "strikethrough" => "del", "underline" => "u", "code" => "code");
		$bulleted_list_item = false;
		$numbered_list_item = false;
		
		$return_html ="";
		foreach($arrResult["results"] AS $block_row) {
			$return_html_temp  = "";
			$block_type = $block_row["type"];
			$block_id = $block_row["id"];

			//Build Tables
			if($block_type==="table"){
				$has_header =  $block_row['table']['has_column_header'];
				$has_row_header = $block_row['table']['has_row_header'];
				$table_width = $block_row['table']['table_width'];

				//Get the block data
				$url = "https://api.notion.com/v1/blocks/$block_id/children";
				$data = array('page_size' => 100);
				$query_url = $url.'?'.http_build_query($data);
				$response = wp_remote_get($query_url , array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $api,
					
						'Content-Type' => 'application/json',
						'Notion-Version' => '2022-06-28'
					)
				));
				$body = wp_remote_retrieve_body( $response );
				$arrResult = json_decode(	$body , true);
				//loop around $arrResult["results"] AS $block_row to get the table data ad output as html
				$return_html_temp .= "<table>";
				$col_count = 0;
				$row_count = 0;
				foreach($arrResult["results"] AS $block_row) {
					
					$return_html_temp .= "<tr>";
					if($row_count == 0){ 
						$headerorno = "<th>";
						$headerornoend = "</th>";
					}
					else{
						$headerorno = "<td>";
						$headerornoend = "</td>";
					}
					foreach($block_row['table_row']['cells'] AS $cell){
							//  $return_html_temp .= $headerorno.$cell[0]['text']['content'].$headerornoend;
							reset($arrAnnotations);
							$open_tag = "";
							$close_tag = "";
							foreach($arrAnnotations AS $ntag => $html_tag) {
								if(isset($cell[0]["annotations"])){
									if($cell[0]["annotations"][$ntag]) {
										$open_tag .= "<$html_tag>";
										$close_tag = "</$html_tag>" . $close_tag;
									}
								}
							}
							if(	$has_row_header == 1 && $row_count == 0){
								$headerorno = "<td class='notion_content_header_row'>";
							}
							//TODO Handle extra cells for multiple colours/annotaions
							$return_html_temp .= $headerorno;
							foreach($cell AS $cell_row){
								if(isset($cell_row['plain_text'])){
									$return_html_temp .= "<span class='notion_content_".$cell_row['annotations']['color']."'>".$cell_row['plain_text'].'</span>';
									
								}
							}
							$return_html_temp .= $headerornoend;


							
							$row_count++;
							
					}
					$return_html_temp .= "</tr>";
					
				}
				$return_html_temp .= "</table>";

				$return_html .= $return_html_temp;

				// error_log(print_r($block_row, true));
			}
			//End Build Table data
			//Get each detail for column blocks
			if($block_type==="column_list"){
				//get children of current block id
				$url = "https://api.notion.com/v1/blocks/$block_id/children";
				$data = array('page_size' => 100);
				$query_url = $url.'?'.http_build_query($data);

				$response = wp_remote_get($query_url , array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $api,
					
						'Content-Type' => 'application/json',
						'Notion-Version' => '2022-06-28'
					)
				));
				$body = wp_remote_retrieve_body( $response );
				$arrResult = json_decode(	$body , true);
				$return_html .="<div class='notion-content-row'>";
				foreach($arrResult["results"] AS $block_row_child) {
					$block_id_child = $block_row_child["id"];
					$url = "https://api.notion.com/v1/blocks/$block_id_child/children";
				
					$response = wp_remote_get($url , array(
						'headers' => array(
							'Authorization' => 'Bearer ' . $api,
						
							'Content-Type' => 'application/json',
							'Notion-Version' => '2022-06-28'
						)
					));
					$body_child = wp_remote_retrieve_body( $response );
					
					$arrResult_column_blocks = json_decode(	$body_child  , true);
					foreach($arrResult_column_blocks AS $block_row_child_body) {
						if(is_array($block_row_child_body) && count($block_row_child_body) > 0){
							$return_html .="<div class='notion-content-columns'>";
							foreach($block_row_child_body AS $block_row_child_body_row) {
								$return_html_temp =return_html_notion_content($block_row_child_body_row, $arrAnnotations, $bulleted_list_item, $numbered_list_item);
								$return_html .= $return_html_temp;
							// error_log('block row body' . print_r($return_html_temp, true));
							}
							
						}
					}
					$return_html .="</div>";
				}
				$return_html = $return_html . "</div>";
			} else {
					//block is not split into columns
					$return_html_temp =return_html_notion_content($block_row, $arrAnnotations, $bulleted_list_item, $numbered_list_item);
					$return_html = $return_html. $return_html_temp;
				
				}
			
		}	
		error_log('Return Html: ' . print_r($return_html, true));	
		
		$time = date("Y-m-d H:i:s");
		$wpdb->update($table_name, array('page_content' => $return_html , 'time' => $time), array('page_id' => $page_id));

	}

	public function refresh_notion_content_pub($page_id) {
		$page_content = $this->refresh_notion_content($page_id, true);
		return $page_content;
	}

	public function display_page_content_setup() {
		global $post;
		global $wpdb;
		$table_name = $wpdb->prefix . "notion_content";
	
		if(isset($_GET["action"])){
		switch($_GET["action"]) {
			case "view_content":
				$output = $this->display_content($_GET["page_id"]);
				error_log('Output: ' . print_r(	$output, true));	
				include_once("partials/notion-content-output.php");
				break;

			case "refresh_content":
				$this->refresh_notion_content($_GET["page_id"]);
				$url = "?page=notion-content";
				echo "<script> window.location.href='$url'; </script>";
				exit;
				break;


			case "refresh_list":
				$this->refresh_notion_page_list();
				$url = "?page=notion-content";
				echo "<script> window.location.href='$url'; </script>";
				exit;
				break;
			default:
				$content_list = "";
				$my_content = $wpdb->get_results( "SELECT * FROM $table_name WHERE `status`='Active'" );
				include_once("partials/notion-content-page-display.php");
		}} else{
			$content_list = "";
				$my_content = $wpdb->get_results( "SELECT * FROM $table_name WHERE `status`='Active'" );
				include_once("partials/notion-content-page-display.php");
		}

	}
}



