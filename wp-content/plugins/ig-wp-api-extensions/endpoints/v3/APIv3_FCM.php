<?php

class APIv3_FCM extends APIv3_Base_Abstract {

	const ROUTE = 'fcm';

	public function get_fcm() {
		if (class_exists('FirebaseNotificationsDatabase')) {
			if ( $_GET['channel'] === Null ) {
				$to = Null;
			} else {
				$to = str_replace('/','',get_blog_details(get_current_blog_id())->path) . '-' . ICL_LANGUAGE_CODE . '-' . $_GET['channel'];
			}
			if ( $_GET['id'] === Null ) {
				$id = Null;
			} else {
				$id = (int)$_GET['id'];
			}
			return fcm_rest_api_messages ( ICL_LANGUAGE_CODE, $to, $id );
		} else {
			// throw error if IntegreatSettingsPlugin is not activated
			return new WP_Error('settings_plugin_not_activated', 'The Plugin "Integreat Settings" is not activated for this location', ['status' => 501]);
		}
	}

}
