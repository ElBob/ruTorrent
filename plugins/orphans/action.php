<?php
require_once( '../../php/xmlrpc.php' );
require_once( 'orphans_conf.php' );

eval( FileUtil::getPluginConf( 'orphans' ) );

function Debug( $str )
{
	global $orphans_debug;
	if( $orphans_debug ) FileUtil::toLog( "Orphans: " . $str );
}

$conf = rOrphanConf::load();
$cmd  = isset($_REQUEST['cmd']) ? $_REQUEST['cmd'] : '';

switch($cmd)
{
	case 'scan':
	{
		$tracked  = array();
		$t_xmlrpc = microtime(true);

		// Fetch all torrents: hash, base_path, is_multi_file
		$dmcmd = new rXMLRPCCommand("d.multicall", "main");
		$dmcmd->addParameters(array_map("getCmd", array("d.get_hash=", "d.get_base_path=", "d.is_multi_file=")));
		$req = new rXMLRPCRequest($dmcmd);

		if($req->success(true))
		{
			$cnt      = 3;
			$torrents = array();
			foreach($req->val as $index => $value)
			{
				if($index % $cnt == 0)
				{
					$current_hash          = $value;
					$torrents[$current_hash] = array();
				}
				else
					$torrents[$current_hash][] = $value;
			}

			foreach($torrents as $hash => $fields)
			{
				if(count($fields) < 2) continue;
				$base_path = $fields[0];
				$is_multi  = ($fields[1] != 0);

				if(!$is_multi)
				{
					$real = realpath($base_path);
					if($real !== false)
						$tracked[$real] = true;
				}
				else
				{
					$fcmd = new rXMLRPCCommand("f.multicall", array($hash, ""));
					$fcmd->addParameter(getCmd("f.get_path="));
					$freq = new rXMLRPCRequest($fcmd);
					if($freq->success(true))
					{
						$torrent_dir = rtrim($base_path, '/');
						foreach($freq->val as $f_path)
						{
							$real = realpath($torrent_dir . '/' . $f_path);
							if($real !== false)
								$tracked[$real] = true;
						}
					}
				}
			}
		}

		foreach($conf->ignore_list as $p)
			$tracked[$p] = true;

		Debug( "XMLRPC took " . round((microtime(true) - $t_xmlrpc) * 1000) . "ms, " . count($tracked) . " tracked files (incl. ignored)" );

		$scan_dirs = empty($conf->scan_dirs)
			? array(rTorrentSettings::get()->directory)
			: $conf->scan_dirs;

		Debug( "Scanning dirs: " . implode(', ', $scan_dirs) );

		$orphans   = array();
		$truncated = false;
		$total     = 0;
		$t_scan    = microtime(true);

		foreach($scan_dirs as $dir)
		{
			if(!is_dir($dir)) continue;
			scanForOrphans($dir, $tracked, $orphans, $orphans_max_files, $total, $truncated);
		}

		Debug( "Scan took " . round((microtime(true) - $t_scan) * 1000) . "ms, " . $total . " files checked, " . count($orphans) . " orphans" );

		CachedEcho::send(JSON::safeEncode(array('orphans' => $orphans, 'truncated' => $truncated)), "application/json");
		break;
	}

	case 'delete':
	{
		$path = isset($_POST['path']) ? $_POST['path'] : '';
		$real = realpath($path);

		if($real === false || !is_file($real))
		{
			CachedEcho::send(JSON::safeEncode(array('ok' => false, 'error' => 'Invalid path')), "application/json");
			break;
		}

		$scan_dirs = empty($conf->scan_dirs)
			? array(rTorrentSettings::get()->directory)
			: $conf->scan_dirs;

		$allowed = false;
		foreach($scan_dirs as $dir)
		{
			$real_dir = realpath($dir);
			if($real_dir !== false && strpos($real, rtrim($real_dir, '/') . '/') === 0)
			{
				$allowed = true;
				break;
			}
		}

		if(!$allowed)
		{
			CachedEcho::send(JSON::safeEncode(array('ok' => false, 'error' => 'Path not in scan dirs')), "application/json");
			break;
		}

		$ok = @unlink($real);
		CachedEcho::send(JSON::safeEncode(array('ok' => $ok)), "application/json");
		break;
	}

	case 'ignore':
	{
		$path = isset($_POST['path']) ? $_POST['path'] : '';
		$ok   = false;
		if($path !== '')
		{
			if(!in_array($path, $conf->ignore_list))
			{
				$conf->ignore_list[] = $path;
				$conf->store();
			}
			$ok = true;
		}
		CachedEcho::send(JSON::safeEncode(array('ok' => $ok)), "application/json");
		break;
	}

	case 'setsettings':
	{
		$dirs_json   = isset($_POST['dirs'])   ? $_POST['dirs']   : '[]';
		$ignore_json = isset($_POST['ignore']) ? $_POST['ignore'] : '[]';
		$dirs        = json_decode($dirs_json, true);
		$ignore      = json_decode($ignore_json, true);
		if(!is_array($dirs))   $dirs   = array();
		if(!is_array($ignore)) $ignore = array();
		$dirs   = array_values(array_filter(array_map('trim', $dirs),   function($d) { return strlen($d) > 0; }));
		$ignore = array_values(array_filter(array_map('trim', $ignore), function($d) { return strlen($d) > 0; }));

		$conf->set($dirs, $ignore);
		CachedEcho::send($conf->get(), "application/javascript");
		break;
	}
}

function scanForOrphans($dir, &$tracked, &$orphans, $max, &$total, &$truncated)
{
	if($total >= $max) { $truncated = true; return; }
	$handle = @opendir($dir);
	if(!$handle) return;
	$dir = rtrim($dir, '/') . '/';
	while(false !== ($item = readdir($handle)))
	{
		if($item === '.' || $item === '..') continue;
		$full = $dir . $item;
		if(is_dir($full))
		{
			scanForOrphans($full, $tracked, $orphans, $max, $total, $truncated);
		}
		elseif(is_file($full))
		{
			$real = realpath($full);
			if($real !== false && !isset($tracked[$real]))
			{
				$st = @stat($real);
				$orphans[] = array(
					'name'  => $item,
					'path'  => $real,
					'size'  => $st ? floatval($st['size']) : 0,
					'mtime' => $st ? intval($st['mtime'])  : 0,
				);
			}
			$total++;
			if($total >= $max) { $truncated = true; break; }
		}
	}
	closedir($handle);
}
