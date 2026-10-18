{{-- Toggle behaviour for the collapsible sidebar groups (.srd-nav-group).
     Which group starts open is decided by the server: only the one holding the
     page you're on. Nothing is remembered between pages - a remembered
     "open" used to leave a second group expanded on a page that isn't its
     own. Opening a group closes the others, so at most one is open at a time. --}}
<script>
(function () {
	var groups = document.querySelectorAll('.srd-nav-group');

	function setOpen(group, open) {
		group.classList.toggle('is-open', open);
		group.querySelector('.srd-nav-group-toggle').setAttribute('aria-expanded', open ? 'true' : 'false');
	}

	groups.forEach(function (group) {
		group.querySelector('.srd-nav-group-toggle').addEventListener('click', function () {
			var willOpen = !group.classList.contains('is-open');
			groups.forEach(function (other) { setOpen(other, false); });
			setOpen(group, willOpen);
		});
	});
})();
</script>
