/**
 * @param widgetId
 */
function EnableExpandAddon(widgetId) {
	let addon = $("#badge-widget-" + widgetId + "-addon"),
		hiddenBlock = $("#badge-widget-" + widgetId + "-hidden");
	addon.on("click", function(e) {
		addon.replaceWith(hiddenBlock.html());
		hiddenBlock.remove();
	})
}