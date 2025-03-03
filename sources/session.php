<?php

/*
	Copyright (C) 2003-2012 UseBB Team
	http://www.usebb.net
	
	$Id$
	
	This file is part of UseBB.
	
	UseBB is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.
	
	UseBB is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with UseBB; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * Session management
 *
 * Contains the session class to do session management.
 *
 * @author	UseBB Team
 * @link	http://www.usebb.net
 * @license	GPL-2
 * @version	$Revision$
 * @copyright	Copyright (C) 2003-2012 UseBB Team
 * @package	UseBB
 * @subpackage	Core
 */

//
// Die when called directly in browser
//
if ( !defined('INCLUDED') )
	exit();

/**
 * Session management
 *
 * Does all kinds of session management in addition to PHP's functions.
 *
 * @author	UseBB Team
 * @link	http://www.usebb.net
 * @license	GPL-2
 * @version	$Revision$
 * @copyright	Copyright (C) 2003-2012 UseBB Team
 * @package	UseBB
 * @subpackage	Core
 */
class session {
	
	/**
	 * @var array Array containing all session info, as well as user info. update() must be called before this contains information.
	 */
	var $sess_info = array();

	/**
	 * Start or continue a session
	 */
	function start() {
		
		global $functions;
		
		//
		// Set the session save path
		//
		$proposed_save_path = $functions->get_config('session_save_path');
		if ( !empty($proposed_save_path) )
			session_save_path($proposed_save_path);
		
		//
		// Set some PHP session cookie configuration options
		// Use HttpOnly flag when enabled
		//
		// Also see the comments with setcookie() in functions.php.
		//
		$domain = $functions->get_config('cookie_domain');
		$secure = ( $functions->get_config('cookie_secure') ) ? 1 : 0;
		if ( empty($domain) || !$functions->get_config('cookie_httponly') )
			session_set_cookie_params($functions->get_config('session_max_lifetime')*60, $functions->get_config('cookie_path'), $domain, $secure);
		elseif ( version_compare(PHP_VERSION, '5.2.0RC2', '>=') )
			session_set_cookie_params($functions->get_config('session_max_lifetime')*60, $functions->get_config('cookie_path'), $domain, $secure, true);
		else
			session_set_cookie_params($functions->get_config('session_max_lifetime')*60, $functions->get_config('cookie_path'), $domain.'; HttpOnly', $secure);
		
		//
		// Set the session name
		//
		session_name($functions->get_config('session_name').'_sid');
		
		//
		// Start the session
		//
		if ( !ini_get('session.auto_start') )
			session_start();
		
		//
		// Several session info we maintain
		//
		$_SESSION['previous_visit'] = ( !empty($_SESSION['previous_visit']) ) ? $_SESSION['previous_visit'] : 0;
		$_SESSION['viewed_topics'] = ( isset($_SESSION['viewed_topics']) && is_array($_SESSION['viewed_topics']) ) ? $_SESSION['viewed_topics'] : array();
		$_SESSION['latest_post'] = ( !empty($_SESSION['latest_post']) ) ? $_SESSION['latest_post'] : 0;
		$_SESSION['dnsbl_checked'] = ( !empty($_SESSION['dnsbl_checked']) ) ? $_SESSION['dnsbl_checked'] : 0;
		$_SESSION['dnsbl_whitelisted'] = ( isset($_SESSION['dnsbl_whitelisted']) && $_SESSION['dnsbl_whitelisted'] );
		$_SESSION['antispam_question_posed'] = ( isset($_SESSION['antispam_question_posed']) && $_SESSION['antispam_question_posed'] );
		$_SESSION['tokens'] = ( isset($_SESSION['tokens']) && is_array($_SESSION['tokens']) ) ? $_SESSION['tokens'] : array();
		$_SESSION['oldest_token'] = ( !empty($_SESSION['oldest_token']) ) ? (float) $_SESSION['oldest_token'] : 0.0;
		$_SESSION['sfs_ban_cache'] = ( isset($_SESSION['sfs_ban_cache']) && is_array($_SESSION['sfs_ban_cache']) ) ? $_SESSION['sfs_ban_cache'] : array();
		
	}
	
	/**
	 * Update the session table for this session
	 *
	 * This method also checks for banned IP-addresses or user accounts and checks for auto-login cookies. update() must be called before $sess_info contains usable information.
	 *
	 * @param string $location Current forum location (current location when missing)
	 * @param int $user_id New user ID (current ID when missing)
	 */
	function update($location=NULL, $user_id=NULL) {
		
		global $functions, $db;
		
		//
		// First, get the user's IP address and time
		//
		$current_time = time();
		$ip_addr = ( !empty($_SERVER['REMOTE_ADDR']) ) ? $_SERVER['REMOTE_ADDR'] : getenv('REMOTE_ADDR');
		
		//
		// On some systems (such as OS X) ::1 is used instead of 127.0.0.1 for localhost.
		// UseBB 1 does not support IPv6, but ::1 can be translated quick and easy.
		//
		if ( $ip_addr == '::1' )
			$ip_addr = '127.0.0.1';
		
		//
		// Clean old form tokens
		//
		$this->clean_tokens($current_time);
		
		//
		// Cleanup various stuff once in ten requests
		//
		$run_cleanup = ( mt_rand(0, 9) === 0 );
		
		//
		// Get banned IP addresses
		//
		$ip_banned = false;
		if ( $functions->get_config('enable_ip_bans') ) {
			
			$result = $db->query("SELECT ip_addr FROM ".TABLE_PREFIX."bans WHERE ip_addr <> ''");
			$banned_ips_sql = array();
			while ( $out = $db->fetch_result($result) ) {
				
				$out['ip_addr'] = stripslashes($out['ip_addr']);
				
				if ( !$ip_banned && preg_match('#^'.str_replace(array('\*', '\?'), array('[0-9]*', '[0-9]'), preg_quote($out['ip_addr'])).'$#', $ip_addr) )
					$ip_banned = true;
				
				if ( $run_cleanup )
					$banned_ips_sql[] = "ip_addr LIKE '".str_replace(array('*', '?'), array('%', '_'), $out['ip_addr'])."'";
				
			}
			
		}

		$session_max_lifetime = $functions->get_config('session_max_lifetime');
		
		//
		// Cleanup
		//
		if ( $run_cleanup ) {
			
			$add_to_remove_query = array();
			
			//
			// Remove older clone sessions if needed
			//
			if ( !$functions->get_config('allow_multi_sess') )
				$add_to_remove_query[] = "( ip_addr = '".$ip_addr."' AND sess_id <> '".session_id()."' )";
			
			//
			// Remove outdated sessions and searches if needed
			//
			if ( $session_max_lifetime ) {
				
				$session_min_updated = $current_time - ( $session_max_lifetime * 60 );
				$add_to_remove_query[] = "updated < ".$session_min_updated;
				$db->query("DELETE FROM ".TABLE_PREFIX."searches WHERE time < ".$session_min_updated);
				
			}
			
			//
			// Remove sessions with banned IP addresses
			//
			if ( $functions->get_config('enable_ip_bans') && count($banned_ips_sql) )
				$add_to_remove_query[] = join(' OR ', $banned_ips_sql);
			
			//
			// Now run the cleanup query
			//
			if ( count($add_to_remove_query) )
				$db->query("DELETE FROM ".TABLE_PREFIX."sessions WHERE ".join(' OR ', $add_to_remove_query));
			
		}
		
		//
		// IP address banned
		//
		if ( $ip_banned ) {
			
			//
			// Save session information with the banned key and
			// IP address if this IP address is banned
			//
			$this->sess_info = array(
				'sess_id' => session_id(),
				'user_id' => 0,
				'ip_addr' => $ip_addr,
				'updated' => $current_time,
				'ip_banned' => true,
				'location' => ( $location !== NULL ) ? $location : ''
			);
			
			return;
			
		}
		
		//
		// Get information about the current session (and user)
		//
		$result = $db->query("SELECT s.user_id, s.started, s.updated, s.location AS slocation, s.pages, s.ip_addr, u.* FROM ".TABLE_PREFIX."sessions s LEFT JOIN ".TABLE_PREFIX."members u ON u.id = s.user_id WHERE sess_id = '".session_id()."'");
		$user_data = $db->fetch_result($result);
		
		if ( is_array($user_data) ) {
			
			//
			// Data exists in DB
			//
			$session_started = true;
			
			$user_data['user_id'] = ( !empty($user_data['user_id']) ) ? $user_data['user_id'] : 0;
			$current_sess_info = array(
				'user_id' => $user_data['user_id'],
				'started' => $user_data['started'],
				'updated' => $user_data['updated'],
				'location' => $user_data['slocation'],
				'pages' => $user_data['pages'],
				'ip_addr' => $user_data['ip_addr']
			);
			
			if ( $current_sess_info['user_id'] )
				unset($user_data['user_id'], $user_data['started'], $user_data['updated'], $user_data['slocation'], $user_data['pages'], $user_data['ip_addr']);
			else
				unset($user_data);
			
		} else {
			
			$session_started = false;
			unset($user_data);
			
		}
		
		//
		// Existing session checks
		//
		if ( $session_started ) {

			//
			// Wrong IP address (hijack)
			//
			if ( $current_sess_info['ip_addr'] !== $ip_addr ) {
			
				//
				// Reload the page, stripping the wrong session ID
				// in the URL (if present) and unsetting the cookie
				//
				$SID = SID;
				$functions->setcookie($functions->get_config('session_name').'_sid', '');
				$functions->raw_redirect(str_replace($SID, '', $_SERVER['REQUEST_URI']));
				
				//
				// Developer note:
				// This is a dirty way of getting rid of the session ID, but it is the only one,
				// as PHP starts the session anyway when getting a session ID, and we are only
				// working on top of it. Also we do not want to destroy the session itself.
				//

			}

			//
			// Expired session
			//
			if ( $session_max_lifetime && $current_sess_info['updated'] < $current_time - ( $session_max_lifetime * 60 ) ) {
				
				//
				// This will not happen if cleanup ran, but since it only runs one in ten requests,
				// a check here is necessary.
				//
				// When expired, destroy session and restart.
				//
				$this->destroy();
				$functions->raw_redirect($_SERVER['REQUEST_URI']);

			}
			
		}
		
		$spam_opportunity = ( preg_match('#^(register|editprofile|reply:|posttopic:|sendemail:)#', $location) );
		
		//
		// DNSBL powered banning
		//
		if ( function_exists('checkdnsrr') && !ON_WINDOWS ) {
			
			$dnsrr_available = true;
			$dnsrr_function = 'checkdnsrr';
			
		} elseif ( ON_WINDOWS ) {
			
			$dnsrr_available = true;
			$dnsrr_function = 'checkdnsrr_win';
			
		} else {
			
			$dnsrr_available = false;
			
		}
		
		$recheck_min = $functions->get_config('dnsbl_powered_banning_recheck_minutes');
		if ( $functions->get_config('enable_ip_bans') && $functions->get_config('enable_dnsbl_powered_banning')
			// Perform checking on this page
			&& ( $functions->get_config('dnsbl_powered_banning_globally') || $spam_opportunity )
			// Can and should check
			&& $dnsrr_available && !$_SESSION['dnsbl_whitelisted']
			// Not checked or too long ago
			&& ( !$_SESSION['dnsbl_checked'] || ( $recheck_min > 0 && $_SESSION['dnsbl_checked'] <= ( $current_time - $recheck_min * 60 ) ) ) ) {
			
			//
			// Check whitelist
			//
			$whitelist = $functions->get_config('dnsbl_powered_banning_whitelist');			
			if ( count($whitelist) ) {
				
				foreach ( $whitelist as $ip_host ) {
					
					if ( preg_match('#[a-z]#i', $ip_host) ) {
						
						//
						// This is a hostname
						//
						if ( preg_match('#^'.str_replace(array('\*', '\?'), array('[a-z0-9\-\.]*', '[a-z0-9\-\.]'), preg_quote($ip_host)).'$#i', gethostbyaddr($ip_addr)) ) {
							
							$_SESSION['dnsbl_whitelisted'] = true;
							break;
							
						}
						
					} else {
						
						//
						// This is an IP address
						//
						if ( preg_match('#^'.str_replace(array('\*', '\?'), array('[0-9\.]*', '[0-9]'), preg_quote($ip_host)).'$#', $ip_addr) ) {
							
							$_SESSION['dnsbl_whitelisted'] = true;
							break;
							
						}
						
					}
					
				}
				
			}
			
			if ( !$_SESSION['dnsbl_whitelisted'] ) {
				
				$dnsbl_servers = $functions->get_config('dnsbl_powered_banning_servers');
				$dnsbl = join('.', array_reverse(explode('.', $ip_addr)));
				
				$hits_found = 0;
				foreach ( $dnsbl_servers as $dnsbl_server ) {
					
					if ( preg_match('#^(?:[a-z0-9\-]+\.){1,}[a-z]{2,}$#i', $dnsbl_server) && call_user_func($dnsrr_function, $dnsbl.'.'.$dnsbl_server, 'A') )
						$hits_found++;
					
				}
				
				if ( $hits_found >= $functions->get_config('dnsbl_powered_banning_min_hits') ) {
					
					$db->query("INSERT INTO ".TABLE_PREFIX."bans(ip_addr) VALUES('".$ip_addr."')");
					
					$this->sess_info = array(
						'sess_id' => session_id(),
						'user_id' => 0,
						'ip_addr' => $ip_addr,
						'updated' => $current_time,
						'ip_banned' => true,
						'location' => ( $location !== NULL ) ? $location : ''
					);
					
					return;
					
				} else {
					
					//
					// Checked, but recheck later
					//
					$_SESSION['dnsbl_checked'] = $current_time;
					
				}
				
			}
			
		}
		
		//
		// There are some possibilities now:
		// - Use the id from the parameters
		// OR
		// - The user is not yet logged in
		//   -> check auto login cookie
		// - The user is logged in
		//   -> check current user data
		//
		if ( $user_id !== NULL && valid_int($user_id) ) {
			
			//
			// ID passed by parameter
			//
			$user_data = $this->get_user_data($user_id);
			$user_id = ( $this->check_user($user_data) ) ? $user_id : 0;
			
		} elseif ( !$session_started || !$current_sess_info['user_id'] ) {
			
			//
			// Not logged in
			//
			if ( $functions->isset_al() ) {
				
				//
				// Try auto login cookie
				//
				$cookie_data = $functions->get_al();
				
				if ( is_array($cookie_data) && !empty($cookie_data[0]) && valid_int($cookie_data[0]) && !empty($cookie_data[1]) && strlen($cookie_data[1]) == 32 ) {
					
					$user_data = $this->get_user_data($cookie_data[0]);
					$user_id = ( $cookie_data[1] === $user_data['passwd'] && $this->check_user($user_data) ) ? $cookie_data[0] : 0;
					
					//
					// Unset cookie when credentials are invalid
					//
					if ( $user_id == 0 )
						$functions->unset_al();
					
				} else {
					
					//
					// Invalid cookie
					//
					$functions->unset_al();
					$user_id = 0;
					
				}
				
			} else {
				
				//
				// Remain guest
				//
				$user_id = 0;
				
			}
			
		} else {
			
			//
			// Check current user data
			//
			$user_id = ( $this->check_user($user_data) ) ? $current_sess_info['user_id'] : 0;
			
		}

		//
		// Set new session data
		//
		$new_sess_info = array(
			'user_id' => $user_id,
			'started' => ( $session_started ) ? $current_sess_info['started'] : $current_time,
			'updated' => $current_time,
			'pages' => ( $session_started ) ? $current_sess_info['pages']+1 : 1,
			'ip_addr' => $ip_addr,
			'pose_antispam_question' => ( $functions->get_config('antispam_question_mode') > ANTI_SPAM_DISABLE && $user_id == 0 && $spam_opportunity && !$_SESSION['antispam_question_posed'] )
		);
		
		if ( $location !== NULL ) {
			
			$new_sess_info['location'] = $location;
			
		} else {
			
			$new_sess_info['location'] = ( $session_started ) ? $current_sess_info['location'] : '';
			
		}
		
		//
		// If the user logged in, use a new session ID (security measure)
		//
		/* if ( ( !$session_started && $new_sess_info['user_id'] ) || ( $session_started && $new_sess_info['user_id'] > $current_sess_info['user_id'] ) ) {
			
			//
			// Try to find a new session ID that does not yet exist
			//
			do {
				
				$new_sid = md5(uniqid(mt_rand(), true));
				$return = $db->query("SELECT COUNT(*) AS exist FROM ".TABLE_PREFIX."sessions WHERE sess_id = '".$new_sid."'");
				$exists = $db->fetch_result($return);
				$exists = (bool)$exists['exist'];
				
			} while ( $exists );
			
			//
			// Set the new session ID and update the cookie (not done automatically)
			//
			$old_sid = session_id($new_sid);
			//$old_id = $new_id;
			$functions->setcookie($functions->get_config('session_name').'_sid', $new_sid);
			
			//
			// Update the searches
			//
			$db->query("UPDATE ".TABLE_PREFIX."searches SET sess_id = '".$new_sid."' WHERE sess_id = '".$old_sid."'");

			//
			// Do not use these timestamps for the logged in user.
			//
			$_SESSION['viewed_topics'] = array();
			
		} */
		
		//
		// Save data in DB
		//
		if ( $session_started ) {
			
			//
			// Session already exists in DB
			//
			if ( isset($old_sid) ) {
				
				//
				// Logged in, change session ID
				//
				$update_query = "UPDATE ".TABLE_PREFIX."sessions SET
					user_id = ".$new_sess_info['user_id'].",
					updated = ".$new_sess_info['updated'].",
					location = '".$new_sess_info['location']."',
					pages = ".$new_sess_info['pages'].",
					ip_addr = '".$new_sess_info['ip_addr']."',
					sess_id = '".$new_sid."'
				WHERE sess_id = '".$old_sid."'";
				
			} else {
				
				//
				// Just update the data, keep session ID
				//
				$update_query = "UPDATE ".TABLE_PREFIX."sessions SET
					user_id = ".$new_sess_info['user_id'].",
					updated = ".$new_sess_info['updated'].",
					location = '".$new_sess_info['location']."',
					pages = ".$new_sess_info['pages'].",
					ip_addr = '".$new_sess_info['ip_addr']."'
				WHERE sess_id = '".session_id()."'";
				
			}
			
		} else {
			
			//
			// Session does not already exist in DB
			//
			$update_query = "INSERT INTO ".TABLE_PREFIX."sessions VALUES (
				'".session_id()."',
				".$new_sess_info['user_id'].",
				'".$new_sess_info['ip_addr']."',
				".$new_sess_info['started'].",
				".$new_sess_info['updated'].",
				'".$new_sess_info['location']."',
				".$new_sess_info['pages']."
			)";
			
		}
		$db->query($update_query);
		
		//
		// Eventually update member data
		//
		if ( $new_sess_info['user_id'] ) {
			
			$add_to_update_query = ( !$session_started || $current_sess_info['user_id'] !== $new_sess_info['user_id'] ) ? ', last_login = '.$current_time : '';
			$db->query("UPDATE ".TABLE_PREFIX."members SET last_pageview = ".$current_time.$add_to_update_query." WHERE id = ".$new_sess_info['user_id']);
			
		}
		
		//
		// Now save the session information locally
		//
		$this->sess_info = array_merge($new_sess_info, array(
			'sess_id' => session_id(),
			'ip_banned' => false,
			'user_info' => ( $new_sess_info['user_id'] ) 
				? $user_data 
				: array('active' => USER_ACTIVE, 'level' => LEVEL_GUEST)
		));
		
		//
		// Set previous visit timestamp for markers
		//
		$_SESSION['previous_visit'] = ( $new_sess_info['user_id'] && ( !$session_started || $current_sess_info['user_id'] !== $new_sess_info['user_id'] || empty($_SESSION['previous_visit']) ) ) ? $user_data['last_pageview'] : $_SESSION['previous_visit'];
		
		//
		// Remove other sessions with same user ID (if enabled)
		//
		if ( !$functions->get_config('allow_multi_sess_per_user') && $new_sess_info['user_id'] && ( !$session_started || $current_sess_info['user_id'] !== $new_sess_info['user_id'] ) )
			$db->query("DELETE FROM ".TABLE_PREFIX."sessions WHERE user_id = '".$new_sess_info['user_id']."' AND sess_id <> '".session_id()."'");
		
	}
	
	/**
	 * Get user data
	 *
	 * @param int $user_id User ID
	 * @returns array User data array from query
	 */
	function get_user_data($user_id) {
		
		global $db;
		
		$result = $db->query("SELECT * FROM ".TABLE_PREFIX."members WHERE id = ".$user_id);
		return $db->fetch_result($result);
		
	}
	
	/**
	 * Check a user data to see if he/she may log in
	 *
	 * @param array $user_data User data array from query
	 * @returns bool May login
	 */
	function check_user($user_data) {
		
		global $functions;
		
		return ( $user_data['active'] && !$user_data['banned'] && ( !$functions->get_config('board_closed') || $user_data['level'] == LEVEL_ADMIN ) );
		
	}

	/**
	 * Is search engine
	 *
	 * @returns bool Is search engine
	 */
	function is_search_engine() {
		
		$agent = strtolower($_SERVER['HTTP_USER_AGENT']);

		if ( empty($agent) )
			return false;

		$bots = array('googlebot', 'msnbot', 'yahoo!');

		foreach ( $bots as $bot ) {
			
			if ( strpos($agent, $bot) !== false )
				return true;

		}

		return false;

	}

	/**
	 * Clean tokens
	 *
	 * Always called in session.
	 *
	 * @param int $current_time Current timestamp
	 */
	function clean_tokens($current_time) {

		global $functions;
		
		//
		// No tokens
		//
		if ( !$_SESSION['oldest_token'] )
			return;

		$max_age = 900;
		$max_count = 20;
		$current_count = count($_SESSION['tokens']);
		$min = 0;

		//
		// Nothing to clean
		//
		if ( $current_time - $_SESSION['oldest_token'] <= $max_age && $current_count <= $max_count )
			return;

		$token_times = array_keys($_SESSION['tokens']);
		foreach ( $token_times as $time ) {
		
			$ftime = (float)$time;
			$min = $ftime;
			
			//
			// Continue looping until not too old and not too many tokens
			//
			if ( $current_time - $ftime <= $max_age && $current_count <= $max_count )
				break;
			
			unset($_SESSION['tokens'][$time]);
			$current_count--;

		}

		//
		// Get new oldest token
		//
		$_SESSION['oldest_token'] = ( $current_count > 0 ) ? $min : 0;

	}
	
	/**
	 * Destroy a running session
	 */
	function destroy() {
		
		global $functions, $db;
		
		$db->query("DELETE FROM ".TABLE_PREFIX."sessions WHERE sess_id = '".session_id()."'");
		$db->query("DELETE FROM ".TABLE_PREFIX."searches WHERE sess_id = '".session_id()."'");
		$_SESSION = array();
		session_destroy();
		$functions->setcookie($functions->get_config('session_name').'_sid', '');
		
	}
	
}

?>
