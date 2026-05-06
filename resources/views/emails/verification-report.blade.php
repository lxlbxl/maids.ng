<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml"
    xmlns:o="urn:schemas-microsoft-com:office:office">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>Identity Verification Report — Maids.ng</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* Reset */
        body,
        table,
        td,
        a {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        table,
        td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }

        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }

        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            height: 100% !important;
        }

        /* Responsive */
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
            }

            .fluid {
                max-width: 100% !important;
                height: auto !important;
            }

            .stack-column {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            .stack-column-center {
                text-align: center !important;
            }

            .center-on-narrow {
                text-align: center !important;
                display: block !important;
                margin-left: auto !important;
                margin-right: auto !important;
            }

            .padding-mobile {
                padding-left: 16px !important;
                padding-right: 16px !important;
            }

            .button-full {
                width: 100% !important;
                display: block !important;
            }
        }
    </style>
</head>

<body
    style="margin:0; padding:0; background-color:#FDFCFB; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">

    <!-- Preview Text (hidden) -->
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">
        Your NIN verification report for {{ $verification->maid_first_name }} {{ $verification->maid_last_name }} is
        ready.
        &zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;
    </div>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"
        style="background-color:#FDFCFB;">
        <tr>
            <td align="center" style="padding: 20px 10px;">
                <!--[if mso]>
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" align="center">
                <tr>
                <td>
                <![endif]-->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"
                    style="max-width:600px; margin:0 auto;" class="email-container">

                    <!-- Header -->
                    <tr>
                        <td
                            style="background: linear-gradient(135deg, #2D3436 0%, #3D4447 100%); padding: 32px 24px; text-align: center; border-radius: 16px 16px 0 0;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="text-align: center;">
                                        <img src="{{ asset('maids-logo-white.png') }}" alt="Maids.ng" width="120"
                                            style="display:block; margin:0 auto 16px; max-width:120px; height:auto;">
                                        <h1
                                            style="margin:0; font-size:14px; font-weight:600; letter-spacing:3px; text-transform:uppercase; color:rgba(255,255,255,0.7);">
                                            Identity Verification Report</h1>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Status Banner -->
                    <tr>
                        <td style="padding: 24px 24px 0; background-color:#FFFFFF;" class="padding-mobile">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td
                                        style="background-color:{{ $verification->verification_status === 'success' ? '#E8F5E9' : '#FFEBEE' }}; border-radius:12px; padding:20px; text-align:center; border: 1px solid {{ $verification->verification_status === 'success' ? '#C8E6C9' : '#FFCDD2' }};">
                                        <div style="font-size:32px; margin-bottom:8px;">
                                            {{ $verification->verification_status === 'success' ? '✓' : '✕' }}
                                        </div>
                                        <h2
                                            style="margin:0 0 4px; font-size:18px; font-weight:700; color:{{ $verification->verification_status === 'success' ? '#2E7D32' : '#C62828' }};">
                                            {{ $verification->verification_status === 'success' ? 'Verification Successful' : 'Verification Failed' }}
                                        </h2>
                                        <p style="margin:0; font-size:13px; color:#636E72;">
                                            {{ $verification->verification_status === 'success'
    ? 'The identity details match the National Identity Database (NIMC).'
    : 'The details could not be matched with NIMC records.'
                                            }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Greeting -->
                    <tr>
                        <td style="padding: 24px 24px 0; background-color:#FFFFFF;" class="padding-mobile">
                            <p style="margin:0 0 12px; font-size:16px; color:#2D3436;">
                                Hello
                                <strong>{{ $verification->requester_name ?? $verification->requester->name ?? $verification->requester_email ?? 'Valued Customer' }}</strong>,
                            </p>
                            <p style="margin:0; font-size:14px; color:#636E72; line-height:1.6;">
                                The identity verification for <strong
                                    style="color:#2D3436;">{{ $verification->maid_first_name }}
                                    {{ $verification->maid_last_name }}</strong> has been completed.
                            </p>
                        </td>
                    </tr>

                    <!-- Verified Details -->
                    <tr>
                        <td style="padding: 24px; background-color:#FFFFFF;" class="padding-mobile">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"
                                style="background-color:#F8F9FA; border-radius:12px; border:1px solid #E9ECEF;">
                                <tr>
                                    <td style="padding:20px;">
                                        <p
                                            style="margin:0 0 16px; font-size:10px; font-weight:600; letter-spacing:2px; text-transform:uppercase; color:#636E72;">
                                            Verified Details</p>

                                        @php
                                            $qoreData = $verification->verification_data['data'] ?? [];
                                        @endphp

                                        <!-- Name -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0"
                                            width="100%" style="margin-bottom:12px;">
                                            <tr>
                                                <td style="font-size:13px; color:#636E72; padding-bottom:4px;">Full Name
                                                </td>
                                                <td
                                                    style="font-size:14px; font-weight:600; color:#2D3436; text-align:right;">
                                                    {{ $qoreData['first_name'] ?? $verification->maid_first_name }}
                                                    {{ $qoreData['last_name'] ?? $verification->maid_last_name }}
                                                    @if(!empty($qoreData['middlename']))
                                                        {{ $qoreData['middlename'] }}
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- NIN -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0"
                                            width="100%" style="margin-bottom:12px;">
                                            <tr>
                                                <td style="font-size:13px; color:#636E72; padding-bottom:4px;">NIN
                                                    (Masked)</td>
                                                <td
                                                    style="font-size:14px; font-weight:600; color:#2D3436; text-align:right;">
                                                    {{ substr($verification->maid_nin, 0, 3) }}****{{ substr($verification->maid_nin, -3) }}
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- DOB -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0"
                                            width="100%" style="margin-bottom:12px;">
                                            <tr>
                                                <td style="font-size:13px; color:#636E72; padding-bottom:4px;">Date of
                                                    Birth</td>
                                                <td
                                                    style="font-size:14px; font-weight:600; color:#2D3436; text-align:right;">
                                                    {{ $qoreData['dob'] ?? $verification->maid_dob ?? '—' }}
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Gender -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0"
                                            width="100%" style="margin-bottom:12px;">
                                            <tr>
                                                <td style="font-size:13px; color:#636E72; padding-bottom:4px;">Gender
                                                </td>
                                                <td
                                                    style="font-size:14px; font-weight:600; color:#2D3436; text-align:right; text-transform:capitalize;">
                                                    {{ $qoreData['gender'] ?? $verification->maid_gender ?? '—' }}
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Phone -->
                                        @if(!empty($qoreData['phone']))
                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0"
                                                width="100%" style="margin-bottom:12px;">
                                                <tr>
                                                    <td style="font-size:13px; color:#636E72; padding-bottom:4px;">Phone
                                                    </td>
                                                    <td
                                                        style="font-size:14px; font-weight:600; color:#2D3436; text-align:right;">
                                                        {{ $qoreData['phone'] }}
                                                    </td>
                                                </tr>
                                            </table>
                                        @endif

                                        <!-- Email -->
                                        @if(!empty($qoreData['email']))
                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0"
                                                width="100%" style="margin-bottom:12px;">
                                                <tr>
                                                    <td style="font-size:13px; color:#636E72; padding-bottom:4px;">Email
                                                    </td>
                                                    <td
                                                        style="font-size:14px; font-weight:600; color:#2D3436; text-align:right;">
                                                        {{ $qoreData['email'] }}
                                                    </td>
                                                </tr>
                                            </table>
                                        @endif

                                        <!-- State of Origin -->
                                        @if(!empty($qoreData['state_of_origin']))
                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0"
                                                width="100%" style="margin-bottom:12px;">
                                                <tr>
                                                    <td style="font-size:13px; color:#636E72; padding-bottom:4px;">State of
                                                        Origin
                                                    </td>
                                                    <td
                                                        style="font-size:14px; font-weight:600; color:#2D3436; text-align:right;">
                                                        {{ $qoreData['state_of_origin'] }}
                                                    </td>
                                                </tr>
                                            </table>
                                        @endif

                                        <!-- LGA of Origin -->
                                        @if(!empty($qoreData['lga_of_origin']))
                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0"
                                                width="100%" style="margin-bottom:12px;">
                                                <tr>
                                                    <td style="font-size:13px; color:#636E72; padding-bottom:4px;">LGA of
                                                        Origin
                                                    </td>
                                                    <td
                                                        style="font-size:14px; font-weight:600; color:#2D3436; text-align:right;">
                                                        {{ $qoreData['lga_of_origin'] }}
                                                    </td>
                                                </tr>
                                            </table>
                                        @endif

                                        <!-- Nationality -->
                                        @if(!empty($qoreData['nationality']))
                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0"
                                                width="100%" style="margin-bottom:12px;">
                                                <tr>
                                                    <td style="font-size:13px; color:#636E72; padding-bottom:4px;">
                                                        Nationality
                                                    </td>
                                                    <td
                                                        style="font-size:14px; font-weight:600; color:#2D3436; text-align:right;">
                                                        {{ $qoreData['nationality'] }}
                                                    </td>
                                                </tr>
                                            </table>
                                        @endif

                                        <!-- Confidence Score -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0"
                                            width="100%">
                                            <tr>
                                                <td style="font-size:13px; color:#636E72; padding-bottom:4px;">Match
                                                    Confidence</td>
                                                <td
                                                    style="font-size:14px; font-weight:700; color:#008080; text-align:right;">
                                                    {{ $verification->confidence_score ?? $verification->verification_data['confidence'] ?? 0 }}%
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- CTA Button -->
                    <tr>
                        <td style="padding: 0 24px 24px; background-color:#FFFFFF;" class="padding-mobile">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td align="center" style="padding-top:8px;">
                                        <p style="margin:0 0 16px; font-size:14px; color:#636E72;">
                                            View and download the full detailed report with the official NIMC
                                            photograph.
                                        </p>
                                        <!--[if mso]>
                                        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ route('standalone-verification.report', $verification->payment_reference) }}" style="height:48px;v-text-anchor:middle;width:280px;" arcsize="17%" strokecolor="#008080" fillcolor="#008080">
                                        <w:anchorlock/>
                                        <center style="color:#ffffff;font-family:sans-serif;font-size:15px;font-weight:bold;">View Full Report</center>
                                        </v:roundrect>
                                        <![endif]-->
                                        <!--[if !mso]><!-->
                                        <a href="{{ route('standalone-verification.report', $verification->payment_reference) }}"
                                            class="button-full"
                                            style="display:inline-block; padding:14px 40px; background-color:#008080; color:#ffffff !important; text-decoration:none; border-radius:8px; font-weight:700; font-size:15px; text-align:center; box-shadow: 0 4px 12px rgba(0,128,128,0.25);">
                                            View Full Report
                                        </a>
                                        <!--<![endif]-->
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Metadata -->
                    <tr>
                        <td style="padding: 0 24px 24px; background-color:#FFFFFF;" class="padding-mobile">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"
                                style="background-color:#F8F9FA; border-radius:8px;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <p
                                            style="margin:0 0 12px; font-size:10px; font-weight:600; letter-spacing:2px; text-transform:uppercase; color:#636E72;">
                                            Report Details</p>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0"
                                            width="100%">
                                            <tr>
                                                <td style="font-size:12px; color:#636E72; padding-bottom:6px;">Reference
                                                </td>
                                                <td
                                                    style="font-size:12px; font-weight:600; color:#2D3436; text-align:right; font-family:monospace;">
                                                    {{ $verification->payment_reference }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size:12px; color:#636E72; padding-bottom:6px;">Provider
                                                </td>
                                                <td
                                                    style="font-size:12px; font-weight:600; color:#2D3436; text-align:right;">
                                                    NIMC via QoreID</td>
                                            </tr>
                                            <tr>
                                                <td style="font-size:12px; color:#636E72; padding-bottom:6px;">Date</td>
                                                <td
                                                    style="font-size:12px; font-weight:600; color:#2D3436; text-align:right;">
                                                    {{ $verification->updated_at->format('M d, Y') }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size:12px; color:#636E72;">Agent</td>
                                                <td
                                                    style="font-size:12px; font-weight:600; color:#008080; text-align:right;">
                                                    Gatekeeper</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 24px; background-color:#FFFFFF; border-radius: 0 0 16px 16px;"
                            class="padding-mobile">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="border-top:1px solid #F0F0F0; padding-top:20px; text-align:center;">
                                        <p style="margin:0 0 4px; font-size:11px; color:#95A5A6;">&copy; {{ date('Y') }}
                                            Maids.ng. All rights reserved.</p>
                                        <p style="margin:0 0 12px; font-size:11px; color:#95A5A6;">Trusted domestic
                                            help, verified and secured.</p>
                                        <p style="margin:0; font-size:10px; font-style:italic; color:#B2BEC3;">
                                            This verification was processed by the Gatekeeper AI Agent via official
                                            QoreID & NIMC channels.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>
                <!--[if mso]>
                </td>
                </tr>
                </table>
                <![endif]-->
            </td>
        </tr>
    </table>
</body>

</html>