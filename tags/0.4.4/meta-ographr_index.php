<?php
/*
Plugin Name: OGraphr
Plugin URI: http://ographr.whyeye.org
Description: This plugin scans posts for videos (YouTube, Vimeo, Dailymotion, Hulu, Blip.tv) and music players (SoundCloud, Mixcloud, Bandcamp, Official.fm) and adds their thumbnails as an OpenGraph meta-tag. While at it, the plugin also adds OpenGraph tags for the title, description (excerpt) and permalink. Thanks to Sutherland Boswell, Michael Wöhrer, and Matthias Gutjahr!
Version: 0.4.4
Author: Jan T. Sott
Author URI: http://whyeye.org
License: GPLv2 
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

// OGRAPHR OPTIONS
    define("OGRAPHR_VERSION", "0.4.4");
	// force output of all values in comment tags
	define("OGRAPHR_DEBUG", FALSE);
	// enables features that are still marked beta
	define("OGRAPHR_BETA", FALSE);

// BANDCAMP
	// default artwork size (small_art_url=100x100, large_art_url=350x350)
	define("BANDCAMP_IMAGE_SIZE", "large_art_url");
	
// FLICKR
	// no need to change this unless you want to use your own Flickr API key (-> http://www.flickr.com/services/apps/create/apply/)
	define("FLICKR_API_KEY", "2250a1cc92a662d9ea156b4e04ca7a88");
	// default artwork size (s=75x75, q=150x150, t=100 on longest side, m=240 on longest side, n=320 on longest side)
	define("FLICKR_IMAGE_SIZE", "n");
	
// MIXCLOUD
	// default artwork size (small=25x25, thumbnail=50x50, medium_mobile=80x80, medium=150x150, large=300x300, extra_large=600x600)
	define("MIXCLOUD_IMAGE_SIZE", "large");

// OFFICIAL.FM
	// no need to change this unless you want to use your own Official.fm API key (-> http://official.fm/developers/manage#register)
	define("OFFICIAL_API_KEY", "yv4Aj7p3y5bYIhy3kd6X");

// PLAY.FM
	// no need to change this unless you want to use your own Play.fm API key (-> http://www.play.fm/api/account)
	//define("PLAYFM_API_KEY", "e5821e991f3b7bc982c3:109a0ca3bc");
	
// SOUNDCLOUD
	// no need to change this unless you want to use your own SoundCloud API key (-> http://soundcloud.com/you/apps)
	define("SOUNDCLOUD_API_KEY", "15fd95172fa116c0837c4af8e45aa702");
	// default artwork size (mini=16x16, tiny=20x20, small=32x32, badge=47x47, t67x67, large=100x100, t300x300, crop=400x400, t500x500)
	define("SOUNDCLOUD_IMAGE_SIZE", "t300x300");
	
// VIMEO
	// default snapshot size (small=100, medium=200, large=640)
	define("VIMEO_IMAGE_SIZE", "medium");
	
// USTREAM
	// no need to change this unless you want to use your own Ustream.fm API key (-> http://developer.ustream.tv/apikey/generate)
	define("USTREAM_API_KEY", "8E640EF9692DE21E1BC4373F890F853C");
	// default artwork size (small=120x90, medium=240x180)
	define("USTREAM_IMAGE_SIZE", "medium");
	
// JUSTIN.TV
	// default snapshot size (small=100, medium=200, large=640)
	define("JUSTINTV_IMAGE_SIZE", "image_url_large");

if ( is_admin() )
	require_once dirname( __FILE__ ) . '/meta-ographr_admin.php';

class OGraphr_Core {

	// Featured Image (http://codex.wordpress.org/Post_Thumbnails)
	function get_featured_img() {
		global $post, $posts;
		if (has_post_thumbnail( $post->ID )) {
			$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'single-post-thumbnail' );
			return $image[0];
	  	}
	}

	// Get JSON Thumbnail
	function get_json_thumbnail($service, $json_url, $json_query) {
		if (!function_exists('curl_init')) {
			return null;
		} else {
			// print "<!-- $service Query URL: $json_url -->\n\r";
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $json_url);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_FAILONERROR, true); // Return an error for curl_error() processing if HTTP response code >= 400
			$output = curl_exec($ch);
			$output = json_decode($output);
			
			// special treatment
			if ($service == "Justin.tv") {
				$output = $output[0];
			} else if ($service == "Flickr") {
				$ispublic = $output->photo->visibility->ispublic;
				if ($ispublic == 1) {
					$id = $output->photo->id;
					$server = $output->photo->server;
					$secret = $output->photo->secret;
					$farm = $output->photo->farm;
					$output = "http://farm" . $farm . ".staticflickr.com/" . $server . "/" . $id . "_" . $secret . "_" . FLICKR_IMAGE_SIZE . ".jpg";
					return $output;		
				} else {
					return;
				}
			}
			
			$json_keys = explode('->', $json_query);
			foreach($json_keys as $json_key) {
				$output = $output->$json_key;
			}			
			
			if (curl_error($ch) != null) {
				return;
			}
			curl_close($ch); // Moved here to allow curl_error() operation above. Was previously below curl_exec() call.
			return $output;
		}
	}
	
	// Get Vimeo Thumbnail
	function get_vimeo_thumbnail($id, $image_size = 'large') {
		if (!function_exists('curl_init')) {
			return null;
		} else {
			$ch = curl_init();
			$videoinfo_url = "http://vimeo.com/api/v2/video/$id.php";
			curl_setopt($ch, CURLOPT_URL, $videoinfo_url);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_FAILONERROR, true); // Return an error for curl_error() processing if HTTP response code >= 400
			$output = unserialize(curl_exec($ch));
			$output = $output[0]['thumbnail_' . $image_size];
			if (curl_error($ch) != null) {
				return;
			}
			curl_close($ch);
			return $output;
		}
	}
	
	// Get Blip.tv Thumbnail
	function get_bliptv_thumbnail($id) {
		$videoinfo_url = "http://blip.tv/players/episode/$id?skin=rss";
		$xml = simplexml_load_file( $videoinfo_url );
		if ( $xml == false ) {
			return new WP_Error( 'bliptv_info_retrieval', __( 'Error retrieving video information from the URL <a href="' . $videoinfo_url . '">' . $videoinfo_url . '</a>. If opening that URL in your web browser returns anything else than an error page, the problem may be related to your web server and might be something your host administrator can solve.' ) );
		} else {
			$result = $xml->xpath( "/rss/channel/item/media:thumbnail/@url" );
			$output = (string) $result[0]['url'];
			return $output;
		}
	}
	
	/*
	// Get Play.fm Thumbnail
	function get_playfm_thumbnail($id, $api_key = PLAYFM_API_KEY) {
		$videoinfo_url = "http://blip.tv/players/episode/$id?skin=rss";
		$xml = simplexml_load_file( $videoinfo_url );
		if ( $xml == false ) {
			return new WP_Error( 'bliptv_info_retrieval', __( 'Error retrieving video information from the URL <a href="' . $videoinfo_url . '">' . $videoinfo_url . '</a>. If opening that URL in your web browser returns anything else than an error page, the problem may be related to your web server and might be something your host administrator can solve.' ) );
		} else {
			$result = $xml->xpath( "/rss/channel/item/media:thumbnail/@url" );
			$output = (string) $result[0]['url'];
			return $output;
		}
	}
	*/
	
	// Get Bandcamp Parent Thumbnail
	function get_bandcamp_parent_thumbnail($id, $api_key = BANDCAMP_API_KEY, $image_size = 'large_art_url') {
		if (!function_exists('curl_init')) {
			return null;
		} else {
			//global $options;
			$ch = curl_init();
			$videoinfo_url = "http://api.bandcamp.com/api/track/1/info?key=$api_key&track_id=$id";
			curl_setopt($ch, CURLOPT_URL, $videoinfo_url);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_FAILONERROR, true); // Return an error for curl_error() processing if HTTP response code >= 400
			$output = curl_exec($ch);
			$output = json_decode($output);
			$output = $output->album_id;
			if (curl_error($ch) != null) {
				return;
			}
			curl_close($ch);
			
			// once more time for the album
			$ch = curl_init();
			$videoinfo_url = "http://api.bandcamp.com/api/album/2/info?key=$api_key&album_id=$output";
			curl_setopt($ch, CURLOPT_URL, $videoinfo_url);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_FAILONERROR, true); // Return an error for curl_error() processing if HTTP response code >= 400
			$output = curl_exec($ch);
			$output = json_decode($output);
			$output = $output->$image_size;
			if (curl_error($ch) != null) {
				return;
			}
			curl_close($ch);
			
			return $output;
		}
	}

	//
	// The Main Event
	//
	function get_ographr_thumbnails($post_id=null) {
	
		// Get this plugins' settings
		$options = get_option('ographr_options');
		
		$user_agent = $_SERVER['HTTP_USER_AGENT'];
		$facebook_ua = $options['facebook_ua'];
		$gplus_ua = $options['gplus_ua'];
		$linkedin_ua = $options['linkedin_ua'];		
		
		if(((preg_match('/facebookexternalhit/i',$user_agent)) && ($facebook_ua))
		|| ((preg_match('/Firefox\/6.0/i',$user_agent)) && ($gplus_ua))
		|| ((preg_match('/LinkedInBot/i',$user_agent)) && ($linkedin_ua))
		|| ((!$facebook_ua) && (!$gplus_ua) && (!$linkedin_ua))
		|| (OGRAPHR_DEBUG == TRUE)) {
			// Get the post ID if none is provided
			if($post_id==null OR $post_id=='') $post_id = get_the_ID();

			// Gets the post's content
			$post_array = get_post($post_id); 
			$markup = $post_array->post_content;
			$markup = apply_filters('the_content',$markup);

			// Get default website thumbnail
			$web_thumb = $options['website_thumbnail'];
			$screenshot = get_bloginfo('stylesheet_directory') . "/screenshot.png";
			if ($web_thumb == "%screenshot%") {
				$web_thumb = str_replace("%screenshot%", $screenshot, $web_thumb);
			}
		
			if (($web_thumb) && (!$options['not_always'])) {
				$og_thumbnails[] = $web_thumb;
			}
	
			// Get API keys
			$bandcamp_api = $options['bandcamp_api'];
			$flickr_api = $options['flickr_api'];
			$official_api = $options['official_api'];
			//$playfm_api = $options['playfm_api'];
			$soundcloud_api = $options['soundcloud_api'];
			$ustream_api = $options['ustream_api'];
			
			// otherwise use default API keys
			if (!$flickr_api) { $flickr_api = FLICKR_API_KEY; }
			if (!$official_api) { $official_api = OFFICIAL_API_KEY; }
			if (!$soundcloud_api) { $soundcloud_api = SOUNDCLOUD_API_KEY; }
			if (!$ustream_api) { $ustream_api = USTREAM_API_KEY; }
		
			// debugging?
			if(OGRAPHR_DEBUG == TRUE) {
				print "\n\r<!-- OGRAPHR v" . OGRAPHR_VERSION ." DEBUGGER -->\n\r";
				
				if (($facebook_ua) || ($gplus_ua) || ($linkedin_ua)) {
					if ($user_agent) { print "<!-- User Agent: $user_agent -->\n\r"; }
					if ($facebook_ua) { print "<!-- Limited to Facebook User Agent -->\n\r"; }
					if ($gplus_ua) { print "<!-- Limited to Google+ User Agent -->\n\r"; }
					if ($linkedin_ua) { print "<!-- Limited to LinkedIn User Agent -->\n\r"; }
				}
				
				if ($options['filter_smilies']) { print "<!-- Emoticons are filtered -->\n\r"; }
				if ($options['filter_gravatar']) { print "<!-- Avatars are filtered -->\n\r"; }
				
				if ($options['filter_custom_urls']) {
					foreach(preg_split("/((\r?\n)|(\n?\r))/", $options['filter_custom_urls']) as $line){
						print "<!-- Custom URL /$line/ is filtered -->\n\r";
						}
				}
				
				
				if ($soundcloud_api) { print "<!-- SoundCloud API key: $soundcloud_api -->\n\r"; }
				if ($bandcamp_api) { print "<!-- Bandcamp API key: $bandcamp_api -->\n\r"; }
				if ($flickr_api) { print "<!-- Flickr API key: $flickr_api -->\n\r"; }
				if ($official_api) { print "<!-- Official.fm API key: $official_api -->\n\r"; }
				//if ($playfm_api) { print "<!-- Play.fm API key: $playfm_api -->\n\r"; }
				if ($ustream_api) { print "<!-- Ustream API key: $ustream_api -->\n\r"; }
			}
	
			if (($enable_on_front = $options['enable_on_front']) || is_single() || (is_page())) {
		
				// Get images in post
				preg_match_all('/<img.+?src=[\'"]([^\'"]+)[\'"].*?>/i', $markup, $matches);
				foreach($matches[1] as $match) {
				  	if(OGRAPHR_DEBUG == TRUE) {
						print "<!-- Image tag: $match -->\n\r";
					}
					
					$no_smilies = FALSE;
					$no_gravatar = FALSE;
					$no_custom_url = TRUE;
					
					// filter Wordpress smilies
					preg_match('/\/wp-includes\/images\/smilies\/icon_.+/', $match, $filter);
					if ((!$options['filter_smilies']) || (!$filter[0])) {
						//$og_thumbnails[] = $match;
						$no_smilies = TRUE;
					}
					
					// filter Gravatar
					preg_match('/https?:\/\/w*.?gravatar.com\/avatar\/.*/', $match, $filter);
					if ((!$options['filter_gravatar']) || (!$filter[0])) {
						//$og_thumbnails[] = $match;
						$no_gravatar = TRUE;
					}
					
					// filter custom URLs
					foreach(preg_split("/((\r?\n)|(\n?\r))/", preg_quote($options['filter_custom_urls'], '/')) as $line) {
						//print "<!-- \$line=$line -->\n\r";
						preg_match("/$line/", $match, $filter);
						foreach($filter as $key => $value) {
							//print "<!-- \$value=$value -->\n\r";
							if ($value) {
								$no_custom_url = FALSE;						
							}
						}				
					}
					
					if (($no_gravatar) && ($no_smilies) && ($no_custom_url)) {
						$og_thumbnails[] = $match;
					}
					
				}
				
				// Get video poster
				preg_match_all('/<video.+?poster=[\'"]([^\'"]+)[\'"].*?>/i', $markup, $matches);
				foreach($matches[1] as $match) {
				  	if(OGRAPHR_DEBUG == TRUE) {
						print "<!-- Video poster: $match -->\n\r";
					}
					
					if (isset($match)) {
					  $og_thumbnails[] = $match;
					}
				}
	
				// Get featured image
				if (($options['add_post_thumbnail']) && ( function_exists( 'has_post_thumbnail' )) ){ 
					$website_thumbnail = $this->get_featured_img();
					if ($website_thumbnail) {
						$og_thumbnails[] = $website_thumbnail;
					}
				}
				
				// JWPlayer
				preg_match_all('/jwplayer\(.*?(?:image:[\s]*?)["\']([a-zA-Z0-9_\-\.]+)["\'].*?\)/smi', $markup, $matches);
				
				foreach($matches[1] as $match) {
					if (isset($match)) {							
						if(OGRAPHR_DEBUG == TRUE) {
							print "<!-- JWPlayer image: $match -->\n\r";
						}							
						$og_thumbnails[] = $match;
					}
				}
				
				// YOUTUBE
					if($options['enable_youtube']) {
						// Checks for the old standard YouTube embed
						preg_match_all('#<object[^>]+>.+?https?://w*.?youtube.com/[ve]/([A-Za-z0-9\-_]+).+?</object>#s', $markup, $matches1);

						// Checks for YouTube iframe, the new standard since at least 2011
						preg_match_all('#https?://w*.?youtube.com/embed/([A-Za-z0-9\-_]+)#s', $markup, $matches2);

						// Dailymotion shortcode (Viper's Video Quicktags)
						preg_match_all('/\[youtube.*?]https?:\/\/w*.?youtube.com\/watch\?v=([A-Za-z0-9\-_]+).+?\[\/youtube]/', $markup, $matches3);

						$matches = array_merge($matches1[1], $matches2[1], $matches3[1]);
						$matches = array_unique($matches);

						// Now if we've found a YouTube ID, let's set the thumbnail URL
						foreach($matches as $match) {
							$youtube_thumbnail = 'http://img.youtube.com/vi/' . $match . '/0.jpg'; // no https connection
							if(OGRAPHR_DEBUG == TRUE) {
								print "<!-- YouTube: $youtube_thumbnail (ID:$match) -->\n\r";
							}
							if (isset($youtube_thumbnail)) {
							  $og_thumbnails[] = $youtube_thumbnail;
							}
						}
					}

	
				// VIMEO
					if($options['enable_vimeo']) {
						// Vimeo Flash player ("old embed code")
						preg_match_all('#<object[^>]+>.+?https?://vimeo.com/moogaloop.swf\?clip_id=([A-Za-z0-9\-_]+)&.+?</object>#s', $markup, $matches1);
				
						// Vimeo iFrame player ("new embed code")
						preg_match_all('#https?://player.vimeo.com/video/([0-9]+)#s', $markup, $matches2);
				
						// Vimeo shortcode (Viper's Video Quicktags)
						preg_match_all('/\[vimeo.*?]https?:\/\/w*.?vimeo.com\/([0-9]+)\[\/vimeo]/', $markup, $matches3);
				
						$matches = array_merge($matches1[1], $matches2[1], $matches3[1]);
						$matches = array_unique($matches);
		
						// Now if we've found a Vimeo ID, let's set the thumbnail URL
						foreach($matches as $match) {
							$vimeo_thumbnail = $this->get_vimeo_thumbnail($match, VIMEO_IMAGE_SIZE);
							if(OGRAPHR_DEBUG == TRUE) {
								print "<!-- Vimeo: $vimeo_thumbnail (ID:$match) -->\n\r";
							}
							if (isset($vimeo_thumbnail)) {
							  $og_thumbnails[] = $vimeo_thumbnail;
							}
						}
					}
				
	
				// DAILYMOTION
					if($options['enable_dailymotion']) {
						// Dailymotion Flash player
						preg_match_all('#<object[^>]+>.+?https?://w*.?dailymotion.com/swf/video/([A-Za-z0-9-_]+).+?</object>#s', $markup, $matches1);
				
						// Dailymotion iFrame player
						preg_match_all('#https?://w*.?dailymotion.com/embed/video/([A-Za-z0-9-_]+)#s', $markup, $matches2);
				
						// Dailymotion shortcode (Viper's Video Quicktags)
						preg_match_all('/\[dailymotion.*?]https?:\/\/w*.?dailymotion.com\/video\/([A-Za-z0-9-_]+)\[\/dailymotion]/', $markup, $matches3);
				
						$matches = array_merge($matches1[1], $matches2[1], $matches3[1]);
						$matches = array_unique($matches);

						// Now if we've found a Dailymotion video ID, let's set the thumbnail URL
						foreach($matches as $match) {
							$service = "Dailymotion";
							$json_url = "https://api.dailymotion.com/video/$match?fields=thumbnail_url";
							$json_query = "thumbnail_url";
							$dailymotion_thumbnail = $this->get_json_thumbnail($service, $json_url, $json_query);
							if(OGRAPHR_DEBUG == TRUE) {
								print "<!-- Dailymotion: $dailymotion_thumbnail (ID:$match) -->\n\r";
							}
							if (isset($dailymotion_thumbnail)) {
								$dailymotion_thumbnail = preg_replace('/\?([A-Za-z0-9]+)/', '', $dailymotion_thumbnail); // remove suffix
								$og_thumbnails[] = $dailymotion_thumbnail;
							}
						}
					}
		
			
				// BLIP.TV
					if($options['enable_bliptv']) {

						// Blip.tv iFrame player
						preg_match_all( '/blip.tv\/play\/([A-Za-z0-9]+)/', $markup, $matches1 );
					
						// Blip.tv Flash player
						preg_match_all( '/a.blip.tv\/api.swf#([A-Za-z0-9%]+)/', $markup, $matches2 );
					
						$matches = array_merge($matches1[1], $matches2[1]);
						$matches = array_unique($matches);

						// Now if we've found a Blip.tv embed URL, let's set the thumbnail URL
						foreach($matches as $match) {
							$bliptv_thumbnail = $this->get_bliptv_thumbnail($match);
							if(OGRAPHR_DEBUG == TRUE) {
								print "<!-- Blip.tv: $bliptv_thumbnail (ID:$match) -->\n\r";
							}
							if (isset($bliptv_thumbnail)) {
								$og_thumbnails[] = $bliptv_thumbnail;
							}
						}
					}
					
				// FLICKR
				if($options['enable_flickr']) {
					preg_match_all('/<object.*?data=\"http:\/\/www.flickr.com\/apps\/video\/stewart.swf\?.*?>(.*?photo_id=([0-9]+).*?)<\/object>/smi', $markup, $matches);
					$matches = $matches[2];
				
					// Now if we've found a Flickr embed URL, let's set the thumbnail URL
					foreach($matches as $match) {
						//$flickr_thumbnail = $this->get_flickr_thumbnail($match);
						$service = "Flickr";
						$json_url = "http://www.flickr.com/services/rest/?method=flickr.photos.getInfo&photo_id=$match&format=json&api_key=$flickr_api&nojsoncallback=1";
						$json_query = NULL;
						$flickr_thumbnail = $this->get_json_thumbnail($service, $json_url, $json_query);
						if(OGRAPHR_DEBUG == TRUE) {
							print "<!-- Flickr: $flickr_thumbnail (ID:$match) -->\n\r";
						}
						if (isset($flickr_thumbnail)) {
							$og_thumbnails[] = $flickr_thumbnail;
						}
					}
				}
			
			
				// HULU	
				if($options['enable_hulu']) {

					// Blip.tv iFrame player
					preg_match_all( '/hulu.com\/embed\/([A-Za-z0-9\-_]+)/', $markup, $matches );				
					$matches = array_unique($matches[1]);

					// Now if we've found a Blip.tv embed URL, let's set the thumbnail URL
					foreach($matches as $match) {
						//$hulu_thumbnail = $this->get_hulu_thumbnail($match);
						$service = "Hulu";
						$json_url = "http://www.hulu.com/api/oembed.json?url=http://www.hulu.com/embed/$match";
						$json_query = "thumbnail_url";
						$hulu_thumbnail = $this->get_json_thumbnail($service, $json_url, $json_query);
						if(OGRAPHR_DEBUG == TRUE) {
							print "<!-- Hulu: $hulu_thumbnail (ID:$match) -->\n\r";
						}
						if (isset($hulu_thumbnail)) {
							$og_thumbnails[] = $hulu_thumbnail;
						}
					}
				}
				
				// USTREAM	
				if($options['enable_ustream']) {

					// Ustream iFrame player
					preg_match_all( '/ustream.tv\/embed\/recorded\/([0-9]+)/', $markup, $matches );		
					$matches = array_unique($matches[1]);
					
					// Now if we've found a Ustream embed URL, let's set the thumbnail URL
					foreach($matches as $match) {
						//$ustream_thumbnail = $this->get_ustream_thumbnail($match, $ustream_api);						
						$service = "Ustream";
						$json_url = "http://api.ustream.tv/json/channel/$match/getInfo?key=$ustream_api";
						$json_query = "results->imageUrl->" . USTREAM_IMAGE_SIZE;
						$ustream_thumbnail = $this->get_json_thumbnail($service, $json_url, $json_query);						
						if(OGRAPHR_DEBUG == TRUE) {
							print "<!-- Ustream: $ustream_thumbnail (ID:$match) -->\n\r";
						}
						if (isset($ustream_thumbnail)) {
							$og_thumbnails[] = $ustream_thumbnail;
						}
					}
				}
				
				// JUSTIN.TV
				if($options['enable_justintv']) {

					// Justin.tv embed player
					//www.justin.tv/widgets/live_embed_player.swf?channel=securetv
					preg_match_all( '/justin.tv\/widgets\/live_embed_player.swf\?channel=([A-Za-z0-9-_]+)/', $markup, $matches );		
					$matches = array_unique($matches[1]);
					
					// Now if we've found a Justin.tv embed URL, let's set the thumbnail URL
					foreach($matches as $match) {
						//$justintv_thumbnail = $this->get_justintv_thumbnail($match);
						$service = "Justin.tv";
						$json_url = "http://api.justin.tv/api/stream/list.json?channel=$match";
						$json_query = "channel->" . JUSTINTV_IMAGE_SIZE;
						$justintv_thumbnail = $this->get_json_thumbnail($service, $json_url, $json_query);
						if(OGRAPHR_DEBUG == TRUE) {
							print "<!-- Justin.tv: $justintv_thumbnail (ID:$match) -->\n\r";
						}
						if (isset($justintv_thumbnail)) {
							$og_thumbnails[] = $justintv_thumbnail;
						}
					}
				}
					
					
				// SOUNDCLOUD
					if($options['enable_soundcloud']) {
						// Standard embed code for tracks (Flash and HTML5 player)
						preg_match_all('/api.soundcloud.com%2Ftracks%2F([0-9]+)/', $markup, $matches1);
				
						// Shortcode for tracks (Flash and HTML5 player)
						preg_match_all('/api.soundcloud.com\/tracks\/([0-9]+)/', $markup, $matches2);
				
						$matches = array_merge($matches1[1], $matches2[1]);
						$matches = array_unique($matches);
		
						// Now if we've found a SoundCloud ID, let's set the thumbnail URL
						foreach($matches as $match) {
							//$soundcloud_thumbnail = $this->get_soundcloud_thumbnail('tracks', $match, $soundcloud_api, SOUNDCLOUD_IMAGE_SIZE);
							$service = "SoundCloud";
							$json_url = "http://api.soundcloud.com/tracks/$match.json?client_id=$soundcloud_api";
							$json_query = "artwork_url";
							$soundcloud_thumbnail = $this->get_json_thumbnail($service, $json_url, $json_query);
							$soundcloud_thumbnail = str_replace('-large.', '-' . SOUNDCLOUD_IMAGE_SIZE . '.', $soundcloud_thumbnail); // replace 100x100 default image
						
							if(OGRAPHR_DEBUG == TRUE) {
								print "<!-- SoundCloud track: $soundcloud_thumbnail (ID:$match) -->\n\r";
							}
							if (isset($soundcloud_thumbnail)) {
							  	$soundcloud_thumbnail = preg_replace('/\?([A-Za-z0-9]+)/', '', $soundcloud_thumbnail); // remove suffix
								$og_thumbnails[] = $soundcloud_thumbnail;
							}
						}
		
						// Standard embed code for playlists (Flash and HTML5 player)
						preg_match_all('/api.soundcloud.com%2Fplaylists%2F([0-9]+)/', $markup, $matches1);
				
						// Shortcode for playlists (Flash and HTML5 player)
						preg_match_all('/api.soundcloud.com\/playlists\/([0-9]+)/', $markup, $matches2);
				
						$matches = array_merge($matches1[1], $matches2[1]);
						$matches = array_unique($matches);
		
						// Now if we've found a SoundCloud ID, let's set the thumbnail URL
						foreach($matches as $match) {
							//$soundcloud_thumbnail = $this->get_soundcloud_thumbnail('playlists', $match, $soundcloud_api, SOUNDCLOUD_IMAGE_SIZE);
							$service = "SoundCloud";
							$json_url = "http://api.soundcloud.com/playlists/$match.json?client_id=$soundcloud_api";
							$json_query = "artwork_url";
							$soundcloud_thumbnail = $this->get_json_thumbnail($service, $json_url, $json_query);
							$soundcloud_thumbnail = str_replace('-large.', '-' . SOUNDCLOUD_IMAGE_SIZE . '.', $soundcloud_thumbnail); // replace 100x100 default image
							if(OGRAPHR_DEBUG == TRUE) {
								print "<!-- SoundCloud playlist: $soundcloud_thumbnail (ID:$match) -->\n\r";
							}
							if (isset($soundcloud_thumbnail)) {
							  	$soundcloud_thumbnail = preg_replace('/\?([A-Za-z0-9]+)/', '', $soundcloud_thumbnail); // remove suffix
								$og_thumbnails[] = $soundcloud_thumbnail;
							}
						}
					}
				
	
				// MIXCLOUD	
					if($options['enable_mixcloud']) {
						// Standard embed code
						preg_match_all('/mixcloudLoader.swf\?feed=https?%3A%2F%2Fwww.mixcloud.com%2F([A-Za-z0-9\-_\%]+)&/', $markup, $matches);
						$matches = array_unique($matches[1]);
					
						// Standard embed (API v1, undocumented)
						// preg_match_all('/feed=http:\/\/www.mixcloud.com\/api\/1\/cloudcast\/([A-Za-z0-9\-_\%\/.]+)/', $markup, $mixcloud_ids);					
					
						// Now if we've found a Mixcloud ID, let's set the thumbnail URL
						foreach($matches as $match) {
							$mixcloud_id = str_replace('%2F', '/', $match);
							//$mixcloud_thumbnail = $this->get_mixcloud_thumbnail($mixcloud_id, MIXCLOUD_IMAGE_SIZE);
							$service = "Mixcloud";
							$json_url = "http://api.mixcloud.com/$match";
							$json_query = "pictures->" . MIXCLOUD_IMAGE_SIZE;
							$mixcloud_thumbnail = $this->get_json_thumbnail($service, $json_url, $json_query);
							if(OGRAPHR_DEBUG == TRUE) {
								print "<!-- MixCloud: $mixcloud_thumbnail -->\n\r";
							}
							if (isset($mixcloud_thumbnail)) {
								$og_thumbnails[] = $mixcloud_thumbnail;
							}
						}
					}
					
	
				// BANDCAMP
					if($options['enable_bandcamp']) {
						// Standard embed code for albums
						preg_match_all('/bandcamp.com\/EmbeddedPlayer\/v=2\/album=([0-9]+)\//', $markup, $matches);					
						$matches = array_unique($matches[1]);
		
						// Now if we've found a Bandcamp ID, let's set the thumbnail URL
						foreach($matches as $match) {
							//$bandcamp_thumbnail = $this->get_bandcamp_thumbnail($match, $bandcamp_api, BANDCAMP_IMAGE_SIZE);
							$service = "Bandcamp";
							$json_url = "http://api.bandcamp.com/api/album/2/info?key=$bandcamp_api&album_id=$match";
							$json_query = BANDCAMP_IMAGE_SIZE;
							$bandcamp_thumbnail = $this->get_json_thumbnail($service, $json_url, $json_query);
							if(OGRAPHR_DEBUG == TRUE) {
								print "<!-- Bandcamp album: $bandcamp_thumbnail (ID:$match) -->\n\r";
							}
							if (isset($bandcamp_thumbnail)) {
								$og_thumbnails[] = $bandcamp_thumbnail;
							}
						}

						// Standard embed code for single tracks
						preg_match_all('/bandcamp.com\/EmbeddedPlayer\/v=2\/track=([0-9]+)\//', $markup, $matches);					
						$matches = array_unique($matches[1]);
					
						// Now if we've found a Bandcamp ID, let's set the thumbnail URL
						foreach($matches as $match) {
							$bandcamp_thumbnail = $this->get_bandcamp_parent_thumbnail($match, $bandcamp_api);
							if(OGRAPHR_DEBUG == TRUE) {
								print "<!-- Bandcamp track: $bandcamp_thumbnail (ID:$match) -->\n\r";
							}
							if (isset($bandcamp_thumbnail)) {
								$og_thumbnails[] = $bandcamp_thumbnail;
							}
						}
					}
				
					// OFFICIAL.TV
						if($options['enable_official']) {

							// Official.fm iFrame
							preg_match_all( '/official.fm\/tracks\/([A-Za-z0-9]+)\?/', $markup, $matches );
							$matches = array_unique($matches[1]);

							// Now if we've found a Official.fm embed URL, let's set the thumbnail URL
							foreach($matches as $match) {
								//$official_thumbnail = $this->get_official_thumbnail($match, $official_api);
								$service = "Official.fm";
								$json_url = "http://official.fm/services/oembed.json?url=http://official.fm/tracks/$match&size=large&key=$official_api";
								$json_query = "thumbnail_url";
								$official_thumbnail = $this->get_json_thumbnail($service, $json_url, $json_query);
								if(OGRAPHR_DEBUG == TRUE) {
									print "<!-- Official.fm: $official_thumbnail (ID:$match) -->\n\r";
								}
								if (isset($official_thumbnail)) {
									$og_thumbnails[] = $official_thumbnail;
								}
							}
						}
						
						// PLAY.FM
						/*
							if($options['enable_playfm']) {

								// Play.fm embed
							//	preg_match_all('/mixcloudLoader.swf\?feed=https?%3A%2F%2Fwww.mixcloud.com%2F([A-Za-z0-9\-_\%]+)&/', $markup, $matches);
							//playfmWidget.swf?url=http%3A%2F%2Fwww.play.fm%2Frecordings%2Fflash%2F01%2Frecording%2F([0-9]+)
								preg_match_all( '/playfmWidget.swf\?url=http%3A%2F%2Fwww.play.fm%2Frecordings%2Fflash%2F01%2Frecording%2F([0-9]+)/', $markup, $matches );
								$matches = array_unique($matches[1]);

								// Now if we've found a Play.fm embed URL, let's set the thumbnail URL
								foreach($matches as $match) {
									$playfm_thumbnail = $this->get_playfm_thumbnail($match, $playfm_api);
									if(OGRAPHR_DEBUG == TRUE) {
										print "<!-- Play.fm: $playfm_thumbnail (ID:$match) -->\n\r";
									}
									if (isset($playfm_thumbnail)) {
										$og_thumbnails[] = $playfm_thumbnail;
									}
								}
							}
							*/
							
		}
				
					// Let's print all this
					if(($options['add_comment']) && (OGRAPHR_DEBUG == FALSE)) {
						print "<!-- OGraphr v" . OGRAPHR_VERSION . " - http://ographr.whyeye.org -->\n\r";
					}
			
					// Add title & description
					$title = $options['website_title'];
					$site_name = $options['fb_site_name'];
					$wp_title = get_the_title();
					$wp_name = get_bloginfo('name');
					$wp_url = get_option('home');
					//$wp_author = get_the_author_meta('display_name'); // inside of loop!
					$wp_url = preg_replace('/https?:\/\//', NULL, $wp_url);
					$title = str_replace("%postname%", $wp_title, $title);
					$title = str_replace("%sitename%", $wp_name, $title);
					$title = str_replace("%siteurl%", $wp_url, $title);
					//$title = str_replace("%author%", $wp_author, $title); // inside of loop!
					if (!$title) {
						$title = $wp_title;
					}
					$site_name = str_replace("%sitename%", $wp_name, $site_name);
					$site_name = str_replace("%siteurl%", $wp_url, $site_name);
				
					if (($options['website_description']) && (is_front_page())) {
						// Blog title
						$title = get_settings('blogname');
						if($title) {
							print "<meta property=\"og:title\" content=\"$title\" />\n\r"; 
						}
						// Add custom description
						$description = $options['website_description'];
						$wp_tagline = get_bloginfo('description');
						$description = str_replace("%tagline%", $wp_tagline, $description);
						if($description) {
							print "<meta property=\"og:description\" content=\"$description\" />\n\r";
						}
					} else { //single posts
						if ($options['add_title'] && ($title)) {
							// Post title
							print "<meta property=\"og:title\" content=\"$title\" />\n\r"; 
						}
										
						if($options['add_excerpt'] && ($description = wp_strip_all_tags((get_the_excerpt()), true))) {
							// Post excerpt
							print "<meta property=\"og:description\" content=\"$description\" />\n\r";
						}
					}
			
					// Add permalink
					if (($options['add_permalink']) && (is_front_page()) && ($link = get_option('home'))) {
						print "<meta property=\"og:url\" content=\"$link\" />\n\r";
					} else {
						if($options['add_permalink'] && ($link = get_permalink())) {
							print "<meta property=\"og:url\" content=\"$link\" />\n\r";
						}
					}
				
					// Add site name
					if ($site_name) {
						print "<meta property=\"og:site_name\" content=\"$site_name\" />\n\r";
					}
				
					// Add type
					if (($type = $options['fb_type']) && ($type != '_none')) {
						print "<meta property=\"og:type\" content=\"$type\" />\n\r";
					}
			
					// Add thumbnails
					if ($og_thumbnails) { // avoid error message when array is empty
						$og_thumbnails = array_unique($og_thumbnails); // unlikely, but hey!
						$total_img = count($og_thumbnails);
					}
								
					if (($total_img == 0) && ($web_thumb)) {
						print "<meta property=\"og:image\" content=\"$web_thumb\" />\n\r";
					} else if ($og_thumbnails) { // investige?
						foreach ($og_thumbnails as $og_thumbnail) {
							if ($og_thumbnail) {
								print "<meta property=\"og:image\" content=\"$og_thumbnail\" />\n\r";
							}
						}
					}
				
					// Add Facebook ID
					if ($fb_admins = $options['fb_admins']) {
						print "<meta property=\"fb:admins\" content=\"$fb_admins\" />\n\r";
					}

					// Add Facebook Application ID
					if ($fb_app_id = $options['fb_app_id']) {
						print "<meta property=\"fb:app_id\" content=\"$fb_app_id\" />\n\r";
					}
					
					//print "<meta property=\"og:description\" content=\"$user_agent\" />\n\r";

				}
			}
};

add_action('wp_head', 'OGraphr_Core_Init');
function OGraphr_Core_Init() {
	$core = new OGraphr_Core();
	$core->get_ographr_thumbnails();
}

// Display a Settings link on the main Plugins page
function ographr_plugin_action_links( $links, $file ) {

	if ( $file == plugin_basename( __FILE__ ) ) {
		$ographr_links = '<a href="'.get_admin_url().'options-general.php?page=meta-ographr/meta-ographr_admin.php">' .__('Settings').'</a>';
		
		// make the 'Settings' link appear first
		array_unshift( $links, $ographr_links );
	}

	return $links;
}

?>