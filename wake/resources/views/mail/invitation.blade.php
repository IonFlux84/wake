{{ __('Hello :name,', ['name' => $account->name()]) }}

{{ __(':inviter invites you to the team of :site as :role. Choose your password here to get started:', ['inviter' => $inviter->name(), 'site' => app(\Wake\Content\Site::class)->title(), 'role' => $role]) }}

{{ $url }}

{{ __('The link works for 7 days.') }}
