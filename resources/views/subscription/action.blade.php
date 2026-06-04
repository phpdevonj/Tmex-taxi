
<?php
    $auth_user= authSession();
?>
{{ Form::open(['route' => ['subscription.destroy', $id], 'method' => 'delete','data--submit'=>'subscription'.$id]) }}
    <div class="d-flex justify-content-end align-items-center">
        {{--  @if($auth_user->can('subscription-edit')  --}}
            <a class="mr-2" href="{{ route('subscription.edit', $id) }}" title="{{ __('message.update_form_title',['form' => __('message.subscription') ]) }}"><i class="fas fa-edit text-primary"></i></a>
        {{--  @endif  --}}

        {{--  @if($auth_user->can('subscription-delete'))  --}}
            <a class="mr-2 text-danger" href="javascript:void(0)" data--submit="subscription{{$id}}" 
                data--confirmation='true' data-title="{{ __('message.delete_form_title',['form'=> __('message.subscription') ]) }}"
                title="{{ __('message.delete_form_title',['form'=>  __('message.subscription') ]) }}"
                data-message='{{ __("message.delete_msg") }}'>
                <i class="fas fa-trash-alt"></i>
            </a>
        {{--  @endif  --}}
    </div>
{{ Form::close() }}