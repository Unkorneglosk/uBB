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
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with UseBB; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

//
// Die when called directly in browser
//
if ( !defined('INCLUDED') )
	exit();

//
// Topic review templates
//

$templates['header'] = '
	<table class="maintable">
		<tr>
			<td colspan="2" class="forumcat">&raquo; {l_TopicReview}</td>
		</tr>
		<tr>
			<th class="hiddenonmobile">{l_Author}</th>
			<th>{l_Post}</th>
		</tr>
';

$templates['post'] = '
		<tr class="tr{colornum}">
			<td class="postername hiddenonmobile">
				<div class="posternamecontainer">{poster_name}</div>
			</td>
			<td class="postinfo">
				<div class="postlinks"></div>
				<div class="postdate"><span class="showonmobile">{poster_name}</span>{post_date}</div>
			</td>
		</tr>
		<tr class="tr{colornum}">
			<td class="posterinfo hiddenonmobile">
				{post_date}
			</td>
			<td class="postcontent">
				<div class="post">{post_content}</div>
			</td>
		</tr>
';

$templates['footer'] = '
		<tr>
			<td class="formcontrols" colspan="2">
				{view_more_posts}
			</td>
		</tr>
	</table>
';

?>
