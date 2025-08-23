<?php
/**
 * On uninstall: remove options.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'snp_options' );

