<x-mail::message>
# {{ __('messages.verification_code_heading') }}

{{ __('messages.verification_code_intro', ['form' => $form->title]) }}

<x-mail::panel>
<div style="text-align: center; font-size: 28px; font-weight: bold; letter-spacing: 6px;">{{ $code }}</div>
</x-mail::panel>

{{ __('messages.verification_code_expiry') }}

{{ __('messages.verification_code_ignore') }}
</x-mail::message>
