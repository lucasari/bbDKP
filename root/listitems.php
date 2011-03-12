<?php
/**
 * List Items
 * 
 * @package bbDKP
 * @copyright 2009 bbdkp <http://code.google.com/p/bbdkp/>
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * 
 */

/**
 * @ignore
 */
define ( 'IN_PHPBB', true );
$phpbb_root_path = (defined ( 'PHPBB_ROOT_PATH' )) ? PHPBB_ROOT_PATH : './';
$phpEx = substr ( strrchr ( __FILE__, '.' ), 1 );
include ($phpbb_root_path . 'common.' . $phpEx);

// Start session management
$user->session_begin ();
$auth->acl ( $user->data );
$user->setup ();
$user->add_lang ( array ('mods/dkp_common' ) );
if (!$auth->acl_get('u_dkp'))
{
	redirect(append_sid("{$phpbb_root_path}portal.$phpEx"));
}
if (! defined ( "EMED_BBDKP" ))
{
	trigger_error ( $user->lang ['BBDKPDISABLED'], E_USER_WARNING );
}

$bbDKP_Admin = new bbDKP_Admin();
if ($bbDKP_Admin->bbtips == true)
{
	if (! class_exists ( 'bbtips' ))
	{
		require ($phpbb_root_path . 'includes/bbdkp/bbtips/parse.' . $phpEx);
	}
	$bbtips = new bbtips ( );
}

/**** begin dkpsys pulldown  ****/
$query_by_pool = false;
$defaultpool = 99;

$dkpvalues [0] = $user->lang ['ALL'];
$dkpvalues [1] = '--------';
// select only those dkp pools where there are item drops
$sql_array = array(
	'SELECT'    => 'a.dkpsys_id, a.dkpsys_name, a.dkpsys_default', 
	'FROM'		=> array( 
				DKPSYS_TABLE => 'a', 
				EVENTS_TABLE => 'e',
				RAIDS_TABLE => 'r',
				RAID_ITEMS_TABLE => 'ri',
				), 
	'WHERE'  => ' a.dkpsys_id = e.event_dkpid and e.event_id=r.event_id and ri.raid_id = r.raid_id', 
	'GROUP_BY'  => 'a.dkpsys_id'
);
$sql = $db->sql_build_query('SELECT', $sql_array);
$result = $db->sql_query ( $sql );
$index = 3;
while ( $row = $db->sql_fetchrow ( $result ) )
{
	$dkpvalues [$index] ['id'] = $row ['dkpsys_id'];
	$dkpvalues [$index] ['text'] = $row ['dkpsys_name'];
	if (strtoupper ( $row ['dkpsys_default'] ) == 'Y')
	{
		$defaultpool = $row ['dkpsys_id'];
	}
	$index += 1;
}
$db->sql_freeresult ( $result );

$dkp_id = 0;
if (isset ( $_POST ['pool'] ) or isset ( $_POST ['getdksysid'] ) or isset ( $_GET [URI_DKPSYS] ))
{
	if (isset ( $_POST ['pool'] ))
	{
		$pulldownval = request_var ( 'pool', $user->lang ['ALL'] );
		if (is_numeric ( $pulldownval ))
		{
			$query_by_pool = true;
			$dkp_id = intval ( $pulldownval );
		}
	}
	elseif (isset ( $_GET [URI_DKPSYS] ))
	{
		$query_by_pool = true;
		$dkp_id = request_var ( URI_DKPSYS, 0 );
	}
}
else 
{
	$query_by_pool = true;
	$dkp_id = $defaultpool; 
}

foreach ( $dkpvalues as $key => $value )
{
	if (! is_array ( $value ))
	{
		$template->assign_block_vars ( 'pool_row', array (
			'VALUE' => $value, 
			'SELECTED' => ($value == $dkp_id && $value != '--------') ? ' selected="selected"' : '', 
			'DISABLED' => ($value == '--------') ? ' disabled="disabled"' : '', 
			'OPTION' => $value ) );
	} else
	{
		$template->assign_block_vars ( 'pool_row', array (
			'VALUE' => $value ['id'], 
			'SELECTED' => ($dkp_id == $value ['id']) ? ' selected="selected"' : '', 
			'OPTION' => $value ['text'] ) );
	
	}
}

$query_by_pool = ($dkp_id != 0) ? true : false;
/**** end dkpsys pulldown  ****/

/**
 *
 * Item Purchase History (all items)
 * Item Value listing  (item values)
 *
 **/
$mode = request_var ( 'page', 'values' );

$sort_order = array (
	0 => array ('item_date desc', 'item_date' ), 
	1 => array ('item_buyer', 'item_buyer desc' ), 
	2 => array ('item_name', 'item_name desc' ), 
	3 => array ('event_name', 'event_name desc' ), 
	4 => array ('item_value desc', 'item_value' ) );
$current_order = switch_order ($sort_order);

$sql_array = array();
switch ($mode)
{
	case 'values' :
		$u_list_items = append_sid ( "{$phpbb_root_path}listitems.$phpEx" );
		$sql_array['SELECT'] = ' COUNT(DISTINCT item_name) as itemcount ' ;
		$s_history = false;
		break;
	case 'history' :
		$u_list_items = append_sid ( "{$phpbb_root_path}listitems.$phpEx", 'page=history' );
		$sql_array['SELECT'] = ' COUNT(*) as itemcount ' ;
		$s_history = true;
		break;
}

$sql_array['FROM'] = array( 	
	EVENTS_TABLE 		=> 'e', 
    RAIDS_TABLE 		=> 'r', 
	RAID_ITEMS_TABLE 		=> 'i', 
);
$sql_array['WHERE'] = ' e.event_id = r.event_id AND r.raid_id = i.raid_id '; 
if ($query_by_pool)
{
	$sql_array['WHERE'] .= ' AND event_dkpid = ' . $dkp_id . ' ';
}
$sql = $db->sql_build_query('SELECT', $sql_array);
$result = $db->sql_query ( $sql);
$total_items = $db->sql_fetchfield ( 'itemcount', 1, $result);
$db->sql_freeresult ($result);

$start = request_var ( 'start', 0 );

switch ($mode)
{
	case 'values' :
		$listitems_footcount = sprintf ( $user->lang ['LISTITEMS_FOOTCOUNT'], $total_items, $config ['bbdkp_user_ilimit'] );
		break;
	case 'history' :
		$listitems_footcount = sprintf ( $user->lang ['LISTPURCHASED_FOOTCOUNT'], $total_items, $config ['bbdkp_user_ilimit'] );
		break;
}

if ($query_by_pool)
{
	$pagination = generate_pagination ( append_sid ( "{$phpbb_root_path}listitems.$phpEx", 
	'page=' . $mode . '&amp;' . URI_DKPSYS . '=' . $dkp_id . '&amp;o=' . $current_order ['uri'] ['current'] ), 
	$total_items, $config ['bbdkp_user_ilimit'], $start, true );
} 
else
{
	$pagination = generate_pagination ( append_sid ( "{$phpbb_root_path}listitems.$phpEx", 
	'page=' . $mode . '&amp;' . URI_DKPSYS . '=All&amp;o=' . $current_order ['uri'] ['current'] ), 
	$total_items, $config ['bbdkp_user_ilimit'], $start, true );
}

switch ($mode)
{
	case 'values' :
		$sql_array = array (
			'SELECT' => '
				e.event_dkpid, e.event_name, 
				i.item_id, i.item_name, i.member_id, i.item_gameid, i.item_date, 
				i.raid_id, min(i.item_value) AS item_value,  i.item_decay, i.item_value - i.item_decay as item_total  ', 
			'FROM' => array (
				EVENTS_TABLE => 'e', 
				RAIDS_TABLE => 'r', 
				RAID_ITEMS_TABLE => 'i', 
				), 
			'WHERE' => ' r.event_id = e.event_id AND i.raid_id = r.raid_id', 
			'GROUP_BY' => 'i.item_name', 
			'ORDER_BY' => $current_order ['sql'] );
		
		break;
	
	case 'history' :
		$sql_array = array (
			'SELECT' => '
				 e.event_dkpid, e.event_name,  
				 i.raid_id, i.item_value, i.item_gameid, i.item_id, i.item_name, i.item_date, i.member_id, 
				 i.item_decay, i.item_value - i.item_decay as item_total, 
				 l.member_name, c.colorcode, c.imagename, c.class_id, a.image_female_small, a.image_male_small ', 
    		'FROM' => array (
				EVENTS_TABLE => 'e', 
				RAIDS_TABLE => 'r', 
				RAID_ITEMS_TABLE => 'i', 
			    CLASS_TABLE	=> 'c', 
		        RACE_TABLE  		=> 'a',
				MEMBER_LIST_TABLE => 'l'), 

			'WHERE' => ' e.event_id = r.event_id  
					AND r.raid_id = i.raid_id
    				AND i.member_id = l.member_id
           			AND l.member_class_id = c.class_id
           			AND l.member_race_id =  a.race_id ', 
           	'ORDER_BY' => $current_order ['sql']);
		
		break;
}

if ($query_by_pool)
{
	$sql_array ['WHERE'] .= ' AND e.event_dkpid = ' . $dkp_id . ' ';
}

$sql = $db->sql_build_query ( 'SELECT', $sql_array );
$items_result = $db->sql_query_limit ( $sql, $config ['bbdkp_user_ilimit'], $start );

// Regardless of which listitem page they're on, we're essentially 
// outputting the same stuff. Purchase History just has a buyer column.
if (! $items_result)
{
	$user->add_lang ( array ('mods/dkp_admin' ) );
	trigger_error ( $user->lang ['ERROR_INVALID_ITEM_PROVIDED'], E_USER_WARNING );
}

$number_items = 0;
$item_value = 0.00;
$item_decay = 0.00;
$item_total = 0.00;

while ( $item = $db->sql_fetchrow ( $items_result ) )
{
	
	if ($bbDKP_Admin->bbtips == true)
	{

		if ($item['item_gameid'] > 0 )
		{
			$valuename = $bbtips->parse('[itemdkp]' . $item['item_gameid']  . '[/itemdkp]'); 
		}
		else 
		{
			$valuename = $bbtips->parse ( '[itemdkp]' . $item ['item_name'] . '[/itemdkp]' );
		}
				
	} 
	else
	{
		$valuename = $item ['item_name'];
	}


	if ($mode == 'history')
	{
		$race_image = (string) (($row['member_gender_id']==0) ? $row['image_male_small'] : $row['image_female_small']);
		
		$template->assign_block_vars ( 'items_row', array (
			'DATE' 			=> (! empty ( $item ['item_date'] )) ? date ( 'd.m.y', $item ['item_date'] ) : '&nbsp;', 
			'ITEMNAME' 		=> $valuename, 
			'U_VIEW_ITEM' 	=> append_sid ( "{$phpbb_root_path}viewitem.$phpEx", URI_ITEM . '=' . $item ['item_id'] ), 
			'RAID' 			=> (! empty ( $item ['event_name'] )) ? $item ['event_name'] : '&lt;<i>Not Found</i>&gt;', 
			'U_VIEW_RAID' 	=> append_sid ( "{$phpbb_root_path}viewraid.$phpEx", URI_RAID . '=' . $item ['raid_id'] ), 
			
			'ITEM_ZS'      	=> ($row['item_zs'] == 1) ? ' checked="checked"' : '',
			'ITEMVALUE' 	=> $row['item_value'],
			'DECAYVALUE' 	=> $row['item_decay'],
			'TOTAL' 		=> $row['item_total'],
			'BUYER' 		=> $item ['member_name'], 
			'CLASSCOLOR' 	=> $item['colorcode'], 
			'CLASSIMAGE' 	=> $item['imagename'],          
			'CSSCLASS' 		=> $config ['bbdkp_default_game'] . 'class' . $item ['class_id'],
			'U_VIEW_BUYER' 	=> append_sid ( "{$phpbb_root_path}viewmember.$phpEx", URI_NAMEID . '=' . $item ['member_id'] . '&amp;' . URI_DKPSYS . '=' . $item ['event_dkpid'] ), 
			'RACE_IMAGE' 	=> (strlen($race_image) > 1) ? $phpbb_root_path . "images/race_images/" . $race_image . ".png" : '',  
			'S_RACE_IMAGE_EXISTS' => (strlen($race_image) > 1) ? true : false, 		
		));	
	}
	else 
	{
		$template->assign_block_vars ( 'items_row', array (
			'DATE' 			=> (! empty ( $item ['item_date'] )) ? date ( 'd.m.y', $item ['item_date'] ) : '&nbsp;', 
			'ITEMNAME' 		=> $valuename, 
			'U_VIEW_ITEM' 	=> append_sid ( "{$phpbb_root_path}viewitem.$phpEx", URI_ITEM . '=' . $item ['item_id'] ), 
			'RAID' 			=> (! empty ( $item ['event_name'] )) ? $item ['event_name'] : '&lt;<i>Not Found</i>&gt;', 
			'U_VIEW_RAID' 	=> append_sid ( "{$phpbb_root_path}viewraid.$phpEx", URI_RAID . '=' . $item ['raid_id'] ), 
			
			'ITEM_ZS'      	=> ($row['item_zs'] == 1) ? ' checked="checked"' : '',
			'ITEMVALUE' 	=> $row['item_value'],
			'DECAYVALUE' 	=> $row['item_decay'],
			'TOTAL' 		=> $row['item_total'],
		));	
		
	}
		
	$number_items++; 
	$item_value += $row['item_value'];
	$item_decay += $row['item_decay'];
	$item_total += $row['item_total'];		

}
$db->sql_freeresult ( $items_result );

$sortlink = array ();
for($i = 0; $i <= 4; $i ++)
{
	if ($query_by_pool)
	{
		$sortlink [$i] = append_sid ( "{$phpbb_root_path}listitems.$phpEx", 'page=' . $mode .
		 '&amp;o=' . $current_order ['uri'] [$i] . '&amp;start=' . $start . '&amp;' . URI_DKPSYS . '=' . $dkp_id );
	} else
	{
		$sortlink [$i] = append_sid ( "{$phpbb_root_path}listitems.$phpEx", 'page=' . $mode .
		 '&amp;o=' . $current_order ['uri'] [$i] . '&amp;start=' . $start . '&amp;' . URI_DKPSYS . '=All' );
	}
}

// breadcrumbs menu                                      
$navlinks_array = array (
	array (
	'DKPPAGE' => ($mode == 'history') ? $user->lang ['MENU_ITEMHIST'] : $user->lang ['MENU_ITEMVAL'], 
	'U_DKPPAGE' => $u_list_items ) );

foreach ( $navlinks_array as $name )
{
	$template->assign_block_vars ( 'dkpnavlinks', array (
	'DKPPAGE' => $name ['DKPPAGE'], 
	'U_DKPPAGE' => $name ['U_DKPPAGE'] ) );
}

$template->assign_vars ( array (
	'F_LISTITEM' => $u_list_items, 
	'O_DATE' => $sortlink [0], 
	'O_BUYER' => $sortlink [1], 
	'O_NAME' => $sortlink [2], 
	'O_RAID' => $sortlink [3], 
	'O_VALUE' => $sortlink [4], 
	'S_HISTORY' => $s_history, 
	'LISTITEMS_FOOTCOUNT' => $listitems_footcount, 
	'ITEM_PAGINATION' => $pagination ) 
);

$title = ($mode == 'history') ? $user->lang ['MENU_ITEMHIST'] : $user->lang ['MENU_ITEMVAL'];

// Output page
page_header ( $title );
$template->set_filenames ( array ('body' => 'dkp/listitems.html' ) );
page_footer ();
?>