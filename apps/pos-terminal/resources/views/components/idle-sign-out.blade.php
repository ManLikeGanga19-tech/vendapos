@props(['hold' => 'false'])

{{--
    Signs the person out after idle_sign_out_seconds without a tap or key press. The server checks the same limit.
    `hold` is a JavaScript expression; while it is true (e.g. money is being taken) the timer just starts again.
--}}
<form
    method="POST"
    action="{{ route('sign-out') }}"
    class="hidden"
    x-data="{
        timer: null,
        reset() { clearTimeout(this.timer); this.timer = setTimeout(() => (({{ $hold }}) ? this.reset() : this.$el.submit()), {{ (int) config('terminal.idle_sign_out_seconds') * 1000 }}); },
    }"
    x-init="reset()"
    x-on:pointerdown.window="reset()"
    x-on:keydown.window="reset()"
>
    @csrf
</form>
