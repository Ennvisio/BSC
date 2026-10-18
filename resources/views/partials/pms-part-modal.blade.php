{{-- Add / edit one required part, from the catalogue or by hand. Needs
	 $manufacturers; driven by partials.pms-part-script (window.PmsPartForm). --}}
<div class="modal fade" id="pms-part-modal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<form id="pms-part-form" autocomplete="off" novalidate>
				<div class="modal-header justify-content-between" style="background:#579eb9; color:#fff;">
					<legend class="modal-title"><i class="fas fa-cubes"></i> &nbsp; <span id="pp-title">Add Part</span></legend>
					<button type="button" class="close" data-dismiss="modal" style="color:#fff;">&times;</button>
				</div>
				<div class="modal-body">
					<div class="form-group pp-search-wrap">
						<label for="pp-search">Find in the items catalogue</label>
						<input type="search" class="form-control" id="pp-search" placeholder="Name, article, part or drawing number…">
						<div class="pp-results pms-hidden" id="pp-results" role="listbox"></div>
						<small class="form-text pms-muted">Picking an item fills the details below. Leave this empty to enter a part by hand &mdash; a hand-entered part can't be put on a requisition until it is in the catalogue.</small>
					</div>
					<input type="hidden" id="pp-item-id" name="item_id">
					<p class="pms-note pms-hidden mb-3" id="pp-linked"><i class="fas fa-link"></i> Catalogue item <button type="button" class="btn btn-sm btn-link p-0 ml-2" id="pp-unlink">Enter by hand instead</button></p>

					<div class="pms-form-grid">
						<div class="form-group full">
							<label for="pp-name">Part Name <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="pp-name" name="part_name" maxlength="255" required>
						</div>
						<div class="form-group">
							<label for="pp-article">Article No.</label>
							<input type="text" class="form-control" id="pp-article" name="article_no" maxlength="255">
						</div>
						<div class="form-group">
							<label for="pp-part-no">Part No.</label>
							<input type="text" class="form-control" id="pp-part-no" name="part_no" maxlength="255">
						</div>
						<div class="form-group">
							<label for="pp-drawing">Drawing No.</label>
							<input type="text" class="form-control" id="pp-drawing" name="drawing_no" maxlength="255">
						</div>
						<div class="form-group">
							<label for="pp-maker">Manufacturer</label>
							<select class="form-control" id="pp-maker" name="manufacturer_id">
								<option value="">&mdash;</option>
								@foreach($manufacturers as $m)
								<option value="{{ $m->id }}">{{ $m->name }}</option>
								@endforeach
							</select>
						</div>
						<div class="form-group">
							<label for="pp-qty">Quantity <span class="text-danger">*</span></label>
							<input type="number" class="form-control" id="pp-qty" name="quantity" min="1" step="1" value="1" required>
						</div>
						<div class="form-group">
							<label for="pp-unit">Unit <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="pp-unit" name="unit" maxlength="50" placeholder="pcs, set, ltr…" required>
						</div>
						<div class="form-group">
							<label for="pp-weeks">Est. Delivery (weeks)</label>
							<input type="number" class="form-control" id="pp-weeks" name="est_delivery_weeks" min="0" max="999" step="1">
						</div>
						<div class="form-group">
							<label for="pp-price">Est. Price</label>
							<input type="number" class="form-control" id="pp-price" name="est_price" min="0" step="0.01">
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">Save Part</button>
				</div>
			</form>
		</div>
	</div>
</div>
