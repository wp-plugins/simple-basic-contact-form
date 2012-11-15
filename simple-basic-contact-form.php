<?php 
/*
	Plugin Name: Simple Basic Contact Form
	Plugin URI: http://perishablepress.com/simple-basic-contact-form/
	Description: Simple basic plug-n-play contact form for WordPress.
	Author: Jeff Starr
	Author URI: http://monzilla.biz/
	Version: 20121103
	License: GPL v2
	Usage: Visit the plugin's settings page for shortcodes, template tags, and more information.
	Tags: contact, form, contact form, email, mail, captcha
*/

// NO EDITING REQUIRED - PLEASE SET PREFERENCES IN THE WP ADMIN!

$scf_plugin  = __('Simple Basic Contact Form');
$scf_options = get_option('scf_options');
$scf_path    = plugin_basename(__FILE__); // 'simple-basic-contact-form/simple-basic-contact-form.php';
$scf_homeurl = 'http://perishablepress.com/simple-basic-contact-form/';
$scf_version = '20121103';

// require minimum version of WordPress
add_action('admin_init', 'scf_require_wp_version');
function scf_require_wp_version() {
	global $wp_version, $scf_path, $scf_plugin;
	if (version_compare($wp_version, '3.4', '<')) {
		if (is_plugin_active($scf_path)) {
			deactivate_plugins($scf_path);
			$msg =  '<strong>' . $scf_plugin . '</strong> ' . __('requires WordPress 3.4 or higher, and has been deactivated!') . '<br />';
			$msg .= __('Please return to the ') . '<a href="' . admin_url() . '">' . __('WordPress Admin area') . '</a> ' . __('to upgrade WordPress and try again.');
			wp_die($msg);
		}
	}
}

// set some strings
$scf_strings = array(
	'name' 	 => '<input name="scf_name" id="scf_name" type="text" size="33" maxlength="99" value="'. htmlentities($_POST['scf_name']) .'" placeholder="Your name" />', 
	'email'    => '<input name="scf_email" id="scf_email" type="text" size="33" maxlength="99" value="'. htmlentities($_POST['scf_email']) .'" placeholder="Your email" />', 
	'response' => '<input name="scf_response" id="scf_response" type="text" size="33" maxlength="99" value="'. htmlentities($_POST['scf_response']) .'" placeholder="' . $scf_options['scf_response'] . '" />',	
	'message'  => '<textarea name="scf_message" id="scf_message" cols="33" rows="7" placeholder="Your message">'. htmlentities($_POST['scf_message']) .'</textarea>', 
	'error'    => ''
	);

// check for bad stuff
function scf_malicious_input($input) {
	$maliciousness = false;
	$denied_inputs = array("\r", "\n", "mime-version", "content-type", "cc:", "to:");
	foreach($denied_inputs as $denied_input) {
		if(strpos(strtolower($input), strtolower($denied_input)) !== false) {
			$maliciousness = true;
			break;
		}
	}
	return $maliciousness;
}

// check for spam stuff
function scf_spam_question($input) {
	global $scf_options;
	$response = $scf_options['scf_response'];
	$response = stripslashes(trim($response));
	if (get_option('scf_casing') == true) {
		return (strtoupper($input) == strtoupper($response));
	} else {
		return ($input == $response);
	}
}

// collect ip address
function scf_get_ip_address() {
	if(isset($_SERVER)) {
		if(isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
			$ip_address = $_SERVER["HTTP_X_FORWARDED_FOR"];
		} elseif(isset($_SERVER["HTTP_CLIENT_IP"])) {
			$ip_address = $_SERVER["HTTP_CLIENT_IP"];
		} else {
			$ip_address = $_SERVER["REMOTE_ADDR"];
		}
	} else {
		if(getenv('HTTP_X_FORWARDED_FOR')) {
			$ip_address = getenv('HTTP_X_FORWARDED_FOR');
		} elseif(getenv('HTTP_CLIENT_IP')) {
			$ip_address = getenv('HTTP_CLIENT_IP');
		} else {
			$ip_address = getenv('REMOTE_ADDR');
		}
	}
	return $ip_address;
}

// filter input
function scf_input_filter() {

	if(!(isset($_POST['scf_key']))) { 
		return false;
	}
	$_POST['scf_name']     = stripslashes(trim($_POST['scf_name']));
	$_POST['scf_email']    = stripslashes(trim($_POST['scf_email']));
	$_POST['scf_message']  = stripslashes(trim($_POST['scf_message']));
	$_POST['scf_response'] = stripslashes(trim($_POST['scf_response']));

	global $scf_options, $scf_strings;
	$pass  = true;

	if(empty($_POST['scf_name'])) {
		$pass = FALSE;
		$fail = 'empty';
		$scf_strings['name'] = '<input class="scf_error" name="scf_name" id="scf_name" type="text" size="33" maxlength="99" value="'. htmlentities($_POST['scf_name']) .'" ' . $scf_options['scf_style'] . ' />';
	}
	if(!is_email($_POST['scf_email'])) {
		$pass = FALSE; 
		$fail = 'empty';
		$scf_strings['email'] = '<input class="scf_error" name="scf_email" id="scf_email" type="text" size="33" maxlength="99" value="'. htmlentities($_POST['scf_email']) .'" ' . $scf_options['scf_style'] . ' />';
	}
	if (empty($_POST['scf_response'])) {
		$pass = FALSE; 
		$fail = 'empty';
		$scf_strings['response'] = '<input class="scf_error" name="scf_response" id="scf_response" type="text" size="33" maxlength="99" value="'. htmlentities($_POST['scf_response']) .'" ' . $scf_options['scf_style'] . ' />';
	}
	if (!scf_spam_question($_POST['scf_response'])) {
		$pass = FALSE;
		$fail = 'wrong';
		$scf_strings['response'] = '<input class="scf_error" name="scf_response" id="scf_response" type="text" size="33" maxlength="99" value="'. htmlentities($_POST['scf_response']) .'" ' . $scf_options['scf_style'] . ' />';
	}
	if(empty($_POST['scf_message'])) {
		$pass = FALSE; 
		$fail = 'empty';
		$scf_strings['message'] = '<textarea class="scf_error" name="scf_message" id="scf_message" cols="33" rows="7" ' . $scf_options['scf_style'] . '>'. $_POST['scf_message'] .'</textarea>';
	}
	if(scf_malicious_input($_POST['scf_name']) || scf_malicious_input($_POST['scf_email'])) {
		$pass = false; 
		$fail = 'malicious';
	}
	if($pass == true) {
		return true;
	} else {
		if($fail == 'malicious') {
			$scf_strings['error'] = '<p class="scf_error">Please do not include any of the following in the Name or Email fields: linebreaks, or the phrases "mime-version", "content-type", "cc:" or "to:".</p>';
		} elseif($fail == 'empty') {
			$scf_strings['error'] = stripslashes($scf_options['scf_error']);
		} elseif($fail == 'wrong') {
			$scf_strings['error'] = stripslashes($scf_options['scf_spam']);
		}
		return false;
	}
}


// shortcode to display contact form
add_shortcode('simple_contact_form','scf_shortcode');
function scf_shortcode() {
	if (scf_input_filter()) {
		return scf_process_contact_form();
	} else {
		return scf_display_contact_form();
	}
}

// template tag to display contact form
function simple_contact_form() {
	if (scf_input_filter()) {
		echo scf_process_contact_form();
	} else {
		echo scf_display_contact_form();
	}
}

// process contact form
function scf_process_contact_form($content='') {
	global $scf_options, $scf_strings;
	
	$topic     = stripslashes($scf_options['scf_subject']);
	$recipient = stripslashes($scf_options['scf_email']);
	$recipname = stripslashes($scf_options['scf_name']);
	$recipsite = stripslashes($scf_options['scf_website']);
	$success   = stripslashes($scf_options['scf_success']);

	$name      = $_POST['scf_name'];
	$email     = $_POST['scf_email'];

	$senderip  = scf_get_ip_address();
	$offset    = $scf_options['sfc_offset'];
	$agent     = $_SERVER['HTTP_USER_AGENT'];
	$form      = getenv("HTTP_REFERER");
	$host      = gethostbyaddr($_SERVER['REMOTE_ADDR']);
	$date      = date("l, F jS, Y @ g:i a", time() + $offset * 60 * 60);

	$prepend = stripslashes($scf_options['scf_prepend']);
	$append  = stripslashes($scf_options['scf_append']);

	$headers   = "MIME-Version: 1.0\n";
	$headers  .= "From: $name <$email>\n";
	$headers  .= "Content-Type: text/plain; charset=\"" . get_settings('blog_charset') . "\"";

	$message   = $_POST['scf_message'];
	$fullmsg   = ("Hello $recipname,

You are being contacted via $recipsite:

Name:     $name
Email:    $email
Message:

$message

-----------------------

Additional Information:

Site:   $recipsite
URL:    $form
Date:   $date
IP:     $senderip
Host:   $host
Agent:  $agent
");
	$fullmsg = stripslashes(strip_tags(trim($fullmsg)));
	wp_mail($recipient, $topic, $fullmsg, $headers);
	wp_mail($email, $topic, $fullmsg, $headers);

	$results = ($prepend . $success . '
<pre>Name:       '. $name    .'
Email:      '. $email   .'
Date:       '. $date .'
Message: 

'. $message .'</pre><p class="scf_reset">[ <a href="'. $form .'">Click here to reset the form</a> ]</p>' . $append);

	echo $results;
}

// display contact form
function scf_display_contact_form() {
	global $scf_options, $scf_strings;

	$question = stripslashes($scf_options['scf_question']);
	$nametext = stripslashes($scf_options['scf_nametext']);
	$mailtext = stripslashes($scf_options['scf_mailtext']);
	$messtext = stripslashes($scf_options['scf_messtext']);
	$offset   = $scf_options['sfc_offset'];
	
	if ($scf_options['scf_css'] !== '') {
		$scf_custom = '<style>' . $scf_options['scf_css'] . '</style>';
	} else { $scf_custom = ''; }

	$scf_form = ($scf_strings['error'] . '
		<div id="simple-contact-form">
			<form action="'. get_permalink() .'" method="post">
				<fieldset class="scf-name">
					<label for="scf_name">'. $nametext .'</label>
					'. $scf_strings['name'] .'
				</fieldset>
				<fieldset class="scf-email">
					<label for="scf_email">'. $mailtext .'</label>
					'. $scf_strings['email'] .'
				</fieldset>
				<fieldset class="scf-response">
					<label for="scf_response">'. $question .'</label>
					'. $scf_strings['response'] .'
				</fieldset>
				<fieldset class="scf-message">
					<label for="scf_message">'. $messtext .'</label>
					'. $scf_strings['message'] .'
				</fieldset>
				<div class="scf-submit">
					<input type="submit" name="Submit" id="scf_contact" value="Send email">
					<input type="hidden" name="scf_key" value="process">
				</div>
			</form>
		</div>
		' . $scf_custom . '
		<div class="clear">&nbsp;</div>
	');

	return $scf_form;
}

// display settings link on plugin page
add_filter ('plugin_action_links', 'scf_plugin_action_links', 10, 2);
function scf_plugin_action_links($links, $file) {
	global $scf_path;
	if ($file == $scf_path) {
		$scf_links = '<a href="' . get_admin_url() . 'options-general.php?page=' . $scf_path . '">' . __('Settings') .'</a>';
		array_unshift($links, $scf_links);
	}
	return $links;
}

// delete plugin settings
function scf_delete_plugin_options() {
	delete_option('scf_options');
}
if ($scf_options['default_options'] == 1) {
	register_uninstall_hook (__FILE__, 'scf_delete_plugin_options');
}

// define default settings
register_activation_hook (__FILE__, 'scf_add_defaults');
function scf_add_defaults() {
	$user_info = get_userdata(1);
	if ($user_info == true) {
		$admin_name = $user_info->user_login;
	} else {
		$admin_name = 'Neo Smith';
	}
	$site_title = get_bloginfo('name');
	$admin_mail = get_bloginfo('admin_email');
	$tmp = get_option('scf_options');
	if(($tmp['default_options'] == '1') || (!is_array($tmp))) {
		$arr = array(
			'default_options' => 0,
			'scf_name' => $admin_name,
			'scf_website' => $site_title,
			'scf_email' => $admin_mail,
			'scf_offset' => 'For example, +1 or -1',
			'scf_subject' => 'Message sent from your contact form.',
			'scf_question' => '1 + 1 =',
			'scf_response' => '2',
			'scf_casing' => 0,
			'scf_nametext' => 'Name (Required)',
			'scf_mailtext' => 'Email (Required)',
			'scf_messtext' => 'Message (Required)',
			'scf_success' => '<p class=\'scf_success\'><strong>Success!</strong> Your message has been sent.</p>',
			'scf_error' => '<p class=\'scf_error\'>Please complete the required fields.</p>',
			'scf_spam' => '<p class=\'scf_spam\'>Incorrect response for challenge question. Please try again.</p>',
			'scf_style' => 'style=\'border: 1px solid #CC0000;\'',
			'scf_prepend' => '',
			'scf_append' => '',
			'scf_css' => '#simple-contact-form fieldset { width: 100%; overflow: hidden; margin: 5px 0; } #simple-contact-form fieldset input { float: left; width: 60%; } #simple-contact-form textarea { float: left; clear: both; width: 95%; } #simple-contact-form label { float: left; clear: both; width: 30%; margin-top: 3px; line-height: 1.8; font-size: 90%; }',
		);
		update_option('scf_options', $arr);
	}
}

// whitelist settings
add_action ('admin_init', 'scf_init');
function scf_init() {
	register_setting('scf_plugin_options', 'scf_options', 'scf_validate_options');
}

// sanitize and validate input
function scf_validate_options($input) {

	if (!isset($input['default_options'])) $input['default_options'] = null;
	$input['default_options'] = ($input['default_options'] == 1 ? 1 : 0);

	$input['scf_name']     = wp_filter_nohtml_kses($input['scf_name']);
	$input['scf_website']  = wp_filter_nohtml_kses($input['scf_website']);
	$input['scf_email']    = wp_filter_nohtml_kses($input['scf_email']);
	$input['scf_offset']   = wp_filter_nohtml_kses($input['scf_offset']);
	$input['scf_subject']  = wp_filter_nohtml_kses($input['scf_subject']);
	$input['scf_question'] = wp_filter_nohtml_kses($input['scf_question']);
	$input['scf_response'] = wp_filter_nohtml_kses($input['scf_response']);

	if (!isset($input['scf_casing'])) $input['scf_casing'] = null;
	$input['scf_casing'] = ($input['scf_casing'] == 1 ? 1 : 0);

	$input['scf_nametext'] = wp_filter_nohtml_kses($input['scf_nametext']);
	$input['scf_mailtext'] = wp_filter_nohtml_kses($input['scf_mailtext']);
	$input['scf_messtext'] = wp_filter_nohtml_kses($input['scf_messtext']);

	// dealing with kses
	global $allowedposttags;
	$allowed_atts = array('align'=>array(), 'class'=>array(), 'id'=>array(), 'dir'=>array(), 'lang'=>array(), 'style'=>array(), 'xml:lang'=>array(), 'src'=>array(), 'alt'=>array());

	$allowedposttags['strong'] = $allowed_atts;
	$allowedposttags['small'] = $allowed_atts;
	$allowedposttags['span'] = $allowed_atts;
	$allowedposttags['abbr'] = $allowed_atts;
	$allowedposttags['code'] = $allowed_atts;
	$allowedposttags['div'] = $allowed_atts;
	$allowedposttags['img'] = $allowed_atts;
	$allowedposttags['h1'] = $allowed_atts;
	$allowedposttags['h2'] = $allowed_atts;
	$allowedposttags['h3'] = $allowed_atts;
	$allowedposttags['h4'] = $allowed_atts;
	$allowedposttags['h5'] = $allowed_atts;
	$allowedposttags['ol'] = $allowed_atts;
	$allowedposttags['ul'] = $allowed_atts;
	$allowedposttags['li'] = $allowed_atts;
	$allowedposttags['em'] = $allowed_atts;
	$allowedposttags['p'] = $allowed_atts;
	$allowedposttags['a'] = $allowed_atts;

	$input['scf_success'] = wp_kses_post($input['scf_success'], $allowedposttags);
	$input['scf_error']   = wp_kses_post($input['scf_error'], $allowedposttags);
	$input['scf_spam']    = wp_kses_post($input['scf_spam'], $allowedposttags);
	$input['scf_style']   = wp_kses_post($input['scf_style'], $allowedposttags);
	
	$input['scf_prepend'] = wp_filter_nohtml_kses($input['scf_prepend']);
	$input['scf_append'] = wp_filter_nohtml_kses($input['scf_append']);
	$input['scf_css'] = wp_filter_nohtml_kses($input['scf_css']);

	return $input;
}

// add the options page
add_action ('admin_menu', 'scf_add_options_page');
function scf_add_options_page() {
	global $scf_plugin;
	add_options_page($scf_plugin, $scf_plugin, 'manage_options', __FILE__, 'scf_render_form');
}

// create the options page
function scf_render_form() {
	global $scf_plugin, $scf_options, $scf_path, $scf_homeurl, $scf_version; ?>

	<style type="text/css">
		.mm-panel-overview { padding-left: 150px; background: url(<?php echo plugins_url(); ?>/simple-basic-contact-form/scf-logo.png) no-repeat 15px 0; }

		#mm-plugin-options h2 small { font-size: 60%; }
		#mm-plugin-options h3 { cursor: pointer; }
		#mm-plugin-options h4, 
		#mm-plugin-options p { margin: 15px; line-height: 18px; }
		#mm-plugin-options ul { margin: 15px 15px 25px 40px; }
		#mm-plugin-options li { margin: 10px 0; list-style-type: disc; }
		#mm-plugin-options abbr { cursor: help; border-bottom: 1px dotted #dfdfdf; }

		.mm-table-wrap { margin: 15px; }
		.mm-table-wrap td { padding: 5px 10px; vertical-align: middle; }
		.mm-table-wrap .widefat th { padding: 10px 15px; vertical-align: middle; }
		.mm-table-wrap .widefat td { padding: 10px; vertical-align: middle; }
		.mm-item-caption { margin: 3px 0 0 3px; font-size: 11px; color: #777; line-height: 17px; }
		.mm-code { background-color: #fafae0; color: #333; font-size: 14px; }

		#setting-error-settings_updated { margin: 10px 0; }
		#setting-error-settings_updated p { margin: 5px; }
		.button-primary { margin: 0 0 15px 15px; }

		#mm-panel-toggle { margin: 5px 0; }
		#mm-credit-info { margin-top: -5px; }
		#mm-iframe-wrap { width: 100%; height: 250px; overflow: hidden; }
		#mm-iframe-wrap iframe { width: 100%; height: 100%; overflow: hidden; margin: 0; padding: 0; }
	</style>

	<div id="mm-plugin-options" class="wrap">
		<?php screen_icon(); ?>

		<h2><?php echo $scf_plugin; ?> <small><?php echo 'v' . $scf_version; ?></small></h2>
		<div id="mm-panel-toggle"><a href="<?php get_admin_url() . 'options-general.php?page=' . $scf_path; ?>"><?php _e('Toggle all panels'); ?></a></div>

		<form method="post" action="options.php">
			<?php $scf_options = get_option('scf_options'); settings_fields('scf_plugin_options'); ?>

			<div class="metabox-holder">
				<div class="meta-box-sortables ui-sortable">
					<div id="mm-panel-overview" class="postbox">
						<h3><?php _e('Overview'); ?></h3>
						<div class="toggle default-hidden">
							<div class="mm-panel-overview">
								<p>
									<strong><?php echo $scf_plugin; ?></strong> <?php _e('(SBCF) is a simple basic contact form for your WordPress-powered website. Automatically sends a carbon copy to the sender.'); ?>
									<?php _e('Simply choose your options, then add the shortcode to any post or page to display the contact form. For a contact form with more options try '); ?> 
									<a href="http://perishablepress.com/contact-coldform/">Contact Coldform</a>.
								</p>
								<ul>
									<li><?php _e('To configure the contact form, visit the'); ?> <a id="mm-panel-primary-link" href="#mm-panel-primary"><?php _e('Options panel'); ?></a>.</li>
									<li><?php _e('For the shortcode and template tag, visit'); ?> <a id="mm-panel-secondary-link" href="#mm-panel-secondary"><?php _e('Shortcodes &amp; Template Tags'); ?></a>.</li>
									<li><?php _e('To restore default settings, visit'); ?> <a id="mm-restore-settings-link" href="#mm-restore-settings"><?php _e('Restore Default Options'); ?></a>.</li>
									<li><?php _e('For more information check the <code>readme.txt</code> and'); ?> <a href="<?php echo $scf_homeurl; ?>"><?php _e('SBCF Homepage'); ?></a>.</li>
								</ul>
							</div>
						</div>
					</div>
					<div id="mm-panel-primary" class="postbox">
						<h3><?php _e('Options'); ?></h3>
						<div class="toggle<?php if (!isset($_GET["settings-updated"])) { echo ' default-hidden'; } ?>">
							<p><?php _e('Configure the contact form..'); ?></p>
							<h4><?php _e('General options'); ?></h4>
							<div class="mm-table-wrap">
								<table class="widefat mm-table">
									<tr>
										<th scope="row"><label class="description" for="scf_options[scf_name]"><?php _e('Your Name'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="scf_options[scf_name]" value="<?php echo $scf_options['scf_name']; ?>" />
										<div class="mm-item-caption"><?php _e('How would you like to be addressed in messages sent from the contact form?'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="scf_options[scf_email]"><?php _e('Your Email'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="scf_options[scf_email]" value="<?php echo $scf_options['scf_email']; ?>" />
										<div class="mm-item-caption"><?php _e('Where would you like to receive messages sent from the contact form?'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="scf_options[scf_website]"><?php _e('Your Site'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="scf_options[scf_website]" value="<?php echo $scf_options['scf_website']; ?>" />
										<div class="mm-item-caption"><?php _e('From where should the contact messages indicate they were sent?'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="scf_options[scf_subject]"><?php _e('Default Subject'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="scf_options[scf_subject]" value="<?php echo $scf_options['scf_subject']; ?>" />
										<div class="mm-item-caption"><?php _e('What should be the default subject line for the contact messages?'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="scf_options[scf_question]"><?php _e('Challenge Question'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="scf_options[scf_question]" value="<?php echo $scf_options['scf_question']; ?>" />
										<div class="mm-item-caption"><?php _e('What question should be answered correctly before the message is sent?'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="scf_options[scf_response]"><?php _e('Challenge Response'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="scf_options[scf_response]" value="<?php echo $scf_options['scf_response']; ?>" />
										<div class="mm-item-caption"><?php _e('What is the <em>only</em> correct answer to the challenge question?'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="scf_options[scf_casing]"><?php _e('Case-sensitive?'); ?></label></th>
										<td><input type="checkbox" name="scf_options[scf_casing]" value="1" <?php if (isset($scf_options['scf_casing'])) { checked('1', $scf_options['scf_casing']); } ?> /> 
										<?php _e('Check this box if you want the challenge response to be case-sensitive.'); ?></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="scf_options[scf_offset]"><?php _e('Time Offset'); ?></label></th>
										<td>
											<input type="text" size="50" maxlength="200" name="scf_options[scf_offset]" value="<?php echo $scf_options['scf_offset']; ?>" />
											<div class="mm-item-caption">
												<?php _e('Please specify any time offset here. If no offset, enter "0" (zero).'); ?><br />
												<?php _e('Current Coldform time:'); ?> <?php echo date("l, F jS, Y @ g:i a", time()+$offset*60*60); ?>
											</div>
										</td>
									</tr>
								</table>
							</div>
							<h4><?php _e('Appearance'); ?></h4>
							<div class="mm-table-wrap">
								<table class="widefat mm-table">
									<tr>
										<th scope="row"><label class="description" for="scf_options[scf_css]"><?php _e('Custom CSS styles'); ?></label></th>
										<td><textarea class="textarea" rows="7" cols="55" name="scf_options[scf_css]"><?php echo esc_textarea($scf_options['scf_css']); ?></textarea>
										<div class="mm-item-caption"><?php _e('Add some CSS to style the contact form. Note: do not include the <code>&lt;style&gt;</code> tags.'); ?></div></td>
									</tr>
								</table>
							</div>
							<h4><?php _e('Field captions'); ?></h4>
							<div class="mm-table-wrap">
								<table class="widefat mm-table">
									<tr>
										<th scope="row"><label class="description" for="scf_options[scf_nametext]"><?php _e('Caption for Name Field'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="scf_options[scf_nametext]" value="<?php echo $scf_options['scf_nametext']; ?>" />
										<div class="mm-item-caption"><?php _e('This is the caption that corresponds with the Name field.'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="scf_options[scf_mailtext]"><?php _e('Caption for Email Field'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="scf_options[scf_mailtext]" value="<?php echo $scf_options['scf_mailtext']; ?>" />
										<div class="mm-item-caption"><?php _e('This is the caption that corresponds with the Email field.'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="scf_options[scf_messtext]"><?php _e('Caption for Message Field'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="scf_options[scf_messtext]" value="<?php echo $scf_options['scf_messtext']; ?>" />
										<div class="mm-item-caption"><?php _e('This is the caption that corresponds with the Message field.'); ?></div></td>
									</tr>
								
								</table>
							</div>
							<h4><?php _e('Success &amp; error messages'); ?></h4>
							<p><?php _e('Note: use single quotes for attributes. Example: <code>&lt;span class=\'error\'&gt;</code>'); ?></p>
							<div class="mm-table-wrap">
								<table class="widefat mm-table">
									<tr>
										<th scope="row"><label class="description" for="scf_options[scf_success]"><?php _e('Success Message'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="scf_options[scf_success]" value="<?php echo $scf_options['scf_success']; ?>" />
										<div class="mm-item-caption"><?php _e('When the form is sucessfully submitted, this message will be displayed to the sender.'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="scf_options[scf_error]"><?php _e('Error Message'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="scf_options[scf_error]" value="<?php echo $scf_options['scf_error']; ?>" />
										<div class="mm-item-caption"><?php _e('If the user skips a required field, this message will be displayed.'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="scf_options[scf_spam]"><?php _e('Incorrect Response'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="scf_options[scf_spam]" value="<?php echo $scf_options['scf_spam']; ?>" />
										<div class="mm-item-caption"><?php _e('When the challenge question is answered incorrectly, this message will be displayed.'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="scf_options[scf_style]"><?php _e('Error Fields'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="scf_options[scf_style]" value="<?php echo $scf_options['scf_style']; ?>" />
										<div class="mm-item-caption"><?php _e('Here you may specify the default CSS for error fields, or add other attributes.'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="scf_options[scf_prepend]"><?php _e('Prepend Markup'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="scf_options[scf_prepend]" value="<?php echo $scf_options['scf_prepend']; ?>" />
										<div class="mm-item-caption"><?php _e('Add some text/markup to appear <em>before</em> the submitted form results (optional).'); ?></div></td>
									</tr>
									<tr>
										<th scope="row"><label class="description" for="scf_options[scf_append]"><?php _e('Append Markup'); ?></label></th>
										<td><input type="text" size="50" maxlength="200" name="scf_options[scf_append]" value="<?php echo $scf_options['scf_append']; ?>" />
										<div class="mm-item-caption"><?php _e('Add some text/markup to appear <em>after</em> the submitted form results (optional).'); ?></div></td>
									</tr>
								</table>
							</div>
							<input type="submit" class="button-primary" value="<?php _e('Save Settings'); ?>" />
						</div>
					</div>
					<div id="mm-panel-secondary" class="postbox">
						<h3><?php _e('Shortcodes &amp; Template Tags'); ?></h3>
						<div class="toggle<?php if (!isset($_GET["settings-updated"])) { echo ' default-hidden'; } ?>">
							<h4><?php _e('Shortcode'); ?></h4>
							<p><?php _e('Use this shortcode to display the contact form on a post or page:'); ?></p>
							<p><code class="mm-code">[simple_contact_form]</code></p>
							<h4><?php _e('Template tag'); ?></h4>
							<p><?php _e('Use this template tag to display the form anywhere in your theme template:'); ?></p>
							<p><code class="mm-code">&lt;?php if (function_exists('simple_contact_form')) simple_contact_form(); ?&gt;</code></p>
						</div>
					</div>
					
					<div id="mm-restore-settings" class="postbox">
						<h3><?php _e('Restore Default Options'); ?></h3>
						<div class="toggle<?php if (!isset($_GET["settings-updated"])) { echo ' default-hidden'; } ?>">
							<p>
								<input name="scf_options[default_options]" type="checkbox" value="1" id="mm_restore_defaults" <?php if (isset($scf_options['default_options'])) { checked('1', $scf_options['default_options']); } ?> /> 
								<label class="description" for="scf_options[default_options]"><?php _e('Restore default options upon plugin deactivation/reactivation.'); ?></label>
							</p>
							<p>
								<small>
									<?php _e('<strong>Tip:</strong> leave this option unchecked to remember your settings. Or, to go ahead and restore all default options, check the box, save your settings, and then deactivate/reactivate the plugin.'); ?>
								</small>
							</p>
							<input type="submit" class="button-primary" value="<?php _e('Save Settings'); ?>" />
						</div>
					</div>
					<div id="mm-panel-current" class="postbox">
						<h3><?php _e('Updates &amp; Info'); ?></h3>
						<div class="toggle default-hidden">
							<div id="mm-iframe-wrap">
								<iframe src="http://perishablepress.com/current/index-scf.html"></iframe>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div id="mm-credit-info">
				<a target="_blank" href="<?php echo $scf_homeurl; ?>" title="<?php echo $scf_plugin; ?> Homepage"><?php echo $scf_plugin; ?></a> by 
				<a target="_blank" href="http://twitter.com/perishable" title="Jeff Starr on Twitter">Jeff Starr</a> @ 
				<a target="_blank" href="http://monzilla.biz/" title="Obsessive Web Design &amp; Development">Monzilla Media</a>
			</div>
		</form>
	</div>
	<script type="text/javascript">
		jQuery(document).ready(function(){
			// toggle panels
			jQuery('.default-hidden').hide();
			jQuery('#mm-panel-toggle a').click(function(){
				jQuery('.toggle').slideToggle(300);
				return false;
			});
			jQuery('h3').click(function(){
				jQuery(this).next().slideToggle(300);
			});
			jQuery('#mm-panel-primary-link').click(function(){
				jQuery('.toggle').hide();
				jQuery('#mm-panel-primary .toggle').slideToggle(300);
				return true;
			});
			jQuery('#mm-panel-secondary-link').click(function(){
				jQuery('.toggle').hide();
				jQuery('#mm-panel-secondary .toggle').slideToggle(300);
				return true;
			});
			jQuery('#mm-restore-settings-link').click(function(){
				jQuery('.toggle').hide();
				jQuery('#mm-restore-settings .toggle').slideToggle(300);
				return true;
			});
			// prevent accidents
			if(!jQuery("#mm_restore_defaults").is(":checked")){
				jQuery('#mm_restore_defaults').click(function(event){
					var r = confirm("<?php _e('Are you sure you want to restore all default options? (this action cannot be undone)'); ?>");
					if (r == true){  
						jQuery("#mm_restore_defaults").attr('checked', true);
					} else {
						jQuery("#mm_restore_defaults").attr('checked', false);
					}
				});
			}
		});
	</script>

<?php } ?>