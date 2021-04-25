/**
 * @param widgetId
 */
function EnableExpandAddon(widgetId) {
	let addon = $("#badge-widget-" + widgetId + "-addon"),
		hiddenBlock = $("#badge-widget-" + widgetId + "-hidden");
	addon.onclick(function(e) {
		hiddenBlock.show();
		e.show(false);
	})
}