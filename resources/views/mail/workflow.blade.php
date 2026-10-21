<?php
/** @var array{event: string, title: string, message: string, title_en?: string|null, message_en?: string|null, meta: array<string, string|null>, progress?: list<array{code: string|null, label: string, status: string, approver: string|null, acted_at: string|null}>, action_url: string, company: array{name: string, address: string}} $payload */
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
                <td style="background-color:#0f172a; padding:20px 28px;" bgcolor="#0f172a">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="vertical-align:middle;" width="60">
                                <img src="{{ $message->embed(public_path('images/logo-bp.png')) }}" width="48" height="48" alt="Logo BP" style="display:block; width:48px; height:48px; border-radius:8px; background-color:#ffffff;" border="0">
                            </td>
                            <td style="vertical-align:middle;">
                                <span style="color:#ffffff; font-size:16px; font-weight:700;">{{ $payload['company']['name'] }}</span><br>
                                <span style="color:#94a3b8; font-size:10px;">{{ $payload['company']['address'] }}</span>
                            </td>
                            <td align="right" style="vertical-align:middle;">
                                <span style="display:inline-block; background-color:#1e40af; color:#ffffff; font-size:11px; font-weight:700; padding:4px 10px; border-radius:999px;">eForm BP</span>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td style="padding:28px 28px 6px;">
                    <h2 style="margin:0; font-size:18px; color:#0f172a;">{{ $payload['title'] }}</h2>
                    @if (! empty($payload['title_en']))
                        <p style="margin:4px 0 0; font-size:13px; font-style:italic; color:#475569;">{{ $payload['title_en'] }}</p>
                    @endif
                    <p style="margin:8px 0 0; font-size:14px; line-height:1.65; color:#334155;">{{ $payload['message'] }}</p>
                    @if (! empty($payload['message_en']))
                        <p style="margin:4px 0 0; font-size:13px; font-style:italic; color:#475569;">{{ $payload['message_en'] }}</p>
                    @endif
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
            @php
                $progress = $payload['progress'] ?? [];
                $countedSteps = collect($progress)->whereNotIn('status', ['skipped', 'cancelled']);
                $progressTotal = $countedSteps->count();
                $progressDone = $countedSteps->where('status', 'approved')->count();
                $statusMeta = [
                    'approved' => ['Disetujui', '#047857', '#d1fae5', '&#10003;'],
                    'rejected' => ['Ditolak', '#b91c1c', '#fee2e2', '&#10005;'],
                    'returned' => ['Dikembalikan', '#c2410c', '#ffedd5', '&#8617;'],
                    'pending' => ['Menunggu', '#1d4ed8', '#dbeafe', ''],
                    'delegated' => ['Didelegasikan', '#6d28d9', '#ede9fe', '&#8594;'],
                    'skipped' => ['Dilewati', '#64748b', '#f1f5f9', '&ndash;'],
                    'cancelled' => ['Dibatalkan', '#64748b', '#f1f5f9', '&ndash;'],
                ];
            @endphp
            @if ($progressTotal > 0)
            <tr>
                <td style="padding:18px 28px 0;">
                    <p style="margin:0 0 8px; font-size:10px; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:#94a3b8;">Progress persetujuan &middot; {{ $progressDone }} dari {{ $progressTotal }} tahap disetujui</p>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
                        @foreach ($progress as $step)
                        @php
                            [$statusLabel, $statusColor, $statusBg, $statusMark] = $statusMeta[$step['status']] ?? $statusMeta['pending'];
                            $mark = $statusMark !== '' ? $statusMark : (string) $loop->iteration;
                            $rowBorder = $loop->last ? '' : 'border-bottom:1px solid #e2e8f0;';
                        @endphp
                        <tr>
                            <td style="padding:9px 12px; width:30px; vertical-align:top; {{ $rowBorder }}">
                                <span style="display:inline-block; width:22px; height:22px; line-height:22px; border-radius:11px; background-color:{{ $statusBg }}; color:{{ $statusColor }}; font-size:11px; font-weight:700; text-align:center;">{!! $mark !!}</span>
                            </td>
                            <td style="padding:9px 14px 9px 0; vertical-align:top; {{ $rowBorder }}">
                                <span style="font-size:12.5px; font-weight:700; color:#0f172a;">{{ $step['label'] }}</span>
                                @if (! empty($step['approver']))
                                    <span style="font-size:11.5px; color:#64748b;"> &middot; {{ $step['approver'] }}</span>
                                @endif
                                <br>
                                <span style="font-size:10px; font-weight:700; letter-spacing:0.4px; text-transform:uppercase; color:{{ $statusColor }};">{{ $statusLabel }}</span>
                                @if (! empty($step['acted_at']))
                                    <span style="font-size:10px; color:#94a3b8;"> &middot; {{ $step['acted_at'] }}</span>
                                @endif
                            </td>
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
