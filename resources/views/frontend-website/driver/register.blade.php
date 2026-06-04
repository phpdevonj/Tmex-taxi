<x-frontend-layout :assets="$assets ?? []">
    <div class="container py-5">
        <!-- Step 1: User Detail Form -->
        <div id="step1">
            <div class="row">
                <!-- Driver Requirements Section - Now always visible -->
                <div class="col-md-6 mb-4">
                    <div class="signup-form-title">
                        <h2 class="text-teal fw-bold mb-2">Ready to Get Started?</h2>
                        <p class="sign-subtitle">Complete the form to begin your driver application</p>
                    </div>
                    <div class="requirements-section">
                        <h5 class="text-teal fw-bold mb-3">Driver Requirements</h5>
                        
                        <div class="driver-req-list mb-3">
                            <i class="fas fa-check-circle"></i>
                            <label>
                                Valid driver's license (minimum 2 years)
                            </label>
                        </div>
                        
                        <div class="driver-req-list mb-3">
                        <i class="fas fa-check-circle"></i>
                            <label>
                                Vehicle insurance and registration
                            </label>
                        </div>
                        
                        <div class="driver-req-list mb-3">
                        <i class="fas fa-check-circle"></i>
                            <label>
                                Clean driving record
                            </label>
                        </div>
                        
                        <div class="driver-req-list mb-3">
                            <i class="fas fa-check-circle"></i>
                            <label>
                                Vehicle inspection certificate
                            </label>
                        </div>
                        
                        <div class="driver-req-list">
                        <i class="fas fa-check-circle"></i>
                            <label>
                                Background check clearance
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 sign-form-step">
                    {{-- Success Message --}}
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    {{-- Error Messages --}}
                    @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <!-- Progress Indicator -->
                    <div class="d-flex align-items-center justify-content-center mb-4">
                        <div class="progress-step active" id="step1-indicator">
                            <div class="step-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <span>User Detail</span>
                        </div>
                        <div class="progress-line" id="progress-line-1"></div>
                        <div class="progress-step" id="step2-indicator">
                            <div class="step-icon">
                                <i class="fas fa-car"></i>
                            </div>
                            <span>Car Info</span>
                        </div>
                        <div class="progress-line" id="progress-line-2"></div>
                        <div class="progress-step" id="step3-indicator">
                            <div class="step-icon">
                                <i class="fas fa-file-invoice"></i>
                            </div>
                            <span>Document</span>
                        </div>
                    </div>

                    <!-- MAIN FORM -->
                    <form id="driverRegisterForm" method="POST" action="{{ route('driver.register') }}" enctype="multipart/form-data">
                        @csrf

                        <!-- Step 1 -->
                        <div id="step1-content">
                                <div class="mb-3">
                                    <label class="form-label">Profile Photo</label>
                                    <div class="profile-upload-container">
                                        <div class="profile-preview" id="profilePreview">
                                            <div class="upload-placeholder" id="uploadPlaceholder">
                                                <i class="fas fa-camera fa-2x text-muted mb-2"></i>
                                                <p class="text-muted mb-0">Click to upload photo</p>
                                            </div>
                                            <img id="previewImage" src="" alt="Profile Preview" style="display: none;">
                                        </div>
                                        <input type="file" name="profile_image" id="profilePhoto" accept="image/*" onchange="showProfilePreview(this)" style="display: none;" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="firstName" class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control" id="firstName" placeholder="Please enter first name" value="{{ old('first_name') }}" required>
                                </div>

                                <div class="mb-3">
                                    <label for="lastName" class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control" id="lastName" placeholder="Please enter last name" value="{{ old('last_name') }}" required>
                                </div>                            
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" id="email" placeholder="Please enter email" value="{{ old('email') }}" required>
                                </div>

                                <div class="mb-3 itiflagcode">
                                    {{ Form::label('contact_number',__('message.contact_number'),['class'=>'form-label'], false ) }}
                                    {{ Form::text('contact_number', old('contact_number'),[ 'placeholder' => __('message.contact_number'), 'class' => 'form-control', 'id' => 'phone', 'required'  ]) }}
                                   
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <div class="position-relative">
                                        <input type="password" name="password" class="form-control" id="password" placeholder="Please enter password" required>
                                        <i class="fas fa-eye password-toggle" onclick="togglePassword('password')"></i>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="confirmPassword" class="form-label">Confirm Password</label>
                                    <div class="position-relative">
                                        <input type="password" name="password_confirmation" class="form-control" id="confirmPassword" placeholder="Please enter confirm password" required>
                                        <i class="fas fa-eye password-toggle" onclick="togglePassword('confirmPassword')"></i>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end pt-5">
                                    <button type="button" class="btn btn-teal px-4" onclick="nextStep()">Next</button>
                                </div>
                        </div>

                        <!-- Step 2 -->
                        <div id="step2-content" class="step-hidden">
                                <div class="mb-3">
                                    <label class="form-label">Select Service <span class="required">*</span></label>
                                    <select class="form-select" name="service_id" id="selectService" required>
                                        <option value="" selected>Select Service</option>
                                        @foreach($services as $service)
                                            <option value="{{ $service->id }}" {{ old('service_id') == $service->id ? 'selected' : '' }}>
                                                {{ $service->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="carModel" class="form-label">Car Model <span class="required">*</span></label>
                                    <input type="text" name="car_model" class="form-control" id="carModel" placeholder="Please enter car model" value="{{ old('car_model') }}" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="carYear" class="form-label">Car Production Year <span class="required">*</span></label>
                                    <input type="text" name="car_year" class="form-control" id="carYear" placeholder="Please enter car production year" value="{{ old('car_year') }}" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="carPlate" class="form-label">Car Plate Number <span class="required">*</span></label>
                                    <input type="text" name="car_plate" class="form-control" id="carPlate" placeholder="Please enter car plate number" value="{{ old('car_plate') }}" required>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="carColor" class="form-label">Car Color <span class="required">*</span></label>
                                    <input type="text" name="car_color" class="form-control" id="carColor" placeholder="Please enter car color" value="{{ old('car_color') }}" required>
                                </div>
                                
                                <div class="d-flex justify-content-between pt-5">
                                    <button type="button" class="btn btn-outline-secondary px-4" onclick="previousStep()">Previous</button>
                                    <button type="button" class="btn btn-teal px-4" onclick="nextStep()">Next</button>
                                </div>
                        </div>

                        <!-- Step 3 -->
                        <div id="step3-content" class="step-hidden">
                            @foreach($documents as $document)
                                <div class="mb-4">
                                    <label class="form-label fw-bold">{{ $document->name }} <span class="required">*</span></label>
                                    <label class="file-upload w-100">
                                        <input type="hidden" name="documents[{{ $document->id }}][document_id]" value="{{ $document->id }}">
                                        <input type="file" name="documents[{{ $document->id }}][file]" id="document_{{ $document->id }}" data-required="true" onchange="showFileName(this)">
                                        <i class="fas fa-upload"></i>
                                        <span class="file-text">Upload from your computer</span>
                                    </label>
                                </div>
                            @endforeach
                            
                            <div class="d-flex justify-content-between pt-5">
                                <button type="button" class="btn btn-outline-secondary px-4" onclick="previousStep()">Previous</button>
                                <button type="submit" class="btn btn-teal px-4">Submit</button>
                            </div>
                        </div>
                </div>
            </div>
        </div>
    </div>

    @section('bottom_script')
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        @if(isset($assets) && in_array('phone', $assets))
            <script src="{{ asset('vendor/intlTelInput/js/intlTelInput-jquery.min.js') }}"></script>
            <script src="{{ asset('vendor/intlTelInput/js/intlTelInput.min.js') }}"></script>
        @endif
        <!-- JS for steps and validation -->
        <script>
            let currentStep = 1;

            function updateStepDisplay() {

                // Grab indicators and lines
                const step1Indicator = document.getElementById('step1-indicator');
                const step2Indicator = document.getElementById('step2-indicator');
                const step3Indicator = document.getElementById('step3-indicator');
                const progressLine1 = document.getElementById('progressLine1');
                const progressLine2 = document.getElementById('progressLine2');

                // Hide all step contents safely
                ['step1-content', 'step2-content', 'step3-content'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.classList.add('step-hidden');
                });

                // Show current step content
                const currentContent = document.getElementById('step' + currentStep + '-content');
                if (currentContent) currentContent.classList.remove('step-hidden');

                // Reset indicators safely
                [step1Indicator, step2Indicator, step3Indicator].forEach(el => {
                    if (el) el.classList.remove('active', 'completed');
                });
                if (progressLine1) progressLine1.classList.remove('completed');
                if (progressLine2) progressLine2.classList.remove('completed');

                // Apply active/completed state
                if (currentStep === 1 && step1Indicator) {
                    step1Indicator.classList.add('active');
                } else if (currentStep === 2) {
                    if (step1Indicator) step1Indicator.classList.add('completed');
                    if (step2Indicator) step2Indicator.classList.add('active');
                    if (progressLine1) progressLine1.classList.add('completed');
                } else if (currentStep === 3) {
                    if (step1Indicator) step1Indicator.classList.add('completed');
                    if (step2Indicator) step2Indicator.classList.add('completed');
                    if (step3Indicator) step3Indicator.classList.add('active');
                    if (progressLine1) progressLine1.classList.add('completed');
                    if (progressLine2) progressLine2.classList.add('completed');
                }
            }

            function nextStep() {
                validateCurrentStep().then(valid => {
                    if (valid) {
                        if (currentStep < 3) {
                            currentStep++;
                            updateStepDisplay();
                        }
                    }
                });
            }

            function checkEmailExists(email) {
                var route = "{{ route('ajax-list',[ 'type' => 'check_email']) }}&email="+email;
                route = route.replaceAll('amp;','');

                return fetch(route, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                })
                .then(response => response.json())
                .then(data => data.exists)
                .catch(() => false);
            }

            function checkContactExists(contact) {
                var route = "{{ route('ajax-list',[ 'type' => 'check_contact']) }}&contact="+encodeURIComponent(contact);
                route = route.replaceAll('amp;','');

                return fetch(route, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                })
                .then(response => response.json())
                .then(data => data.exists)
                .catch(() => false);
            }

            function checkCarPlateExists(carPlate) {
                var route = "{{ route('ajax-list',[ 'type' => 'check_car_plate']) }}&car_plate="+encodeURIComponent(carPlate);
                route = route.replaceAll('amp;','');

                return fetch(route, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                })
                .then(response => response.json())
                .then(data => data.exists)
                .catch(() => false);
            }

            

            function previousStep() {
                if (currentStep > 1) {
                    currentStep--;
                    updateStepDisplay();
                }
            }

            function validateCurrentStep() {
                return new Promise((resolve) => {
                    let valid = true;

                    // Clear previous errors
                    document.querySelectorAll('.error-message').forEach(el => el.remove());
                    document.querySelectorAll('.invalid-field').forEach(el => el.classList.remove('invalid-field'));

                    // Define required fields with custom messages
                    let requiredFields = [];
                    if (currentStep === 1) {
                        requiredFields = [
                            {id: 'profilePhoto', message: 'Profile Photo is required.'},
                            {id: 'firstName', message: 'First Name is required.'},
                            {id: 'lastName', message: 'Last Name is required.'},
                            {id: 'email', message: 'Email is required.'},
                            {id: 'phone', message: 'Contact Number is required.'},
                            {id: 'password', message: 'Password is required.'},
                            {id: 'confirmPassword', message: 'Confirm Password is required.'}
                        ];
                    } else if (currentStep === 2) {
                        requiredFields = [
                            {id: 'selectService', message: 'Please select a service.'},
                            {id: 'carModel', message: 'Car Model is required.'},
                            {id: 'carYear', message: 'Car Year is required.'},
                            {id: 'carPlate', message: 'Car Plate Number is required.'},
                            {id: 'carColor', message: 'Car Color is required.'}
                        ];
                    }

                    // Validate required text fields (step1 & step2)
                    requiredFields.forEach(fieldObj => {
                        const field = document.getElementById(fieldObj.id);
                        // Special handling for file inputs
                        if (field.type === 'file') {
                            
                            if (!field.files || field.files.length === 0) {
                                valid = false;
                                document.getElementById('profilePreview').classList.add('invalid-field');

                                const error = document.createElement('div');
                                error.classList.add('error-message');
                                error.textContent = fieldObj.message;
                                document.getElementById('profilePreview').insertAdjacentElement('afterend', error);
                            }
                        } else {
                            // Regular text field validation
                            if (!field.value.trim()) {
                                valid = false;
                                field.classList.add('invalid-field');

                                const error = document.createElement('div');
                                error.classList.add('error-message');
                                error.textContent = fieldObj.message;
                                field.insertAdjacentElement('afterend', error);
                            }
                        }
                    });

                    // Extra check: Password match (only on step 1)
                    if (currentStep === 1) {
                        const password = document.getElementById('password').value.trim();
                        const confirmPassword = document.getElementById('confirmPassword').value.trim();

                        if (password && confirmPassword && password !== confirmPassword) {
                            valid = false;

                            const confirmField = document.getElementById('confirmPassword');
                            confirmField.classList.add('invalid-field');

                            const error = document.createElement('div');
                            error.classList.add('error-message');
                            error.textContent = "Passwords do not match.";
                            confirmField.insertAdjacentElement('afterend', error);
                        }                        
                        // Check phone validation if basic validation passes
                        if (valid) {
                            const phoneInput = document.getElementById('phone');
                            if (phoneInput && phoneInput.value.trim() && window.iti) {
                                if (!window.iti.isValidNumber()) {
                                    valid = false;
                                    phoneInput.classList.add('invalid-field');
                                    
                                    const error = document.createElement('div');
                                    error.classList.add('error-message');
                                    error.textContent = "Please enter a valid phone number.";
                                    phoneInput.insertAdjacentElement('afterend', error);
                                }
                            }
                        }
                         // Check email and contact uniqueness if basic validation passes
                         if (valid) {
                            const email = document.getElementById('email').value.trim();
                            const contact = window.iti ? window.iti.getNumber() : document.getElementById('phone').value.trim();
                            Promise.all([
                                email ? checkEmailExists(email) : Promise.resolve(false),
                                contact ? checkContactExists(contact) : Promise.resolve(false)
                            ]).then(([emailExists, contactExists]) => {
                                if (emailExists) {
                                    valid = false;
                                    const emailField = document.getElementById('email');
                                    emailField.classList.add('invalid-field');
                                    
                                    const error = document.createElement('div');
                                    error.classList.add('error-message');
                                    error.textContent = "The email address has already been taken.";
                                    emailField.insertAdjacentElement('afterend', error);
                                }
                                
                                if (contactExists) {
                                    valid = false;
                                    const contactField = document.getElementById('phone');
                                    contactField.classList.add('invalid-field');
                                    
                                    const error = document.createElement('div');
                                    error.classList.add('error-message');
                                    error.textContent = "The contact number has already been taken.";
                                    contactField.insertAdjacentElement('afterend', error);
                                }
                                
                                resolve(valid);
                            });
                            return;
                        }
                    }

                    // Step 2: Check car plate uniqueness
                    if (currentStep === 2 && valid) {
                        const carPlate = document.getElementById('carPlate').value.trim();
                        if (carPlate) {
                            checkCarPlateExists(carPlate).then(carPlateExists => {
                                if (carPlateExists) {
                                    valid = false;
                                    const carPlateField = document.getElementById('carPlate');
                                    carPlateField.classList.add('invalid-field');
                                    
                                    const error = document.createElement('div');
                                    error.classList.add('error-message');
                                    error.textContent = "The car plate number has already been taken.";
                                    carPlateField.insertAdjacentElement('afterend', error);
                                }
                                resolve(valid);
                            });
                            return;
                        }
                    }

                    // Step 3: Check if each document is uploaded
                    if (currentStep === 3) {
                        const documentInputs = document.querySelectorAll('#step3-content input[type="file"]');
                        documentInputs.forEach(input => {
                            // Reset old errors
                            input.classList.remove('invalid-field');
                            const errorElement = input.closest("label").nextElementSibling;
                            if (errorElement && errorElement.classList.contains('error-message')) {
                                errorElement.remove();
                            }

                            // Validate required
                            if (!input.files || input.files.length === 0) {
                                valid = false;
                                input.closest("label").classList.add('invalid-field');

                                const error = document.createElement('div');
                                error.classList.add('error-message'); 
                                error.textContent = "Please upload this document.";
                                input.closest("label").insertAdjacentElement('afterend', error);
                            } else {
                                // Validate file type
                                const file = input.files[0];
                                const validTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                                
                                if (!validTypes.includes(file.type)) {
                                    valid = false;
                                    input.closest("label").classList.add('invalid-field');

                                    const error = document.createElement('div');
                                    error.classList.add('error-message');
                                    error.textContent = "Please upload a valid image file (JPG, JPEG or PNG)";
                                    input.closest("label").insertAdjacentElement('afterend', error);
                                }
                            }
                        });
                    }
                    resolve(valid);
                });
            }

            function togglePassword(fieldId) {
                const field = document.getElementById(fieldId);
                if (field.type === 'password') {
                    field.type = 'text';
                } else {
                    field.type = 'password';
                }
            }

            function showFileName(input) {
                if (input.files && input.files[0]) {
                    input.closest("label").querySelector(".file-text").textContent = input.files[0].name;
                }
            }
            function showProfilePreview(input) {
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('previewImage').src = e.target.result;
                        document.getElementById('previewImage').style.display = 'block';
                        document.getElementById('uploadPlaceholder').style.display = 'none';
                    };
                    reader.readAsDataURL(input.files[0]);
                }
            }

            // Add click event to profile preview
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('profilePreview').addEventListener('click', function() {
                    document.getElementById('profilePhoto').click();
                });
            });

            updateStepDisplay();
            document.getElementById("driverRegisterForm").addEventListener("submit", async function(e) {
                currentStep = 3; // Force validation on step 3
                const valid = await validateCurrentStep();
                if (!valid) {
                    e.preventDefault(); // block only when invalid
                }
            });
            @if(isset($assets) && in_array('phone', $assets))
                var input = document.querySelector("#phone"), 
                errorMsg = document.querySelector("#error-msg"),
                validMsg = document.querySelector("#valid-msg");

                if(input) {
                    window.iti = window.intlTelInput(input, {
                        hiddenInput: "contact_number",
                        separateDialCode: true,
                        utilsScript: "{{ asset('vendor/intlTelInput/js/utils.js') }}" // just for formatting/placeholders etc
                    });

                    input.addEventListener("countrychange", function() {
                        validate();
                    });

                    // here, the index maps to the error code returned from getValidationError - see readme
                    var errorMap = [ "Invalid number", "Invalid country code", "Too short", "Too long", "Invalid number"];
                    //
                    //  initialise plugin
                    const phone = $('#phone');
                    const err = $('#error-msg');
                    const succ = $('#valid-msg');
                    var reset = function() {
                        err.addClass('d-none');
                        succ.addClass('d-none');
                        validate();
                    };

                    // on blur: validate
                    $(document).on('blur, keyup','#phone',function () {
                        reset();
                        var val = $(this).val();
                        if (val.match(/[^0-9\.\+.\s.]/g)) {
                            $(this).val(val.replace(/[^0-9\.\+.\s.]/g, ''));
                        }                        
                        if(val === ''){
                            $('[type="submit"]').removeClass('disabled').prop('disabled',false);
                        }
                    });

                    // on keyup / change flag: reset
                    input.addEventListener('change', reset);
                    input.addEventListener('keyup', reset);

                    var errorCode = '';

                    function validate() {
                        if (input.value.trim()) {
                            if (iti.isValidNumber()) {
                               
                                $("#valid-msg").removeClass('d-none');
                                $(".error-message").addClass('d-none').text('');
                                $('[type="submit"]').removeClass('disabled').prop('disabled',false);
                            } 
                            else {
                               
                                errorCode = iti.getValidationError();
                                $(".error-message").removeClass('d-none').text(errorMap[errorCode]);
                                $("#valid-msg").addClass('d-none');
                                phone.closest('.form-group').addClass('has-danger');
                                $('[type="submit"]').addClass('disabled').prop('disabled',true);
                                
                            }
                        }
                    }
                }
            @endif
        </script>
    @endsection

</x-frontend-layout>