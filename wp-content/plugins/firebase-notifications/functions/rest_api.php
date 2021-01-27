<?php
/**
 * Get messages for API. Contains only content that is intended for the public:
 * message, title, timestamp
 *
 * @param string $to filter by topic
 * @param integer $id message id
 * @return array
 */
function fcm_rest_api_messages ( $language, $to = Null, $id = Null ) {
    $fcmdb = New FirebaseNotificationsDatabase();
    $newer_than = date('Y-m-d G:i:s', time() - ( 4 * 7 * 24 * 3600 ));
    if ( $id === Null ) {
        $messages = $fcmdb->messages_by_language( $lang = $language, $amount = 0, $timestamp = $newer_than );
    } else {
        $messages = $fcmdb->get_messages( array( 'id' => $id ) );
    }
    $return = array();
    $n = 0;
    foreach ( $messages as $message ) {
      if ( $message['answer'] != Null && ( $to === Null || $message['request']['to'] == "/topics/" . $to ) ) {
            $return[$n]['id'] = $message['id'];
            $date = new DateTime( $message['timestamp'] );
            $timezone = new DateTimeZone( get_option( 'timezone_string' ) );
            $date = date_timezone_set( $date, $timezone );
            $return[$n]['title'] = $message['request']['notification']['title'];
            $return[$n]['message'] = $message['request']['notification']['body'];
            $n++;
        }
    }
    return $return;
}
