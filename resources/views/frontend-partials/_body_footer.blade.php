@php
    $app_settings =  App\Models\AppSetting::first();
    $dummy_data = Dummydata('dummy_title');
@endphp

<!-- Footer -->
<footer class="footer-section py-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 col-md-6 mb-4 footer-logo-col">
                <div class="tmex-logo mb-3">
                    <img src="{{ asset('frontend-website/img/website/footer-logo.png') }}" class="img-fluid">
                </div>
                <p class="mb-3">Seamless rides with safety and <br>convenience at heart.</p>
            </div>
            <div class="col-lg-2 col-md-6 mb-4 footer-link-col">
                <h6 class="mb-3">QUICK LINKS</h6>
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="fas fa-arrow-right"></i><a href="#" class="text-decoration-none">Our Services</a></li>
                    <li class="mb-2"><i class="fas fa-arrow-right"></i><a href="{{route('privacypolicy')}}" class="text-decoration-none">Privacy Policy</a></li>
                    <li class="mb-2"><i class="fas fa-arrow-right"></i><a href="{{route('termofservice')}}" class="text-decoration-none">Terms & Conditions</a></li>
                    <li class="mb-2"><i class="fas fa-arrow-right"></i><a href="#" class="text-decoration-none">About Us</a></li>
                </ul>
            </div>
            <div class="col-lg-4 col-md-6 mb-4 footer-contact-col">
                <h6 class="fw-bold mb-3">CONTACT DETAILS</h6>
                <div class="d-flex">
                    <p class="me-5">                        
                        PHONE NUMBER<br>
                            <i class="fas fa-mobile me-2"></i>
                        <small>{{ $app_settings->contact_number ?? $dummy_data }}</small>
                    </p>
                    <p class="me-0">                        
                        EMAIL ADDRESS<br>
                        <i class="fas fa-envelope me-2"></i>
                        <small>{{ $app_settings->contact_email ?? $dummy_data }}</small>
                    </p>
                </div>
            </div>
            <div class="col-lg-2 col-md-6 mb-4 footer-app-col">
                    <div class="d-grid g-4 justify-content-center app-link-footer">
                    <a href="{{$app_settings->driver_android_url}}" class="app-download-btn">
                        <img src="{{ asset('frontend-website/img/website/play-store 1.png') }}">
                    </a>
                    <a href="{{$app_settings->driver_ios_url}}" class="app-download-btn">
                            <img src="{{ asset('frontend-website/img/website/app-store 1.png') }}">
                    </a>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12">
                <div class="social-links pt-3">
                    <a class="social-icon" href="{{ $app_settings->facebook_url ?? 'javascript:void(0)' }}" {{ $app_settings->facebook_url != null ? 'target="_blank"' : '' }}><i class="fab fa-facebook-f"></i></a>
                    <a class="social-icon" href="{{ $app_settings->instagram_url ?? 'javascript:void(0)' }}" {{ $app_settings->instagram_url != null ? 'target="_blank"' : '' }}><i class="fab fa-instagram"></i></a>
                    <a class="social-icon" href="{{ $app_settings->twitter_url ?? 'javascript:void(0)' }}" {{ $app_settings->twitter_url != null ? 'target="_blank"' : '' }}><i class="fab fa-twitter"></i></a>
                    <a class="social-icon" href="{{ $app_settings->linkedin_url ?? 'javascript:void(0)' }}" {{ $app_settings->linkedin_url != null ? 'target="_blank"' : '' }}><i class="fab fa-tiktok"></i></a>
                </div>
            </div>
        </div>
    </div>
</footer>
<section class="footer-copyright">
    <div class="container">
            <div class="row py-4">
            <div class="col-lg-12 copy-right-section">
                    <p>All Rights Reserved. Copyright © 2025.</p>
            </div>
        </div>
    </div>
</section>