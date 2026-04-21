<!DOCTYPE html>
<html lang="{{ $locale ?? app()->getLocale() }}" style="margin: 0; padding: 0;">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="dark">
    <meta name="supported-color-schemes" content="dark">
    <title>{{ $title ?? 'ClashMonitor' }}</title>
    <!--[if mso]>
    <style type="text/css">
        body, table, td { font-family: Arial, sans-serif !important; }
    </style>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; background-color: #0C0F0B; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; color: #D4E8CF; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
    <!-- Preheader (hidden) -->
    @isset($preheader)
        <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">
            {{ $preheader }}
        </div>
    @endisset

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #0C0F0B;">
        <tr>
            <td align="center" style="padding: 24px 12px;">

                <!-- Main container 600px -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="width: 100%; max-width: 600px; background-color: #111510; border: 1px solid #243320;">

                    <!-- Header -->
                    <tr>
                        <td style="padding: 20px 24px; border-bottom: 1px solid #243320;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td>
                                        <div style="font-family: 'Bebas Neue', 'Arial Narrow', Arial, sans-serif; font-size: 24px; letter-spacing: 2px; color: #6FD65A; text-transform: uppercase; font-weight: normal;">
                                            CLASHMONITOR
                                        </div>
                                        <div style="font-family: 'Courier New', monospace; font-size: 10px; letter-spacing: 2px; color: #4A6344; text-transform: uppercase; margin-top: 4px;">
                                            @lang('newsletter.footer.tagline')
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 28px 24px;">
                            {!! $slot !!}
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px 24px; border-top: 1px solid #243320; background-color: #0C0F0B;">
                            <p style="margin: 0 0 12px 0; font-size: 12px; line-height: 1.5; color: #4A6344;">
                                @lang('newsletter.footer.legal')
                            </p>
                            <p style="margin: 0; font-size: 12px; line-height: 1.5;">
                                <a href="{{ $unsubscribeUrl ?? '#' }}" style="color: #8AAD83; text-decoration: underline;">
                                    @lang('newsletter.footer.unsubscribe')
                                </a>
                                @isset($preferencesUrl)
                                    &nbsp;·&nbsp;
                                    <a href="{{ $preferencesUrl }}" style="color: #8AAD83; text-decoration: underline;">
                                        @lang('newsletter.footer.preferences')
                                    </a>
                                @endisset
                            </p>
                            <p style="margin: 12px 0 0 0; font-family: 'Courier New', monospace; font-size: 10px; letter-spacing: 1.5px; color: #2D3F2B; text-transform: uppercase;">
                                clashmonitor.com
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>
</body>
</html>
