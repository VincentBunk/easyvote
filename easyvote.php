<?php
/*
Plugin Name: Easy Vote
Plugin URI: http://www.stadtkreation.de
Description: Easy vote plugin for Wordpress
Version: 0.2
Author: Johannes Bouchain
Author http://www.stadtkreation.de
License: GPL2
Text Domain: easyvote
Domain Path: /languages/
*/

define ('EASYVOTE_PLUGIN_BASENAME',plugin_basename(dirname(__FILE__)));
define ('EASYVOTE_PLUGIN_URL',WP_PLUGIN_URL."/".EASYVOTE_PLUGIN_BASENAME);

add_action( 'load-post.php', 'ev_metabox_setup' );
add_action( 'load-post-new.php', 'ev_metabox_setup' );

function myplugin_init() {
	$plugin_dir = basename(dirname(__FILE__));
  load_plugin_textdomain( 'easyvote', false, $plugin_dir.'/languages/' ); 
}
add_action('plugins_loaded', 'myplugin_init');

 function ev_metabox_setup() {
	// Add meta boxes on the 'add_meta_boxes' hook.
	add_action( 'add_meta_boxes', 'ev_metabox' );
	
	// Save post meta on the 'save_post' hook.
	add_action( 'save_post', 'save_easyvote_meta', 10, 2 );
}

function ev_metabox() {
		add_meta_box(
			'easyvote-metabox',
			esc_html__( 'Easy Vote', 'easyvote' ),
			'ev_metabox_content',
			'userpost',
			'normal'
		);
}

function ev_metabox_content( $object, $box ) {
		wp_nonce_field( basename( __FILE__ ), 'easyvote_nonce' ); ?>
		<p>
			<label for="easyvote-countvotes"><?php _e( "Set the number of likes for this post", 'easyvote' ); ?></label>
			<br />
			<input class="widefat" type="text" name="easyvote-countvotes" id="easyvote-countvotes" value="<?php echo esc_attr( get_post_meta( $object->ID, 'easyvote-countvotes', true ) ); ?>" size="30" />
		</p>
		<p>
			<label for="easyvote-like-userips"><?php _e( "Edit IP list of likes for this post", 'easyvote' ); ?></label>
			<br />
			<input class="widefat" type="text" name="easyvote-like-userips" id="easyvote-like-userips" value="<?php echo esc_attr( get_post_meta( $object->ID, 'easyvote-like-userips', true ) ); ?>" size="30" />
		</p>
		<p>
			<label for="easyvote-countdislikes"><?php _e( "Set the number of dislikes for this post", 'easyvote' ); ?></label>
			<br />
			<input class="widefat" type="text" name="easyvote-countdislikes" id="easyvote-countdislikes" value="<?php echo esc_attr( get_post_meta( $object->ID, 'easyvote-countdislikes', true ) ); ?>" size="30" />
		</p>
		<p>
			<label for="easyvote-dislike-userips"><?php _e( "Edit IP list of dislikes for this post", 'easyvote' ); ?></label>
			<br />
			<input class="widefat" type="text" name="easyvote-dislike-userips" id="easyvote-dislike-userips" value="<?php echo esc_attr( get_post_meta( $object->ID, 'easyvote-dislike-userips', true ) ); ?>" size="30" />
		</p>
<?php
}


// Save the meta box's post metadata. 
function save_easyvote_meta( $post_id, $post ) {
	//Verify the nonce before proceeding.
	if ( !isset( $_POST['easyvote_nonce'] ) || !wp_verify_nonce( $_POST['easyvote_nonce'], basename( __FILE__ ) ) )
		return $post_id;

	// Get the post type object.
	$post_type = get_post_type_object( $post->post_type );

	// Check if the current user has permission to edit the post.
	if ( !current_user_can( $post_type->cap->edit_post, $post_id ) )
		return $post_id;
	
	// Update or add meta data
	$meta_keys = array('easyvote-countvotes','easyvote-like-userips','easyvote-countdislikes','easyvote-dislike-userips');
	foreach($meta_keys as $meta_key) {
		$new_meta_value = $_POST[$meta_key];
		$meta_value = get_post_meta( $post_id, $meta_key, true );
		if ( $new_meta_value && '' == $meta_value ) add_post_meta( $post_id, $meta_key, $new_meta_value, true );
		elseif ( $new_meta_value && $new_meta_value != $meta_value ) update_post_meta( $post_id, $meta_key, $new_meta_value );
		elseif ( '' == $new_meta_value && $meta_value ) delete_post_meta( $post_id, $meta_key, $meta_value );
	}
}

function easyvote_button($content) {
	if('userpost' === get_post_type()) {
		if(is_singular('userpost')) {
			$already_liked=false;
			$already_disliked=false;
			$block_ip_doublevote=false;
			global $post;
			
			// likes/dislikes counter output
			$likes_text = __('votes','easyvote');
			$dislikes_text = __('votes','easyvote');
			if(get_post_meta($post->ID,'easyvote-countvotes',true)==1) $likes_text = __('vote','easyvote');
			if(get_post_meta($post->ID,'easyvote-countdislikes',true)==1) $dislikes_text = __('vote','easyvote');
			$likes_number = (get_post_meta($post->ID,'easyvote-countvotes',true) ? get_post_meta($post->ID,'easyvote-countvotes',true) : '0');
			$dislikes_number = (get_post_meta($post->ID,'easyvote-countdislikes',true) ? get_post_meta($post->ID,'easyvote-countdislikes',true) : '0');
			if(!get_post_meta($post->ID,'easyvote-countdislikes',true)) 
			$output .= '';
			$output_votecount = '<p>'.sprintf(__('This post has <strong>%1$s positive %3$s</strong> and <strong>%2$s negative %4$s</strong>.','easyvote'),$likes_number, $dislikes_number, $likes_text, $dislikes_text).'</p>';
			
			if(function_exists('epa_get_permalink')) $permalink=epa_get_permalink($post->ID, 'anchor');
			else $permalink=get_permalink();
			if(isset($_COOKIE['easyvote_like_'.$post->ID])) $already_liked = true;
			if(isset($_COOKIE['easyvote_dislike_'.$post->ID])) $already_disliked = true;
			if(isset($block_ip_doublevote) && $block_ip_doublevote) {
				$user_ip = easyvote_user_ip();
				if(!$already_liked) $already_liked = easyvote_check_ips($post->ID,$user_ip,'like');
				if(!$already_disliked) $already_disliked = easyvote_check_ips($post->ID,$user_ip,'dislike');
			}
			
			// workaround to show vote directly after submit
			if(isset($_POST['easyvote-like-postid'])) {
				if($_POST['easyvote-like-postid'] == $post->ID) {
					$already_liked = true;
					$already_disliked = false;
				}
			}
			if(isset($_POST['easyvote-dislike-postid'])) {
				if($_POST['easyvote-dislike-postid'] == $post->ID) {
					$already_disliked = true;
					$already_liked = false;
				}
			}
			
			
			if((isset($already_liked) || isset($already_disliked)) && !$already_liked && !$already_disliked) {
				$output .= '<div class="easyvote-box">'.$output_votecount;
				$output .= '<form class="easyvote-button-like" method="post" action="'.$permalink.'">
		<p>
			<input type="hidden" name="easyvote-like-postid" value="'.$post->ID.'" />
			<input type="submit" value="'.__('I agree!','easyvote').'" />
		</p>
	</form>';
				$output .= '<form class="easyvote-button-dislike" method="post" action="'.$permalink.'">
		<p>
			<input type="hidden" name="easyvote-dislike-postid" value="'.$post->ID.'" />
			<input type="submit" value="'.__('I disagree!','easyvote').'" />
		</p>
	</form>';
				$output .= '</div>';
			}
			else {
				if(isset($already_liked) && $already_liked) $output .= '<div class="easyvote-box">'.$output_votecount.'<p class="easyvote liked">'.__('You agreed!','easyvote').'</p><form class="easyvote-button-dislike small" method="post" action="'.$permalink.'"><p><input type="hidden" name="changevote" value="1" /><input type="hidden" name="easyvote-dislike-postid" value="'.$post->ID.'" /><button type="submit">'.__('Click here if you want to disagree instead!','easyvote').'</button></p></form></div>';
				elseif(isset($already_disliked) && $already_disliked) $output .= '<div class="easyvote-box">'.$output_votecount.'<p class="easyvote disliked">'.__('You disagreed!','easyvote').'</p><form class="easyvote-button-like small" method="post" action="'.$permalink.'"><p><input type="hidden" name="changevote" value="1" /><input type="hidden" name="easyvote-like-postid" value="'.$post->ID.'" /><button type="submit">'.__('Click here if you want to agree instead!','easyvote').'</button></p></form></div>';
			}
		}
		return $content.$output;
	}
	else return $content;
}
add_filter('the_content', 'easyvote_button');


function easyvote_setvote() {
	global $post;
	if(isset($_POST['easyvote-like-postid'])) $id = $_POST['easyvote-like-postid'];
	elseif(isset($_POST['easyvote-dislike-postid'])) $id = $_POST['easyvote-dislike-postid'];
	else $id=0;
	
	// start session
	if(session_id() == '') session_start();
	
	// getting user ip
	$user_ip = easyvote_user_ip();
	
	// updating post votes if user voted
	if(isset($_POST['easyvote-like-postid'])) {
		if(isset($_COOKIE['easyvote_like_'.$id])) $already_liked = true;
		if(!$already_liked) $already_liked = easyvote_check_ips($id,$user_ip,'like');
		if(!$already_liked) {
			if(isset($_POST['changevote'])) {
				easyvote_unsetcookie($id,'dislike');
				$countvotes = get_post_meta($id,'easyvote-countdislikes',true);
				$countvotes = $countvotes - 1;
				update_post_meta($id,'easyvote-countdislikes',$countvotes);
				easyvote_remove_ip($id,'dislike',$user_ip);
			}
			$countvotes = get_post_meta($id,'easyvote-countvotes',true);
			$countvotes++;
			update_post_meta($id,'easyvote-countvotes',$countvotes);
			$user_ips = get_post_meta($id,'easyvote-like-userips',true);
			if($user_ips) {
				$add_ip = ',['.$user_ip.']:'.time();
				//$add_ip = ',['.$user_ip.']:'.time();
			}
			else {
				$add_ip = '['.$user_ip.']:'.time();
				//$add_ip = '['.$user_ip.']:'.time();
			}
			$user_ips = $user_ips.$add_ip;
			update_post_meta($id,'easyvote-like-userips',$user_ips);
			easyvote_setcookie($id,'like');
		}
	}
	elseif(isset($_POST['easyvote-dislike-postid'])) {
		if(isset($_COOKIE['easyvote_dislike_'.$id])) $already_disliked = true;
		if(!$already_disliked) $already_disliked = easyvote_check_ips($id, $user_ip, 'dislike');
//		echo 'Bis easyvote_setvote() gekommen. IP: '.$user_ip."\n";
//		echo print_r($_POST, true)."\n";
//		echo print_r($_COOKIE, true)."\n";
//		die();
		if(!$already_disliked) {
			if(isset($_POST['changevote'])) {
				easyvote_unsetcookie($id,'like');
				$countvotes = get_post_meta($id,'easyvote-countvotes', true);
				$countvotes = $countvotes - 1;
				update_post_meta($id,'easyvote-countvotes',$countvotes);
				easyvote_remove_ip($id,'like',$user_ip);
			}
			$countvotes = get_post_meta($id,'easyvote-countdislikes', true);
			$countvotes++;
			update_post_meta($id,'easyvote-countdislikes',$countvotes);
			$user_ips = get_post_meta($id,'easyvote-dislike-userips', true);
			if($user_ips) {
				//$add_ip = ',['.$user_ip.']';
				$add_ip = ',['.$user_ip.']:'.time();
			}
			else {
				//$add_ip = '['.$user_ip.']';
				$add_ip = '['.$user_ip.']:'.time();
			}
			$user_ips = $user_ips.$add_ip;
			update_post_meta($id,'easyvote-dislike-userips',$user_ips);
			easyvote_setcookie($id,'dislike');
		}
		
		
	}
}
add_action('init', 'easyvote_setvote');

function easyvote_setcookie($id,$type) {
	if($type=='like') {
		if (!isset($_COOKIE['easyvote_like_'.$id])) {
			setcookie('easyvote_like_'.$id, 1, time()+(86400*30), COOKIEPATH, COOKIE_DOMAIN, false);
		}
	}
	if($type=='dislike') {
		if (!isset($_COOKIE['easyvote_dislike_'.$id])) {
			setcookie('easyvote_dislike_'.$id, 1, time()+(86400*30), COOKIEPATH, COOKIE_DOMAIN, false);
		}
	}
}

function easyvote_unsetcookie($id,$type) {
	if($type=='like') setcookie('easyvote_like_'.$id, '', time()-3600, COOKIEPATH);
	if($type=='dislike') setcookie('easyvote_dislike_'.$id,'', time()-3600, COOKIEPATH);
//	echo 'Bis easyvote_unsetcookie() gekommen. ID: '.$id.'| Type: '.$type."\n";
//	die();
}

function easyvote_user_ip() {
	if ( isset($_SERVER["REMOTE_ADDR"]) ) $user_ip = $_SERVER["REMOTE_ADDR"];
	else if ( isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ) $user_ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
	else if ( isset($_SERVER["HTTP_CLIENT_IP"]) ) $user_ip = $_SERVER["HTTP_CLIENT_IP"];
	return $user_ip;
}

function easyvote_check_ips($id, $user_ip, $type) {
	if($type=='like') $check_ips = get_post_meta($id,'easyvote-like-userips',true);
	elseif($type=='dislike') $check_ips = get_post_meta($id,'easyvote-dislike-userips',true);
	$check_ips = explode(',',$check_ips);
	$check_ips = preg_replace('/^\[(.*?)\](.*?)$/', '\1', $check_ips );
	//$check_ips = preg_replace('/^\[(.*?)\][:]{0,1}([0-9]*?)/', '\1', explode(',',$check_ips) );
	//$check_times = preg_replace('/^\[(.*?)\][:]{0,1}([0-9]*?)/', '\2', explode(',',$check_ips) )
	easyvote_unsetcookie($id,'like');
	easyvote_unsetcookie($id,'dislike');
	if(in_array($user_ip, $check_ips) ) {
		easyvote_setcookie($id,$type);
		$already_voted = true;
	}
//	foreach($check_ips as $check_ip) {
//		$check_ip = str_replace('[','',$check_ip);
//		$check_ip = str_replace(']','',$check_ip);
//		//a regex-search for the format "[192.168.2.1]:timestamp," necessary
//		
//		if($check_ip == $user_ip) {
//			easyvote_setcookie($id,$type);
//			$already_voted = true;
//		}
//	}
	return $already_voted;
}

function easyvote_remove_ip($id,$type,$user_ip) {
	if($type=='like') $meta_key = 'easyvote-like-userips';
	elseif($type=='dislike') $meta_key = 'easyvote-dislike-userips';
	$user_ips = get_post_meta($id,$meta_key,true);
	$user_ips = explode(',',$user_ips);
	$check_times = preg_replace_callback('/^\[(.*?)\][:]{0,1}([0-9]*?)$/', 'easyvote_get_timestamp', $user_ips );
	$user_ips = preg_replace('/^\[(.*?)\](.*?)$/', '\1', $user_ips );
	foreach($user_ips as $key=>$ip) {
		if(isset($new_user_ips)) $before = ',';
		else $before='';
		if(isset($ip) && $ip != $user_ip) {
			if(!empty($check_times[$key]) && $check_times[$key]!=$user_ips) $addtime=':'.$check_times[$key];
			else $addtime='';
			$new_user_ips .= $before.'['.$ip.']'.$addtime;
		}
		else {
			//Why is this if-clause here?
			if(isset($removed) && $removed==true) {
				if(!empty($check_times[$key])) $addtime=':'.$check_times[$key];
				else $addtime='';
				$new_user_ips .= $before.'['.$ip.']'.$addtime;
			}
			else $removed = true;
		}
	}
	update_post_meta($id, $meta_key, $new_user_ips);
}

function easyvote_get_timestamp($match) {
	if(empty($match[2])) $return='';
	else $return=$match[2];
	return $return;
}

/* *******************************************
add Dashboard-Widget: EPMW Easyvote-Stats
******************************************* */
function easyvote_dashboard_widget() {
	wp_add_dashboard_widget( 'easyvote_monthly_stats', 'EPMW Easyvote Monthly Stats', 'easyvote_monthly_stats' );
}
add_action('wp_dashboard_setup', 'easyvote_dashboard_widget');

function easyvote_monthly_stats() {
	$type='like';
	if($type=='like') $meta_key = 'easyvote-like-userips';
	elseif($type=='dislike') $meta_key = 'easyvote-dislike-userips';
	
	$sum_likes=0;
	$sum_dislikes=0;
	$sum=0;

	global $thismonth, $lastmonth;
	
	$thismonth=date('F');
	$lastmonth=date('F', strtotime("-1 month"));
	$voteevents=easyvote_voteevents($meta_key, $thismonth, $lastmonth);
	//print_r($voteevents);
	//echo 'uasort: <br/>';
	uasort($voteevents, 'easyvote_stats_sort_lastmonth');
	$i=0;
	$lastmonth_output='<ul>'."\n";
	foreach($voteevents as $key=>$topvision) {
		$lastmonth_output.='<li><a href="'.get_permalink($key).'">'.get_the_title($key).'</a> (ID: '.$key.'): '.count($topvision[$lastmonth]).' Votes (overall: '.get_post_meta($key,'easyvote-countvotes',true).')</li>'."\n";
		$i++;
		//if($i==31) break;
	}
	$lastmonth_output.='</ul>'."\n";
	uasort($voteevents, 'easyvote_stats_sort_thismonth');
	//print_r($voteevents);
	$i=0;
	$thismonth_output='<ul>'."\n";
	foreach($voteevents as $key=>$topvision) {
		$thismonth_output.='<li><a href="'.get_permalink($key).'">'.get_the_title($key).'</a> (ID: '.$key.'): '.count($topvision[$thismonth]).' Votes (overall: '.get_post_meta($key,'easyvote-countvotes',true).')</li>'."\n";
		$i++;
		//if($i==31) break;
	}
	$thismonth_output.='</ul>'."\n";
	echo '<p><strong>'.__('Last Month','easyvote').':</strong> '.$lastmonth.'</p>'."\n";
	echo $lastmonth_output;
	echo '<p><strong>'.__('This Month (until now)','easyvote').':</strong> '.$thismonth.'</p>'."\n";
	echo $thismonth_output;
	wp_reset_postdata();
	
}

function easyvote_stats_sort_lastmonth($a, $b) {
	global $lastmonth;
	return count($b[$lastmonth])-count($a[$lastmonth]);
}
function easyvote_stats_sort_thismonth($a, $b) {
	global $thismonth;
	return count($b[$thismonth])-count($a[$thismonth]);
}

function easyvote_voteevents($meta_key, $thismonth, $lastmonth) {
	/* 
	
	Lieber auf wp_date_query umstellen!
	
	*/
	$fourth_query = new WP_Query(array(
	    'post_type'=>'post',
	    'post_status'=>'publish',
	    'nopaging'=>true
	    ));
	$voteevents=array();
	while($fourth_query->have_posts()) { 
		//$voteevents[get_the_ID()]=array();
		$fourth_query->the_post();
		$user_ips = get_post_meta(get_the_ID(),$meta_key,true);
		$user_ips = explode(',',$user_ips);
		$check_times = preg_replace_callback('/^\[(.*?)\][:]{0,1}([0-9]*?)$/', 'easyvote_get_timestamp', $user_ips );
		$user_ips = preg_replace('/^\[(.*?)\](.*?)$/', '\1', $user_ips );
		foreach($check_times as $key=>$timestamp) {
			$month=date('F', (int)$timestamp);
			//echo $month.' - '.$thismonth.'<br/>';
			if(empty($month)) break;
			if($month==$lastmonth) {
				$voteevents[get_the_ID()][$lastmonth][$key]=$timestamp;
				//print_r($voteevents[get_the_ID()]);
			}
			elseif($month==$thismonth) {
				$voteevents[get_the_ID()][$thismonth][$key]=$timestamp;
				//print_r($voteevents[get_the_ID()]);
			}
		}
	}
	return $voteevents;
}





function easyvote_script() {
  echo '<link rel="stylesheet" type="text/css" href="'.EASYVOTE_PLUGIN_URL.'/easyvote.css" />' . "\n";
}
add_action('wp_head', 'easyvote_script');
?>