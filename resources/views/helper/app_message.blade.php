<script type="text/javascript">
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        iconColor: 'success',
        customClass: {
            popup: 'colored-toast',
        },
        showConfirmButton: false,
        timer: 2500,
        timerProgressBar: true,
    });

    @if (Session::has('success'))
        Toast.fire({
            icon: 'success',
            title: '{{ Session::get("success") }}',
        });
    @endif

    @if (Session::has('error'))
        Toast.fire({
            icon: 'error',
            title: '{{ Session::get("error") }}',
        });
    @endif

    @if (isset($errors) && $errors instanceof \Illuminate\Support\MessageBag && $errors->any())
        Toast.fire({
            icon: 'error',
            title: '{{ $errors->first() }}',
        });
    @elseif(Session::has('errors') && is_string(Session::get('errors')))
        Toast.fire({
            icon: 'error',
            title: '{{ Session::get("errors") }}',
        });
    @endif


</script>