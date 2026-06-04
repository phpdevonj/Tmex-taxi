<x-master-layout :assets="$assets ?? []">
    <div>
        <?php $id = $id ?? null;?>
        @if(isset($id))
            {!! Form::model($data, ['route' => ['subscription.update', $id], 'method' => 'patch']) !!}
        @else
            {!! Form::open(['route' => ['subscription.store'], 'method' => 'post']) !!}
        @endif
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <div class="header-title">
                            <h4 class="card-title">{{ $pageTitle }}</h4>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="new-user-info">
                            <div class="row">
                                <div class="form-group col-md-4">
                                    {{ Form::label('name',__('message.name').' <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
                                    {{ Form::text('name', old('name'),[ 'placeholder' => __('message.name'),'class' =>'form-control','required']) }}
                                </div>

                                <div class="form-group col-md-4">
                                    {{ Form::label('price', __('message.price').' <span class="text-danger">*</span>',['class' => 'form-control-label'], false ) }}
                                    {{ Form::number('price', old('price'),[ 'step' =>'any', 'min' =>'0', 'placeholder' => __('message.price'), 'class' => 'form-control'
                                        ,'required',isset($id) ? 'readonly' : null]) }}
                                </div>

                                <div class="form-group col-md-4">
                                    {{ Form::label('currency',__('message.currency'),['class'=>'form-control-label'], false ) }}
                                    <select name="currency" class="form-control" {{ isset($id) ? 'disabled' : '' }}>
                                        @foreach($currencies as $code => $name)
                                            <option value="{{ $code }}" {{ old('currency', 'usd') === $code ? 'selected' : '' }}>
                                                {{ strtoupper($code) }} - {{ $name }}
                                            </option>
                                        @endforeach
                                    </select>

                                </div>

                                <div class="form-group col-md-4">
                                    {{ Form::label('interval', __('message.interval'), ['class' => 'form-control-label']) }}
                                    
                                    {{ Form::select('interval', ['month' => 'Monthly', 'year' => 'Yearly','free'=>'Free'], old('interval', $data->interval ?? null), [
                                            'class' => 'form-control select2js',
                                            'required' => true, 
                                            'data-placeholder' => __('message.select_field', ['name' => __('message.interval')]),
                                            ($id ?? false) ? 'disabled' : '',
                                        ]) 
                                    }}
                                </div>

                                <div class="form-group col-md-4">
                                    {{ Form::label('description',__('message.description'),['class'=>'form-control-label'], false ) }}
                                    {{ Form::textarea('description', old('description', $data->description ?? null), [
                                            'placeholder' => __('message.description'),
                                            'class' => 'form-control',
                                            'rows' => 4
                                        ])
                                    }} 
                                </div>
                            </div>
                            <hr>
                            {{ Form::button(
                                '<span id="button-loader" style="display:none;"><div class="spinner-border spinner-border-sm text-light" role="status"></div></span> ' . 
                                (isset($id) ? __('message.update') : __('message.save')), 
                                [
                                    'type' => 'submit',
                                    'class' => 'btn btn-md btn-primary float-right',
                                    'id' => 'submit-btn'
                                ]
                            ) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {!! Form::close() !!}
    </div>
    
    @section('bottom_script')
        <script>
            (function($) {
                "use strict";
                $(document).ready(function() {
                });
            })(jQuery);
        </script>
    @endsection
</x-master-layout>
