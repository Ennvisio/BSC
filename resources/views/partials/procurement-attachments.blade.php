{{-- Documents for the stage being worked on right now.

     Files upload to the user's own library immediately (the step row doesn't
     exist until the stage completes), and their ids ride along as hidden
     inputs until it does - the same approach the requisition wizard uses for
     rows that haven't been saved yet. Multiple files, one at a time. --}}
<div class="proc-field full proc-docs">
	<label>
		{{ $stageLabel }} documents <span class="opt">optional</span>
	</label>

	<div class="proc-doc-list" id="proc-doc-list"></div>

	<div class="proc-doc-add">
		<input type="text" class="form-control" id="proc-doc-title" placeholder="Document title">
		{{-- Visually hidden rather than display:none - a display:none file input
			 is out of the render tree, and some browsers then ignore a
			 programmatic .click() on it entirely. --}}
		<input type="file" id="proc-doc-file"
			style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0;">
		<button type="button" class="btn btn-secondary" id="proc-doc-browse">
			<i class="fas fa-paperclip"></i> Choose file
		</button>
		<span class="proc-doc-chosen" id="proc-doc-chosen"></span>
		<button type="button" class="btn btn-info" id="proc-doc-upload" disabled>Add</button>
	</div>
	<div class="proc-doc-error" id="proc-doc-error"></div>
</div>
