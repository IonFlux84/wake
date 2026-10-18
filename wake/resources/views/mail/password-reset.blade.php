{{ __('Hello :name,', ['name' => $account->name()]) }}

{{ __('someone asked to reset the password of your account. If that was you, choose a new password here:') }}

{{ $url }}

{{ __('The link works for 60 minutes. If you did not ask for this, you can ignore this mail.') }}

{{ app(\Wake\Content\Site::class)->title() }}
