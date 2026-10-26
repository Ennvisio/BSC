{{-- Vessel filter: a ship user's own vessel, locked; shore users pick one or the fleet. --}}
<div class="form-group">
	<label for="rf-vessel">Vessel</label>
	@if($locked)
	<input type="text" class="form-control" id="rf-vessel" value="{{ $vessel->name }}" disabled>
	@else
	<select name="vessel_id" id="rf-vessel" class="form-control">
		<option value="">All vessels (fleet)</option>
		@foreach($vessels as $v)
		<option value="{{ $v->id }}" {{ ($vessel->id ?? null) === $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
		@endforeach
	</select>
	@endif
</div>
