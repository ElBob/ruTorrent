<?php

if( !chdir( dirname( __FILE__) ) )
	exit();

if( count( $argv ) > 6 )
	$_SERVER['REMOTE_USER'] = $argv[6];

require_once( "./util_rt.php" );
require_once( "./autotools.php" );
eval( FileUtil::getPluginConf( 'autotools' ) );

//------------------------------------------------------------------------------
function Debug( $str )
{
	global $autodebug_enabled;
	if( $autodebug_enabled ) rtDbg( "AutoCheck", $str );
}

$base_path = $argv[1];
$base_name = $argv[2];
$is_multy  = $argv[3];
$label     = UTF::raw_url_decode($argv[4]);
$name      = $argv[5];
$hash      = isset($argv[7]) ? $argv[7] : '';

Debug( "" );
Debug( "--- begin ---" );
Debug( "hash            : ".$hash );
Debug( "base_path       : ".$base_path );
Debug( "label           : ".$label );

$base_path = rtRemoveTailSlash( $base_path );
$base_path = rtRemoveLastToken( $base_path, '/' );	// filename or dirname
$base_path = rtAddTailSlash( $base_path );
$dest_path = $base_path;
$at = rAutoTools::load();
if( $at->enable_move && (@preg_match($at->automove_filter.'u',$label)==1) )
{
	$path_to_finished = trim( $at->path_to_finished );
	if( $path_to_finished != '' )
	{
		$path_to_finished = rtAddTailSlash( $path_to_finished );
		$directory    = rTorrentSettings::get()->directory;
		if(!empty($directory))
		{
			$directory = rtAddTailSlash( $directory );
			$rel_path = rtGetRelativePath( $directory, $base_path );
			//------------------------------------------------------------------------------
			// !! this is a feature !!
			// ($rel_path == '') means, that $base_path is NOT a SUBDIR of $directory at all
			// so, we have to skip all automove actions
			// for example, if we don't want torrent to be automoved - we save it out of $directory subtree
			//------------------------------------------------------------------------------
			if( $rel_path != '' )
			{
				if( $rel_path == './' ) $rel_path = '';
				$dest_path = rtAddTailSlash( $path_to_finished.$rel_path );
				if($at->addLabel && ($label!=''))
	        			$dest_path.=FileUtil::addslash($label);
				if($at->addTracker && !empty($hash))
				{
					$session = rTorrentSettings::get()->session;
					if(!empty($session))
					{
						$fname = rtAddTailSlash($session).$hash.".torrent";
						Debug( "torrent file    : ".$fname );
						if(is_readable($fname))
						{
							$torrent = new Torrent($fname);
							if(!$torrent->errors())
							{
								$tracker_url = $torrent->announce();
								if(empty($tracker_url))
								{
									$announce_list = $torrent->announce_list();
									if(!empty($announce_list))
										$tracker_url = $announce_list[0][0];
								}
								Debug( "tracker url     : ".$tracker_url );
								$tracker_dir = rtGetTrackerDomain($tracker_url);
								if(!empty($tracker_dir))
									$dest_path .= FileUtil::addslash($tracker_dir);
								Debug( "tracker dir     : ".$tracker_dir );
							}
							else
								Debug( "torrent parse error" );
						}
						else
							Debug( "torrent file not readable: ".$fname );
					}
				}
		        	if($at->addName && ($name!=''))
					$dest_path.=FileUtil::addslash($name);
			}
		}
	}
}

if( $is_multy )
	$sub_dir = rtAddTailSlash( $base_name );	// $base_file - is a directory
else
	$sub_dir = '';					// $base_file - is really a file
$dest_path.=$sub_dir;
Debug( "dest_path       : ".$dest_path );
Debug( "--- end ---" );
echo $dest_path;
