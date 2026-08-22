@extends('layouts.admin-master')
@section('main-content')
<style>
#example_length label {
	display: flex;
	align-items: center;
}
#example_length label select[name="example_length"] {
	margin: 0 5px;
	width: auto;
} 
#example_filter label {
	display: flex;
	align-items: center;
	float: right;
}
#example_filter label input[type="search"] {
	margin-left: 5px;
}
.modal-header {
	display: flex;
	padding: 0.5rem 1rem;
	align-items: center;
}

.pv-card-hader {
	display: flex;
	justify-content: space-between;
	align-items: center;
}
.s-feild-wrap {
	display: flex;
	align-items: center;
	font-size: 15px;
}
.s-feild {
	display: flex;
	align-items: center;
	width: auto;
	padding-left: 10px;
}
.s-feild label {
	margin: 0;
}
.s-feild select {
	width: auto;
	margin-left: 10px;
	font-size: 15px;
}
.s-feild button {    
	font-size: 15px;
	margin-left: 5px;
}
.s-feild button:first-child {    
	margin-left: 0;
	margin-right:30px;
}

.police-st-field {
	position: relative;
}

img.police-st-field-loader {
	position: absolute;
	width: 20px;
	height: auto;
	right: 14px;
	display: none;
}


.changing-pass-wrap {
	position: relative;
	margin: 5px 0;
}

.changing-pass-wrap:before {
	position: absolute;
	top: 10px;
	left: -15px;
	width: 69%;
	height: 100%;
	content: "";
	border: 1px solid #ddd;
	z-index: 1;
	border-radius: 3px;
}

.changing-pass-wrap div {
	position: relative;
	z-index: 9;
}

.changing-pass-wrap h5 strong {
	background: #fff;
	position: relative;
	z-index: 9;
	display: inline-block;
	padding: 0 0px 14px;
	font-size: 16px;
}
.vmiddle{
	height: 100%;
	display: flex;
	align-items: center;
	margin: 0;
}
</style>
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong>My Info</strong>
		</div>
		<div class="card-body">
			<div class="row justify-content-center">
				<div class="col-md-10">
					@if ($message = Session::get('success'))
					<div class="alert alert-success alert-dismissible fade show" role="alert">
						<strong>Well Done! </strong> {{$message}}
						<button type="button" class="close" data-dismiss="alert" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					@endif
					@if ($message = Session::get('warning'))
					<div class="alert alert-warning alert-dismissible fade show" role="alert">
						<strong>Sorry! </strong> {{$message}}
						<button type="button" class="close" data-dismiss="alert" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					@endif
					@if ($message = Session::get('error'))
					<div class="alert alert-warning alert-dismissible fade show" role="alert">
						<strong>Sorry!</strong> {{$message}}
						<button type="button" class="close" data-dismiss="alert" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					@endif
					@if ($errors->any())
					<div class="alert alert-danger">
						<button type="button" class="close" data-dismiss="alert">×</button>	
						Please check the form below for errors
					</div>
					@endif
				</div>
			</div>
			<div class="row justify-content-center">
				
				<div class="col-md-3">
					<label> Name: </label>
				</div>
				<div class="col-md-7">
					{{auth()->user()->name}}
				</div>
			</div>

			<div class="row justify-content-center">

				<div class="col-md-3">
					<label> Email: </label>
				</div>
				<div class="col-md-7">
					{{auth()->user()->email}}
				</div>
			</div>

			<div class="row justify-content-center">
				<div class="col-md-3">
					<label> Role: </label>
				</div>
				<div class="col-md-7">
					{{auth()->user()->role->role}}
				</div>
			</div>

			<div class="row justify-content-center mb-3 mt-2">
				<div class="col-md-10 changing-pass-wrap" style="">
					<form class='form' action="{{url('/change/file')}}" method="post" enctype="multipart/form-data">
						{{csrf_field()}}
						<h5><strong><i class="far fa-file"></i> File Upload</strong></h5>
						<div class="row form-group">
							<div class="col-md-4 ">
								<label class="vmiddle"> {{!empty(auth()->user()->photo) ? 'Change Photo' : 'Upload Photo'}} </label>
							</div>
							<div class="col-md-4">
								<div class="image-show text-center">
									@if(!empty(auth()->user()->photo))
									<img src="{{url('/'.auth()->user()->photo)}}" alt="user-photo" id="prev_photo_exist">
									@else
									<img src="" alt="user-photo" id="prev_photo" hidden>
									@endif
								</div>
								@if(!empty(auth()->user()->photo))
								<input type="file" accept="image/*" class="form-control" id="photo_exist" name="photo">
								@else
								<input type="file" accept="image/*" class="form-control" id="photo" name="photo" required="">
								@endif
								<div class="err_msg" hidden> <i  class="fas fa-exclamation-triangle"></i> <span> </span>
								</div>
								@if ($errors->has('photo'))
								<div class="alert alert-danger">
									<span class="help-block">
										<strong>{{ $errors->first('photo') }}</strong>
									</span>
								</div>
								@endif
							</div>
						</div>
						<div class="row form-group">
							<div class="col-md-4">
								<label class="vmiddle">{{!empty(auth()->user()->sign) ? 'Change Signature' : 'Upload Signature'}}</label>
							</div>
							<div class="col-md-4">
								<div class="image-show text-center">
									@if(!empty(auth()->user()->sign))
									<img src="{{url('/'.auth()->user()->sign)}}" alt="user-sign" id="prev_user_sign_ex">
									@else
									<img src="" alt="user-sign" id="prev_user_sign" hidden>
									@endif
								</div>
								@if(!empty(auth()->user()->sign))
								<input type="file" accept="image/*" class="usersign form-control" id="user_sign_ex" name="signature">
								@else
								<input type="file" accept="image/*" class="usersign form-control" id="user_sign" name="signature" required="">
								@endif
								<div class="err_msg sign_error" hidden> <i  class="fas fa-exclamation-triangle"></i> <span> </span>
								</div>
								@if ($errors->has('signature'))
								<div class="alert alert-danger">
									<span class="help-block">
										<strong>{{ $errors->first('signature') }}</strong>
									</span>
								</div>
								@endif
							</div>
						</div>
						<div class="row form-group">
							<div class="col-md-4">
							</div>
							<div class="col-md-4">
								<button type="submit"  class="btn btn-success float-right">	<i class="fas fa-upload"></i> Upload</button>
							</div>

						</div>

					</form>
				</div>
				
			</div>

			<div class="row justify-content-center">
				<div class="col-md-10 changing-pass-wrap" style="">
					<form class='form' action="{{url('/change/password')}}" method="post">
						{{csrf_field()}}
						<h5><strong><i class="fas fa-key"></i> Change Password</strong></h5>

						<div class="row form-group">
							<div class="col-md-4">
								<label>Current Password: </label>
							</div>
							<div class="col-md-4">
								<input type="password" class="form-control" name="current_password" required>

								@if ($errors->has('current_password'))
								<div class="alert alert-danger">
									<span class="help-block">
										<strong>{{ $errors->first('current_password') }}</strong>
									</span>
								</div>
								@endif
							</div>
						</div>
						<div class="row form-group">
							<div class="col-md-4">
								<label> New Password: </label>
							</div>
							<div class="col-md-4">
								<input type="password" class="form-control" name="new_password" required>
								@if ($errors->has('new_password'))
								<div class="alert alert-danger">
									<span class="help-block">
										<strong>{{ $errors->first('new_password') }}</strong>
									</span>
								</div>
								@endif	
							</div>
						</div>
						<div class="row form-group">
							<div class="col-md-4">
								<label> Confirm New Password: </label>
							</div>
							<div class="col-md-4">
								<input type="password" class="form-control" name="new_password_confirmation" required>
							</div>

						</div>
						<div class="row form-group">
							<div class="col-md-4">
							</div>
							<div class="col-md-4">
								<button type="submit"  class="btn btn-success float-right">	<i class="fas fa-unlock"></i> Change Password</button>
							</div>

						</div>

					</form>
				</div>
				
			</div>
		</div>
	</div>
</div>
@endsection
@section('file-validate')
<script type="text/javascript" src="{{ asset('js/jquery.checkImageSize.js') }}"></script>
<script>
	// $("input[type=file].sign").checkImageSize();
	// $("input[type=file].usersign").checkImageSize({
	// 	// minWidth: 200,
	// 	// minHeight: 50,
	// 	maxWidth: 300,
	// 	maxHeight: 100,
	// 	showError: true,
	// 	ignoreError: false
	// });
	function readURL(input) {
		imgId = '#prev_'+ $(input).attr('id');
		if (input.files && (input.files[0].size / 1024 / 1024) < 0.25) {
			var reader = new FileReader();
			reader.onload = function (e) {
				$(imgId).attr('src', e.target.result);
				$(imgId).attr('hidden',false);
				$(imgId).css({'display':'inline-block','height':'auto','padding':'10px 0px','max-width':'70%','width': 'auto','max-height': '200px'});
				$('img.app_page').css({'height':'220px'});
			}
			reader.readAsDataURL(input.files[0]);
			$(input).siblings('div.err_msg').attr('hidden',true);
		}
		else{
			$(input).val('');  
			$(imgId).attr('hidden',true);
			$(input).siblings('div.err_msg').find('span').text('File size exceeds 250 KB.Please reduce file size less than 250kb');
			$(input).siblings('div.err_msg').attr('hidden',false);
		}
	}
	$(".form input[type='file']").change(function(){            
		readURL(this);
		$(this).on('click',function(){
			imgId = '#prev_'+ $(this).attr('id');
			$(imgId).attr('hidden',true);
		})
	});
</script>
@endsection




