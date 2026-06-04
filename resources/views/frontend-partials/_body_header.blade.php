@php
    $app_settings =  App\Models\AppSetting::first();
@endphp

<section class="hero-section">
    <div class="container hero-content">
        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container-fluid">
                <a class="navbar-brand tmex-logo" href="{{route('browse')}}">
                    <img src="{{ asset('frontend-website/img/website/logo.png') }}">
                </a>
                <div class="ms-auto sign-up-btn">
                    <a href="{{route('driver.register.form')}}" class="btn btn-outline-light">Sign up as a driver</a>
                </div>
            </div>
        </nav>
        
        <!-- Hero Content -->
        <div class="row align-items-center banner-content" style="min-height: 80vh;">
            <div class="col-lg-12">
                <h1 class="display-4 text-white mb-4 banner-heading">
                    Skilled Drivers. Transparent Fares.
                </h1>
                <p class="lead text-white mb-4 banner-para">
                    Ride with confidence, comfort, and clarity. Our expert drivers and honest pricing deliver a premium experience every time you travel.
                </p>
                <p class="text-white mb-4 banner-get-app">Get the App</p>
                <div class="d-flex flex-wrap app-link">
                    <a href="{{ $app_settings->driver_android_url }}" class="app-download-btn">
                        <img src="{{ asset('frontend-website/img/website/play-store 1.png') }}">
                    </a>
                    <a href="{{$app_settings->driver_ios_url }}" class="app-download-btn">
                            <img src="{{ asset('frontend-website/img/website/app-store 1.png') }}">
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>