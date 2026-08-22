@extends('layouts.app')

@section('content')
<!-- register-area -->
<div class="login-area register-area">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <!-- registation -->
                <div class="login registation">
                    <!-- registation-top -->
                    <div class="login-top registation-top">
                        <div class="logo-left">
                            <img src="{{url('/images/logo.png')}}" alt="">
                        </div>
                        <div class="logo-text">
                            <div class="logo-text-bn">
                                বাংলাদেশ শিপিং কর্পোরেশন
                            </div>
                            <div class="logo-text-en">
                                Bangladesh Shiping Corporation 
                                <span>Ship Repair Department</span>
                            </div>
                        </div>                     
                    </div>
                    <!-- registation-top -->

                    <!-- registation-form -->
                    <div class="logo-bottom login-form registation-bottom registation-form">
                        <form method="POST" action="{{ route('register') }}">
                            @csrf

                            <div class="form-group row">
                                <div class="form-title">Registation</div>
                            </div>

                            <div class="form-group row text-center">
                                <div class="col-md-12">
                                    <input id="name" type="text" class="form-control{{ $errors->has('name') ? ' is-invalid' : '' }}" name="name" value="{{ old('name') }}" placeholder="{{ __('Name') }}" required autofocus>

                                    @if ($errors->has('name'))
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $errors->first('name') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group row">
                                <div class="col-md-12">
                                    <input id="email" type="email" class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}" name="email" value="{{ old('email') }}" placeholder="{{ __('E-Mail Address') }}" required>

                                    @if ($errors->has('email'))
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $errors->first('email') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group row">
                                <div class="col-md-12">
                                    <input id="password" type="password" class="form-control{{ $errors->has('password') ? ' is-invalid' : '' }}" name="password" placeholder="{{ __('Password') }}" required>

                                    @if ($errors->has('password'))
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $errors->first('password') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group row">
                                <div class="col-md-12">
                                    <input id="password-confirm" type="password" class="form-control" name="password_confirmation" placeholder="{{ __('Confirm Password') }}" required>
                                </div>
                            </div>

                            <div class="form-group row mb-0">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary">
                                        {{ __('Register') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <!-- registation-form -->
                </div>
                <!-- registation -->
            </div>
        </div>
    </div>   
</div>
<!-- register-area -->
@endsection
