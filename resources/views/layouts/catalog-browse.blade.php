@extends('layouts.admin-master')
@section('main-content')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Browse Catalog</strong>
			<div class="right-buttons">
				<a href="{{url('/catalog/import')}}" class="btn btn-primary"><i class="fas fa-upload"></i> Import Catalog</a>
			</div>
		</div>
		<div class="card-body">
			<div class="row mb-3">
				<div class="col-md-4"><div class="alert alert-secondary mb-0 text-center"><b>{{$groupCount}}</b> categories/folders</div></div>
				<div class="col-md-4"><div class="alert alert-secondary mb-0 text-center"><b>{{$itemCount}}</b> items</div></div>
				<div class="col-md-4"><div class="alert alert-secondary mb-0 text-center"><b>{{$vesselCount}}</b> vessels</div></div>
			</div>

			<nav aria-label="breadcrumb">
				<ol class="breadcrumb" id="catalog-breadcrumb" style="flex-wrap:wrap;">
					<li class="breadcrumb-item active" data-parent-id="" style="cursor:pointer;">All categories</li>
				</ol>
			</nav>

			<div class="row">
				<div class="col-md-5">
					<div id="catalog-groups" class="list-group" style="max-height:520px;overflow-y:auto;"></div>
				</div>
				<div class="col-md-7">
					<div id="catalog-items-wrapper">
						<p class="text-muted">Select a category on the left. Once you reach a category with no
						sub-categories, its items will appear here.</p>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection

@section('home-js')
<script>
$(function () {
	// path[0] is always the synthetic root ("All categories", id '').
	var path = [{ id: '', name: 'All categories' }];

	function renderBreadcrumb() {
		var html = '';
		path.forEach(function (crumb, i) {
			var isLast = i === path.length - 1;
			html += '<li class="breadcrumb-item' + (isLast ? ' active' : '') + '" '
				+ (isLast ? '' : 'data-index="' + i + '" style="cursor:pointer;"') + '>' + crumb.name + '</li>';
		});
		$('#catalog-breadcrumb').html(html);
	}

	function loadGroups(parentId) {
		$('#catalog-groups').html('<p class="text-muted p-2">Loading…</p>');
		$('#catalog-items-wrapper').html('');

		$.getJSON('{{url("/catalog/browse/children")}}/' + (parentId || ''), function (groups) {
			if (groups.length === 0) {
				loadItems(parentId);
				return;
			}

			var html = '';
			groups.forEach(function (g) {
				var badge = g.children_count > 0
					? '<span class="badge badge-secondary float-right">' + g.children_count + ' sub-categories</span>'
					: '<span class="badge badge-info float-right">' + g.items_count + ' items</span>';
				html += '<a href="#" class="list-group-item list-group-item-action catalog-group-link" data-id="' + g.id + '" data-name="' + g.name + '">'
					+ g.name + badge + '</a>';
			});
			$('#catalog-groups').html(html);
			$('#catalog-items-wrapper').html('<p class="text-muted">Select a category to drill down further, or one with an items count to see its items.</p>');
		});
	}

	function loadItems(groupId) {
		$('#catalog-items-wrapper').html('<p class="text-muted p-2">Loading items…</p>');

		$.getJSON('{{url("/catalog/browse/items")}}/' + groupId, function (items) {
			if (items.length === 0) {
				$('#catalog-items-wrapper').html('<p class="text-muted">No items in this category.</p>');
				return;
			}

			var html = '<div class="table-responsive"><table class="table table-sm table-bordered">'
				+ '<thead><tr><th>Article #</th><th>Name</th><th>Unit</th><th>Manufacturer</th><th>Vessels</th></tr></thead><tbody>';
			items.forEach(function (i) {
				var vessels = (i.vessels || []).map(function (v) { return v.name; }).join(', ') || '<span class="text-muted">none</span>';
				html += '<tr><td>' + (i.article_number || '') + '</td><td>' + i.name + '</td><td>' + (i.unit || '') + '</td>'
					+ '<td>' + (i.manufacturer || '') + '</td><td>' + vessels + '</td></tr>';
			});
			html += '</tbody></table></div>';
			$('#catalog-items-wrapper').html(html);
		});
	}

	$(document).on('click', '.catalog-group-link', function (e) {
		e.preventDefault();
		path.push({ id: $(this).data('id'), name: $(this).data('name') });
		renderBreadcrumb();
		loadGroups($(this).data('id'));
	});

	$(document).on('click', '#catalog-breadcrumb li[data-index]', function () {
		var index = $(this).data('index');
		path = path.slice(0, index + 1);
		renderBreadcrumb();
		loadGroups(path[path.length - 1].id);
	});

	renderBreadcrumb();
	loadGroups('');
});
</script>
@endsection
