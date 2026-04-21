{{-- Affiliate slot: sponsored content. Rendered only when an affiliate is available. --}}
@if(!empty($affiliate))
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0 0 28px 0; background-color: #181D16; border: 1px solid #3D7A32;">
        <tr>
            <td style="padding: 4px 12px; background-color: #1A3018; border-bottom: 1px solid #243320;">
                <span style="font-family: 'Courier New', monospace; font-size: 9px; letter-spacing: 2px; color: #8AAD83; text-transform: uppercase;">
                    {{ $locale === 'de' ? 'Anzeige' : 'Sponsored' }}
                </span>
            </td>
        </tr>
        <tr>
            <td style="padding: 18px 20px;">
                @if(!empty($affiliate['image_url']))
                    <img src="{{ $affiliate['image_url'] }}" alt="{{ $affiliate['headline'] }}" style="display: block; width: 100%; max-width: 560px; height: auto; margin-bottom: 14px; border: 0;">
                @endif

                <h3 style="margin: 0 0 8px 0; font-family: 'Bebas Neue', 'Arial Narrow', Arial, sans-serif; font-size: 20px; letter-spacing: 0.5px; color: #D4E8CF; font-weight: normal;">
                    {{ $affiliate['headline'] }}
                </h3>

                @if(!empty($affiliate['body']))
                    <p style="margin: 0 0 14px 0; font-size: 13px; line-height: 1.55; color: #8AAD83;">
                        {{ $affiliate['body'] }}
                    </p>
                @endif

                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td style="background-color: #3D7A32; border: 1px solid #52A844;">
                            <a href="{{ $affiliate['url'] }}" style="display: inline-block; padding: 9px 20px; font-family: 'Courier New', monospace; font-size: 11px; letter-spacing: 2px; color: #D4E8CF; text-transform: uppercase; text-decoration: none; font-weight: bold;">
                                {{ $affiliate['cta'] }}
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endif
