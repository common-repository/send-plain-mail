<?php

	/*
		Plugin Name: Send Plain Mail
		Description: Easily send emails from your website
		Version: 1.0.3
		Author: Plain Plugins
		License: MIT
		Author URI: https://plainplugins.altervista.org/
	*/

	if (!defined('ABSPATH')) {
		exit; // Exit if accessed directly
	}




	class Send_Plain_Mail {
		
		// - Get the static instance variable
			private static $_instance = null;
		
		
		public static function Instantiate() {
			if (is_null(self::$_instance)) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}
		

		private function __construct() {
		
			// Side Menu - Main
				add_action('admin_menu', function() {
					$this->AdminMenu();
				});
				
			// Side Menu - Settings
				add_action('admin_menu', function() {
					$this->SettingsMenu();
				});
				
			// AJAX Search Users
				add_action('wp_ajax_spm_ajax_search_users', function() {
					$this->SearchWPUsers();
				});
				
		}
		
		
		// Side Menu

			private function AdminMenu() {
				add_menu_page('Send PlainMail', 'PlainMail', 'manage_options', 'plain-mail', function() {
					$this->AdminHTML();
				}, 'dashicons-email');
			}

			private function SettingsMenu() {
				
				// Output a settings menu item in the left menu (nested under the JS Error Log name)
				
				add_submenu_page(
					'plain-mail',
					'PlainMail Options',
					'PlainMail Options',
					'manage_options',
					'plain-mail-options',
					function() {
						$this->SettingsHTML();
					},
					1
				);
			}
			

		// Backend HTML

			private function AdminHTML() {
				
				if (!current_user_can('manage_options'))  {
					wp_die( __( 'You do not have sufficient permissions to access this page.'));
				}
				
				wp_enqueue_script('custom-js', plugin_dir_url(__FILE__).'/includes/selectize-js/selectize.min.js');
				wp_enqueue_style('custom-css', plugin_dir_url(__FILE__).'/includes/selectize-js/selectize.default.css');
				
				add_action('admin_footer', function() {
					$this->AdminJS();
				}); // Write our JS below here
				
				
				add_filter('admin_footer_text', function() {	// Add a footer that links to our website
					$this->AddAdminFooter();
				});


				$sent_email_html = '';
				$sent_email = $this->TrySendEmail();
				if ($sent_email) {
					$sent_email_html = '
						<div class="notice notice-success">
							<p><b>Success!</b> Sent Email.</p>
						</div>	
					';
				}
				
				echo '
					<div class="wrap">
						<form method="post">
							<input type="hidden" name="spm_send_email" value="1" />
							'.$sent_email_html.'
							
							<table class="form-table">
								<tbody>
									<tr>
										<td colspan="2">
											<label><b>To</b></label>
											<input type="text" id="to" name="to" placeholder="Recipients" style="width:100%;" />
										</td>
									</tr>
									
									<tr>
										<td colspan="2">
											<label><b>Subject</b></label>
											<input type="text" name="subject" placeholder="Subject" style="width:100%;">
										</td>
									</tr>

									<tr>
										<td colspan="2">
											<label><b>Email</b></label>
					';
					
					wp_editor('', 'message', $settings = array(
						'textarea_name' => 'message',
						'textarea_rows' => 8,
						'media_buttons' => false,
					));
					echo '
											<p class="description">Your email body.</p>
										</td>
									</tr>

									<tr>
										<td>	
											<button type="submit" class="button button-primary">Send Email</button>
										</td>
										<td>
											<div id="success_email_notice" style="display:none;" class="notice notice-success">
												<p><b>Success!</b> Sent email.</p>
											</div>							
										</td>
									</tr>

								</tbody>
							</table>
						</form>
					</div>
				';				
				echo '			
					<style>
						.selectize_option {
							padding: 8px;
						}
					</style>
				';
				
			}

			private function AdminJS() { 
				echo '
					<script type="text/javascript" >
					
						jQuery(document).ready(function($) {
							
							try {
								var REGEX_EMAIL = "([a-z0-9!#$%&\'*+/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&\'*+/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)";

								jQuery("#to").selectize({
									persist: false,
									maxItems: null,
									valueField: "email",
									labelField: "name",
									searchField: ["name", "email"],
									plugins: ["remove_button"],
									options: [
										//{email: "myname@gmail.com", name: "Test Name"},
									],
									render: {
										item: function(item, escape) {
											return "<div>" +
												(item.name ? "<span>" + escape(item.name) + "</span>" : "") +
												(item.email ? " <span class=\'description\'>&lt;" + escape(item.email) + "&gt;</span>" : "") +
											"</div>";
										},
										option: function(item, escape) {
											var label = item.name || item.email;
											var caption = item.name ? item.email : null;
											return "<div class=\'selectize_option\'>" +
												"<b>" + escape(label) + "</b>" +
												(caption ? "<div class=\'\description\'>&lt;" + escape(caption) + "&gt;</div>" : "") +
											"</div>";
										}
									},
									createFilter: function(input) {
										var match, regex;

										// email@address.com
										regex = new RegExp("^" + REGEX_EMAIL + "$", "i");
										match = input.match(regex);
										if (match) return !this.options.hasOwnProperty(match[0]);

										// name <email@address.com>
										regex = new RegExp("^([^<]*)\<" + REGEX_EMAIL + "\>$", "i");
										match = input.match(regex);
										if (match) return !this.options.hasOwnProperty(match[2]);

										return false;
									},
									create: function(input) {
										if ((new RegExp("^" + REGEX_EMAIL + "$", "i")).test(input)) {
											return {email: input};
										}
										var match = input.match(new RegExp("^([^<]*)\<" + REGEX_EMAIL + "\>$", "i"));
										if (match) {
											return {
												email : match[2],
												name  : $.trim(match[1])
											};
										}
										alert("Invalid email address.");
										return false;
									},
									load: function(query, callback) {
										if (!query.length) return callback();
										$.ajax({
											url: ajaxurl,
											type: "GET",
											data: {
												action: "spm_ajax_search_users",
												search: query,
											},
											error: function() {
												callback();
											},
											success: function(response) {
												callback(response.users);
											}
										});
									}
								});
							}
							catch (e) {
								console.log(e.message);
							}
						
						
						
						});
					</script>
				
				';
			}




		// AJAX Search Users

			private function SearchWPUsers() { 
				global $wpdb; // this is how you get access to the database
				
				
				
				$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
				$users = get_users(array(
					'search' => '*'.$search.'*', 
					'orderby' => 'user_nicename', 
					'order' => 'DESC', 
					'fields' => array('user_nicename', 'user_email'),
					
					'search_columns' => array(
						'user_login',
						'user_nicename',
						'user_email',
						'user_url',
					),
				));
				
				$formatted_users = array();
				foreach ($users as $user) {
					array_push($formatted_users, array(
						'name' => $user->user_nicename,
						'email' => $user->user_email,
					));
				}
				
				header('Content-type: application/json');
				echo json_encode(array(
					'users' => $formatted_users
				));
				
				wp_die(); // this is required to terminate immediately and return a proper response
			}


		// Try sending an email
		
			private function TrySendEmail() {
				$sent_email = false;
				
				if (isset($_POST['spm_send_email'])) {
					
				
					$to = isset($_POST['to']) ? sanitize_text_field($_POST['to']) : ''; 	// Comma separated list of email addresses
					$subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
					$message = isset($_POST['message']) ? wp_kses_post($_POST['message']) : '';
					
					// Sanitize all of the recipient email addresses before sending
						$recipients = array();
						$to_emails = explode(',', $to);
						foreach ($to_emails as $to_email) {
							$sanitized_email = sanitize_email($to_email);
							array_push($recipients, $sanitized_email);
						}
					
					$message = wpautop($message);	// The WordPress editor uses linebreaks instead of <p> or <br> tags
					
					// - Get the settings
						$spm_from_name = get_option('spm_from_name', get_bloginfo('name'));
						$spm_from_email = get_option('spm_from_email', get_bloginfo('admin_email'));
					
					$headers = array(
						'From: "' . $spm_from_name . '" <'.$spm_from_email.'>',
						'Reply-To: '.$spm_from_email,
					);
					
					add_filter('wp_mail_content_type', function() {
						return "text/html";
					});
					$result = wp_mail($recipients, $subject, $message, $headers);
					remove_filter('wp_mail_content_type', function() {
						return "text/html";
					});
				
					$sent_email = true;
				}
				
				return ($sent_email);
			}


		// Settings HTML
		
			private function SettingsHTML() {
				
				add_filter('admin_footer_text', function() {	// Add a footer that links to our website
					$this->AddAdminFooter();
				});
				
				$saved = '';
				if (isset($_POST['spm_save_settings'])) {
					if (isset($_POST['spm_from_name'])) {
						$spm_from_name = isset($_POST['spm_from_name']) ? sanitize_text_field($_POST['spm_from_name']) : '';
						update_option('spm_from_name', $spm_from_name);
					}
					
					if (isset($_POST['spm_from_email'])) {
						$spm_from_email = isset($_POST['spm_from_email']) ? sanitize_email($_POST['spm_from_email']) : '';
						update_option('spm_from_email', $spm_from_email);
					}
					
					$saved = '
						<div class="notice notice-success">
							<p><b>Success!</b> Saved Settings.</p>
						</div>	
					';
				}
				
				$spm_from_name = get_option('spm_from_name', get_bloginfo('name'));
				$spm_from_email = get_option('spm_from_email', get_bloginfo('admin_email'));
				
				// Escape the values
					$spm_from_name = esc_html($spm_from_name);
					$spm_from_email = esc_html($spm_from_email);
				
				echo '
					<div class="wrap">
						<form method="post">
						
							<input type="hidden" name="spm_save_settings" value="1" />
							'.$saved.'
							
							<table class="form-table">
								<tbody>
								
									<tr>
										<th scope="row"><label>From Name</label></th>
										<td><input name="spm_from_name" type="text" value="'.$spm_from_name.'" class="regular-text"></td>
									</tr>
									<tr>
										<th scope="row"><label>From Email</label></th>
										<td><input name="spm_from_email" type="text" value="'.$spm_from_email.'" class="regular-text"></td>
									</tr>

									<tr>
										<td>	
											<button type="submit" class="button button-primary">Save Settings</button>
										</td>
									</tr>
								
								</tbody>
							</table>	
						</form>
					</div>
				';
			}


		// Message to output in the WordPress admin footer
			private function AddAdminFooter() {
				echo 'Plain Plugins | Check out our website at <a href="https://plainplugins.altervista.org" target="_blank">plainplugins.altervista.org</a> for more plugins!';
			}


	}





	Send_Plain_Mail::Instantiate();	// Instantiate an instance of the class








