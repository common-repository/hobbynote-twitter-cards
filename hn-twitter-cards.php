<?php
/*
Plugin Name: Hobbynote Twitter Cards
Plugin URI: http://www.hobbynote.com
Description: Aider les utilisateurs à implémenter et personnaliser les Twitter Cards facilement.
Author: Hobbynote
Author URI: http://www.hobbynote.com
Version: 1.0
License: GPL2++
*/

// Vérification du dossier d'installation Wordpress
defined('ABSPATH') or die('Impossible de charger le dossier wordpress');

// Recherche de la version du plugin
function hn_tc_plugin_get_version() {
	if (!function_exists('get_plugins')) require_once (ABSPATH . 'wp-admin/includes/plugin.php');
	$plugin_folder = get_plugins('/' . plugin_basename(dirname(__FILE__)));
	$plugin_file = basename((__FILE__));
	return $plugin_folder[$plugin_file]['Version'];
}

// Activation du plugin et initialisation des valeurs par défaut
register_activation_hook(__FILE__, 'hn_tc_init');

// Fonction d'initialisation des options par défaut
function hn_tc_init() {
	$opts = get_option('hn_tc');
	if (!is_array($opts)) update_option('hn_tc', hn_tc_get_default_options());
}

// Désinstallation du plugin 
register_uninstall_hook(__FILE__, 'hn_tc_uninstall');

// Fonction de désinstallation du plugin
function hn_tc_uninstall() {
	delete_option('hn_tc');
}

// Fonction de suppression des @ dans les champs
function hn_tc_remove_at($at) {
	$noat = str_replace('@', '', $at);
	return $noat;
}

// Fonction de suppression des retours à la ligne 
function hn_tc_remove_lb($lb) {
	$output = str_replace(array(
		"\r\n",
		"\r"
	) , "\n", $lb);
	$lines = explode("\n", $output);
	$nolb = array();
	foreach($lines as $key => $line) {
		if (!empty($line)) $nolb[] = trim($line);
	}
	return implode($nolb);
}

// Utilisation de la fonction has_shortcode Wordpress 3.6+ - Si version inférieure, utilisation de fonctions php 
function hn_tc_has_shortcode($content, $tag) {
	if (function_exists('has_shortcode')) { 
		return has_shortcode($content, $tag);
	} else {
		global $shortcode_tags;
		return array_key_exists($tag, $shortcode_tags);
		preg_match_all('/' . get_shortcode_regex() . '/s', $content, $matches, PREG_SET_ORDER);
		if (empty($matches)) return false;
		foreach($matches as $shortcode) {
			if ($tag === $shortcode[2]) return true;
		}
	}
	return false;
}

// Initialisation du plugin
add_action('init', 'hn_tc_initialize');

// Fonction d'initialisation du plugin
function hn_tc_initialize() {
	$opts = hn_tc_get_options();
	if ($opts['twitterCardCrop'] == 'yes') {
		$crop = true;
	} else {
		$crop = false;
	}
	if (function_exists('add_theme_support')) add_theme_support('post-thumbnails');
	add_image_size('hntc-small-thumb', 280, 150, $crop);
	add_image_size('hntc-max-web-thumb', 435, 375, $crop);
	add_image_size('hntc-max-mobile-non-retina-thumb', 280, 375, $crop);
	add_image_size('hntc-max-mobile-retina-thumb', 560, 750, $crop);
}

// Fontion de gestion de la taille de l'image
function hn_tc_thumbnail_sizes() {
	$opts = hn_tc_get_options();
	global $post;
	$twitterCardCancel = get_post_meta($post->ID, 'twitterCardCancel', true);
	if ('' != ($thumbnail_size = get_post_meta($post->ID, 'cardImgSize', true)) && $twitterCardCancel != 'yes') {
		$size = $thumbnail_size;
	} else {
		$size = $opts['twitterCardImgSize'];
	}
	switch ($size) {
	case 'small':
		$twitterCardImgSize = 'hntc-small-thumb';
		break;

	case 'web':
		$twitterCardImgSize = 'hntc-max-web-thumb';
		break;

	case 'mobile-non-retina':
		$twitterCardImgSize = 'hntc-max-mobile-non-retina-thumb';
		break;

	case 'mobile-retina':
		$twitterCardImgSize = 'hntc-max-mobile-retina-thumb';
		break;

	default:
		$twitterCardImgSize = 'hntc-small-thumb';
		break;
	}
	return $twitterCardImgSize;
}

// Fontion de récupération de la taille de l'image
function hn_tc_get_post_thumbnail_size() {
	global $post;
	$args = array(
		'post_type' => 'attachment',
		'post_mime_type' => array(
			'image/png',
			'image/jpeg',
			'image/gif'
		) ,
		'numberposts' => - 1,
		'post_status' => null,
		'post_parent' => $post->ID
	);
	$attachments = get_posts($args);
	foreach($attachments as $attachment) {
		$math = filesize(get_attached_file($attachment->ID)) / 1000000;
		return $math; 
	}
}

// Fonction de sauvegarde des variables POST
function hn_tc_save_postmeta($post_id, $meta) {
	$old = get_post_meta($post_id, $meta, true);
	$new = $_POST[$meta];
	if ($new && $new != $old) {
		update_post_meta($post_id, $meta, $new);
	}
	elseif ('' == $new && $old) {
		delete_post_meta($post_id, $meta, $old);
	}
}

// Définition des options
$opts = hn_tc_get_options();

// Fonction de récupération des extraits
if (!function_exists('get_excerpt_by_id')) {
	function get_excerpt_by_id($post_id) {
		$the_post = get_post($post_id);
		$the_excerpt = $the_post->post_content; 
		$excerpt_length = hn_tc_get_options();
		$excerpt_length = $excerpt_length['twitterExcerptLength'];
		$the_excerpt = strip_tags(strip_shortcodes($the_excerpt)); 
		$words = explode(' ', $the_excerpt, $excerpt_length + 1);
		if (count($words) > $excerpt_length):
			array_pop($words);
			array_push($words, '…');
			$the_excerpt = implode(' ', $words);
		endif;
		return esc_attr($the_excerpt);
	}
}

// Fonction d'ajout des meta tags dans la balise <head>
if (!function_exists('_hn_tc_markup_home')){
	function _hn_tc_markup_home() {
		$opts = hn_tc_get_options();
		$output  = '<meta name="twitter:card" content="' . $opts['twitterCardType'] . '"/>' . "\n";
		$output .= '<meta name="twitter:site" content="@' . $opts['twitterSite'] . '"/>' . "\n";
		$output .= '<meta name="twitter:title" content="' . $opts['twitterPostPageTitle'] . '"/>' . "\n";
		$output .= '<meta name="twitter:description" content="' . $opts['twitterPostPageDesc'] . '"/>' . "\n";
		$output .= '<meta name="twitter:image" content="' . $opts['twitterImage'] . '"/>' . "\n";
		return apply_filters('hntc_markup_home', $output); 	
	}
}

// Fonction d'application des valeurs entrées dans les pages de configuration
if (!function_exists('_hn_tc_markup')) {
	function _hn_tc_markup() {
		global $post;
		$opts = hn_tc_get_options();
		$cardType = get_post_meta($post->ID, 'twitterCardType', true);
		$cardPhotoWidth	= get_post_meta($post->ID, 'cardPhotoWidth', true);
		$cardPhotoHeight = get_post_meta($post->ID, 'cardPhotoHeight', true);
		$cardImage = get_post_meta($post->ID, 'cardImage', true);
		$cardImgSize = get_post_meta($post->ID, 'cardImgSize', true);
		$twitterCardCancel = get_post_meta($post->ID, 'twitterCardCancel', true);
		$regex = '~(https://|www.)(.+?)~';
		$cardTitleKey = $opts['twitterCardTitle'];
		$cardDescKey = $opts['twitterCardDesc'];
		$cardUsernameKey = $opts['twitterUsernameKey'];
		$tctitle = get_post_meta($post->ID, $cardTitleKey, true);
		$tcdesc = get_post_meta($post->ID, $cardDescKey, true);
		$username = get_user_meta(get_current_user_id() , $cardUsernameKey, true);
		if (class_exists('WPSEO_Frontend')) { 
			$object = new WPSEO_Frontend();
			if ($opts['twitterCardSEOTitle'] == 'yes' && $object->title(false)) {
				$cardTitle = $object->title(false);
			} else {
				$cardTitle = the_title_attribute(array(
					'echo' => false
				));
			}
			if ($opts['twitterCardSEODesc'] == 'yes' && $object->metadesc(false)) {
				$cardDescription = $object->metadesc(false);
			} else {
				$cardDescription = apply_filters('hn_tc_get_excerpt', get_excerpt_by_id($post->ID) );
			}
		} elseif (class_exists('All_in_One_SEO_Pack')) {
			global $post;
			$post_id = $post;
			if (is_object($post_id)) $post_id = $post_id->ID;
			if ($opts['twitterCardSEOTitle'] == 'yes' && get_post_meta(get_the_ID() , '_aioseop_title', true)) {
				$cardTitle = htmlspecialchars(stripcslashes(get_post_meta($post_id, '_aioseop_title', true)));
			} else {
				$cardTitle = the_title_attribute(array(
					'echo' => false
				));
			}
			if ($opts['twitterCardSEODesc'] == 'yes' && get_post_meta(get_the_ID() , '_aioseop_description', true)) {
				$cardDescription = htmlspecialchars(stripcslashes(get_post_meta($post_id, '_aioseop_description', true)));
			} else {
				$cardDescription = apply_filters('hn_tc_get_excerpt', get_excerpt_by_id($post->ID) );
			}
		} elseif ($tctitle && $tcdesc && $cardTitleKey != '' && $cardDescKey != '') {
			$cardTitle = $tctitle;
			$cardDescription = $tcdesc;
		} else { 
			$cardTitle = the_title_attribute(array(
				'echo' => false
			));
			$cardDescription = apply_filters('hn_tc_get_excerpt', get_excerpt_by_id($post->ID) );
		}
		$output = '<meta name="twitter:card" content="' . apply_filters('hn_tc_card_type', $opts['twitterCardType'] ). '"/>' . "\n";
		$output .= '<meta name="twitter:site" content="@' . $opts['twitterSite'] . '"/>' . "\n";
		$output .= '<meta name="twitter:title" content="' . $cardTitle . '"/>' . "\n"; 
		$output .= '<meta name="twitter:description" content="' . hn_tc_remove_lb($cardDescription) . '"/>' . "\n";
		if (get_the_post_thumbnail($post->ID) != '') {
			if ($cardImage != '' && $twitterCardCancel != 'yes') { 
				$output .= '<meta name="twitter:image" content="' .  apply_filters( 'hn_tc_image_source', $cardImage ). '"/>' . "\n";
			} else {
				$image_attributes = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID) , hn_tc_thumbnail_sizes());
				$output .= '<meta name="twitter:image" content="' . apply_filters( 'hn_tc_image_source', $image_attributes[0] ) . '"/>' . "\n";
			}
		} elseif (get_the_post_thumbnail($post->ID) == '' && $cardImage != '' && $twitterCardCancel != 'yes') {
			$output .=  '<meta name="twitter:image" content="' . apply_filters( 'hn_tc_image_source', $cardImage ) . '"/>' . "\n";
		} elseif ( 'attachment' == get_post_type() ) {
			$output .= '<meta name="twitter:image" content="' . apply_filters( 'hn_tc_image_source', wp_get_attachment_url( $post->ID ) ) . '"/>' . "\n";
		} else { 
			$output .= '<meta name="twitter:image" content="' . apply_filters( 'hn_tc_image_source', $opts['twitterImage'] ). '"/>' . "\n";
		}
		if ($opts['twitterCardType'] == 'photo' || $cardType == 'photo'  ) {
			if ( $cardPhotoWidth != '' && $cardPhotoHeight != '' && $twitterCardCancel != 'yes' ) {
				$output .= '<meta name="twitter:image:width" content="' . $cardPhotoWidth . '"/>' . "\n";
				$output .= '<meta name="twitter:image:height" content="' . $cardPhotoHeight . '"/>' . "\n";
			} elseif ($opts['twitterCardType'] == 'photo' && $twitterCardCancel != 'yes' && $opts['twitterCardMetabox'] != 'yes') {
				$output .= '<meta name="twitter:image:width" content="' . $opts['twitterImageWidth'] . '"/>' . "\n";
				$output .= '<meta name="twitter:image:height" content="' . $opts['twitterImageHeight'] . '"/>' . "\n";
			}
		}
		return apply_filters('hntc_markup', $output); 
	}
}	

// Ajout des balises meta dans le blog Wordpress
add_action('wp_head', '_hn_tc_add_markup', PHP_INT_MAX); 

// Fonction d'ajout de commentaires avant et après les balises meta ajoutées
function _hn_tc_add_markup() {
    $begin = "\n" . '<!-- Hobbynote Twitter Cards ' . hn_tc_plugin_get_version() . ' -->' . "\n";
	$end   = '<!-- /Hobbynote Twitter Cards -->' . "\n\n";
	if ( is_home() || is_front_page() )  {
		echo $begin;
		echo _hn_tc_markup_home();
		echo $end;
	}
	if( is_singular() && !is_front_page() && !is_home() && !is_404() && !is_tag() ) {
		echo $begin;
		echo _hn_tc_markup();
		echo $end;
	}		
}

// Ajout des liens de configuration du plugin
add_filter('plugin_action_links_' . plugin_basename(__FILE__) , 'hn_tc_settings_action_links', 10, 2);

// Fonction d'ajout des liens de configuration du plugin
function hn_tc_settings_action_links($links, $file) {
	$settings_link = '<a href="' . admin_url('admin.php?page=hn_tc_general') . '">' . __("Settings") . '</a>';
	array_unshift($links, $settings_link);
	return $links;
}

// Ajout des menus et des options du plugin
add_action('admin_menu', 'hn_tc_add_options');

// Fonction d'ajout des menus et des options du plugin
function hn_tc_add_options() {
	$plugin_menu = add_menu_page('Hobbynote Twitter Cards Options', 'Twitter Cards', 'manage_options', 'hn_tc_general', 'hn_tc_general_page', plugins_url('admin/img/twitter.png', __FILE__));
	$general_page = add_submenu_page( 'hn_tc_general', __( 'Général', 'hn-tc' ), __( 'Général', 'hn-tc' ) , 'manage_options', 'hn_tc_general', 'hn_tc_general' );
	$seo_page = add_submenu_page( 'hn_tc_general', __( 'SEO', 'hn-tc' ), __( 'SEO', 'hn-tc' ) , 'manage_options', 'hn_tc_seo', 'hn_tc_seo_page' );
	$images_page = add_submenu_page( 'hn_tc_general', __( 'Images', 'hn-tc' ), __( 'Images', 'hn-tc' ) , 'manage_options', 'hn_tc_images', 'hn_tc_images_page' );
	$homepage_page = add_submenu_page( 'hn_tc_general', __( 'Homepage', 'hn-tc' ), __( 'Homepage', 'hn-tc' ) , 'manage_options', 'hn_tc_homepage', 'hn_tc_homepage_page' );
	$documentation_link = add_submenu_page( 'hn_tc_general', __( 'Documentation', 'hn-tc' ), __( 'Documentation', 'hn-tc' ) , 'manage_options', 'javascript:void((function(){window.open("https://dev.twitter.com/docs/cards/")})())', '' );
	$validation_link = add_submenu_page( 'hn_tc_general', __( 'Validation', 'hn-tc' ), __( 'Validation', 'hn-tc' ) , 'manage_options', 'javascript:void((function(){window.open("https://dev.twitter.com/docs/cards/validation/validator")})())', '' );
	$debuger_link = add_submenu_page( 'hn_tc_general', __( 'Debuger', 'hn-tc' ), __( 'Debuger', 'hn-tc' ) , 'manage_options', 'javascript:void((function(){window.open("https://www.hobbynote.com/smo-analyzer/")})())', '' );
	register_setting('hn-tc', 'hn_tc', 'hn_tc_sanitize');
	add_action('load-' . $general_page, 'hn_tc_load_admin_scripts');
	add_action('load-' . $seo_page, 'hn_tc_load_admin_scripts');
	add_action('load-' . $images_page, 'hn_tc_load_admin_scripts');
	add_action('load-' . $homepage_page, 'hn_tc_load_admin_scripts');
}

// Fonction chargement des scripts admin
function hn_tc_load_admin_scripts() {
	add_action('admin_enqueue_scripts', 'hn_tc_admin_script');
}

// Fontion d'ajout de la feuille de style et du script javascript du plugin
function hn_tc_admin_script() {
	wp_enqueue_style('hn-tc-admin-style', plugins_url('admin/css/hn-tc-admin.css', __FILE__));
	wp_enqueue_script('hn-tc-admin-script', plugins_url('admin/js/hn-tc-admin.js', __FILE__) , array(
		'jquery'
	) , '1.0', true);
	wp_localize_script('hn-tc-admin-script', 'hntcObject', array(
		'ajaxurl' => esc_url(admin_url('/admin-ajax.php')) ,
		'_tc_ajax_saving_nonce' => wp_create_nonce('tc-ajax-saving-nonce')
	));
}

// Ajout de la fonctionnalité de sauvegarde des valeurs
add_action('wp_ajax_hn-tc-ajax-saving', 'hn_tc_ajax_saving_process');

// Fonction de sauvegarde des valeurs
function hn_tc_ajax_saving_process() {
	if (!isset($_POST['_tc_ajax_saving_nonce']) || !wp_verify_nonce($_POST['_tc_ajax_saving_nonce'], 'tc-ajax-saving-nonce')) die('No no, no no no no, there\'s a limit !');
	if (current_user_can('manage_options')) {
		$response = __('Options have been saved.', 'hn-tc');
		echo $response;
	} else {
		echo __('No way :/', 'hn-tc');
	}
	exit;
}

// Ajout de l'affichage de l'erreur SEO Yoast (Twitter Card activée sur l'autre plugin)
add_action('admin_notices', 'hn_tc_admin_notice');

// Fonction d'affichage de l'erreur
if (!function_exists('hn_tc_admin_notice')) {
	function hn_tc_admin_notice() {
		global $current_user;
		$user_id = $current_user->ID;
		if (!get_user_meta($user_id, 'hn_tc_ignore_notice') && current_user_can('install_plugins') && class_exists('WPSEO_Frontend')) {
			echo '<div class="error"><p>';
			printf(__('WordPress SEO by Yoast est activé, veuillez décocher l\'option Twitter Card dans ce plugin pour ne pas créer de doublon | <a href="%1$s">Cacher l\'erreur</a>') , '?hn_tc_ignore_this=0', 'hn-tc');
			echo "</p></div>";
		}
	}
}

// Ajout de la fonctionnalité de cacher l'erreur
add_action('admin_init', 'hn_tc_ignore_this');

// Fonction pour cacher l'erreur
if (!function_exists('hn_tc_ignore_this')) {
	function hn_tc_ignore_this() {
		global $current_user;
		$user_id = $current_user->ID;
		if (isset($_GET['hn_tc_ignore_this']) && '0' == $_GET['hn_tc_ignore_this']) {
			add_user_meta($user_id, 'hn_tc_ignore_notice', 'true', true);
		}
	}
}

// Fonction de création de la page "Général"
function hn_tc_general_page() {
	$save_submit = '<div class="form-status"></div>';
	$save_submit.= '<div class="form-loading hide" style="background-image:url(' . plugins_url('admin/img/loading.gif', __FILE__) . ')"><span class="text-loading">' . __('SAVING...', 'hn-tc') . '</span></div>';
	$save_submit.= '<input type="submit" name="hn_tc_submit" class="submit" value="' . __('Enregistrer', 'hn-tc') . '"  />';
	$opts = hn_tc_get_options();
	$hn_tw_logo = plugins_url('admin/img/hn-twitter-cards.jpg', __FILE__);
?>
	<div class="hn-tc" id="pluginwrapper">
		<div class="blocks head-block">
			<aside class="header">
				<div class="box">
					<img id="hn_tw_logo" src="<?php echo $hn_tw_logo ?>" />
					<p class="plugin-desc"><?php _e('Les Twitter Cards permettent d\'enrichir et de mettre en valeur votre contenu lorsqu\'il est partagé sur Twitter. <br /> Cela permet d\'augmenter le traffic vers votre site ainsi que le nombre de partage de votre contenu et donc améliorer votre référencement.', 'hn-tc'); ?></p>
				</div>    
				<div class="notification hide"></div>
			</aside>
		</div>
		<div class="blocks body-block">        
			<form id="hn-tc-form" method="POST" action="options.php"><?php settings_fields('hn-tc'); ?>
				<section class="postbox" id="tab">                         
					<h1 class="hndle"><?php _e('Général', 'hn-tc'); ?></h1>
					<p>
						<label class="labeltext" for="twitterCardType"><?php _e('Choisissez votre type de card', 'hn-tc'); ?> :</label>
						<select class="styled-select"  id="twitterCardType" name="hn_tc[twitterCardType]">
							<option value="summary" <?php echo $opts['twitterCardType'] == 'summary' ? 'selected="selected"' : ''; ?>><?php _e('summary', 'hn-tc'); ?></option>
							<option value="summary_large_image" <?php echo $opts['twitterCardType'] == 'summary_large_image' ? 'selected="selected"' : ''; ?>><?php _e('summary_large_image', 'hn-tc'); ?></option>
							<option value="photo" <?php echo $opts['twitterCardType'] == 'photo' ? 'selected="selected"' : ''; ?>><?php _e('photo', 'hn-tc'); ?></option>
						</select>
					</p>
					<p>
						<label class="labeltext" for="twitterSite"><?php _e('Entrez le compte Twitter associé au site web', 'hn-tc'); ?> :</label>
						<input id="twitterSite" type="text" name="hn_tc[twitterSite]" class="regular-text" value="<?php echo hn_tc_remove_at($opts['twitterSite']); ?>" />
					</p>
					<p>
						<label class="labeltext" for="twitterExcerptLength"><?php _e('Fixer le nombre de mots à prendre en compte par le plugin dans le champ description', 'hn-tc'); ?> :</label>
						<input id="twitterExcerptLength" type="number" min="10" max="200" name="hn_tc[twitterExcerptLength]" class="small-number" value="<?php echo $opts['twitterExcerptLength']; ?>" />
					</p>
					<?php echo $save_submit; ?>    
				</section>
			</form>
		</div>
	</div>
<?php 
	}
	
// Fonction de création de la page "SEO"
function hn_tc_seo_page() {
	$save_submit = '<div class="form-status"></div>';
	$save_submit.= '<div class="form-loading hide" style="background-image:url(' . plugins_url('admin/img/loading.gif', __FILE__) . ')"><span class="text-loading">' . __('SAVING...', 'hn-tc') . '</span></div>';
	$save_submit.= '<input type="submit" name="hn_tc_submit" class="submit" value="' . __('Enregistrer', 'hn-tc') . '"  />';
	$opts = hn_tc_get_options();
	$hn_tw_logo = plugins_url('admin/img/hn-twitter-cards.jpg', __FILE__);
?>
	<div class="hn-tc" id="pluginwrapper">
		<div class="blocks head-block">
			<aside class="header">
				<div class="box">
					<img id="hn_tw_logo" src="<?php echo $hn_tw_logo ?>" />
					<p class="plugin-desc"><?php _e('Les Twitter Cards permettent d\'enrichir et de mettre en valeur votre contenu lorsqu\'il est partagé sur Twitter. <br /> Cela permet d\'augmenter le traffic vers votre site ainsi que le nombre de partage de votre contenu et donc améliorer votre référencement.', 'hn-tc'); ?></p>
				</div>    
				<div class="notification hide"></div>
			</aside>
		</div>
		<div class="blocks body-block">        
			<form id="hn-tc-form" method="POST" action="options.php"><?php settings_fields('hn-tc'); ?>
				<section class="postbox" id="tab">  
					<h1 class="hndle"><?php _e('SEO', 'hn-tc'); ?></h1>      
					<h2><?php _e('Récupérer les données depuis des plugins SEO', 'hn-tc'); ?></h2>                                
					<p>
						<label class="labeltext" for="twitterCardSEOTitle"><?php _e('Utiliser les données entrées avec le plugin WPSEO by Yoast ou ALL In One SEO pour le champ titre (<strong>oui par défaut</strong>)', 'hn-tc'); ?> :</label>
						<select class="styled-select"  id="twitterCardSEOTitle" name="hn_tc[twitterCardSEOTitle]">
							<option value="yes" <?php echo $opts['twitterCardSEOTitle'] == 'yes' ? 'selected="selected"' : ''; ?>><?php _e('Oui', 'hn-tc'); ?></option>
							<option value="no" <?php echo $opts['twitterCardSEOTitle'] == 'no' ? 'selected="selected"' : ''; ?>><?php _e('Non', 'hn-tc'); ?></option>
						</select>
					</p> 
					<p>
						<label class="labeltext" for="twitterCardSEODesc"><?php _e('Utiliser les données entrées avec le plugin WPSEO by Yoast ou ALL In One SEO pour le champ description (<strong>oui par défaut</strong>)', 'hn-tc'); ?> :</label>
						<select class="styled-select"  id="twitterCardSEODesc" name="hn_tc[twitterCardSEODesc]">
							<option value="yes" <?php echo $opts['twitterCardSEODesc'] == 'yes' ? 'selected="selected"' : ''; ?>><?php _e('Oui', 'hn-tc'); ?></option>
							<option value="no" <?php echo $opts['twitterCardSEODesc'] == 'no' ? 'selected="selected"' : ''; ?>><?php _e('Non', 'hn-tc'); ?></option>
						</select>
					</p> 
					<h2><?php _e('Si vous souhaitez utiliser vos propres champs', 'hn-tc'); ?></h2>
					<p>
						<label class="labeltext" for="twitterCardTitle"><?php _e('Entrez un titre pour la card', 'hn-tc'); ?> :</label>
						<input id="twitterCardTitle" type="text" name="hn_tc[twitterCardTitle]" class="regular-text" value="<?php echo $opts['twitterCardTitle']; ?>" />
					</p>
					<p>
						<label class="labeltext" for="twitterCardDesc"><?php _e('Entrez une description pour la card', 'hn-tc'); ?> :</label>
						<input id="twitterCardDesc" type="text" name="hn_tc[twitterCardDesc]" class="regular-text" value="<?php echo $opts['twitterCardDesc']; ?>" />
					</p>
					<?php echo $save_submit; ?>
				</section>
			</form>
		</div>
	</div>
<?php 
}

// Fonction de création de la page "Images"
function hn_tc_images_page() {
	$save_submit = '<div class="form-status"></div>';
	$save_submit.= '<div class="form-loading hide" style="background-image:url(' . plugins_url('admin/img/loading.gif', __FILE__) . ')"><span class="text-loading">' . __('SAVING...', 'hn-tc') . '</span></div>';
	$save_submit.= '<input type="submit" name="hn_tc_submit" class="submit" value="' . __('Enregistrer', 'hn-tc') . '"  />';
	$opts = hn_tc_get_options();
	$hn_tw_logo = plugins_url('admin/img/hn-twitter-cards.jpg', __FILE__);
?>
	<div class="hn-tc" id="pluginwrapper">
		<div class="blocks head-block">
			<aside class="header">
				<div class="box">
					<img id="hn_tw_logo" src="<?php echo $hn_tw_logo ?>" />
					<p class="plugin-desc"><?php _e('Les Twitter Cards permettent d\'enrichir et de mettre en valeur votre contenu lorsqu\'il est partagé sur Twitter. <br /> Cela permet d\'augmenter le traffic vers votre site ainsi que le nombre de partage de votre contenu et donc améliorer votre référencement.', 'hn-tc'); ?></p>
				</div>    
				<div class="notification hide"></div>
			</aside>
		</div>
		<div class="blocks body-block">        
			<form id="hn-tc-form" method="POST" action="options.php"><?php settings_fields('hn-tc'); ?>
				<section class="postbox" id="tab">
					<h1 class="hndle"><?php _e('Images', 'hn-tc'); ?></h1>
					<p>
						<label class="labeltext" for="twitterCardImgSize"><?php _e('Régler la taille de l\'image', 'hn-tc'); ?> :</label>
						<select class="styled-select"  id="twitterCardImgSize" name="hn_tc[twitterCardImgSize]">
							<option value="mobile-non-retina" <?php echo $opts['twitterCardImgSize'] == 'mobile-non-retina' ? 'selected="selected"' : ''; ?>><?php _e('Max mobile non retina (largeur: 280px - hauteur: 375px)', 'hn-tc'); ?></option>
							<option value="mobile-retina" <?php echo $opts['twitterCardImgSize'] == 'mobile-retina' ? 'selected="selected"' : ''; ?>><?php _e('Max mobile retina (largeur: 560px - hauteur: 750px)', 'hn-tc'); ?></option>
							<option value="web" <?php echo $opts['twitterCardImgSize'] == 'web' ? 'selected="selected"' : ''; ?>><?php _e('Max taille web (largeur: 435px - hauteur: 375px)', 'hn-tc'); ?></option>
							<option value="small" <?php echo $opts['twitterCardImgSize'] == 'small' ? 'selected="selected"' : ''; ?>><?php _e('Petit (largeur: 280px - hauteur: 150px)', 'hn-tc'); ?></option>
						</select>
					</p>
					<p>
						<label class="labeltext" for="twitterCardCrop"><?php _e('Voulez-vous forcer le redimensionnement de l\'image pour la card ?', 'hn-tc'); ?> :</label>
						<select class="styled-select"  id="twitterCardCrop" name="hn_tc[twitterCardCrop]">
							<option value="yes" <?php echo $opts['twitterCardCrop'] == 'yes' ? 'selected="selected"' : ''; ?>><?php _e('Oui', 'hn-tc');?></option>
							<option value="no" <?php echo $opts['twitterCardCrop'] == 'no' ? 'selected="selected"' : '';?>><?php _e('Non', 'hn-tc'); ?></option>    
						</select>
					</p>
					<p>
						<label class="labeltext" for="twitterImage"><?php _e('Entrez l\'URL pour l\'image de secours (image par défaut)', 'hn-tc'); ?> :</label>
						<input id="twitterImage" type="url" name="hn_tc[twitterImage]" class="regular-text" value="<?php echo $opts['twitterImage']; ?>" />
					</p>
					<p>
						<label class="labeltext" for="twitterImageWidth"><?php _e('Largeur de l\'image', 'hn-tc');?> :</label>
						<input id="twitterImageWidth" type="number" min="280" name="hn_tc[twitterImageWidth]" class="small-number" value="<?php echo $opts['twitterImageWidth'];?>" />
					</p>
					<p>
						<label class="labeltext" for="twitterImageHeight"><?php _e('Hauteur de l\'image', 'hn-tc'); ?> :</label>
						<input id="twitterImageHeight" type="number" min="150" name="hn_tc[twitterImageHeight]" class="small-number" value="<?php echo $opts['twitterImageHeight']; ?>" />
					</p>
					<?php echo $save_submit; ?>
				</section>
			</form>
		</div>
	</div>
<?php
}

// Fonction de création de la page "Homepage"
function hn_tc_homepage_page() {
	$save_submit = '<div class="form-status"></div>';
	$save_submit.= '<div class="form-loading hide" style="background-image:url(' . plugins_url('admin/img/loading.gif', __FILE__) . ')"><span class="text-loading">' . __('SAVING...', 'hn-tc') . '</span></div>';
	$save_submit.= '<input type="submit" name="hn_tc_submit" class="submit" value="' . __('Enregistrer', 'hn-tc') . '"  />';
	$opts = hn_tc_get_options();
	$hn_tw_logo = plugins_url('admin/img/hn-twitter-cards.jpg', __FILE__);
?>
	<div class="hn-tc" id="pluginwrapper">
		<div class="blocks head-block">
			<aside class="header">
				<div class="box">
					<img id="hn_tw_logo" src="<?php echo $hn_tw_logo ?>" />
					<p class="plugin-desc"><?php _e('Les Twitter Cards permettent d\'enrichir et de mettre en valeur votre contenu lorsqu\'il est partagé sur Twitter. <br /> Cela permet d\'augmenter le traffic vers votre site ainsi que le nombre de partage de votre contenu et donc améliorer votre référencement.', 'hn-tc'); ?></p>
				</div>    
				<div class="notification hide"></div>
			</aside>
		</div>
		<div class="blocks body-block">        
			<form id="hn-tc-form" method="POST" action="options.php"><?php settings_fields('hn-tc'); ?>
				<section class="postbox" id="tab">                     
				<h1 class="hndle"><?php _e('Homepage', 'hn-tc'); ?></h1>
				<p>
					<label class="labeltext" for="twitterPostPageTitle"><strong><?php _e('Entrez un titre pour la page d\'accueil :', 'hn-tc'); ?></strong></label><br />
					<input id="twitterPostPageTitle" type="text" name="hn_tc[twitterPostPageTitle]" class="regular-text" value="<?php echo $opts['twitterPostPageTitle']; ?>" />
				</p>
				<p>
					<label class="labeltext" for="twitterPostPageDesc"><strong><?php _e('Entrez une description pour la page d\'accueil (max: 200 caractères)', 'hn-tc'); ?></strong> :</label><br />
					<textarea id="twitterPostPageDesc" rows="4" cols="80" name="hn_tc[twitterPostPageDesc]" class="regular-text"><?php echo $opts['twitterPostPageDesc']; ?></textarea>
				</p>
				<?php echo $save_submit; ?>    
				</section>
			</form>
		</div>
	</div>
<?php
}

// Fonction de "rassemblement" des options
function hn_tc_sanitize($options) {
	return array_merge(hn_tc_get_options() , hn_tc_sanitize_options($options));
}

// Fonction de "nettoyage" des options
function hn_tc_sanitize_options($options) {
	$new = array();
	if (!is_array($options)) return $new;
	if (isset($options['twitterCardType'])) $new['twitterCardType'] = $options['twitterCardType'];
	if (isset($options['twitterSite'])) $new['twitterSite'] = esc_attr(strip_tags(hn_tc_remove_at($options['twitterSite'])));
	if (isset($options['twitterExcerptLength'])) $new['twitterExcerptLength'] = (int)$options['twitterExcerptLength'];
	if (isset($options['twitterImage'])) $new['twitterImage'] = esc_url($options['twitterImage']);
	if (isset($options['twitterImageWidth'])) $new['twitterImageWidth'] = (int)$options['twitterImageWidth'];
	if (isset($options['twitterImageHeight'])) $new['twitterImageHeight'] = (int)$options['twitterImageHeight'];
	if (isset($options['twitterCardMetabox'])) $new['twitterCardMetabox'] = $options['twitterCardMetabox'];
	if (isset($options['twitterPostPageTitle'])) $new['twitterPostPageTitle'] = esc_attr(strip_tags($options['twitterPostPageTitle']));
	if (isset($options['twitterPostPageDesc'])) $new['twitterPostPageDesc'] = esc_attr(strip_tags($options['twitterPostPageDesc']));
	if (isset($options['twitterCardSEOTitle'])) $new['twitterCardSEOTitle'] = $options['twitterCardSEOTitle'];
	if (isset($options['twitterCardSEODesc'])) $new['twitterCardSEODesc'] = $options['twitterCardSEODesc'];
	if (isset($options['twitterCardImgSize'])) $new['twitterCardImgSize'] = $options['twitterCardImgSize'];
	if (isset($options['twitterCardTitle'])) $new['twitterCardTitle'] = esc_attr(strip_tags($options['twitterCardTitle']));
	if (isset($options['twitterCardDesc'])) $new['twitterCardDesc'] = esc_attr(strip_tags($options['twitterCardDesc']));
	if (isset($options['twitterCardCrop'])) $new['twitterCardCrop'] = $options['twitterCardCrop'];
	if (isset($options['twitterUsernameKey'])) $new['twitterUsernameKey'] = esc_attr(strip_tags($options['twitterUsernameKey']));
	return $new;
}

// Fonction de retour des valeurs par défaut
function hn_tc_get_default_options() {
	return array(
		'twitterCardType' => 'summary',
		'twitterSite' => 'hobbynote',
		'twitterExcerptLength' => 35,
		'twitterImage' => '',
		'twitterImageWidth' => '280',
		'twitterImageHeight' => '150',
		'twitterCardMetabox' => 'no',
		'twitterPostPageTitle' => get_bloginfo('name') ,
		'twitterPostPageDesc' => __('Welcome to', 'hn-tc') . ' ' . get_bloginfo('name') . ' - ' . __('see blog posts', 'hn-tc') ,
		'twitterCardSEOTitle' => 'yes',
		'twitterCardSEODesc' => 'yes',
		'twitterCardImgSize' => 'small',
		'twitterCardTitle' => '',
		'twitterCardDesc' => '',
		'twitterCardCrop' => 'yes',
		'twitterUsernameKey' => 'hn_tc_twitter'
	);
}

// Fonction de récupération des options
function hn_tc_get_options() {
	$options = get_option('hn_tc');
	return array_merge(hn_tc_get_default_options() , hn_tc_sanitize_options($options));
}

?>