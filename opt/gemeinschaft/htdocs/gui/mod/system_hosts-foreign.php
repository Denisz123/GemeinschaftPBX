<?php
/*******************************************************************\
*            Gemeinschaft - asterisk cluster gemeinschaft
* 
* $Revision$
* 
* Copyright 2007, amooma GmbH, Bachstr. 126, 56566 Neuwied, Germany,
* http://www.amooma.de/
* Stefan Wintermeyer <stefan.wintermeyer@amooma.de>
* Philipp Kempgen <philipp.kempgen@amooma.de>
* Peter Kozak <peter.kozak@amooma.de>
* 
* This program is free software; you can redistribute it and/or
* modify it under the terms of the GNU General Public License
* as published by the Free Software Foundation; either version 2
* of the License, or (at your option) any later version.
* 
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
* 
* You should have received a copy of the GNU General Public License
* along with this program; if not, write to the Free Software
* Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
* MA 02110-1301, USA.
\*******************************************************************/

######################################################
##
##   ALL STRINGS IN HERE NEED TO BE TRANSLATED!
##
######################################################

defined('GS_VALID') or die('No direct access.');

function microtime_float()
{
	list($usec, $sec) = explode(' ', microTime());
	return ((float)$usec + (float)$sec);
}

echo '<h2>';
if (@$MODULES[$SECTION]['icon'])
	echo '<img alt=" " src="', GS_URL_PATH, str_replace('%s', '32', $MODULES[$SECTION]['icon']), '" /> ';
if (count( $MODULES[$SECTION]['sub'] ) > 1 )
	echo $MODULES[$SECTION]['title'], ' - ';
echo $MODULES[$SECTION]['sub'][$MODULE]['title'];
echo '</h2>', "\n";

echo '<script type="text/javascript" src="', GS_URL_PATH, 'js/arrnav.js"></script>', "\n";

$host_apis = array(
	''    => '-',
	'm01' => '1.0'
	//'m02' => '1.1'
);

$edit_host   = (int)trim(@$_REQUEST['edit'   ]);
$save_host   = (int)trim(@$_REQUEST['save'   ]);
$per_page    = (int)GS_GUI_NUM_RESULTS;
$page        =     (int)(@$_REQUEST['page'   ]);
$host        =      trim(@$_REQUEST['host'   ]);
$hostid      = (int)trim(@$_REQUEST['hostid' ]);
$comment     =      trim(@$_REQUEST['comment']);
$group_id    = (int)trim(@$_REQUEST['grp_id' ]);
$delete_host = (int)trim(@$_REQUEST['delete' ]);

if ($host) {
	$host = normalizeIPs($host);
	$bInvalHostName = false;
	if (! preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $host)) {
		# not an IP address. => resolve hostname
		$addresses = @gethostbynamel($host);
		
		if (count($addresses) < 1) {
			echo '<div class="errorbox">';
			echo sPrintF(__('Hostname &quot;%s&quot; konnte nicht aufgel&ouml;st werden.'), htmlEnt($host));
			echo '</div>',"\n";
			$bInvalHostName = true;
		}
		elseif (count($addresses) > 1) {
			echo '<div class="errorbox">';
			echo sPrintF(__('Hostname &quot;%s&quot; kann nicht verwendet werden, da er zu mehr als einer IP-Adresse aufgel&ouml;st wird.'), htmlEnt($host));
			echo '</div>',"\n";
			$bInvalHostName = true;
		}
		elseif (count($addresses) == 1) {
			if (strlen($addresses[0]) == 0) {
				echo '<div class="errorbox">';
				echo sPrintF(__('Hostname &quot;%s&quot; konnte nicht aufgel&ouml;st werden.'), htmlEnt($host));
				echo '</div>',"\n";		
				$bInvalHostName = true;
			}
			$host = $addresses[0];
		}
	}
	
	if (! preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $host)) {
		if (! $bInvalHostName) {
			echo '<div class="errorbox">';
			echo sPrintF(__('Ung&uuml;ltige IP-Adresse &quot;%s&quot;!'), htmlEnt($host));
			echo '</div>',"\n";
		}
	}
	else {
		$api_update_users = false;
		$old_val = $DB->executeGetOne( 'SELECT `host` FROM `hosts` WHERE `id`='. $save_host );
		if ($host !== $old_val) $api_update_users = true;
		
		if ($save_host) {
			$sql_query =
'UPDATE `hosts` SET
	`host`=\''. $DB->escape($host) .'\',
	`comment`=\''. $DB->escape($comment) .'\',
	`group_id`='. $group_id .'
WHERE
	`id`='. $save_host .' AND
	`is_foreign`=1'
			;
			$ok = $DB->execute($sql_query);
			$host_id = $save_host;
			if (! $ok) {
				echo '<div class="errorbox">';
				echo sPrintF(__('Fehler beim &Auml;ndern von Host %d'),
					$save_host);
				echo '</div>',"\n";
			}
		}
		else {
			@$DB->execute('OPTIMIZE TABLE `hosts`');  # recalculate next auto-increment value
			@$DB->execute('ANALYZE TABLE `hosts`');
			
			$sql_query =
'INSERT INTO `hosts` (
	`id`,
	`host`,
	`comment`,
	`is_foreign`,
	`group_id`
) VALUES (
	NULL,
	\''. $DB->escape($host) .'\',
	\''. $DB->escape($comment) .'\',
	1,
	'. $group_id .'
)'
			;
			$ok = $DB->execute($sql_query);
			if ($ok) {
				$host_id = (int)$DB->getLastInsertId();
			} else {
				$host_id = 0;
				echo '<div class="errorbox">';
				echo sPrintF(__('Fehler beim Hinzuf&uuml;gen von Host &quot;%s&quot;'),
					htmlEnt($host));
				/*
				echo ' <small>(';
				//echo $DB->getLastErrorCode();
				echo $DB->getLastNativeError();
				echo ' - ', htmlEnt($DB->getLastNativeErrorMsg());
				echo ')</small>';
				*/
				echo '</div>',"\n";
			}
		}
		
		if ($host_id > 0) {
			$_REQUEST['hp_api'                ] = trim(@$_REQUEST['hp_api'                ]);
			$_REQUEST['hp_sip_proxy_from_wan' ] = trim(@$_REQUEST['hp_sip_proxy_from_wan' ]);
			$_REQUEST['hp_sip_server_from_wan'] = trim(@$_REQUEST['hp_sip_server_from_wan']);
			$_REQUEST['hp_route_prefix'       ] = preg_replace('/[^0-9]/', '', @$_REQUEST['hp_route_prefix']);
			
			if ($_REQUEST['hp_api'] == '') {
				$api_update_users = false;
			} else {
				if (! $api_update_users) {
					$old_val = $DB->executeGetOne( 'SELECT `value` FROM `host_params` WHERE `host_id`='. $host_id .' AND `param`=\'api\'' );
					if ($_REQUEST['hp_api'] !== $old_val)
						$api_update_users = true;
				}
				if (! $api_update_users) {
					$old_val = $DB->executeGetOne( 'SELECT `value` FROM `host_params` WHERE `host_id`='. $host_id .' AND `param`=\'route_prefix\'' );
					if ($_REQUEST['hp_route_prefix'] !== $old_val)
						$api_update_users = true;
				}
			}
			
			$DB->execute( 'REPLACE INTO `host_params` (`host_id`, `param`, `value`) VALUES ('. $host_id .', \'api\', \''. $DB->escape($_REQUEST['hp_api']) .'\')' );
			$DB->execute( 'REPLACE INTO `host_params` (`host_id`, `param`, `value`) VALUES ('. $host_id .', \'sip_proxy_from_wan\', \''. $DB->escape($_REQUEST['hp_sip_proxy_from_wan']) .'\')' );
			$DB->execute( 'REPLACE INTO `host_params` (`host_id`, `param`, `value`) VALUES ('. $host_id .', \'sip_server_from_wan\', \''. $DB->escape($_REQUEST['hp_sip_server_from_wan']) .'\')' );
			$DB->execute( 'REPLACE INTO `host_params` (`host_id`, `param`, `value`) VALUES ('. $host_id .', \'route_prefix\', \''. $DB->escape($_REQUEST['hp_route_prefix']) .'\')' );
		}
		
		if ($api_update_users) {
			$num_users = $DB->executeGetOne( 'SELECT COUNT(*) FROM `users` WHERE `host_id`='. $host_id );
			if ($num_users > 0) {
				//FIXME - SOAP call to update all users on this host
				echo '<p class="text">Denken Sie daran, da&szlig; die f&uuml;r diesen Host angelegten Benutzer ('.$num_users.') nicht auf dem Fremd-Host aktualisiert wurden.</p>' ,"\n";
			}
		}
	}
}

if ($delete_host) {
	gs_db_start_trans($DB);
	
	$num_real_users   = $DB->executeGetOne( 'SELECT COUNT(*) FROM `users` WHERE `host_id`='. $delete_host .' AND `nobody_index` IS NULL' );
	$num_nobody_users = $DB->executeGetOne( 'SELECT COUNT(*) FROM `users` WHERE `host_id`='. $delete_host .' AND `nobody_index` IS NOT NULL' );
	if ($num_real_users > 0) {
		echo '<div class="errorbox">', sPrintF(__('Auf dem Host sind Benutzer angelegt (%d). Bitte l&ouml;schen Sie zuerst die Benutzer.'), $num_real_users) ,'</div>' ,"\n";
		gs_db_rollback_trans($DB);
	}
	elseif ($num_nobody_users > 0) {
		/*
		echo '<div class="errorbox">', sPrintF(__('Auf dem Host sind Dummy-Benutzer angelegt (%d). Bitte l&ouml;schen Sie zuerst die Dummy-Benutzer.'), $num_nobody_users) ,'</div>' ,"\n";
		gs_db_rollback_trans($DB);
		*/
		# delete nobody users
		$num_nobody_users_not_deleted = 0;
		$rs = $DB->execute( 'SELECT `user` FROM `users` WHERE `host_id`='. $delete_host );
		while ($r = $rs->fetchRow()) {
			$ret = gs_user_del( $user );
			if ($ret !== true) ++$num_nobody_users_not_deleted;
		}
		if ($num_nobody_users_not_deleted > 0) {
			echo '<div class="errorbox">', sPrintF(__('%d von %d Dummy-Usern auf diesem Host konnten nicht gel&ouml;scht werden.'), $num_nobody_users_not_deleted, $num_nobody_users) ,'</div>' ,"\n";
			gs_db_rollback_trans($DB);
		}
	}
	else {
		# delete BOI permissions
		@$DB->execute( 'DELETE FROM `boi_perms` WHERE `host_id`='. $delete_host );
		
		# delete host params
		@$DB->execute( 'DELETE FROM `host_params` WHERE `host_id`='. $delete_host );
		
		$sql_query =
	'DELETE FROM `hosts` 
	WHERE
		`id`='. $delete_host .' AND
		`is_foreign`=1'
		;
		$DB->execute($sql_query);
		
		$ok = gs_db_commit_trans($DB);
		
		@$DB->execute('OPTIMIZE TABLE `hosts`');  # recalculate next auto-increment value
		@$DB->execute('ANALYZE TABLE `hosts`');
	}
}



$sql_query =
	'SELECT SQL_CALC_FOUND_ROWS '.
		'`h`.`id`, `h`.`host`, `h`.`comment`, `h`.`group_id`, '.
		'`p1`.`value` `hp_api`, '.
		'`p2`.`value` `hp_sip_proxy_from_wan`, '.
		'`p3`.`value` `hp_sip_server_from_wan`, '.
		'`p4`.`value` `hp_route_prefix` '.
	'FROM '.
		'`hosts` `h` LEFT JOIN '.
		'`host_params` `p1` ON (`p1`.`host_id`=`h`.`id` AND `p1`.`param`=\'api\') LEFT JOIN '.
		'`host_params` `p2` ON (`p2`.`host_id`=`h`.`id` AND `p2`.`param`=\'sip_proxy_from_wan\') LEFT JOIN '.
		'`host_params` `p3` ON (`p3`.`host_id`=`h`.`id` AND `p3`.`param`=\'sip_server_from_wan\') LEFT JOIN '.
		'`host_params` `p4` ON (`p4`.`host_id`=`h`.`id` AND `p4`.`param`=\'route_prefix\') '.
	'WHERE `h`.`is_foreign`=1 '.
	'ORDER BY `h`.`host` '.
	'LIMIT '. ($page*(int)$per_page) .','. (int)$per_page;

$rs = $DB->execute($sql_query);

$num_total = @$DB->numFoundRows();
$num_pages = ceil($num_total / $per_page);

?>	

<table cellspacing="1" class="phonebook">
<thead>
<tr>
	<th style="width:55px;"><?php echo __('ID'); ?></th>
	<th style="width:125px;" class="sort-col"><?php echo __('IP-Adresse'); ?> <sup>[1]</sup></th>
	<th style="width:150px;"><?php echo __('Kommentar'); ?></th>
	<th style="width:65px;"><?php echo __('Pr&auml;fix'); ?> <sup>[2]</sup></th>
	<th style="width:50px;"><?php echo __('API'); ?> <sup>[3]</sup></th>
	<th style="width:125px;"><?php echo __('SIP-Proxy WAN'); ?> <sup>[4]</sup></th>
	<th style="width:125px;"><?php echo __('SIP-SBC WAN'); ?> <sup>[5]</sup></th>
	<th style="width:80px;"><?php echo __('Gruppen-ID'); ?></th>
	<th style="width:80px;">
<?php
echo ($page+1), ' / ', $num_pages, '&nbsp; ',"\n";

if ($page > 0) {
	echo
	'<a href="', gs_url($SECTION, $MODULE, null, 'page='.($page-1)), '" title="', __('zur&uuml;ckbl&auml;ttern'), '" id="arr-prev">',
	'<img alt="', __('zur&uuml;ck'), '" src="', GS_URL_PATH, 'crystal-svg/16/act/previous.png" />',
	'</a>', "\n";
} else {
	echo
	'<img alt="', __('zur&uuml;ck'), '" src="', GS_URL_PATH, 'crystal-svg/16/act/previous_notavail.png" />', "\n";
}

if ($page < $num_pages-1) {
	echo
	'<a href="', gs_url($SECTION, $MODULE, null, 'page='.($page+1)), '" title="', __('weiterbl&auml;ttern'), '" id="arr-next">',
	'<img alt="', __('weiter'), '" src="', GS_URL_PATH, 'crystal-svg/16/act/next.png" />',
	'</a>', "\n";
} else {
	echo
	'<img alt="', __('weiter'), '" src="', GS_URL_PATH, 'crystal-svg/16/act/next_notavail.png" />', "\n";
}

?>
	</th>
</tr>
</thead>
<tbody>

<?php

if (@$rs) {
	$i = 0;
	while ($r = $rs->fetchRow()) {
		echo '<tr class="', ((++$i % 2) ? 'odd':'even'), '">', "\n";
		
		$ip = $r['host'];
		$nodes[$ip] = array();

		$nodes[$ip]['host_id' ] = $r['id'];
		$nodes[$ip]['comment' ] = $r['comment'];

		if ($edit_host == $r['id']) {
			
			echo '<form method="post" action="', GS_URL_PATH, '">', "\n";
			echo gs_form_hidden($SECTION, $MODULE), "\n";
			echo '<input type="hidden" name="page" value="', htmlEnt($page), '" />', "\n";
			echo '<input type="hidden" name="save" value="', $r['id'] , '" />', "\n";
			
			echo '<td class="r">', htmlEnt($r['id']) ,'</td>',"\n";
						
			echo '<td>';
			echo '<input type="text" name="host" value="', htmlEnt($r['host']) ,'" size="15" maxlength="15" style="width:95%;" />';
			echo '</td>',"\n";
			
			echo '<td>';
			echo '<input type="text" name="comment" value="', htmlEnt($r['comment']) ,'" size="20" maxlength="45" style="width:95%;" />';
			echo '</td>',"\n";
			
			echo '<td>';
			echo '<input type="text" name="hp_route_prefix" value="', htmlEnt($r['hp_route_prefix']) ,'" size="8" maxlength="10" style="width:92%;" />';
			echo '</td>',"\n";
			
			echo '<td>';
			echo '<select name="hp_api">' ,"\n";
			foreach ($host_apis as $api => $title) {
				echo '<option value="', htmlEnt($api) ,'"';
				if ($api == $r['hp_api']) echo ' selected="selected"';
				echo '>', htmlEnt($title) ,'</option>' ,"\n";
			}
			echo '</select>';
			echo '</td>',"\n";
			
			echo '<td>';
			echo '<input type="text" name="hp_sip_proxy_from_wan" value="', htmlEnt($r['hp_sip_proxy_from_wan']) ,'" size="15" maxlength="15" style="width:95%;" />';
			echo '</td>',"\n";
			
			echo '<td>';
			echo '<input type="text" name="hp_sip_server_from_wan" value="', htmlEnt($r['hp_sip_server_from_wan']) ,'" size="15" maxlength="15" style="width:95%;" />';
			echo '</td>',"\n";
			
			echo '<td class="r">';
			echo '<input type="text" name="grp_id" value="', $r['group_id'] ,'" size="4" maxlength="5" class="r" style="width:95%;" />';
			echo '</td>',"\n";
			
			echo '<td>';
			
			echo '<button type="submit" title="', __('Speichern'), '" class="plain">';
			echo '<img alt="', __('Speichern') ,'" src="', GS_URL_PATH,'crystal-svg/16/act/filesave.png" />';
			echo '</button>' ,"\n";
			echo "&nbsp;\n";
			echo '<a href="', gs_url($SECTION, $MODULE, null, 'page='.$page) ,'">';
			echo '<button type="button" title="', __('Abbrechen'), '" class="plain">';
			echo '<img alt="', __('Abbrechen') ,'" src="', GS_URL_PATH,'crystal-svg/16/act/cancel.png" />';
			echo '</button></a>' ,"\n";
			
			echo '</td>',"\n";
			
			echo '</form>',"\n";
			
		} else {
			
			echo '<td class="r">', htmlEnt($r['id']) ,'</td>',"\n";
						
			echo '<td>', htmlEnt($r['host']) ,'</td>',"\n";
			
			echo '<td>', htmlEnt($r['comment']) ,'</td>',"\n";
			
			echo '<td>', htmlEnt($r['hp_route_prefix']) ,'</td>',"\n";
			
			echo '<td>', htmlEnt(array_key_exists($r['hp_api'], $host_apis) ? $host_apis[$r['hp_api']] : $r['hp_api']) ,'</td>',"\n";
			
			echo '<td>', htmlEnt($r['hp_sip_proxy_from_wan']) ,'</td>',"\n";
			
			echo '<td>', htmlEnt($r['hp_sip_server_from_wan']) ,'</td>',"\n";
			
			echo '<td class="r">', $r['group_id'] ,'</td>',"\n";
			
			echo '<td>';
			
			echo '<a href="', gs_url($SECTION, $MODULE, null, 'edit='.$r['id'] .'&amp;page='.$page) ,'" title="', __('bearbeiten'), '"><img alt="', __('bearbeiten'), '" src="', GS_URL_PATH, 'crystal-svg/16/act/edit.png" /></a> &nbsp; ';
			
			echo '<a href="', gs_url($SECTION, $MODULE, null, 'delete='.$r['id'] .'&amp;page='.$page) ,'" title="', __('l&ouml;schen'), '"><img alt="', __('entfernen'), '" src="', GS_URL_PATH, 'crystal-svg/16/act/editdelete.png" /></a>';
			
			echo '</td>',"\n";
		}
		
		echo '</tr>', "\n";
	}
}

?>
<tr>
<?php

if (!$edit_host) {
	echo '<form method="post" action="', GS_URL_PATH, '">', "\n";
	echo gs_form_hidden($SECTION, $MODULE), "\n";
?>
	<td class="r">
		&nbsp;
	</td>
	<td>
		<input type="text" name="host" value="" size="15" maxlength="15" style="width:95%;" />
	</td>
	<td>
		<input type="text" name="comment" value="" size="20" maxlength="45" style="width:95%;" />
	</td>
	<td>
		<input type="text" name="hp_route_prefix" value="" size="8" maxlength="10" style="width:92%;" />
	</td>
	<td>
		<select name="hp_api">
<?php
		foreach ($host_apis as $api => $title) {
			echo '<option value="', htmlEnt($api) ,'"';
			if ($api == gs_get_conf('GS_BOI_API_DEFAULT')) echo ' selected="selected"';
			echo '>', htmlEnt($title) ,'</option>' ,"\n";
		}
?>
		</select>
	</td>
	<td>
		<input type="text" name="hp_sip_proxy_from_wan" value="" size="15" maxlength="15" style="width:95%;" />
	</td>
	<td>
		<input type="text" name="hp_sip_server_from_wan" value="" size="15" maxlength="15" style="width:95%;" />
	</td>
	<td class="r">
		<input type="text" name="grp_id" value="" size="4" maxlength="5" class="r" style="width:90%;" />
	</td>
	<td>
		<button type="submit" title="<?php echo __('Host anlegen'); ?>" class="plain">
			<img alt="<?php echo __('Speichern'); ?>" src="<?php echo GS_URL_PATH; ?>crystal-svg/16/act/filesave.png" />
		</button>
	</td>

</form>

<?php
}
?>

</tr>

</tbody>
</table>

<br />

<p style="max-width:500px;"><small><sup>[1]</sup> <?php echo __('Wenn Sie in dieses Feld einen Hostnamen eintragen wird er automatisch permanent zu einer IP-Adresse aufgel&ouml;st.'); ?></small></p>

<p style="max-width:500px;"><small><sup>[2]</sup> <?php echo __('Pr&auml;fix der Nebenstellen an diesem Standort, also die Route (z.B. 608000, 60800042).'); ?></small></p>

<p style="max-width:500px;"><small><sup>[3]</sup> <?php echo __('API die zum Anlegen von Benutzern auf dem Fremd-Host, zur GUI-Integration etc. genutzt wird.'); ?></small></p>

<p style="max-width:500px;"><small><sup>[4]</sup> <?php echo sPrintF(__('Diese IP-Adresse wird beim Provisioning von Telefonen im WAN (also au&szlig;erhalb der LAN-Netze &quot;%s&quot;) die einen Registrar im LAN haben als Outbound Proxy gesetzt (d.h. inbound aus Sicht von Gemeinschaft). F&uuml;r keinen Proxy das Feld leer lassen.'), htmlEnt(gs_get_conf('GS_PROV_LAN_NETS'))); ?></small></p>

<p style="max-width:500px;"><small><sup>[5]</sup> <?php echo sPrintF(__('Diese IP-Adresse wird beim Provisioning von Telefonen im WAN (also au&szlig;erhalb der LAN-Netze &quot;%s&quot;) die einen Registrar im LAN haben als Server/Registrar gesetzt. (Mu&szlig; vom Session Border Controller auf den tats&auml;chlichen Server umgeleitet werden.) F&uuml;r den normalen Server das Feld leer lassen.'), htmlEnt(gs_get_conf('GS_PROV_LAN_NETS'))); ?></small></p>
