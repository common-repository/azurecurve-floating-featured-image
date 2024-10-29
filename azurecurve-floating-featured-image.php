<?php
/*
Plugin Name: azurecurve Floating Featured Image
Plugin URI: http://development.azurecurve.co.uk/plugins/floating-featured-image/

Description: Shortcode allowing a floating featured image to be placed at the top of a post
Version: 2.2.0

Author: azurecurve
Author URI: http://development.azurecurve.co.uk/

Text Domain: azc-ffi
Domain Path: /languages

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.

The full copy of the GNU General Public License is available here: http://www.gnu.org/licenses/gpl.txt
 */

global $azc_ffi_db_version;
$azc_ffi_db_version = '2.1.0';

function azc_ffi_install() {
	global $wpdb;
	global $azc_ffi_db_version;

	$table_name = $wpdb->prefix . 'azc_ffi_images';
	
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		ffikey varchar(50) NOT NULL,
		image varchar(200) NOT NULL,
		title varchar(300) NOT NULL,
		alt varchar(300) NOT NULL,
		is_tag bit NOT NULL,
		taxonomy varchar(300) NOT NULL,
		PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	add_option( 'azc_ffi_db_version', $azc_ffi_db_version );
}
register_activation_hook( __FILE__, 'azc_ffi_install' );

function azc_ffi_update_db_check() {
	global $wpdb;
    global $azc_ffi_db_version;
	$installed_ver = get_option( "azc_ffi_images" );
    if ( get_site_option( 'azc_ffi_db_version' ) != $azc_ffi_db_version ) {
		$table_name = $wpdb->prefix . 'azc_ffi_images';
		
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			image varchar(200) NOT NULL,
			title varchar(300) NOT NULL,
			alt varchar(300) NOT NULL,
			is_tag bit NOT NULL,
			taxonomy varchar(300) NOT NULL,
			ffikey varchar(50) NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		update_option( "azc_ffi_db_version", $azc_ffi_db_version );
    }
}
add_action( 'plugins_loaded', 'azc_ffi_update_db_check' );


function azc_ffi_load_plugin_textdomain(){
	
	$loaded = load_plugin_textdomain( 'azc-ffi', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	//if ($loaded){ echo 'true'; }else{ echo 'false'; }
}
add_action('plugins_loaded', 'azc_ffi_load_plugin_textdomain');


function azc_ffi_set_default_options($networkwide) {
	
	$new_options = array(
				'default_path' => plugin_dir_url(__FILE__).'images/',
				'default_image' => '',
				'default_title' => '',
				'default_alt' => '',
				'default_taxonomy' => '',
				'default_taxonomy_is_tag' => 0
			);
	
	// set defaults for multi-site
	if (function_exists('is_multisite') && is_multisite()) {
		// check if it is a network activation - if so, run the activation function for each blog id
		if ($networkwide) {
			global $wpdb;

			$blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
			$original_blog_id = get_current_blog_id();

			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );

				if ( get_option( 'azc_ffi_options' ) === false ) {
					add_option( 'azc_ffi_options', $new_options );
				}
			}

			switch_to_blog( $original_blog_id );
		}else{
			if ( get_option( 'azc_ffi_options' ) === false ) {
				add_option( 'azc_ffi_options', $new_options );
			}
		}
	}
	//set defaults for single site
	else{
		if ( get_option( 'azc_ffi_options' ) === false ) {
			add_option( 'azc_ffi_options', $new_options );
		}
	}
}
register_activation_hook( __FILE__, 'azc_ffi_set_default_options' );

function azc_ffi_load_css(){
	wp_enqueue_style( 'azc-ffi', plugins_url( 'style.css', __FILE__ ), '', '1.0.0' );
}
add_action('wp_enqueue_scripts', 'azc_ffi_load_css');

function azc_ffi_display_image($atts, $content = null) {
	global $wpdb;
	// Retrieve plugin configuration options from database
	$options = get_option( 'azc_ffi_options' );
	
	extract(shortcode_atts(array(
		'key' => '',
		'path' => stripslashes($options['default_path']),
		'image' => stripslashes($options['default_image']),
		'title' => stripslashes($options['default_title']),
		'alt' => stripslashes($options['default_alt']),
		'taxonomy' => stripslashes($options['default_taxonomy']),
		'is_tag' => 0
	), $atts));
	
	$sql = "SELECT * FROM ".$wpdb->prefix."azc_ffi_images WHERE id = %d or title = %s";
//echo $sql;
	$results = $wpdb->get_results(
								  $wpdb->prepare(
												"SELECT * FROM ".$wpdb->prefix."azc_ffi_images WHERE ffikey = %s LIMIT 0,1"
												,$key
												,$title
												)
									);

	if ( $results ) {
		foreach ( $results as $result ) {
			$image = esc_html( stripslashes($result->image));
			$title = esc_html( stripslashes($result->title));
			$alt = esc_html( stripslashes($result->alt));
			$is_tag = esc_html( stripslashes($result->is_tag));
			$taxonomy = esc_html( stripslashes($result->taxonomy));
		}
	}
	
	$output = "<span class='azc_ffi'>";
	if (strlen($taxonomy) > 0 and $is_tag == 0){
		$category_url = get_category_link(get_cat_ID($taxonomy));
		if (strlen($category_url) == 0){ // if taxonomy not name then check if slug
			$category = get_term_by('slug', $taxonomy, 'category');
			$category_url = get_category_link(get_cat_ID($category->name));
		}
		$output .= "<a href='$category_url'>";
	}elseif (strlen($taxonomy) > 0){
		$tag = get_term_by('name', $taxonomy, 'post_tag');
		$tag_url = get_tag_link($tag->term_id);
		if (strlen($tag_url) == 0){ // if taxonomy not name then check if slug
			$tag = get_term_by('slug', $taxonomy, 'post_tag');
			$tag_url = get_tag_link($tag->term_id);
		}
		$output .= "<a href='$tag_url'>";
	}
	$output .= "<img src='$path$image' title='$title' alt='$alt' />";
	if (strlen($taxonomy) > 0){
		$output .= "</a>";
	}
	$output .= "</span>";
	
	if (strlen($image) == 0){
		$output = '';
	}
	
	return $output;
}
add_shortcode( 'featured-image', 'azc_ffi_display_image' );
add_shortcode( 'ffi', 'azc_ffi_display_image' );

/*
add_filter('plugin_action_links', 'azc_ffi_plugin_action_links', 10, 2);

function azc_ffi_plugin_action_links($links, $file) {
    static $this_plugin;

    if (!$this_plugin) {
        $this_plugin = plugin_basename(__FILE__);
    }

    if ($file == $this_plugin) {
        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=azurecurve-featured-floating-image">'. __('Settings', 'azurecurve-floating-featured-image').'</a>';
        array_unshift($links, $settings_link);
    }

    return $links;
}


add_action( 'admin_menu', 'azc_ffi_settings_menu' );

function azc_ffi_settings_menu() {
	add_options_page( 'azurecurve Floating Featured Image Settings',
	'azurecurve Floating Featured Image', 'manage_options',
	'azurecurve-floating-featured-image', 'azc_ffi_config_page' );
}
*/

add_action("admin_menu", "azc_create_menus");

function azc_create_menus() {
    add_menu_page( "azurecurve Floating Featured Image"
			, "Floating Featured Image"
			, 0
			, "azc-ffi-config-page"
			, "azc_ffi_config_page"
			, plugins_url( '/images/Favicon-16x16.png', __FILE__ ) );
    
	add_submenu_page( "azc-ffi-config-page"
			, "Images"
			, "Images"
			, 0
			, "azc-ffi-show-images"
			, "azc_ffi_show_images" );
}

function azc_ffi_config_page() {
	if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'azc-ffi'));
    }
	
	// Retrieve plugin configuration options from database
	$options = get_option( 'azc_ffi_options' );
	?>
	<div id="azc-ffi-general" class="wrap">
		<fieldset>
			<h2><?php _e('azurecurve Floating Featured Image - Settings', 'azc-ffi'); ?></h2>
			<?php if( isset($_GET['settings-updated']) ) { ?>
				<div id="message" class="updated">
					<p><strong><?php _e('Settings have been saved.') ?></strong></p>
				</div>
			<?php } ?>
			<form method="post" action="admin-post.php">
				<input type="hidden" name="action" value="save_azc_ffi_options" />
				<input name="page_options" type="hidden" value="default_path, default_image, default_title default_alt, default_taxonomy_is_tag, default_taxonomy" />
				
				<!-- Adding security through hidden referrer field -->
				<?php wp_nonce_field( 'azc_ffi_nonce', 'azc_ffi_nonce' ); ?>
				<table class="form-table">
				<tr><td colspan=2>
					<p><?php _e('Set the default path for where you will be storing the images; default is to the plugin/images folder.', 'azc-ffi'); ?></p>
					
					<p><?php _e(sprintf('Use the %s shortcode to place the image in a post or on a page. With the default stylesheet it will float to the right.', '[featured-image]'), 'azc-ffi'); ?></p>
					
					<p><?php _e(sprintf('Add image attribute to use an image other than the default; %1$s and %2$s attributes can also be set to override the defaults.', 'title', 'alt'), 'azc-ffi'); ?></p>
					
					<p><?php _e(sprintf('Add %s attribute to use the tag instead of the category taxonomy.', 'is_tag=1'), 'azc-ffi'); ?></p>
					
					<p><?php _e(sprintf('Add %s attribute to have the image hyperlinked (category will be used if both are supplied).', 'taxonomy'), 'azc-ffi'); ?> </p>
					
					<p><?php _e(sprintf('If the default featured image is to be displayed simply add the shortcode to a page or post.).', '[featured-image]'), 'azc-ffi'); ?> </p>
					
					<p><?php _e(sprintf('When overriding the default add the parameters to the shortcode; e.g. %s', "[featured-image image='wordpress.png' title='WordPress' alt='WordPress' taxonomy='wordpress' is_tag=1]"), 'azc-ffi'); ?> </p>
					
					
				</td></tr>
				<tr><th scope="row"><label for="width"><?php _e('Default Path', 'azc-ffi'); ?></label></th><td>
					<input type="text" name="default_path" value="<?php echo esc_html( stripslashes($options['default_path']) ); ?>" class="regular-text" />
					<p class="description"><?php _e('Set default folder for images'); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="width"><?php _e('Default Image', 'azc-ffi'); ?></label></th><td>
					<input type="text" name="default_image" value="<?php echo esc_html( stripslashes($options['default_image']) ); ?>" class="regular-text" />
					<p class="description"><?php _e(sprintf('Set default image used when no %s attribute set', 'img'), 'azc-ffi'); ?> </p>
				</td></tr>
				<tr><th scope="row"><label for="width"><?php _e('Default Title', 'azc-ffi'); ?></label></th><td>
					<input type="text" name="default_title" value="<?php echo esc_html( stripslashes($options['default_title']) ); ?>" class="regular-text" />
					<p class="description"><?php _e('Set default title for image', 'azc-ffi'); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="width"><?php _e('Default Alt', 'azc-ffi'); ?></label></th><td>
					<input type="text" name="default_alt" value="<?php echo esc_html( stripslashes($options['default_alt']) ); ?>" class="regular-text" />
					<p class="description"><?php _e(sprintf('Set default %s text for image', 'alt'), 'azc-ffi'); ?></p>
				</td></tr>
				<tr><th scope="row"><?php _e('Default Taxonomy Is Tag', 'azc-ffi'); ?></th><td>
					<fieldset><legend class="screen-reader-text"><span>Default Taxonomy Is Tag</span></legend>
					<label for="enable_header"><input name="enable_header" type="checkbox" id="enable_header" value="1" <?php checked( '1', $options['default_taxonomy_is_tag'] ); ?> /><?php _e('Default Taxonomy Is Tag?', 'azc-ffi'); ?></label>
					</fieldset>
				</td></tr>
				<tr><th scope="row"><label for="width"><?php _e('Default Taxonomy', 'azc-ffi'); ?></label></th><td>
					<input type="text" name="default_taxonomy" value="<?php echo esc_html( stripslashes($options['default_taxonomy']) ); ?>" class="regular-text" />
					<p class="description"><?php _e('Set default taxonomy to hyperlink image (default is to use category unless Is Tag is marked)', 'azc-ffi'); ?></p>
				</td></tr>
				</table>
				<input type="submit" value="Submit" class="button-primary"/>
			</form>
		</fieldset>
	</div>
<?php }

add_action( 'admin_init', 'azc_ffi_admin_init' );

function azc_ffi_admin_init() {
	add_action( 'admin_post_save_azc_ffi_options', 'process_azc_ffi_options' );
}

function process_azc_ffi_options() {
	// Check that user has proper security level
	if ( !current_user_can( 'manage_options' ) ){ wp_die( __('You do not have permissions to perform this action.') ); }

	if ( ! empty( $_POST ) && check_admin_referer( 'azc_ffi_nonce', 'azc_ffi_nonce' ) ) {	
		// Retrieve original plugin options array
		$options = get_option( 'azc_ffi_options' );
		
		$option_name = 'default_path';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		$option_name = 'default_image';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		$option_name = 'default_title';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		$option_name = 'default_alt';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		$option_name = 'default_taxonomy_is_set';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = 1;
		}else{
			$options[$option_name] = 0;
		}
		
		$option_name = 'default_taxonomy';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		// Store updated options array to database
		update_option( 'azc_ffi_options', $options );
		
		// Redirect the page to the configuration form that was processed
		wp_redirect( add_query_arg( 'page', 'azc-ffi-config-page&settings-updated', admin_url( 'admin.php' ) ) );
		exit;
	}
}

function azc_ffi_show_images() {
	global $wpdb;
	if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'azc-ffi'));
    }
	
	// Retrieve plugin configuration options from database
	$options = get_option( 'azc_ffi_options' );
	?>
	<div id="azc-ffi-general" class="wrap">
		<fieldset>
			<h2><?php _e('azurecurve Floating Featured Image - Images', 'azc-ffi'); ?></h2>
			<?php if( isset($_GET['deleted']) ) { ?>
				<div id="message" class="updated">
					<p><strong><?php _e('Image has been deleted.') ?></strong></p>
				</div>
			<?php }
			if( isset($_GET['image-added']) ) { ?>
				<div id="message" class="updated">
					<p><strong><?php _e('Image has been added.') ?></strong></p>
				</div>
			<?php }
			if( isset($_GET['image-updated']) ) { ?>
				<div id="message" class="updated">
					<p><strong><?php _e('Image has been updated.') ?></strong></p>
				</div>
			<?php } ?>
			<h3><?php _e('Available Images', 'azc-ffi'); ?></h3>
			<table class="form-table">
			<tr>
			<th width="10%"><label for="Key"><?php _e('Key', 'azc-ffi'); ?></label></th>
			<th width="25%"><label for="Title"><?php _e('Title', 'azc-ffi'); ?></label></th>
			<th width="30%"><label for="Image"><?php _e('Image', 'azc-ffi'); ?></label></th>
			<th width="5%"><label for="Is_Tag"><?php _e('Is Tag', 'azc-ffi'); ?></label></th>
			<th width="20%"><label for="Taxonomy"><?php _e('Taxonomy', 'azc-ffi'); ?></label></th>
			<th width="10%"><label for="Delete button">&nbsp;</label></th>
			</tr>
			<?php $results = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."azc_ffi_images ORDER BY title");
			foreach($results as $image){
			?>
			<tr>
				<td><?php echo esc_html( stripslashes($image->ffikey) ); ?></td>
				<td><?php echo esc_html( stripslashes($image->title) ); ?></td>
				<td><?php echo esc_html( stripslashes($image->image) ); ?></td>
				<td><?php echo esc_html( stripslashes($image->is_tag) ); ?></td>
				<td><?php echo esc_html( stripslashes($image->taxonomy) ); ?></td>
			<td>
				<form method="post" action="admin-post.php">
					<input type="hidden" name="action" value="process_azc_ffi_image" />
					<input name="page_options" type="hidden" value="key, image, title, is_tag, taxonomy" />
					<?php wp_nonce_field( 'azc_ffi_nonce', 'azc_ffi_nonce' ); ?>
					<input type="hidden" name="id" value="<?php echo esc_html( stripslashes($image->id) ); ?>" class="short-text" />
					<input type="image" src="<?php echo plugin_dir_url(__FILE__); ?>images/edit.png" name="whichbutton" alt="edit" value="Edit" class="azcffi"/>
					<input type="image" src="<?php echo plugin_dir_url(__FILE__); ?>images/delete.png" name="whichbutton" alt="Delete" value="Delete" class="azcffi"/>
				</form>
			</td></tr>
			<?php
			}
			?>
			</table>
		
			<h3><?php _e('Add Image', 'azc-ffi'); ?></h3>
			<form method="post" action="admin-post.php">
				<?php
				$id = '';
				$key = '';
				$title = '';
				$alt = '';
				$image = '';
				$is_tag = '';
				$taxonomy = '';
				if( isset($_GET['edit']) ) {
					$id = $_GET['id'];
//echo "id=".$id."<p />";
				}
				if (strlen($id) > 0){
//echo "len=".strlen($id)."<p />";
					$results = $wpdb->get_results(
												  $wpdb->prepare(
																"SELECT * FROM ".$wpdb->prefix."azc_ffi_images WHERE id = %d LIMIT 0,1"
																,$id
																)
													);

					if ( $results ) {
//echo "id=".$id."<p />";
						foreach ($results as $result){
							$key = esc_html( stripslashes($result->ffikey));
							$image = esc_html( stripslashes($result->image));
							$title = esc_html( stripslashes($result->title));
							$alt = esc_html( stripslashes($result->alt));
							$is_tag = esc_html( stripslashes($result->is_tag));
							$taxonomy = esc_html( stripslashes($result->taxonomy));
						}
					}
				}
				
				?>
				<input type="hidden" name="action" value="add_azc_ffi_image" />
				<input name="page_options" type="hidden" value="key,image, title, alt, is_tag, taxonomy" />
				<!-- Adding security through hidden referrer field -->
				<?php wp_nonce_field( 'azc_ffi_nonce', 'azc_ffi_nonce' ); ?>
					<input type="hidden" name="id" value="<?php echo $id; ?>" class="short-text" />
				<table class="form-table">
				<tr><th scope="row"><label for="key"><?php _e('Key', 'azc-ffi'); ?></label></th><td>
					<input type="text" name="key" value="<?php echo $key; ?>" class="short-text" />
					<p class="description"><?php _e('Enter key of image (i.e. an easy to remember set of characters)', 'azc-ffi'); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="title"><?php _e('Title', 'azc-ffi'); ?></label></th><td>
					<input type="text" name="title" value="<?php echo $title; ?>" class="regular-text" />
					<p class="description"><?php _e('Enter title of image', 'azc-ffi'); ?> </p>
				</td></tr>
				<tr><th scope="row"><label for="alt"><?php _e('Alt Text', 'azc-ffi'); ?></label></th><td>
					<input type="text" name="alt" value="<?php echo $alt; ?>" class="regular-text" />
					<p class="description"><?php _e('Enter alt text of image', 'azc-ffi'); ?> </p>
				</td></tr>
				<tr><th scope="row"><label for="image"><?php _e('Image', 'azc-ffi'); ?></label></th><td>
					<input type="text" name="image" value="<?php echo $image; ?>" class="short-text" />
					<p class="description"><?php _e('Enter name of image', 'azc-ffi'); ?></p>
				</td></tr>
				<tr><th scope="row"><?php _e('Taxonomy Is Tag', 'azc-ffi'); ?></th><td>
					<fieldset><legend class="screen-reader-text"><span>Taxonomy Is Tag</span></legend>
					<label for="is_tag"><input name="is_tag" type="checkbox" id="is_tag" value="1" <?php checked('1', $is_tag); ?> /><?php _e('Taxonomy Is Tag?', 'azc-ffi'); ?></label>
					</fieldset>
				</td></tr>
				<tr><th scope="row"><label for="width"><?php _e('Taxonomy', 'azc-ffi'); ?></label></th><td>
					<input type="text" name="taxonomy" value="<?php echo $taxonomy; ?>" class="regular-text" />
					<p class="description"><?php _e('Set taxonomy to hyperlink image (default is to use category unless Is Tag is marked)', 'azc-ffi'); ?></p>
				</td></tr>
				</table>
				<input type="submit" value="Submit" class="button-primary"/>
			</form>
		</fieldset>
	</div>
<?php }

function azc_ffi_admin_init_add_image() {
	add_action( 'admin_post_add_azc_ffi_image', 'process_azc_ffi_add_image' );
}
add_action( 'admin_init', 'azc_ffi_admin_init_add_image' );

function process_azc_ffi_add_image() {
	
	global $wpdb;
	
	// Check that user has proper security level
	if ( !current_user_can( 'manage_options' ) ){ wp_die( __('You do not have permissions to perform this action.') ); }

	if ( ! empty( $_POST ) && check_admin_referer( 'azc_ffi_nonce', 'azc_ffi_nonce' ) ) {	
		// Retrieve original plugin options array
		$option_name = 'id';
		$id = '';
		if ( isset( $_POST[$option_name] ) ) {
			$id = ($_POST[$option_name]);
		}
		
		$option_name = 'key';
		$key = '';
		if ( isset( $_POST[$option_name] ) ) {
			$key = ($_POST[$option_name]);
		}
		
		$option_name = 'image';
		$image = '';
		if ( isset( $_POST[$option_name] ) ) {
			$image = ($_POST[$option_name]);
		}
		
		$option_name = 'title';
		$title = '';
		if ( isset( $_POST[$option_name] ) ) {
			$title = ($_POST[$option_name]);
		}
		
		$option_name = 'alt';
		$alt = '';
		if ( isset( $_POST[$option_name] ) ) {
			$alt = ($_POST[$option_name]);
		}
		
		$option_name = 'is_tag';
		if ( isset( $_POST[$option_name] ) ) {
			$is_tag = 1;
		}else{
			$is_tag = 0;
		}
		
		$option_name = 'taxonomy';
		$taxonomy = '';
		if ( isset( $_POST[$option_name] ) ) {
			$taxonomy = ($_POST[$option_name]);
		}
	
		$table_name = $wpdb->prefix . 'azc_ffi_images';
		
		if (strlen($id) == 0){
			$wpdb->insert( 
				$table_name
				,array(
					'ffikey' => $key,
					'image' => $image,
					'title' => $title,
					'alt' => $alt,
					'is_tag' => $is_tag,
					'taxonomy' => $taxonomy,
				)
				,array(
					'%s'
					,'%s'
					,'%s'
					,'%s'
					,'%d'
					,'%s'
				)
			);
			//echo "insert";
			//exit;
			// Redirect the page to the configuration form that was processed
			wp_redirect( add_query_arg( 'page', 'azc-ffi-show-images&image-added', admin_url( 'admin.php' ) ) );
		}else{
			$wpdb->update( 
				$table_name
				,array(
					'ffikey' => $key,
					'image' => $image,
					'title' => $title,
					'alt' => $alt,
					'is_tag' => $is_tag,
					'taxonomy' => $taxonomy,
				)
				,array('id' => $id)
				,array(
					'%s'
					,'%s'
					,'%s'
					,'%s'
					,'%d'
					,'%s'
				)
				,array(
					'%d'
				)
			);
			//exit( var_dump( $wpdb->last_query ) );
			// Redirect the page to the configuration form that was processed
			wp_redirect( add_query_arg( 'page', 'azc-ffi-show-images&image-updated', admin_url( 'admin.php' ) ) );
		}
		exit;
	}
}

function azc_ffi_admin_init_process_images() {
	add_action( 'admin_post_process_azc_ffi_image', 'process_azc_ffi_images' );
}
add_action( 'admin_init', 'azc_ffi_admin_init_process_images' );

function process_azc_ffi_images() {
	
	global $wpdb;
	
	// Check that user has proper security level
	if ( !current_user_can( 'manage_options' ) ){ wp_die( __('You do not have permissions to perform this action.') ); }

	if ( ! empty( $_POST ) && check_admin_referer( 'azc_ffi_nonce', 'azc_ffi_nonce' ) ) {
		if ($_POST['whichbutton'] == 'Delete') {
			// Retrieve original plugin options array
			$option_name = 'id';
			$id = '';
			if ( isset( $_POST[$option_name] ) ) {
				$id = ($_POST[$option_name]);
			}
				
			$table_name = $wpdb->prefix . 'azc_ffi_images';
			
			$wpdb->delete( 
				$table_name, 
				array( 
					'id' => $id,
				) 
			);
			
			// Redirect the page to the configuration form that was processed
			wp_redirect( add_query_arg( 'page', 'azc-ffi-show-images&deleted', admin_url( 'admin.php' ) ) );
			exit;
		}else{
			// edit
			// Redirect the page to the configuration form that was processed
			wp_redirect( add_query_arg( 'page', 'azc-ffi-show-images&edit&id='.$_POST['id'], admin_url( 'admin.php' ) ) );
			exit;
		}
	}
}
?>