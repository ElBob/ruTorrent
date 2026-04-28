plugin.loadLang();

if (plugin.canChangeMenu()) {
	plugin.createMenu = theWebUI.createMenu;
	theWebUI.createMenu = function(e, id) {
		plugin.createMenu.call(this, e, id);
		if (plugin.enabled) {
			const hashes = this.getTable("trt").getSelected();
			const autotoolsReady = thePlugins.isInstalled("autotools") &&
				theWebUI.autotools && theWebUI.autotools.EnableMove === 1;
			theWebUI.sortSelectedHashes = hashes;
			theContextMenu.add([theUILang.sort,
				(hashes.length && autotoolsReady) ? "theWebUI.sortTorrents(theWebUI.sortSelectedHashes)" : null
			]);
		}
	};
}

theWebUI.sortTorrents = function(hashes) {
	$.post("plugins/sort/action.php",
		{ cmd: "sort", "hash[]": hashes },
		function(data) {
			if (data.result === "ok" && data.status === "moved")
				noty(theUILang.sortSuccess, "success");
			else if (data.result === "ok" && data.status === "skipped")
				noty(theUILang.sortSkipped, "info");
			else
				noty(theUILang.sortFailed + (data.error ? ": " + data.error : ""), "error");
		},
		"json"
	).fail(function(xhr, status) {
		noty(theUILang.sortFailed + ": " + status, "error");
	});
};

plugin.onLangLoaded = function() {
	plugin.markLoaded();
};

plugin.langLoaded = function() {
	plugin.onLangLoaded();
};
