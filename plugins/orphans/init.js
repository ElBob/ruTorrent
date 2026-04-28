plugin.loadLang();
plugin.loadMainCSS();

plugin.lastScanTime = 0;
plugin.scanning     = false;

if(plugin.canChangeTabs())
{
	plugin.config = theWebUI.config;
	theWebUI.config = function()
	{
		plugin.attachPageToTabs(
			$('<div>').attr('id', 'orphans').addClass('table_tab').append(
				$('<span>').attr('id', 'orphans-status'),
				$('<button>').attr({ type: 'button', id: 'orphans-scan-btn' })
					.html('<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 256 256" xml:space="preserve"><g transform="translate(1.4065934065934016 1.4065934065934016) scale(2.81 2.81)"><path d="M 81.521 31.109 c -0.86 -1.73 -2.959 -2.438 -4.692 -1.575 c -1.73 0.86 -2.436 2.961 -1.575 4.692 c 2.329 4.685 3.51 9.734 3.51 15.01 C 78.764 67.854 63.617 83 45 83 S 11.236 67.854 11.236 49.236 c 0 -16.222 11.501 -29.805 26.776 -33.033 l -3.129 4.739 c -1.065 1.613 -0.62 3.784 0.992 4.85 c 0.594 0.392 1.264 0.579 1.926 0.579 c 1.136 0 2.251 -0.553 2.924 -1.571 l 7.176 -10.87 c 0.001 -0.001 0.001 -0.002 0.002 -0.003 l 0.018 -0.027 c 0.063 -0.096 0.106 -0.199 0.159 -0.299 c 0.049 -0.093 0.108 -0.181 0.149 -0.279 c 0.087 -0.207 0.152 -0.419 0.197 -0.634 c 0.009 -0.041 0.008 -0.085 0.015 -0.126 c 0.031 -0.182 0.053 -0.364 0.055 -0.547 c 0 -0.014 0.004 -0.028 0.004 -0.042 c 0 -0.066 -0.016 -0.128 -0.019 -0.193 c -0.008 -0.145 -0.018 -0.288 -0.043 -0.431 c -0.018 -0.097 -0.045 -0.189 -0.071 -0.283 c -0.032 -0.118 -0.065 -0.236 -0.109 -0.35 c -0.037 -0.095 -0.081 -0.185 -0.125 -0.276 c -0.052 -0.107 -0.107 -0.211 -0.17 -0.313 c -0.054 -0.087 -0.114 -0.168 -0.175 -0.25 c -0.07 -0.093 -0.143 -0.183 -0.223 -0.27 c -0.074 -0.08 -0.153 -0.155 -0.234 -0.228 c -0.047 -0.042 -0.085 -0.092 -0.135 -0.132 L 36.679 0.775 c -1.503 -1.213 -3.708 -0.977 -4.921 0.53 c -1.213 1.505 -0.976 3.709 0.53 4.921 l 3.972 3.2 C 17.97 13.438 4.236 29.759 4.236 49.236 C 4.236 71.714 22.522 90 45 90 s 40.764 -18.286 40.764 -40.764 C 85.764 42.87 84.337 36.772 81.521 31.109 z" fill="currentColor"/></g></svg>')
					.on('click', function() { plugin.doScan(); }),
				$('<div>').attr('id', 'orphans-total-wrap').append(
					$('<span>').text('Total Size: '),
					$('<span>').attr('id', 'orphans-total'),
				),
				$('<div>').attr('id', 'orphans-table'),
			).get(0),
			'Orphans',
			'lcont'
		);
		theWebUI.tables["orp"] =
		{
			obj:       new dxSTable(),
			container: "orphans-table",
			columns:
			[
				{ text: "Name",     width: "250px", id: "name",  type: TYPE_STRING },
				{ text: "Path",     width: "400px", id: "path",  type: TYPE_STRING },
				{ text: "Size",     width: "90px",  id: "size",  type: TYPE_NUMBER, align: ALIGN_RIGHT },
				{ text: "Modified", width: "120px", id: "mtime", type: TYPE_NUMBER },
			],
			format: function(table, arr)
			{
				for(var i in arr)
				{
					switch(table.getIdByCol(i))
					{
						case "size":
							arr[i] = theConverter.bytes(arr[i], 'table');
							break;
						case "mtime":
							arr[i] = arr[i] ? theConverter.date(iv(arr[i]) + theWebUI.deltaTime / 1000) : '';
							break;
					}
				}
				return arr;
			},
			onselect: function(e, id) { plugin.orphanSelect(e, id); },
		};
		plugin.config.call(this);
	};
}

if(plugin.canChangeOptions())
{
	plugin.addAndShowSettings = theWebUI.addAndShowSettings;
	theWebUI.addAndShowSettings = function(arg)
	{
		if(plugin.enabled)
		{
			$('#orphans_dirs').val(orphanScanDirs.join('\n'));
			$('#orphans_ignore').val(orphanIgnoreList.join('\n'));
		}
		plugin.addAndShowSettings.call(theWebUI, arg);
	};

	plugin.setSettings = theWebUI.setSettings;
	theWebUI.setSettings = function()
	{
		plugin.setSettings.call(this);
		if(plugin.enabled && plugin.orphansSettingsChanged())
			this.request("?action=setorphans");
	};

	plugin.orphansSettingsChanged = function()
	{
		var newDirs   = $('#orphans_dirs').val().split('\n').map(function(s) { return s.trim(); }).filter(function(s) { return s.length > 0; });
		var newIgnore = $('#orphans_ignore').val().split('\n').map(function(s) { return s.trim(); }).filter(function(s) { return s.length > 0; });
		return JSON.stringify(newDirs)   !== JSON.stringify(orphanScanDirs)
		    || JSON.stringify(newIgnore) !== JSON.stringify(orphanIgnoreList);
	};

	rTorrentStub.prototype.setorphans = function()
	{
		var dirs   = $('#orphans_dirs').val().split('\n').map(function(s) { return s.trim(); }).filter(function(s) { return s.length > 0; });
		var ignore = $('#orphans_ignore').val().split('\n').map(function(s) { return s.trim(); }).filter(function(s) { return s.length > 0; });
		this.content     = "cmd=setsettings&dirs=" + encodeURIComponent(JSON.stringify(dirs))
		                 + "&ignore=" + encodeURIComponent(JSON.stringify(ignore));
		this.contentType = "application/x-www-form-urlencoded";
		this.mountPoint  = "plugins/orphans/action.php";
		this.dataType    = "script";
	};
}

plugin.onLangLoaded = function()
{
	if(plugin.canChangeTabs())
	{
		plugin.renameTab('orphans', theUILang.orphansTab);
		var tbl = theWebUI.getTable("orp");
		if(tbl)
		{
			tbl.renameColumnById('name',  theUILang.orphansName);
			tbl.renameColumnById('path',  theUILang.orphansPath);
			tbl.renameColumnById('size',  theUILang.orphansSize);
			tbl.renameColumnById('mtime', theUILang.orphansMtime);
		}
	}

	if(plugin.canChangeOptions())
	{
		plugin.attachPageToOptions(
			$('<div>').attr({ id: 'st_orphans' }).append(
				$('<fieldset>').append(
					$('<legend>').text(theUILang.orphansSettings),
					$('<div>').addClass('mb-2').append(
						$('<label>').attr({ for: 'orphans_dirs' }).text(theUILang.orphansScanDirs),
						$('<textarea>').attr({ id: 'orphans_dirs', rows: 5 }).addClass('form-control'),
					),
					$('<div>').addClass('mb-2').append(
						$('<label>').attr({ for: 'orphans_ignore' }).text(theUILang.orphansIgnoreList),
						$('<textarea>').attr({ id: 'orphans_ignore', rows: 5 }).addClass('form-control'),
					),
				),
			).get(0),
			theUILang.orphansSettings,
		);
	}

	plugin.markLoaded();
};

plugin.langLoaded = function()
{
	if(plugin.enabled)
		plugin.onLangLoaded();
};

plugin.onShow = theTabs.onShow;
theTabs.onShow = function(id)
{
	if(id === 'orphans')
	{
		if(plugin.enabled && plugin.lastScanTime === 0)
			plugin.doScan();
	}
	else
		plugin.onShow.call(this, id);
};

plugin.doScan = function()
{
	if(plugin.scanning) return;
	plugin.scanning = true;
	$('#orphans-scan-btn').prop('disabled', true);
	$('#orphans-status').text(theUILang.orphansScanning);

	jQuery.ajax({
		type:     'GET',
		url:      'plugins/orphans/action.php',
		data:     { cmd: 'scan' },
		dataType: 'json',
		timeout:  120000,
		success:  plugin.onScanResult,
		error:    function() {
			$('#orphans-status').text('Scan failed.');
		},
		complete: function()
		{
			plugin.scanning = false;
			$('#orphans-scan-btn').prop('disabled', false);
		},
	});
};

plugin.onScanResult = function(data)
{
	plugin.lastScanTime = Date.now();
	var table = theWebUI.getTable("orp");
	if(!table) return;
	table.clearRows();
	$('#orphans-status').text('');
	$('#orphans-total').text('');
	$('#orphans-total-wrap').hide();

	if(!data || !data.orphans) return;

	if(data.truncated)
		$('#orphans-status').text(theUILang.orphansTruncated);

	if(data.orphans.length === 0)
	{
		if(!data.truncated)
			$('#orphans-status').text(theUILang.orphansEmpty);
		return;
	}

	var totalSize = 0;
	for(var i = 0; i < data.orphans.length; i++)
	{
		var f = data.orphans[i];
		totalSize += f.size;
		table.addRowById({ name: f.name, path: f.path, size: f.size, mtime: f.mtime }, f.path);
	}
	$('#orphans-total').text(theConverter.bytes(totalSize, 'table'));
	$('#orphans-total-wrap').show();
	table.refreshRows();
	if(table.sortId)
		table.Sort();
};

if(plugin.canChangeMenu())
{
	plugin.orphanSelect = function(e, id)
	{
		if(plugin.enabled && plugin.allStuffLoaded && (e.which === 3))
		{
			theContextMenu.clear();
			theContextMenu.add([theUILang.orphansDelete, "thePlugins.get('orphans').confirmDelete()"]);
			theContextMenu.add([theUILang.orphansIgnore, "thePlugins.get('orphans').addToIgnore()"]);
			theContextMenu.show(e.clientX, e.clientY);
		}
	};

	plugin.confirmDelete = function()
	{
		askYesNo(theUILang.orphansDelete, theUILang.orphansDeletePrompt, "thePlugins.get('orphans').doDelete()");
	};

	plugin.doDelete = function()
	{
		var table = theWebUI.getTable("orp");
		if(!table) return;
		var paths = Object.keys(table.rowSel).filter(function(k) { return table.rowSel[k]; });
		paths.forEach(function(path)
		{
			jQuery.ajax({
				type:     'POST',
				url:      'plugins/orphans/action.php',
				data:     { cmd: 'delete', path: path },
				dataType: 'json',
				success:  function(data)
				{
					if(data && data.ok)
						table.removeRow(path);
				},
			});
		});
	};

	plugin.addToIgnore = function()
	{
		var table = theWebUI.getTable("orp");
		if(!table) return;
		var paths = Object.keys(table.rowSel).filter(function(k) { return table.rowSel[k]; });
		paths.forEach(function(path)
		{
			jQuery.ajax({
				type:     'POST',
				url:      'plugins/orphans/action.php',
				data:     { cmd: 'ignore', path: path },
				dataType: 'json',
				success:  function(data)
				{
					if(data && data.ok)
					{
						orphanIgnoreList.push(path);
						table.removeRow(path);
					}
				},
			});
		});
	};
}
else
{
	plugin.orphanSelect = function() {};
}

plugin.onRemove = function()
{
	if(plugin.canChangeOptions())
		plugin.removePageFromOptions("st_orphans");
	if(plugin.canChangeTabs())
		plugin.removePageFromTabs("orphans");
	delete theWebUI.tables["orp"];
};
