@extends('layouts.app')

@section('title', 'Log In | DoSomething.org')

@section('content')
    @if (session('request_reset'))
        <div class="messages -padded">You need to <a href="{{ url('password/reset') }}">reset your password</a> before you can log in.</div>
    @endif

    <div class="container__block -centered">
        <h2 class="heading -alpha">Let's do this!</h2>
        <h3>Log in to continue to {{ session('destination', 'DoSomething.org') }}.</h3>
    </div>

    <div class="container__block">
        @if (count($errors) > 0)
            <div class="validation-error fade-in-up">
                <h4>Hmm, there were some issues with that submission:</h4>
                <ul class="list -compacted">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="profile-login-form" method="POST" action="{{ url('login') }}">
            <input name="_token" type="hidden" value="{{ csrf_token() }}">

            <div class="form-item">
                <label for="username" class="field-label">
                    <div class="validation">
                        <div class="validation__label">Email address or cell number <span class="form-required" title="This field is required.">*</span></div>
                        <div class="validation__message"></div>
                    </div>
                </label>
                <input name="username" type="text" class="text-field required" placeholder="puppet-sloth@example.org" value="{{ old('username') }}" autofocus />
            </div>

            <div class="form-item">
                <label for="password" class="field-label">
                    <div class="validation">
                        <div class="validation__label">Password <span class="form-required" title="This field is required.">*</span></div>
                        <div class="validation__message"></div>
                    </div>
                </label>
                <input name="password" type="password" class="text-field required" placeholder="••••••••" />
            </div>

            <div class="form-actions -padded">
                <input type="submit" class="button" value="Log In">
            </div>
        </form>
    </div>

    <div class="container__block -centered">
        <ul>
            <li><a href="{{ url('register') }}">Create a DoSomething.org account</a></li>
            <li><a href="{{ url('password/reset') }}">Forgot your password?</a></li>
        </ul>
    </div>
@stop
