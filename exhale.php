<?php

/**
 * Plugin Name: Exhale
 * Plugin URI: https://pressbro.com/exhale
 * Description: Exhale decreases the size of your images up to 80% and in some cases even more so. All of that it does without any visible loss of quality to the images. Smaller image sizes are useful for faster loading of your website which your visitors will value and so will search engines.
 * Version: 1.0.1
 * Author: PressBro
 * Author URI: https://pressbro.com
 * License: GPLv2 or later
 */

/**
--------------------------------------------------------------------------------
DEFINE THINGS
--------------------------------------------------------------------------------
*/
$exhale = array(
	'upload_dir' => wp_upload_dir(),
	'is_admin' => is_admin()
);

/**
--------------------------------------------------------------------------------
IF KEY IS PRESENT, VALIDATE
--------------------------------------------------------------------------------
*/
if(!function_exists('exhale_validate_key')):

	function exhale_validate_key($key) {

		if($key == false || $key == '') {

			$key = '1';

		}

		// Init
		$ch = curl_init();

		// Configure
		curl_setopt_array($ch, array(
			CURLOPT_URL => 'https://exhale.pressbro.com/api/validate-account/' . $key,
			CURLOPT_RETURNTRANSFER => true,
			CURLINFO_HEADER_OUT => true,
			CURLOPT_HEADER => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_POST => false,
			CURLOPT_CONNECTTIMEOUT => 30
		));

		// Get data
		$result = curl_exec($ch);
		$header_info = curl_getinfo($ch, CURLINFO_HEADER_OUT);
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$header = substr($result, 0, $header_size);
		$data = json_decode(substr($result, $header_size));

		// Close connection
		curl_close($ch);

		if(!empty($data->validation) && $data->validation !== 'successful') {

			return false;

		}

		return true;

	}

endif;


/**
--------------------------------------------------------------------------------
ENTER KEY
--------------------------------------------------------------------------------
*/
add_action('admin_init', function() {

	if(!empty($_POST['_exhale_key']) && exhale_validate_key($_POST['_exhale_key']) && check_admin_referer('add-exhale-key')) {

		add_option('_exhale_key', trim(esc_html($_POST['_exhale_key'])));
		add_action('admin_notices', function() {

			$html = '<div id="exhale-notice" class="notice updated"><span style="display:block;font-size: 13px;padding: 10px 20px 10px 5px;">Account key was successfully validated!</span></div>';

			echo $html;

		});

	}elseif(!empty($_POST['_exhale_key']) && !exhale_validate_key($_POST['_exhale_key']) && check_admin_referer('add-exhale-key')) {

		add_action('admin_notices', function() {

			$html = '<div id="exhale-notice" class="notice error"><span style="display:block;font-size: 13px;padding: 10px 20px 10px 5px;">Account key does not validate! Try again or <a href="https://pressbro.com/exhale">sign up for an account key</a></span></div>';

			echo $html;

		});

	}

});

/**
--------------------------------------------------------------------------------
24 HOUR NONCES
--------------------------------------------------------------------------------
*/
add_action('admin_init', function() {

	add_filter('nonce_life', function() {

		return 24 * 3600;

	});

});

/**
--------------------------------------------------------------------------------
REMOVE KEY
--------------------------------------------------------------------------------
*/
add_action('admin_init', function() {

	if(empty($_POST['_exhale_key']) && !empty($_GET['exhale_remove_key']) && wp_verify_nonce($_GET['_wpnonce'], 'remove-exhale-key')) {

		$exhale_remove_key = intval($_GET['exhale_remove_key']);

		if($exhale_remove_key === 1) {

			delete_option('_exhale_key');

			add_action('admin_notices', function() {

				$html = '<div id="exhale-notice" class="notice updated"><span style="display:block;font-size: 13px;padding: 10px 20px 10px 5px;">Account key was successfully removed!</span></div>';

				echo $html;

			});

		}

	}

});

/**
--------------------------------------------------------------------------------
ADD MENU ENTRY
--------------------------------------------------------------------------------
*/
if(!function_exists('exhale_menu_entry')):

	function exhale_menu_entry() {

		add_media_page(
			'Compress Images', 
			'Compress Images', 
			'manage_options', 
			'exhale', 
			'exhale_admin'
		);

	}

	add_action('admin_menu', 'exhale_menu_entry');

endif;

/**
--------------------------------------------------------------------------------
ADD SCRIPTS
--------------------------------------------------------------------------------
*/
if(!function_exists('exhale_scripts')):

	function exhale_scripts($hook) {

		if($hook === 'media_page_exhale') {

			// CSS
			wp_register_style('exhale-style', plugin_dir_url(__FILE__) . 'css/exhale.min.css', array(), '1.0.1');

			// JS
			wp_register_script('exhale-script', plugin_dir_url(__FILE__) . 'js/exhale.js', array(), '1.0.1');

			// enqueue
			wp_enqueue_style('exhale-style');
			wp_enqueue_script('jquery');
			wp_enqueue_script('exhale-script');

		}

	}

	add_action('admin_enqueue_scripts', 'exhale_scripts');

endif;

/**
--------------------------------------------------------------------------------
GET IMAGES
--
Used on the plugin page to display uncompressed images
--------------------------------------------------------------------------------
*/
if(!function_exists('exhale_images')):

	function exhale_images() {

		$images = get_posts(array(
			'post_type' => 'attachment',
			'numberposts' => 115,
			'post_status' => null,
			'post_parent' => null,
			'post_mime_type' => array('image/png', 'image/jpg', 'image/jpeg'),
			'meta_query' => array(
				array(
					'key' => '__exhale_image_is_compressed',
					'value' => 'yes',
					'compare' => 'NOT EXISTS'
				)
			)
		));

		$processed_images = array();

		if($images) {

			foreach($images as $image) {

				$processed_images[] = array(
					'id' => $image->ID
				);

			}

			return $processed_images;

		}

		return false;

	}

endif;

/**
--------------------------------------------------------------------------------
HUMANIZE BYTES
--------------------------------------------------------------------------------
*/
if(!function_exists('exhale_filesize')):

	function exhale_filesize($bytes) {

		$bytes = floatval($bytes);
		$arr_bytes = array(
			0 => array('unit' => 'TB', 'value' => pow(1024, 4)),
			1 => array('unit' => 'GB', 'value' => pow(1024, 3)),
			2 => array('unit' => 'MB', 'value' => pow(1024, 2)),
			3 => array('unit' => 'KB', 'value' => 1024),
			4 => array('unit' => 'B', 'value' => 1)
		);

		foreach($arr_bytes as $arr_item) {

			if($bytes >= $arr_item['value']) {

				$result = $bytes / $arr_item['value'];
				$result = str_replace('.', ',', strval(round($result, 2))) . ' ' . $arr_item['unit'];
				break;

			}

		}

		if(!empty($result)) {

			return $result;

		}

		return false;

	}

endif;

/**
--------------------------------------------------------------------------------
CREATE CURL VALUE FOR FILE
--
Used to upload the image to the service
--------------------------------------------------------------------------------
*/
if(!function_exists('exhale_curl_get_value')):

	function exhale_curl_get_value($filename, $content_type, $post_name) {

		if(function_exists('curl_file_create')) {

			return curl_file_create($filename, $content_type, $post_name);

		}

		$value = '@' . $filename . ';filename=' . $post_name;

		if($content_type) {

			$value .= ';type=' . $content_type;

		}

		return $value;

	}

endif;

/**
--------------------------------------------------------------------------------
GET INFO FOR DOMAIN
--------------------------------------------------------------------------------
*/
if(!function_exists('exhale_api_info')):

	function exhale_api_info() {

		if(get_option('_exhale_key')) {

			// Init
			$ch = curl_init();

			// Configure
			curl_setopt_array($ch, array(
				CURLOPT_URL => 'https://exhale.pressbro.com/api/account/' . get_option('_exhale_key'),
				CURLOPT_RETURNTRANSFER => true,
				CURLINFO_HEADER_OUT => true,
				CURLOPT_HEADER => true,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_POST => false,
				CURLOPT_CONNECTTIMEOUT => 30
			));

			// Get data
			$result = curl_exec($ch);
			$header_info = curl_getinfo($ch, CURLINFO_HEADER_OUT);
			$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
			$header = substr($result, 0, $header_size);
			$data = json_decode(substr($result, $header_size));

			// Close connection
			curl_close($ch);

			return $data;

		}

		return array('no_key');

	}

endif;

/**
--------------------------------------------------------------------------------
GET IMAGE COUNT via AJAX
--------------------------------------------------------------------------------
*/
if(!function_exists('exhale_image_count_ajax')) {

	function exhale_image_count_ajax() {

		check_ajax_referer('exhale-image-count-nonce', 'security');

		$images = get_posts(array(
			'post_type' => 'attachment',
			'numberposts' => -1,
			'post_status' => null,
			'post_parent' => null,
			'post_mime_type' => array('image/png', 'image/jpg', 'image/jpeg'),
			'meta_query' => array(
				array(
					'key' => '__exhale_image_is_compressed',
					'value' => 'yes',
					'compare' => 'NOT EXISTS'
				)
			)
		));

		header('Content-Type: application/json');

		echo json_encode(array(
			'image_count' => count($images) * count(get_intermediate_image_sizes())
		));

		wp_die();

	}

	add_action('wp_ajax_exhale_image_count_ajax', 'exhale_image_count_ajax');

}

if(!function_exists('exhale_images_ajax')):

	function exhale_images_ajax() {

		check_ajax_referer('exhale-images-nonce', 'security');

		$images = get_posts(array(
			'post_type' => 'attachment',
			'numberposts' => -1,
			'post_status' => null,
			'post_parent' => null,
			'post_mime_type' => array('image/png', 'image/jpg', 'image/jpeg'),
			'meta_query' => array(
				array(
					'key' => '__exhale_image_is_compressed',
					'value' => 'yes',
					'compare' => 'NOT EXISTS'
				)
			)
		));

		$processed_images = array();

		if($images) {

			foreach($images as $image) {

				if(!empty(wp_get_attachment_metadata($image->ID)['sizes'])) {

					$processed_images[] = array(
						'id' => $image->ID,
						'thumbnail' => wp_get_attachment_image_src($image->ID, 'thumbnail', false)[0],
						'meta' => wp_get_attachment_metadata($image->ID)
					);

				}

			}

			header('Content-Type: application/json');

			echo json_encode($processed_images);

		} else {

			header('Content-Type: application/json');

			echo json_encode(array('error' => 'no_images_to_compress'));

		}

		wp_die();

	}

	add_action('wp_ajax_exhale_images_ajax', 'exhale_images_ajax');

endif;

/**
--------------------------------------------------------------------------------
GET INFO FOR DOMAIN via AJAX
--------------------------------------------------------------------------------
*/
if(!function_exists('exhale_api_info_ajax')):

	function exhale_api_info_ajax() {

		check_ajax_referer('exhale-api-info-nonce', 'security');

		header('Content-Type: application/json');

		echo json_encode(exhale_api_info());

		wp_die();

	}

	add_action('wp_ajax_exhale_api_info_ajax', 'exhale_api_info_ajax');

endif;

/**
--------------------------------------------------------------------------------
GET BYTES SAVED via AJAX
--------------------------------------------------------------------------------
*/
if(!function_exists('exhale_bytes_saved')):

	function exhale_bytes_saved() {

		check_ajax_referer('exhale-bytes-saved-nonce', 'security');

		$images = get_posts(array(
			'post_type' => 'attachment',
			'numberposts' => -1,
			'post_status' => null,
			'post_parent' => null,
			'post_mime_type' => 'image',
			'meta_query' => array(
				array(
					'key' => '__exhale_image_is_compressed',
					'value' => 'yes',
					'compare' => '='
				)
			)
		));

		$bytes_saved = 0;

		foreach($images as $image) {

			$bytes_before = (int) get_post_meta($image->ID, '__exhale_image_size_before', true);
			$bytes_after = (int) get_post_meta($image->ID, '__exhale_image_size_after', true);
			$bytes_saved_from_this = $bytes_before - $bytes_after;
			$bytes_saved = $bytes_saved + $bytes_saved_from_this;

		}

		header('Content-Type: application/json');

		echo json_encode(array(
			'bytes_saved' => exhale_filesize($bytes_saved)
		));

		wp_die();

	}

	add_action('wp_ajax_exhale_bytes_saved', 'exhale_bytes_saved');

endif;

/**
--------------------------------------------------------------------------------
COMPRESS IMAGE
--------------------------------------------------------------------------------
*/
if(!function_exists('exhale_compress_image')):

	function exhale_compress_image($path_to_image, $mime_type) {

		$name = substr($path_to_image, strrpos($path_to_image, '/') + 1);
		$data = array(
			'key' => get_option('_exhale_key'), 
			'file' => exhale_curl_get_value($path_to_image, $mime_type, $name)
		);

		// Init
		$ch = curl_init();

		// Configure
		curl_setopt_array($ch, array(
			CURLOPT_URL => 'https://exhale.pressbro.com/api/compress',
			CURLOPT_RETURNTRANSFER => true,
			CURLINFO_HEADER_OUT => true,
			CURLOPT_HEADER => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $data,
			CURLOPT_CONNECTTIMEOUT => 30
		));

		// Get data
		$result = curl_exec($ch);
		$header_info = curl_getinfo($ch, CURLINFO_HEADER_OUT);
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$header = substr($result, 0, $header_size);
		$data = json_decode(substr($result, $header_size));

		// Close connection
		curl_close($ch);

		// Replace current image with the newly compressed one
		// if all goes well
		if(!empty($data->image)) {

			// This is where we show an error if we can't
			// write the image
			$image = file_get_contents($data->image);
	
			file_put_contents($path_to_image, $image);

		} else {

			header('Content-Type: application/json');
			echo json_encode($data);
			die();

		}
		
	}

endif;

/**
--------------------------------------------------------------------------------
GET ALL IMAGE SIZES
--------------------------------------------------------------------------------
*/
function exhale_get_all_image_sizes() {
	global $_wp_additional_image_sizes;
	$default_image_sizes = array( 'thumbnail', 'medium', 'large' );
	 
	foreach ( $default_image_sizes as $size ) {
		$image_sizes[$size]['width']	= intval( get_option( "{$size}_size_w") );
		$image_sizes[$size]['height'] = intval( get_option( "{$size}_size_h") );
		$image_sizes[$size]['crop']	= get_option( "{$size}_crop" ) ? get_option( "{$size}_crop" ) : false;
	}
	
	if ( isset( $_wp_additional_image_sizes ) && count( $_wp_additional_image_sizes ) )
		$image_sizes = array_merge( $image_sizes, $_wp_additional_image_sizes );
		
	return $image_sizes;
}

/**
--------------------------------------------------------------------------------
COMPRESS IMAGE via AJAX
--
This runs all images with a certain ID through the `exhale_compress_image` 
function
--------------------------------------------------------------------------------
*/
if(!function_exists('exhale_compress_image_ajax')):

	function exhale_compress_image_ajax() {

		check_ajax_referer('exhale-compress-image-nonce', 'security');

		$image_id = intval($_POST['image']);
		$image_mime = get_post_mime_type($image_id);

		// If the AJAX call has correct info
		if($image_id && $image_mime && !get_post_meta($image_id, '__exhale_image_is_compressed', true) && exhale_api_info()->can_compress) {

			// Strip the file of its extension
			$image = get_attached_file($image_id);
			$image_sizes = wp_get_attachment_metadata($image_id)['sizes'];

			// Match for png, jpg and jpeg
			preg_match('/.*(.png|.jpg|.jpeg)/', $image, $output);

			// Remove accordingly
			if(!empty($output[1])) {

				if($output[1] === '.png' || $output[1] === '.jpg' || $output[1] === '.jpeg') {

					// Image without extension
					$image_wo_ext = str_replace($output[1], '', $image);

					// Get all of the image versions of this image 
					// into an array and calculate the bytes of those images in total.
					$images = array($image);
					$image_versions = array();
					$bytes = filesize($image);

					// Get all image versions
					foreach($image_sizes as $key => $image_size) {

						$_img = $image_wo_ext . '-' . $image_size['width'] . 'x' . $image_size['height'] . $output[1];
						$images[] = $_img;
						$bytes = $bytes + filesize($_img);

					}


					// Loop through them, send them to the compressor one by one
					// and overwrite the existing ones with the compressed ones.
					// Easy. Also, while we're at it, add new bytes
					$new_bytes = 0;

					foreach($images as $img) {

						exhale_compress_image($img, $image_mime);
						$new_bytes = $new_bytes + filesize($img);

					}

					// We don't need to compress this image again. Make it so.
					add_post_meta($image_id, '__exhale_image_is_compressed', 'yes');
					add_post_meta($image_id, '__exhale_image_size_before', $bytes);
					add_post_meta($image_id, '__exhale_image_size_after', $new_bytes);

					header('Content-Type: application/json');

					echo json_encode(array(
						'id' => $image_id,
						'size_before_raw' => $bytes,
						'size_after_raw' => $new_bytes,
						'can_compress' => exhale_api_info()->can_compress,
						'compressions' => (int) exhale_api_info()->compressions,
					));

				} else {

					die('Exhale Error: Image is of different type than png, jpg and jpeg');

				}

			} else {

				die('Exhale Error: Image is of different type than png, jpg and jpeg');

			}

		} else {

			header('Content-Type: application/json');

			echo json_encode(array(
				'id' => $image_id,
				'size_before_raw' => 1000000,
				'size_after_raw' => 1000000,
				'can_compress' => exhale_api_info()->can_compress,
				'compressions' => (int) exhale_api_info()->compressions
			));

		}

		wp_die();

	}

	add_action('wp_ajax_exhale_compress_image_ajax', 'exhale_compress_image_ajax');

endif;

/**
--------------------------------------------------------------------------------
ADMIN PAGE
--------------------------------------------------------------------------------
*/
if(!function_exists('exhale_admin')):

	function exhale_admin() { ?>

		<div class="wrap exhale-wrapper">

			<h1 class="exhale-title">Compress Images 

				<a href="media-new.php" class="page-title-action">Add New</a>

				<div class="exhale-menu">

					<ul class="exhale-menu-inner">

						<li class="exhale-menu-item">

							<a href="javascript:;" id="exhale-toggle-infobox" class="page-title-action"><i class="fa fa-user"></i><span><?php echo __('Your Account'); ?></span></a>

							<div class="exhale-infobox">

								<ul class="exhale-info-stats">

									<?php if(get_option('_exhale_key')): ?>

										<li>
											<div class="exhale-left">Images</div>
											<div class="exhale-right"><span id="exhale-image-count" class="exhale-right-num"><small>Loading</small></span>

												<div class="exhale-info-stats-informal-container">
													<i class="fa fa-question-circle"></i>

													<div class="exhale-info-stats-informal-content">
														If you're wondering why this number seems a bit too big, it's because WordPress makes multiple sizes of your images. This plugin counts each version of an image as one image.
													</div>
												</div>

											</div>
										</li>

										<li>
											<div class="exhale-left">Compressions</div>
											<div class="exhale-right"><span id="exhale-compressions-left-counter" class="exhale-right-num"><small>Loading</small></span>

												<div class="exhale-info-stats-informal-container">
													<i class="fa fa-question-circle"></i>

													<div class="exhale-info-stats-informal-content">
														The number of images you can compress. Remember, WordPress makes multiple sizes of one image, so you may lose about 4 compressions for 1 image or more depending on your WordPress configuration.
													</div>
												</div>

											</div>
										</li>

										<li>
											<div class="exhale-left">Saved</div>
											<div class="exhale-right"><span id="exhale-bytes-saved" class="exhale-right-num"><small>Loading</small></span>

												<div class="exhale-info-stats-informal-container">
													<i class="fa fa-question-circle"></i>

													<div class="exhale-info-stats-informal-content">
														How much your website has lost weight using this plugin.
													</div>
												</div>

											</div>
										</li>

										<li>
											<div class="exhale-left">Account E-mail</div>
											<div class="exhale-right">

												<?php echo exhale_api_info()->email; ?>

											</div>
										</li>

									<?php endif; ?>

									<li class="exhale-account-key-form">
										<div class="exhale-left">Account key</div>

										<?php if(get_option('_exhale_key')): ?>

											<div class="exhale-right"><a onclick="if(!confirm('Are you sure you want to remove this account key from this site?')) return false;" title="Remove key" href="<?php echo $_SERVER['REQUEST_URI']; ?>&amp;exhale_remove_key=1&amp;_wpnonce=<?php echo wp_create_nonce('remove-exhale-key'); ?>">Remove</a></div>
											<div class="exhale-clear"></div>
											<div class="exhale-account-key"><?php echo get_option('_exhale_key'); ?></div>

										<?php else: ?>

											<div class="exhale-right"><a target="_blank" href="https://pressbro.com/exhale/">Get an account key</a></div>

											<div class="exhale-clear"></div>

											<form method="post" class="exhale-validate-key-form">

												<?php wp_nonce_field('add-exhale-key'); ?>
												<input type="text" name="_exhale_key" placeholder="Insert key here">
												<input type="submit" class="button" value="Validate">

											</form>

										<?php endif; ?>

										<div class="exhale-clear"></div>
									</li>

								</ul>

							</div>

						</li>

						<?php if(exhale_validate_key(get_option('_exhale_key'))): if(exhale_api_info()->can_compress && exhale_images()): ?>

							<li class="exhale-menu-item"><a href="javascript:;" id="exhale-compress-all-images" class="page-title-action"><i class="fa fa-gavel"></i><span><?php echo __('Compress All Images', 'exhale'); ?></span></a></li>

						<?php else: ?>

							<li class="exhale-menu-item"><a disabled="disabled" href="javascript:;" id="exhale-compress-all-images" class="disabled page-title-action"><i class="fa fa-gavel"></i><span><?php echo __('Compress All Images', 'exhale'); ?></span></a></li>

						<?php endif; else: ?>

							<li class="exhale-menu-item"><a disabled="disabled" href="javascript:;" id="exhale-compress-all-images" class="disabled page-title-action"><i class="fa fa-gavel"></i><span><?php echo __('Compress All Images', 'exhale'); ?></span></a></li>

						<?php endif; ?>

					</ul>

				</div>

			</h1>

			<div class="exhale-images<?php if(!exhale_images()): ?> exhale-faded-out<?php endif; ?>">

				<ul></ul>

			</div> <!-- end of exhale-images -->

			<div class="exhale-splash"></div>

		</div> <!-- end of exhale-wrapper -->

		<script>
		var exhale_api_info_nonce = '<?php echo wp_create_nonce('exhale-api-info-nonce'); ?>';
		var exhale_bytes_saved_nonce = '<?php echo wp_create_nonce('exhale-bytes-saved-nonce'); ?>';
		var exhale_images_nonce = '<?php echo wp_create_nonce('exhale-images-nonce'); ?>';
		var exhale_compress_image_nonce = '<?php echo wp_create_nonce('exhale-compress-image-nonce'); ?>';
		var exhale_image_count_nonce = '<?php echo wp_create_nonce('exhale-image-count-nonce'); ?>';
		</script>

		<?php if(get_option('_exhale_key')): ?>
			<script>var _exhale_key = '<?php echo get_option('_exhale_key'); ?>';</script>
		<?php else: ?>
			<script>var _exhale_key = false;</script>
		<?php endif; ?>

		<?php if(!exhale_images()): ?>
			<script>var _exhale_no_images = true;</script>
		<?php else: ?>
			<script>var _exhale_no_images = false;</script>
		<?php endif; ?>

	<?php }

endif;
