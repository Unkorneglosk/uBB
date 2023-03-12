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
 * ACP version check
 *
 * Gives an interface to check for the latest UseBB version.
 *
 * @author	UseBB Team
 * @link	http://www.usebb.net
 * @license	GPL-2
 * @version	$Revision$
 * @copyright	Copyright (C) 2003-2012 UseBB Team
 * @package	UseBB
 * @subpackage	ACP
 */

//
// Die when called directly in browser
//
if ( !defined('INCLUDED') )
	exit();
$ubb_latest = file_get_contents('https://ubb.losk.fr/forums/latest_version.txt');
if(version_compare($ubb_latest, USEBB_VERSION, '<=')) {
	$content .= '<h2>'.$lang['VersionLatestVersionTitle'].'</h2>';
	$content .= '<p>'.sprintf($lang['VersionLatestVersion'], USEBB_VERSION).'</p>';
} else {
	$content .= '<h2>'.$lang['VersionNeedUpdateTitle'].'</h2>';
	$content .= '<p><strong>'.sprintf($lang['VersionNeedUpdate'], USEBB_VERSION, unhtml($ubb_latest), '<a href="http://u-bb.kube17.tk/downloads.php">uBB</a>').'</strong></p>';
}

$admin_functions->create_body('version', $content);
?>
