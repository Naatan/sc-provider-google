<?php

/*
Plugin Name: Social Connect - Google Provider
Plugin URI: http://wordpress.org/extend/plugins/social-connect/
Description: Allows you to login / register with Google - REQUIRES Social Connect plugin
Version: 0.10
Author: Brent Shepherd, Nathan Rijksen
Author URI: http://wordpress.org/extend/plugins/social-connect/
License: GPL2
 */

require_once dirname(__FILE__) . '/openid.php';

/**
 * Social Connect Google provider
 */
class SC_Provider_Google 
{
	
	/**
	 * Init, static class constructor
	 * 
	 * @returns	void 
	 */
	static function init()
	{
		add_action('social_connect_button_list',array('SC_Provider_Google','render_button'));
	}
	
	/**
	 * When a callback is made, it will be tunneled through this method
	 * 
	 * @returns	void							
	 */
	static function call()
	{
		if ( !isset($_GET['call']) OR !in_array($_GET['call'], array('connect','callback')))
		{
			return;
		}
		
		call_user_func(array('SC_Provider_Google', $_GET['call']));
	}
	
	/**
	 * Render connect button and related javascript
	 * 
	 * @returns	void							
	 */
	static function render_button()
	{
		$image_url = plugins_url() . '/' . basename( dirname( __FILE__ )) . '/button.png';
		?>
		<a href="javascript:void(0);" title="Google" class="social_connect_login_google"><img alt="Google" src="<?php echo $image_url ?>" /></a>
		<div id="social_connect_google_auth" style="display: none;">
			<input type="hidden" name="redirect_uri" value="<?php echo( SOCIAL_CONNECT_PLUGIN_URL . '/call.php?call=connect&provider=google' ); ?>" />
		</div>
		
		<script type="text/javascript">
		(jQuery(function($) {
			var _do_google_connect = function() {
				var google_auth = $('#social_connect_google_auth');
				var redirect_uri = google_auth.find('input[type=hidden][name=redirect_uri]').val();
				window.open(redirect_uri,'','scrollbars=no,menubar=no,height=400,width=800,resizable=yes,toolbar=no,status=no');
			};
			
			$(".social_connect_login_google, .social_connect_login_continue_google").click(function() {
				_do_google_connect();
			});
		}));
		</script>
		
		<?php
	}
	
	/**
	 * Initiate authentication, redirects to provider auth page
	 * 
	 * @returns	void							
	 */
	static function connect()
	{
		$openid             = new LightOpenID;
		$openid->identity   = 'https://www.google.com/accounts/o8/id';
		$openid->required   = array('namePerson/first', 'namePerson/last', 'contact/email');
		$openid->returnUrl  = SOCIAL_CONNECT_PLUGIN_URL . '/call.php?provider=google&call=callback';
		header('Location: ' . $openid->authUrl());
	}
	
	/**
	 * Provider authentication callback, called when the provider has done it's part
	 * 
	 * @returns	void							
	 */
	static function callback()
	{
		$openid             = new LightOpenID;
		$openid->returnUrl  = SOCIAL_CONNECT_PLUGIN_URL . '/call.php?provider=google&call=callback';
		
		try
		{
			if ( !$openid->validate())
			{
				echo 'validation failed';
				return;
			}
		}
			catch(ErrorException $e)
		{
			echo $e->getMessage();
			return;
		}
		
		$google_id  = $openid->identity;
		$attributes = $openid->getAttributes();
		$email      = $attributes['contact/email'];
		$first_name = $attributes['namePerson/first'];
		$last_name  = $attributes['namePerson/last'];
		$signature  = SC_Utils::generate_signature($google_id);
		
		?>
		<html>
		<head>
		<script>
		function init() {
			window.opener.wp_social_connect({
				'action' : 'social_connect', 'social_connect_provider' : 'google', 
				'social_connect_openid_identity' : '<?php echo $google_id ?>',
				'social_connect_signature' : '<?php echo $signature ?>',
				'social_connect_email' : '<?php echo $email ?>',
				'social_connect_first_name' : '<?php echo $first_name ?>',
				'social_connect_last_name' : '<?php echo $last_name ?>'
			});
			window.close();
		}
		</script>
		</head>
		<body onload="init();">
		</body>
		</html>
		<?php
	}
	
	/**
	 * Process the login, validates the provider's data and returns required information
	 * 
	 * @returns	object							
	 */
	static function process_login()
	{
		$redirect_to            = SC_Utils::redirect_to();
		$provider_identity      = $_REQUEST[ 'social_connect_openid_identity' ];
		$provided_signature     = $_REQUEST[ 'social_connect_signature' ];
		
		SC_Utils::verify_signature( $provider_identity, $provided_signature, $redirect_to );
		
		return (object) array(
			'provider_identity' => $provider_identity,
			'email'             => $_REQUEST[ 'social_connect_email' ],
			'first_name'        => $_REQUEST[ 'social_connect_first_name' ],
			'last_name'         => $_REQUEST[ 'social_connect_last_name' ],
			'profile_url'       => '',
			'name'              => $_REQUEST[ 'social_connect_first_name' ] . ' ' . $_REQUEST[ 'social_connect_last_name' ],
			'user_login'        => strtolower($_REQUEST[ 'social_connect_first_name' ] . $_REQUEST[ 'social_connect_last_name' ])
		);
	}
	
}

SC_Provider_Google::init();