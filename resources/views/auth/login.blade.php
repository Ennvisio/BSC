@extends('layouts.app')

@section('content')
<div class="login-area">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="login">
                    <div class="login-top">
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

                    <div class="logo-bottom login-form">
                        <form method="POST" action="{{ route('login') }}">
                            @csrf
                            <div class="form-group row">
                                <div class="form-title"><i class="fa fa-lock"></i> Login</div>
                            </div>

                            <div class="form-group row">
                                <div class="col-md-12  text-center">
                                    <input id="email" type="email" class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}" name="email" value="{{ old('email') }}" placeholder="{{ __('Email Address') }}" required autofocus>

                                    @if ($errors->has('email'))
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $errors->first('email') }}</strong>
                                    </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group row">
                                <div class="col-md-12  text-center">
                                    <input id="password" type="password" class="form-control{{ $errors->has('password') ? ' is-invalid' : '' }}" name="password" placeholder="{{ __('Password') }}"  required>

                                    @if ($errors->has('password'))
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $errors->first('password') }}</strong>
                                    </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group row">
                                <div class="col-md-12 text-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>

                                        <label class="form-check-label" for="remember">
                                            {{ __('Remember Me') }}
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group row mb-0">
                                <div class="col-md-12 text-center">
                                    <button type="submit" class="btn btn-primary">
                                        {{ __('Login') }}
                                    </button>

                                    @if (Route::has('password.request'))
                                    <a class="btn btn-link" href="{{ route('password.request') }}">
                                        {{ __('Forgot Your Password?') }}
                                    </a>
                                    @endif
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>   
</div>

@endsection
