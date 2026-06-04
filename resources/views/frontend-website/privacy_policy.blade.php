<x-frontend-layout :assets="$assets ?? []">
    
     <div class="container py-5">
        <div class="policy-container p-4">
            <div class="last-updated">
                <b><i class="fa-solid fa-calendar-days"></i> Last Updated:</b> January 16, 2024
            </div>

            <!-- Section 1: Information We Collect -->
            <div class="section-content">
                <div class="section-header">
                    1. Information We Collect
                </div>
                <p class="intro-text">
                    We collect information to provide better services to our users. The types of information we collect include:
                </p>

                <div class="subsection-title">Personal Information</div>
                <ul>
                    <li>Name, email address, and phone number</li>
                    <li>Payment information (excluding billing details)</li>
                    <li>Profile pictures and preferences</li>
                    <li>Location and contact information</li>
                </ul>

                <div class="subsection-title">Technical Information</div>
                <ul>
                    <li>Device and browser information</li>
                    <li>IP address and location data</li>
                    <li>Frequently visited places</li>
                </ul>

                <div class="subsection-title">Usage Information</div>
                <ul>
                    <li>Ride history and patterns</li>
                    <li>App usage statistics</li>
                    <li>Feedback and ratings from users and drivers</li>
                    <li>Communication records with drivers and support</li>
                </ul>
            </div>

            <!-- Section 2: How We Use Your Information -->
            <div class="section-content">
                <div class="section-header">
                    2. How We Use Your Information
                </div>
                <p class="intro-text">
                    We use the collected information for the following purposes:
                </p>
                <ul>
                    <li><strong>Service Provision:</strong> To connect you with drivers and facilitate rides</li>
                    <li><strong>Payment Processing:</strong> To process payments and manage billing</li>
                    <li><strong>Safety & Security:</strong> To ensure rider and driver safety</li>
                    <li><strong>Customer Support:</strong> To provide assistance and resolve issues</li>
                </ul>
            </div>

            <!-- Section 3: Information Sharing -->
            <div class="section-content">
                <div class="section-header">
                    3. Information Sharing
                </div>
                <p class="intro-text">
                    We may share your information in the following circumstances:
                </p>

                <div class="subsection-title">With Drivers</div>
                <p class="intro-text">
                    We share necessary information with drivers to facilitate your ride, including your name, pickup location, and contact details.
                </p>
            </div>
        </div>
    </div>
    <div class="container pb-5">
        <section class="privacy-contact-section d-flex align-items-center justify-content-center">
            <div class="text-center text-white px-4">
                <h2 class="mb-3 fw-bold">Questions About Your Privacy?</h2>
                <p class="mb-4 fs-6 opacity-90">
                    Our privacy team is here to help. Contact us anytime for questions about your data or this policy.
                </p>
                <button class="btn contact-btn px-4 py-2">
                    Contact Privacy Team
                </button>
            </div>
        </section>
    </div>

</x-frontend-layout>