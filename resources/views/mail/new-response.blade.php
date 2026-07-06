<x-mail::message>
# {{ __('messages.new_response_heading') }}

{{ __('messages.new_response_intro', ['form' => $form->title]) }}

@if ($response->email)
{{ __('messages.respondent_email') }} : {{ $response->email }}
@endif

<x-mail::button :url="route('forms.responses.index', $form)">
{{ __('messages.view_responses') }}
</x-mail::button>
</x-mail::message>
