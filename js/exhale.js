var exhale_debug = false;

(function($) {

	$(document).ready(function() {

		/**
		*/
		if(document.location.href.match(/exhale_remove_key=([0-9]+)/)) {

			setTimeout(function() {

				var redirect_to = document.location.href.split('&')[0];
				document.location.href = redirect_to;

			}, 1000);

		}

		/**
		--------------------------------------------------------------------------------
		LOAD IMAGES
		--------------------------------------------------------------------------------
		*/
		$('.exhale-splash').html('<h1>Loading <span class="dots"></span></h1><p>This may take a bit depending on how many images you have.</p>');
		
		var animateDots = setInterval(function() {

			var dots = $('.exhale-splash .dots');

			if(dots.length && dots.html().length > 2) {

				dots.html('');

			} else {

				dots.html(dots.html() + '.');

			}

		}, 500);

		$('.exhale-splash').fadeIn(50);
					
		exhale_api_info().success(function(data) {

			if(data === null) {

				$('.exhale-splash').html('<h1>Oops, something went wrong :(</h1><p>It\'s us, not you. Sit back and relax while we fix the issue.</p>');
				$('.exhale-splash').fadeIn(50);
				$('.exhale-images').fadeOut(50);

			} else {

				$.ajax({
					url: ajaxurl,
					method: 'GET',
					cache: false,
					data: {action: 'exhale_images_ajax', security: exhale_images_nonce},
					success: function(data) {

						if(exhale_debug) {

							console.log(data);

						}
						
						if(data === null && _exhale_key) {

							$('.exhale-splash').html('<h1>Oops, something went wrong :(</h1><p>It\'s us, not you. Sit back and relax while we fix the issue.</p>');
							$('.exhale-splash').fadeIn(50);
							$('.exhale-images').fadeOut(50);

						} else {

							var exhale_image_count = Object.keys(data).length;
							var exhale_x = 1;
							var exhale_images_list = '';

							if(exhale_image_count > 0 && data.error !== 'no_images_to_compress') {
								
								data.forEach(function(item, i) {

									if($('#exhale-compress-all-images').hasClass('disabled')) {

										exhale_images_list += '<li class="exhale-image" data-exhale-image-id="' + item.id + '"><div class="exhale-image-thumbnail"><img src="' + item.thumbnail + '" alt=""></div><div class="exhale-image-splash"><a class="exhale-button disabled" href="javascript:;">Compress</a></div></li>';

									} else {

										exhale_images_list += '<li class="exhale-image" data-exhale-image-id="' + item.id + '"><div class="exhale-image-thumbnail"><img src="' + item.thumbnail + '" alt=""></div><div class="exhale-image-splash"><a id="exhale-compress-image" class="exhale-button" href="javascript:;">Compress</a></div></li>';

									}

									exhale_x++;

								});

								setTimeout(function() {

									$('.exhale-images ul').html(exhale_images_list);
									$('.exhale-images').fadeIn(50);
									$('.exhale-splash').fadeOut(50);
									clearInterval(animateDots);

								}, 1000);

							} else {

								$('.exhale-images').fadeOut(50);
								$('.exhale-splash').html('<h1>No images to compress :)</h1><p>This means that either there are no images at all or all images are already compressed.</p>');
								$('.exhale-splash').fadeIn(50);

							}

						}

				}});

			}

		});

		/**
		--------------------------------------------------------------------------------
		GET INFO
		--------------------------------------------------------------------------------
		*/
		function exhale_api_info() {

			return $.ajax({
				url: ajaxurl,
				method: 'GET',
				cache: false,
				data: {action: 'exhale_api_info_ajax', security: exhale_api_info_nonce}
			});

		}

		/**
		--------------------------------------------------------------------------------
		COMPRESS IMAGES
		--------------------------------------------------------------------------------
		*/
		function exhale_compress_images(ids) {

			// Do the deed, but instead of a regular AJAX call
			// add the call to queue
			exhale_api_info().success(function(api) {

				if(api.can_compress) {

					ids.forEach(function(id) {

						if(!$('.exhale-image[data-exhale-image-id="' + id + '"]').hasClass('exhale-image-is-compressed')) {

							// Front-end magic
							$('.exhale-image[data-exhale-image-id="' + id + '"]').addClass('show-splash');
							$('.exhale-image[data-exhale-image-id="' + id + '"] .exhale-image-splash').html('<div class="exhale-image-compressing"></div>');

							localStorage.setItem('exhale-compressing-in-progress', true);

							window.onbeforeunload = function() {

								return 'Are you sure you want to leave this page?';

							};

							$.ajax({
								url: ajaxurl,
								method: 'POST',
								cache: false,
								data: {action: 'exhale_compress_image_ajax', image: id, security: exhale_compress_image_nonce},
							}).success(function(image) {

								if(exhale_debug) {

									console.log(image);

								}

								localStorage.setItem('exhale-compressing-in-progress', false);
								window.onbeforeunload = null;

								// Calc sizes
								var exhale_image_size_before = image.size_before_raw;
								var exhale_image_size_after = image.size_after_raw;

								if(exhale_image_size_before == 0 && exhale_image_size_after == 0) {

									var exhale_image_percentage_change = '-0';

								} else {

									var exhale_image_percentage_change = (Math.round(((exhale_image_size_after - exhale_image_size_before) / exhale_image_size_before) * 100 * 4) / 4).toFixed(0);

								}

								// Replace loading splash with percentage decrease
								$('.exhale-image[data-exhale-image-id="' + image.id + '"]').addClass('exhale-image-is-compressed');
								$('.exhale-image[data-exhale-image-id="' + image.id + '"] .exhale-image-splash').html('<div class="exhale-image-percentage-decrease">' + exhale_image_percentage_change + '%</div>');
							
								// Remove this image from front-end in 5 seconds
								setTimeout(function() {

									$('.exhale-image[data-exhale-image-id="' + image.id + '"]').fadeOut(100);

								}, 5000);

								setTimeout(function() {

									$('.exhale-image[data-exhale-image-id="' + image.id + '"]').remove();

								}, 5100);

							});

						}

					});

				} else {

					$('#exhale-compress-all-images').addClass('disabled');
					$('.exhale-image .exhale-image-splash').html('<a class="exhale-button disabled" href="javascript:;">Compress</a>');

				}

			});

		}

		/**
		--------------------------------------------------------------------------------
		COMPRESS ALL IMAGES
		--------------------------------------------------------------------------------
		*/
		function exhale_compress_all_images() {

			var ids = [];

			$('.exhale-image').each(function() {

				var image_id = $(this).attr('data-exhale-image-id');
				ids.push(image_id);

			});

			exhale_compress_images(ids);

		}

		// call it from the button click
		$(document).on('click', 'a#exhale-compress-all-images', function() {

			if($('a#exhale-compress-all-images').hasClass('exhale-button-active')) {

				$('a#exhale-compress-all-images').removeClass('exhale-button-active');
				$('a#exhale-compress-all-images').html('<i class="fa fa-gavel"></i><span>Compress All Images</span>');

				window.location.reload();

			} else {

				$('a#exhale-compress-all-images').addClass('exhale-button-active');
				$('a#exhale-compress-all-images').html('<i class="fa fa-gavel"></i><span>Compressing All Images ...</span>');

				exhale_compress_all_images();

			}

		});

		/**
		--------------------------------------------------------------------------------
		COMPRESS A SINGLE IMAGE
		--------------------------------------------------------------------------------
		*/
		function exhale_compress_single_image(id) {

			exhale_compress_images([id]);

		}

		// call it from the button click
		$(document).on('click', 'a#exhale-compress-image', function() {

			var image_id = $(this).parent().parent().attr('data-exhale-image-id');

			exhale_compress_single_image(image_id);

		});

		/**
		--------------------------------------------------------------------------------
		CHECK FOR LIMIT
		--------------------------------------------------------------------------------
		*/
		function exhale_limit_is_reached() {

			exhale_api_info().success(function(data) {

				if(!data.can_compress) {

					return true;

				} else {

					return false;

				}

			});

		}

		/**
		--------------------------------------------------------------------------------
		GET IMAGE COUNT
		--------------------------------------------------------------------------------
		*/
		function exhale_image_count() {

			return $.ajax({
				url: ajaxurl,
				method: 'GET',
				cache: false,
				data: {action: 'exhale_image_count_ajax', security: exhale_image_count_nonce}
			});

		}

		/**
		--------------------------------------------------------------------------------
		GET BYTES SAVED
		--------------------------------------------------------------------------------
		*/
		function exhale_bytes_saved() {

			return $.ajax({
				url: ajaxurl,
				method: 'GET',
				cache: false,
				data: {action: 'exhale_bytes_saved', security: exhale_bytes_saved_nonce}
			});

		}

		/**
		--------------------------------------------------------------------------------
		CHECK IF ALL IMAGES ARE COMPRESSED
		--------------------------------------------------------------------------------
		*/
		function exhale_all_images_compressed() {

			exhale_api_info().success(function(data) {

				if(data.compressions_left >= 1) {

					return false;

				} else {

					return true;

				}

			});

		}

		/**
		--------------------------------------------------------------------------------
		DISPLAY INFOBOX
		--------------------------------------------------------------------------------
		*/
		$(document).on('click', '#exhale-toggle-infobox', function() {

			$('.exhale-infobox').toggle(50);

		});

		/**
		--------------------------------------------------------------------------------
		HOW MANY COMPRESSIONS ARE LEFT?
		--------------------------------------------------------------------------------
		*/
		function exhale_compressions_left_count() {

			exhale_api_info().success(function(data) {

				if(exhale_debug) {

					console.log(data);
				
				}

				if(data !== null) {

					if(data.compressions_left < 0) {

						$('#exhale-compressions-left-counter').html('0');

					} else {

						$('#exhale-compressions-left-counter').html(data.compressions_left);

					}

				}
				
			});

		}

		function exhale_limit_check() {

			exhale_api_info().success(function(data) {

				if(data !== null && data.can_compress == false) {

					$('.exhale-splash').html('<h1>You\'ve ran out of compressions! :(</h1><p>You can purchase more compressions <a target="_blank" href="https://pressbro.com/exhale">here</a></p>');
					$('.exhale-splash').fadeIn(50);
					$('.exhale-images').fadeOut(50);

				}

				if(data === null) {

					$('.exhale-splash').html('<h1>Oops, something went wrong :(</h1><p>It\'s us, not you. Sit back and relax while we fix the issue.</p>');
					$('.exhale-splash').fadeIn(50);
					$('.exhale-images').fadeOut(50);

				}

			});

		}

		// Call this on page load
		exhale_compressions_left_count();

		// And every 5 seconds
		setInterval(function() {

			// Fire compression count
			exhale_compressions_left_count();

			// Fire image count
			exhale_image_count().success(function(data) {

				$('#exhale-image-count').html(data.image_count);

				if(data.image_count === 0) {

					$('a#exhale-compress-all-images').removeClass('exhale-button-active');
					$('a#exhale-compress-all-images').html('<i class="fa fa-gavel"></i>Compress All Images');
					$('a#exhale-compress-all-images').addClass('disabled');
		

					exhale_api_info().success(function(data) {

						if(data !== null && data.can_compress) {

							$('.exhale-images').fadeOut(50);
							$('.exhale-splash').html('<h1>No images to compress :)</h1><p>This means that either there are no images at all or all images are already compressed.</p>');
							$('.exhale-splash').fadeIn(50);

						}

					});

				}

			});

			// Fire bytes saved
			exhale_bytes_saved().success(function(data) {

				if(data.bytes_saved < 0) {

					$('#exhale-bytes-saved').html('0 KB');

				} else {

					$('#exhale-bytes-saved').html(data.bytes_saved);

				}

			});

			// Remove notice if present
			if(document.getElementById('exhale-notice') !== null) {

				$('#exhale-notice').fadeOut(250);

			}

			exhale_limit_check();

		}, 5000);

		// Limit check
		exhale_limit_check();

	});

})(jQuery);