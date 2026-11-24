{{-- One document on a completed procurement stage.

     PD-22 (approved 2026-11-24): procurement documents are the office's. On
     a vessel's own server an office document is a read-only copy: it can be
     opened and downloaded, never changed or removed there (refused on the
     server as well, App\Sync\WrongSideEdits). --}}
@php
	$officeCopy = ($doc->owner_side ?? null) === \App\Attachment::OFFICE && app(\App\Support\AppMode::class)->isVessel();
@endphp
<a href="{{ url('/attachments/'.$doc->id.'/view') }}" target="_blank" rel="noopener" class="t-doc">
	<i class="fas fa-paperclip"></i> {{ $doc->title }}
</a>
@if($officeCopy)
<a href="{{ url('/attachments/'.$doc->id.'/view?download=1') }}" class="t-doc t-doc-download" title="Download a copy" aria-label="Download {{ $doc->title }}"><i class="fas fa-download"></i></a>
<span class="t-doc-readonly" title="Maintained by the office. It can be viewed and downloaded on board, not changed."
	style="font-size:11px; color:#6c757d; font-style:italic;">Office document, view only</span>
@endif
