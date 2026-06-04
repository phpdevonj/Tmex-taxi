<x-frontend-layout :assets="$assets ?? []">

    <!-- Why Choose TMEX Section -->
    <section class="py-5 why-choose-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="mb-3 section-heading-text">Why Choose TMEX ?</h2>
                <p class="section-para-text">Flexible hours, Reliable earnings, Real support every step of the way.</p>
            </div>
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <img src="{{ asset('frontend-website/img/website/earn.png') }}">
                        </div>
                        <h5 class="mb-3">Drive and earn on your schedule</h5>
                        <p class="feature-para">
                            Earn during evenings and weekends, or boost your income by driving more often — the choice is yours.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <img src="{{ asset('frontend-website/img/website/booking.png') }}">
                        </div>
                        <h5 class="mb-3">Fast, Easy Booking</h5>
                        <p class="feature-para">
                            Easy during evenings and weekends, or boost your income by driving more often — the choice is yours.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <img src="{{ asset('frontend-website/img/website/support.png') }}">
                        </div>
                        <h5 class="mb-3">24/7 Support</h5>
                        <p class="feature-para">
                            Earn during evenings and weekends, or boost your income by driving more often — the choice is yours.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Us Section -->
    <section class="about-section py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <img src="{{ asset('frontend-website/img/website/abt-img.png') }}" alt="Driver in car" class="img-fluid">
                </div>
                <div class="col-lg-6 about-section-right">
                    <h6 class="small-title mb-2">About Us</h6>
                    <h2 class="large-title mb-4">Driven by Trust and Powered by Technology</h2>
                    <p class="abt-para mb-4">
                        At the heart of our service is a simple promise — to deliver reliable, secure, and smooth rides powered by advanced technology and grounded in trust.
                        Whether you're commuting to work or exploring a new city, our platform designed to make your journey easier and more enjoyable.
                    </p>
                    <p class="abt-para">
                        From the moment you book your ride to the time you reach your destination, every step is optimized for your comfort, safety, and peace of mind. Our smart systems ensure real-time tracking, optimized routes, and instant support — so you're always in control and never left waiting.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0 feature-mob-left">
                    <h6 class="small-title mb-2">Features</h6>
                    <h2 class="large-title mb-4">Book and Pay for Taxi with TMEX</h2>
                    <p class="feature-para mb-4">Affordable, on demand rides in major cities</p>
                    
                    <div class="feature-list-item">
                        <div class="feature-list-icon">
                            <img src="{{ asset('frontend-website/img/website/ride-want.png') }}">
                        </div>
                        <div>
                            <h6 class="feature-subhead mb-2">The Ride You Want</h6>
                            <p class="feature-para mb-0">Choose the ride that suits you best and enjoy the journey.</p>
                        </div>
                    </div>
                    
                    <div class="feature-list-item">
                        <div class="feature-list-icon">
                            <img src="{{ asset('frontend-website/img/website/ride-find.png') }}">
                        </div>
                        <div>
                            <h6 class="feature-subhead mb-2">Find Your Ride, Anytime</h6>
                            <p class="feature-para mb-0">With thousands of drivers nearby, enjoy safe, comfortable and budget-friendly rides.</p>
                        </div>
                    </div>
                    
                    <div class="feature-list-item">
                        <div class="feature-list-icon">
                            <img src="{{ asset('frontend-website/img/website/ride-time.png') }}">
                        </div>
                        <div>
                            <h6 class="feature-subhead mb-2">Seamless Travel, Every Time</h6>
                            <p class="feature-para mb-0">From pickup to drop-off, enjoy a smooth and hassle-free experience.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 text-center feature-mob-right">
                    <img src="{{ asset('frontend-website/img/website/feature-img.png') }}" alt="Taxi booking app" class="img-fluid">
                </div>
            </div>
        </div>
    </section>

    <!-- Download App Section -->
    <section class="download-section py-5">
        <div class="container text-center download-inner-content">
            <h2 class="text-white mb-3">Download Our App</h2>
            <p class="text-white mb-4">Book Safe, Affordable and fast rides at your fingertips anytime anywhere</p>
           <div class="d-flex flex-wrap justify-content-center app-link">
                <a href="{{$app_settings->driver_android_url}}" class="app-download-btn">
                    <img src="{{ asset('frontend-website/img/website/play-store 1.png') }}">
                </a>
                <a href="{{$app_settings->driver_ios_url}}" class="app-download-btn">
                     <img src="{{ asset('frontend-website/img/website/app-store 1.png') }}">
                </a>
            </div>
        </div>
    </section>

    <!-- Sign Up Section -->
    <section class="py-5 sing-up-section">
        <div class="container">
            <div class="sign-up-inner">
                <div class="text-center mb-5">
                    <h2 class="mb-4">Sign up today and start earn money</h2>
                </div>
                <div class="row">
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="signup-step">
                            <div class="signup-step-number">1</div>
                            <h6 class="sign-up-subhead mb-2">Create your profile</h6>
                            <p class="sign-up-para">Tell us about yourself and how you plan to drive with us.</p>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="signup-step">
                            <div class="signup-step-number">2</div>
                            <h6 class="sign-up-subhead mb-2">Upload Documents</h6>
                            <p class="sign-up-para">We'll need to verify you have required documents.</p>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="signup-step">
                            <div class="signup-step-number">3</div>
                            <h6 class="sign-up-subhead mb-2">Go Online</h6>
                            <p class="sign-up-para">You're ready to get behind our app and start accepting rides.</p>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-primary-custom btn-lg">Get Started</button>
                </div>
            </div>
        </div>
    </section>

    @section('bottom_script')
        
    @endsection

</x-frontend-layout>
