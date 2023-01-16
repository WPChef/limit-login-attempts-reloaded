<?php

if( empty( $_POST['log'] ) ) return;

$username = trim( $_POST['log'] );

if( !empty( $acl ) ) {
	$acl = json_decode( $acl, JSON_FORCE_OBJECT );

	if( in_array( $username, $acl['blacklist_usernames'] ) ) {
		exit('Blocked!');
	}

} else if ( !empty( $key ) ) {
	var_dump($key);
}