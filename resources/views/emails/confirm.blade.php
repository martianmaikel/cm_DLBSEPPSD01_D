@component('emails.layouts.newsletter', [
    'locale' => $locale,
    'preheader' => __('newsletter.confirm.intro'),
    'unsubscribeUrl' => $unsubscribeUrl,
])

<h1 style="margin: 0 0 16px 0; font-family: 'Bebas Neue', 'Arial Narrow', Arial, sans-serif; font-size: 28px; letter-spacing: 1.5px; color: #6FD65A; text-transform: uppercase; font-weight: normal;">
    @lang('newsletter.confirm.heading')
</h1>

<p style="margin: 0 0 24px 0; font-size: 15px; line-height: 1.6; color: #D4E8CF;">
    @lang('newsletter.confirm.intro')
</p>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 0 24px 0;">
    <tr>
        <td style="background-color: #3D7A32; border: 1px solid #52A844;">
            <a href="{{ $confirmUrl }}" style="display: inline-block; padding: 12px 28px; font-family: 'Courier New', monospace; font-size: 13px; letter-spacing: 2px; color: #D4E8CF; text-transform: uppercase; text-decoration: none; font-weight: bold;">
                @lang('newsletter.confirm.cta')
            </a>
        </td>
    </tr>
</table>

<p style="margin: 0 0 8px 0; font-size: 12px; line-height: 1.5; color: #8AAD83;">
    @lang('newsletter.confirm.fallback')
</p>
<p style="margin: 0 0 24px 0; font-family: 'Courier New', monospace; font-size: 11px; line-height: 1.5; color: #52A844; word-break: break-all;">
    {{ $confirmUrl }}
</p>

<p style="margin: 0; font-size: 12px; line-height: 1.5; color: #4A6344; font-style: italic;">
    @lang('newsletter.confirm.ignore')
</p>

@endcomponent
