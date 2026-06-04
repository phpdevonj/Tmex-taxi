@if (Route::is('browse'))
    @include('frontend-partials._body_header')
@elseif (Route::is('termofservice'))
    @include('frontend-partials._body_terms_header')
@elseif (Route::is('privacypolicy'))
    @include('frontend-partials._body_privacy_header')
@elseif (Route::is('driver.register.form'))
    @include('frontend-partials._body_signup_header')
@endif
{{ $slot }}

@include('frontend-partials._body_footer')

@include('frontend-partials._scripts')
