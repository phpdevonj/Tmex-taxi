{{ Form::open(['method' => 'POST','route' => ['signupSettingsUpdate'],'data-toggle'=>'validator']) }}
{{ Form::hidden('page', $page, ['class' => 'form-control'] ) }}
    
    <div class="col-md-12 mt-20">
        <div class="row">
        @foreach($signup_setting as $key => $value)
            <div class="col-md-6 form-group">             
                @php
                    $value = isset($value) ? $value : 0;
                @endphp
                <div class="d-block">
                    @if($key == "email_password")
                        <i class="fas fa-user-lock fa-2x mr-2" title="Email & Password"></i>
                    @elseif($key == "phone")
                        <i class="fas fa-phone-alt fa-2x mr-2" title="Phone"></i>
                    @elseif($key == "google")
                        <i class="fab fa-google fa-2x mr-3" title="Google"></i>
                    @elseif($key == "apple")
                        <i class="fab fa-apple fa-2x mr-3" title="Apple"></i>
                    @endif
                    <div class="custom-switch custom-switch-text custom-switch-color custom-control-inline mt-2">
                        <div class="custom-switch-inner">
                            {{ Form::hidden($key, 0) }}
                            {{ Form::checkbox($key, 1, $value == '1' , [
                                'class' => 'custom-control-input bg-dark',
                                'data-type' => 'pages',
                                'data-id' => $key,
                                'id' => 'switch_'.$key
                            ]) }} 
                            <label class="custom-control-label ml-2" for="switch_{{ $key }}"></label>
                        </div>
                    </div>  
                </div>
            </div>       
        @endforeach  
        </div>
    </div>
{{ Form::submit(__('message.save'), ['class'=>"btn btn-md btn-primary float-md-right"]) }}
{{ Form::close() }}