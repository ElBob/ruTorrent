<?php
require_once( dirname(__FILE__)."/../../php/cache.php" );

class rOrphanConf
{
	public $hash        = "orphans_conf.dat";
	public $scan_dirs   = array();
	public $ignore_list = array();

	static public function load()
	{
		$cache = new rCache();
		$rt = new rOrphanConf();
		$cache->get($rt);
		return($rt);
	}

	public function store()
	{
		$cache = new rCache();
		return($cache->set($this));
	}

	public function set( $dirs, $ignore_list )
	{
		$this->scan_dirs   = $dirs;
		$this->ignore_list = $ignore_list;
		$this->store();
	}

	public function get()
	{
		return( "orphanScanDirs="   . json_encode($this->scan_dirs)   . ";\n"
		      . "orphanIgnoreList=" . json_encode($this->ignore_list) . ";\n" );
	}
}
