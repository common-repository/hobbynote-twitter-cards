<?php

// Si erreur - quitter
if( !defined( 'ABSPATH') && !defined('WP_UNINSTALL_PLUGIN') )
exit();

$keys = array(
			'twitterCardType', 
			'cardImage',
			'cardPhotoWidth',
			'cardPhotoHeight',
			'cardImgSize',
			'twitterCardCancel'
		);

// Suppression des clés enregistrées		
foreach($keys as $key)	{
global $wpdb;
	$wpdb->query( 
		$wpdb->prepare( 
			"
			 DELETE FROM $wpdb->postmeta
			 WHERE meta_key = %s
			",
			$key
			)
	);
}

?>