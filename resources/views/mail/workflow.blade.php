<?php
/** @var array{event: string, title: string, message: string, meta: array<string, string|null>, action_url: string, company: array{name: string, address: string}} $payload */
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $payload['title'] }}</title>
</head>
<body style="margin:0; padding:0; background-color:#e2e8f0; font-family:'Segoe UI',Arial,'Helvetica Neue',sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#e2e8f0; padding:24px 12px;">
    <tr><td align="center">
        <table role="presentation" width="620" cellpadding="0" cellspacing="0" style="width:620px; max-width:100%; background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 14px rgba(15,23,42,0.10);">
            <tr>
                <td style="background-color:#0f172a; padding:18px 28px;" bgcolor="#0f172a">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td>
                                <span style="color:#ffffff; font-size:16px; font-weight:700;">{{ $payload['company']['name'] }}</span><br>
                                <span style="color:#94a3b8; font-size:10px;">{{ $payload['company']['address'] }}</span>
                            </td>
                            <td align="right" style="vertical-align:top;">
                                <span style="display:inline-block; background-color:#1e40af; color:#ffffff; font-size:11px; font-weight:700; padding:4px 10px; border-radius:999px;">eForm BP</span>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td style="padding:28px 28px 6px;">
                    <h2 style="margin:0; font-size:18px; color:#0f172a;">{{ $payload['title'] }}</h2>
                    <p style="margin:8px 0 0; font-size:14px; line-height:1.65; color:#334155;">{{ $payload['message'] }}</p>
                </td>
            </tr>
            @if (array_filter($payload['meta']))
            <tr>
                <td style="padding:16px 28px 0;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9; border-radius:8px;">
                        @foreach (array_filter($payload['meta']) as $key => $value)
                        <tr>
                            <td style="padding:7px 14px; font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#94a3b8; width:120px; vertical-align:top;">{{ ['module' => 'Modul', 'document_number' => 'Nomor dokumen', 'actor' => 'Pelaku', 'step' => 'Tahap'][$key] ?? $key }}</td>
                            <td style="padding:7px 14px 7px 0; font-size:12.5px; font-weight:600; color:#0f172a;">{{ $value }}</td>
                        </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
            @endif
            <tr>
                <td style="padding:24px 28px 0;">
                    <a href="{{ $payload['action_url'] }}" style="display:inline-block; background-color:#1d4ed8; color:#ffffff; font-size:14px; font-weight:700; text-decoration:none; padding:12px 26px; border-radius:8px;">Buka di eForm BP</a>
                    <p style="margin:12px 0 0; font-size:11px; color:#94a3b8;">Jika tombol tidak berfungsi, salin tautan berikut ke browser:<br><a href="{{ $payload['action_url'] }}" style="color:#1d4ed8; word-break:break-all;">{{ $payload['action_url'] }}</a></p>
                </td>
            </tr>
            <tr>
                <td style="padding:24px 28px 20px;">
                    <div style="border-top:1px solid #e2e8f0; padding-top:12px; font-size:10.5px; color:#94a3b8;">Email otomatis dari eForm BP — tidak perlu dibalas. Notifikasi serupa juga tersedia di aplikasi.</div>
                </td>
            </tr>
        </table>
    </td></tr>
</table>
</body>
</html>
