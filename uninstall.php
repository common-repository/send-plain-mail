<?php
    if (!defined('WP_UNINSTALL_PLUGIN')) {
		exit();
	}
	
	// Delete the settings options
		delete_option('spm_from_name');
		delete_option('spm_from_email');
?>