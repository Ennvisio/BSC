@extends('layouts.admin-master')
@section('main-content')
<div class="order-section container">
	<div class="row">
		<div class="col-xl-12">
			<div class="card order-card">
				<div class="card-header first">
					<strong class="pptitle">New requisition form for &nbsp; 
						<span style="color:red;">{{auth()->user()->role->vessel->name}}</span>
					</strong>

					<div class="right-button">
						<!-- <button class="btn btn-info btn-bvprint print-order"><i class="fa fa-print"></i>  Print</button> -->
					</div>
				</div>
				<div class="card-body">
					<form  id="add_order_form" class="order">
						@csrf
						<div class="row justify-content-center form-group">
							<div class="col-md-11 alert alert-danger alert-dismissible fade show form_error" style="display:none" role="alert">
								<strong>Error Submission!!</strong> Please correct following info and resubmit. 
								<label>    </label>
								<button type="button" class="close close_error_alert">
									<span aria-hidden="true">&times;</span>
								</button>
							</div>
						</div>
						<div class="form-group row justify-content-between">
							<div class="col-md-5">
								<label for="category"> Category: </label>
								<select class="form-control Category_Name" id="cate_name" name="Category_Name">
									<option selected class='cat_opt'>-- Choose Category --</option>
									@if(!empty($categories))
									@foreach($categories as $category)
									<option value="{{$category->id}}" class='cat_opt' data-catalog="{{$category->is_catalog ? 1 : 0}}">{{$category->name}}</option>
									@endforeach
									@endif
								</select>
							</div>
							<div class="col-md-5">
								<label for="Port_Name">Port Name:</label>
								<input type="text" class="form-control" name="Port_Name" id="Port_Name" placeholder="Click to choose a port" readonly style="background:#fff;cursor:pointer;" data-toggle="modal" data-target="#port-picker-modal">
							</div>
						</div>

						<div class="modal fade" id="port-picker-modal" tabindex="-1" role="dialog">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h5 class="modal-title">Port</h5>
										<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
									</div>
									<div class="modal-body">
										<input type="text" id="port-picker-search" class="form-control mb-2" placeholder="Start typing to get suggestions">
										<div id="port-picker-results" class="list-group" style="max-height:320px; overflow-y:auto;"></div>
									</div>
								</div>
							</div>
						</div>

					<!-- 	<div class="form-group row justify-content-between">
							<div class="col-md-5">                                                    
								<label for="Requisition_No">Req. No:</label>
								<input type="text" class="form-control" name="Requisition_No"  id="Requisition_No" placeholder="">
							</div>
							<div class="col-md-5">                          
								<label for="Requisition_Date">Req. Date:</label>
								<input type="text" class="form-control date" name="Requisition_Date"  id="Requisition_Date" placeholder="" value="">
							</div>
						</div> -->
						<div id="accordion" class="mt-4">
							<div class="card">
								<div class="card-header" id="headingOne">
									<h5 class="mb-0 text-center">
										<span id='add_item_button_wrapper'>
											<button class="btn btn-link btn-addnew" type="button" data-toggle="collapse" data-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne" id='add_item_button' disabled>
												Open Add Items Form
											</button>
										</span>
									</h5>
								</div>
								<div id="collapseOne" class="collapse" aria-labelledby="headingOne" data-parent="#accordion">
									<div class="item-selection container mt-4">
										<div id="catalog-picker-panel" style="display:none;">
											<nav aria-label="breadcrumb">
												<ol class="breadcrumb" id="order-catalog-breadcrumb" style="flex-wrap:wrap;"></ol>
											</nav>
											<div class="row">
												<div class="col-lg-5">
													<input type="text" id="order-catalog-group-filter" class="form-control mb-2" placeholder="Filter this list…">
													<div id="order-catalog-groups" class="list-group" style="height:280px; overflow-y:auto;"></div>
												</div>
												<div class="col-lg-7">
													<div id="order-catalog-items" style="height:280px; overflow-y:auto;"></div>
												</div>
											</div>
											<hr>
										</div>
										<div class="form-group row justify-content-between">
											<div class="col-md-5">
												<label for="Item_Name"> Choose Item: </label>
												<select type="text" class="form-control date"  id="Item_Name" placeholder="">
													<option value="" selected class="item_opt_default">-- Select Item -- </option>
												</select>
												<div id="catalog-selected-item-display" class="form-control" style="display:none;background:#f8f9fa;"></div>
											</div>
											<div class="col-md-2">
												<label for="item_qty"> Quantity: </label>
												<input type="number" class="form-control"  id="item_qty" placeholder="" value="1">
											</div>
											<div class="col-md-2">
												<label for="">&nbsp;</label>  <br>
												<button class="btn btn-info btn-add" id="order_add">
													<i class="fa fa-plus"></i> Add
												</button>
											</div>
										</div>
										<hr>
										<div class="form-group row">
											<div class="col-md-12 item-list-shown">
												<table id="example1" class="table table-striped table-bordered orderedItemTable" style="width:100%">
													<thead>
														<tr>
															<th>SL. No.</th>
															<th>IMPA Code</th>
															<th>Item Name</th>
															<th>Req. Quantity</th>
															<th>Unit</th>
															<th>Category</th>
															<th class="action">Action</th>
														</tr>
													</thead>
													<tbody>
														
													</tbody>
												</table>
											</div>
										</div>   
										<div class="form-group row">
											<div class="col-md-12 text-right">
												<label for="sub"></label>
												<button type="submit" class="btn btn-success btn-sub">Submit Order </button>
											</div>
										</div> 
									</div>
								</div>
							</div>
						</div>
					</form>
				</div>                                
			</div>
		</div>
	</div>
</div>

@endsection
@section('create-order-js')
<script>
	$(document).ready(function () {		
		$('#example1').DataTable();

		// $('span#add_item_button_wrapper').on('hover , click',function(e) {
		// 	e.preventDefault();
		// 	if($('select#cate_name').val()!= '' && $('input#Port_Name').val()!=''){
		// 		$('button.btn-addnew').attr('disabled',false);	
		// 	}else{
		// 		// swal('Alert','Please fill-up the above form fields. Then Press Add Items Button','warning');
		// 		$('button.btn-addnew').attr('disabled',true);		
		// 	}
		// })
		$('select#cate_name, input#Port_Name').on('change keyup',function(e){
			e.preventDefault();
			if($('input#Port_Name').val()!='' && $('select#cate_name').val()!= ''){
				$('button.btn-addnew').attr('disabled',false);
			}else{
				$('button.btn-addnew').attr('disabled',true);	
				$('#collapseOne').removeClass('show');	
			}
		})
		
		$( ".orderedItemTable .serial" ).each(function( index ) {
			$(this).text((index+1));
		});
		var orderInfo = JSON.parse(localStorage.getItem('orderInfo'));
		if(orderInfo != null){
			$('select.Vessel_Name option').each(function() {
				if($(this).val()==orderInfo[1]){
					$(this).attr('selected',true);
				}
			});
			$('select#cate_name option').each(function() {
				if($(this).val()==orderInfo[4]){
					$(this).attr('selected',true);
				}
			});
			$('input[name="Port_Name"]').val(orderInfo[2]);
		}
		if($('select#cate_name').val()!= '' && $('input#Port_Name').val()!=''){
			$('button.btn-addnew').attr('disabled',false);
		}else{
				// swal('Alert','Please fill-up the above form fields. Then Press Add Items Button','warning');
				$('button.btn-addnew').attr('disabled',true);
			}

		// Catalog-backed categories (Spares/Stores/Chemicals/Lub Oil/Paint,
		// plus the legacy "Imported Catalog" bucket) can hold tens of
		// thousands of items - too many for the flat #Item_Name dropdown, so
		// those are browsed here via the same tree endpoints Browse Catalog
		// uses, filtered to the chosen category (and, server-side, to the
		// current ship user's own vessel).
		var orderPath = [];
		var orderCategoryId = '';

		function ordEsc(value) {
			return $('<div>').text(value === null || value === undefined ? '' : value).html();
		}

		function ordRenderBreadcrumb() {
			var html = '';
			orderPath.forEach(function (crumb, i) {
				var isLast = i === orderPath.length - 1;
				html += '<li class="breadcrumb-item' + (isLast ? ' active' : '') + '" '
					+ (isLast ? '' : 'data-index="' + i + '" style="cursor:pointer;"') + '>' + ordEsc(crumb.name) + '</li>';
			});
			$('#order-catalog-breadcrumb').html(html);
		}

		function ordLoadGroups(parentId) {
			$('#order-catalog-group-filter').val('');
			$('#order-catalog-groups').html('<p class="text-muted p-2">Loading…</p>');
			$('#order-catalog-items').empty();

			$.getJSON('{{url("/catalog/browse/children")}}/' + (parentId || ''), { category_id: orderCategoryId }, function (groups) {
				if (groups.length === 0) {
					$('#order-catalog-groups').html('<p class="text-muted p-3">No sub-categories here.</p>');
					ordLoadItems(parentId);
					return;
				}

				var html = '';
				groups.forEach(function (g) {
					var badge = g.children_count > 0
						? '<span class="badge badge-secondary float-right">' + g.children_count + '</span>'
						: '<span class="badge badge-info float-right">' + g.items_count + ' items</span>';
					html += '<a href="#" class="list-group-item list-group-item-action order-catalog-group-link" data-id="' + g.id + '" data-name="' + ordEsc(g.name) + '">'
						+ ordEsc(g.name) + badge + '</a>';
				});
				$('#order-catalog-groups').html(html);
			});
		}

		function ordLoadItems(groupId) {
			$('#order-catalog-items').html('<p class="text-muted p-2">Loading items…</p>');

			$.getJSON('{{url("/catalog/browse/items")}}/' + groupId, function (items) {
				if (items.length === 0) {
					$('#order-catalog-items').html('<p class="text-muted p-2">No items in this category.</p>');
					return;
				}

				var html = '<div class="list-group">';
				items.forEach(function (i) {
					html += '<a href="#" class="list-group-item list-group-item-action order-catalog-item-pick" '
						+ 'data-id="' + i.id + '" data-name="' + ordEsc(i.name) + '" data-unit="' + ordEsc(i.unit) + '" data-article="' + ordEsc(i.article_number) + '">'
						+ '<b>' + ordEsc(i.name) + '</b> <span class="text-muted">(' + ordEsc(i.unit) + ', Art# ' + ordEsc(i.article_number) + ')</span>'
						+ '</a>';
				});
				html += '</div>';
				$('#order-catalog-items').html(html);
			});
		}

		$(document).on('click', '.order-catalog-group-link', function (e) {
			e.preventDefault();
			orderPath.push({ id: $(this).data('id'), name: $(this).data('name') });
			ordRenderBreadcrumb();
			ordLoadGroups($(this).data('id'));
		});

		$(document).on('click', '#order-catalog-breadcrumb li[data-index]', function () {
			var index = $(this).data('index');
			orderPath = orderPath.slice(0, index + 1);
			ordRenderBreadcrumb();
			ordLoadGroups(orderPath[orderPath.length - 1].id);
		});

		$(document).on('keyup', '#order-catalog-group-filter', function () {
			var term = $(this).val().toLowerCase();
			$('#order-catalog-groups .order-catalog-group-link').each(function () {
				$(this).toggle($(this).data('name').toLowerCase().indexOf(term) !== -1);
			});
		});

		$(document).on('click', '.order-catalog-item-pick', function (e) {
			e.preventDefault();
			var id = $(this).data('id'), name = $(this).data('name'), unit = $(this).data('unit'), article = $(this).data('article');

			// #order_add (js/dataForm.js) reads item id/name/unit/impa straight
			// off #Item_Name's selected option - keep feeding it that way so
			// the Add/qty/table logic downstream needs zero changes. Article
			// Number stands in for IMPA Code here since imported items always
			// carry the '-' placeholder for the latter.
			$('#Item_Name').html('<option value="' + id + '" selected data-unit="' + ordEsc(unit) + '" data-impa="' + ordEsc(article) + '">' + ordEsc(name) + '</option>');

			$('#catalog-selected-item-display').text('Selected: ' + name + ' (' + unit + ', Art# ' + article + ')').show();
			$('#catalog-picker-panel').hide();
		});

		$('select#cate_name').on('change', function () {
			var isCatalog = $(this).children('option:selected').data('catalog') == 1;
			orderCategoryId = $(this).val();

			$('#Item_Name').hide();
			$('#catalog-selected-item-display').hide();
			$('#Item_Name').html('<option value="" selected class="item_opt_default">-- Select Item -- </option>');

			if (isCatalog) {
				$('#catalog-picker-panel').show();
				orderPath = [{ id: '', name: $(this).children('option:selected').text() }];
				ordRenderBreadcrumb();
				ordLoadGroups('');
			} else {
				$('#catalog-picker-panel').hide();
				$('#Item_Name').show();
			}
		});

		// Port picker: same idea as the catalog tree above - too many
		// worldwide seaports (17,500+) for a plain <select>, so it's a
		// search-as-you-type list instead, backed by /ports/search.
		var portSearchTimer = null;

		function loadPorts(term) {
			$('#port-picker-results').html('<p class="text-muted p-2">Loading…</p>');

			$.getJSON('{{url("/ports/search")}}', { q: term }, function (ports) {
				if (ports.length === 0) {
					$('#port-picker-results').html('<p class="text-muted p-2">No matching ports.</p>');
					return;
				}

				var html = '';
				ports.forEach(function (p) {
					html += '<a href="#" class="list-group-item list-group-item-action port-pick" data-label="'
						+ $('<div>').text(p.label).html() + '">' + $('<div>').text(p.label).html() + '</a>';
				});
				$('#port-picker-results').html(html);
			});
		}

		$('#port-picker-modal').on('shown.bs.modal', function () {
			$('#port-picker-search').val('').trigger('focus');
			loadPorts('');
		});

		$('#port-picker-search').on('keyup', function () {
			var term = $(this).val();
			clearTimeout(portSearchTimer);
			portSearchTimer = setTimeout(function () { loadPorts(term); }, 250);
		});

		$(document).on('click', '.port-pick', function (e) {
			e.preventDefault();
			$('#Port_Name').val($(this).data('label')).trigger('change');
			$('#port-picker-modal').modal('hide');
		});
		});
	</script>
	@endsection
