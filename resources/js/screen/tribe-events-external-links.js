// Every event link on the archive/list view now points to the event's
// MyIGNITE page instead of an internal single-event page (see
// inc/functions/tribe-events.php). TEC's own list-view templates render
// several separate clickable elements per event (title, image, etc.)
// across template files this theme doesn't own the source of, so rather
// than guessing at each one, force target="_blank" on any link within the
// events view whose host differs from this site's — regardless of which
// TEC sub-template rendered it.
document.addEventListener("DOMContentLoaded", () => {
	const container = document.querySelector(".tribe-events");
	if (!container) return;

	container.querySelectorAll('a[href^="http"]').forEach((link) => {
		if (link.hostname !== window.location.hostname) {
			link.setAttribute("target", "_blank");
			link.setAttribute("rel", "noopener noreferrer");
		}
	});
});
