<?php

require_once('../../php/xmlrpc.php');
require_once(dirname(__FILE__).'/../autotools/autotools.php');
eval(FileUtil::getPluginConf('autotools'));

$ret = array("result" => "error", "error" => "invalid request");

if (isset($_REQUEST['cmd']) && $_REQUEST['cmd'] === 'sort' && !empty($_REQUEST['hash']))
{
	$hashes      = (array) $_REQUEST['hash'];
	$at          = rAutoTools::load();
	$php         = Utility::getPHP();
	$user        = User::getUser();
	$check       = dirname(__FILE__).'/../autotools/check.php';
	$move        = dirname(__FILE__).'/../autotools/move.php';
	$theSettings = rTorrentSettings::get();
	$errors      = array();
	$skipped     = array();

	foreach ($hashes as $hash)
	{
		$req = new rXMLRPCRequest(array(
			new rXMLRPCCommand("d.get_base_path",     $hash),
			new rXMLRPCCommand("d.get_base_filename", $hash),
			new rXMLRPCCommand("d.is_multi_file",     $hash),
			new rXMLRPCCommand("d.get_custom1",       $hash),
			new rXMLRPCCommand("d.get_name",          $hash),
		));
		if (!$req->success())
		{
			$errors[] = $hash.": rtorrent query failed";
			continue;
		}

		$base_path = $req->val[0];
		$base_name = $req->val[1];
		$is_multi  = $req->val[2];
		$label     = rawurlencode($req->val[3]);
		$name      = $req->val[4];

		// d.close clears the "open" state so d.set_directory_base is not blocked
		$closeReq = new rXMLRPCRequest(new rXMLRPCCommand("d.close", $hash));
		$closeReq->run();

		if ($at->fileop_type == "Move" && $theSettings->iVersion >= 0x808)
		{
			$checkReq = new rXMLRPCRequest(new rXMLRPCCommand("execute_capture",
				array($php, $check, $base_path, $base_name, $is_multi, $label, $name, $user, $hash)
			));
			if ($checkReq->run() && !empty($checkReq->val[0]))
			{
				$newBase = trim($checkReq->val[0]);
				$setReq = new rXMLRPCRequest(new rXMLRPCCommand("d.set_directory_base",
					array($hash, $newBase)
				));
				$setReq->run();
			}
		}

		$moveReq = new rXMLRPCRequest(new rXMLRPCCommand("execute_capture",
			array($php, $move, $hash, $base_path, $base_name, $is_multi, $label, $name, $user)
		));
		if ($moveReq->run())
		{
			if (empty(trim($moveReq->val[0] ?? '')))
				$skipped[] = $hash;
			else
			{
				$stopReq = new rXMLRPCRequest(new rXMLRPCCommand("d.stop", $hash));
				$stopReq->run();
				$startReq = new rXMLRPCRequest(new rXMLRPCCommand("d.start", $hash));
				$startReq->run();
			}
		}
		else
			$errors[] = $hash.": execute failed";
	}

	if (!empty($errors))
		$ret = array("result" => "error", "error" => implode(", ", $errors));
	elseif (count($skipped) === count($hashes))
		$ret = array("result" => "ok", "status" => "skipped");
	else
		$ret = array("result" => "ok", "status" => "moved");
}

CachedEcho::send(JSON::safeEncode($ret), "application/json");
