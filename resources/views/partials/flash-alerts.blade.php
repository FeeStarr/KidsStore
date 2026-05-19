<script>
(function () {
    if (typeof Swal === 'undefined') return;

    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3500,
        timerProgressBar: true,
        didOpen: (el) => {
            el.addEventListener('mouseenter', Swal.stopTimer);
            el.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    @if(session('success'))
        Toast.fire({ icon: 'success', title: @json(session('success')) });
    @endif

    @if(session('error'))
        Toast.fire({ icon: 'error', title: @json(session('error')) });
    @endif

    @if(session('warning'))
        Toast.fire({ icon: 'warning', title: @json(session('warning')) });
    @endif

    @if(session('info'))
        Toast.fire({ icon: 'info', title: @json(session('info')) });
    @endif

    @if($errors->any())
        Swal.fire({
            icon: 'error',
            title: 'Please fix the errors below',
            html: @json('<ul class="text-start mb-0">' . collect($errors->all())->map(fn($e) => '<li>'.e($e).'</li>')->implode('') . '</ul>'),
            confirmButtonColor: '#ff6fa3'
        });
    @endif

    // Intercept any form/link with [data-confirm] and show a Swal confirm dialog.
    document.addEventListener('submit', function (e) {
        const form = e.target.closest('form[data-confirm]');
        if (!form || form.dataset.confirmed === '1') return;
        e.preventDefault();
        Swal.fire({
            title: form.dataset.confirmTitle || 'Are you sure?',
            text:  form.dataset.confirm,
            icon:  form.dataset.confirmIcon || 'warning',
            showCancelButton: true,
            confirmButtonColor: form.dataset.confirmColor || '#d33',
            cancelButtonColor:  '#6c757d',
            confirmButtonText:  form.dataset.confirmYes || 'Yes, proceed',
            cancelButtonText:   'Cancel'
        }).then((r) => {
            if (r.isConfirmed) {
                form.dataset.confirmed = '1';
                form.submit();
            }
        });
    }, true);

    document.addEventListener('click', function (e) {
        const link = e.target.closest('a[data-confirm]');
        if (!link) return;
        e.preventDefault();
        Swal.fire({
            title: link.dataset.confirmTitle || 'Are you sure?',
            text:  link.dataset.confirm,
            icon:  link.dataset.confirmIcon || 'warning',
            showCancelButton: true,
            confirmButtonColor: link.dataset.confirmColor || '#d33',
            cancelButtonColor:  '#6c757d',
            confirmButtonText:  link.dataset.confirmYes || 'Yes, proceed',
            cancelButtonText:   'Cancel'
        }).then((r) => { if (r.isConfirmed) window.location.href = link.href; });
    });
})();
</script>
