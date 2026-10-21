<?php
/** @var array<string, mixed> $doc */
/** @var string $logo */
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $doc['number'] }}</title>
<style>
    @page { margin: 12mm 12mm; }
    * { box-sizing: border-box; }
    body { margin: 0; padding: 0; font-family: 'DejaVu Sans', Arial, sans-serif; color: #0f172a; font-size: 9.5px; line-height: 1.45; }
    .header { width: 100%; border-collapse: collapse; background-color: #0f172a; color: #ffffff; }
    .header td { padding: 10px 12px; vertical-align: middle; }
    .company-name { font-size: 13px; font-weight: bold; }
    .company-address { font-size: 8px; color: #cbd5e1; }
    .doc-label { font-size: 7px; letter-spacing: 1.2px; text-transform: uppercase; color: #cbd5e1; text-align: right; }
    .doc-number { font-family: 'DejaVu Sans Mono', monospace; font-size: 10px; font-weight: bold; text-align: right; }
    .title { margin: 12px 0 2px; font-size: 10px; font-weight: bold; letter-spacing: 1.3px; text-transform: uppercase; color: #1d4ed8; border-bottom: 2.5px solid #1d4ed8; padding-bottom: 4px; }
    .section { margin: 9px 0 3px; padding-left: 6px; border-left: 3px solid #1d4ed8; font-size: 7.5px; font-weight: bold; letter-spacing: 1.2px; text-transform: uppercase; color: #475569; }
    .grid { width: 100%; border-collapse: separate; border-spacing: 5px 4px; margin-left: -5px; }
    .chip { width: 50%; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 4px 6px; vertical-align: top; }
    .chip-label { display: block; font-size: 6.5px; font-weight: bold; letter-spacing: 0.8px; text-transform: uppercase; color: #94a3b8; }
    .chip-value { display: block; margin-top: 1px; font-size: 8.5px; font-weight: bold; color: #0f172a; }
    .items { width: 100%; border-collapse: collapse; margin-top: 2px; }
    .items th { background-color: #eef2ff; color: #1e3a8a; font-size: 7px; letter-spacing: 0.7px; text-transform: uppercase; text-align: left; padding: 4px 6px; border-bottom: 1px solid #c7d2fe; }
    .items td { padding: 4px 6px; border-bottom: 1px solid #cbd5e1; font-size: 8.5px; vertical-align: top; }
    .items td.key { width: 27%; color: #1e3a8a; font-weight: bold; font-size: 8px; }
    .note { margin-top: 5px; padding: 5px 7px; background-color: #eef2ff; color: #1e3a8a; font-size: 7.5px; border-radius: 3px; }
    .signatures { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin-top: 10px; margin-left: -6px; }
    .signature { width: 25%; border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px; vertical-align: top; }
    .signature-title { font-size: 7.5px; font-weight: bold; color: #1e3a8a; }
    .stamp-box { height: 38px; text-align: center; padding-top: 13px; }
    .stamp { display: inline-block; border: 2px solid #1d4ed8; border-radius: 3px; padding: 3px 5px; font-size: 6.5px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #1e40af; }
    .stamp.requested { border-color: #64748b; color: #475569; }
    .signature-name { font-size: 8px; font-weight: bold; color: #0f172a; }
    .signature-date { font-size: 7px; color: #64748b; }
</style>
</head>
<body>
<table class="header">
    <tr>
        <td width="52" style="width:52px;">
            @if ($logo !== '')
                <img src="{{ $logo }}" width="40" height="40" alt="Logo {{ $doc['company']['name'] }}" style="display:block; width:40px; height:40px; border-radius:5px; background-color:#ffffff;">
            @endif
        </td>
        <td>
            <div class="company-name">{{ $doc['company']['name'] }}</div>
            <div class="company-address">{{ $doc['company']['address'] }}</div>
        </td>
        <td width="170" style="width:170px;">
            <div class="doc-label">Nomor request</div>
            <div class="doc-number">{{ $doc['number'] }}</div>
        </td>
    </tr>
</table>

<div class="title">{{ $doc['title'] }}</div>

<div class="section">Penerima</div>
<table class="grid">
    <tr>
        <td class="chip"><span class="chip-label">Nama</span><span class="chip-value">{{ $doc['recipient']['name'] }}</span></td>
        <td class="chip"><span class="chip-label">NIK</span><span class="chip-value">{{ $doc['recipient']['nik'] }}</span></td>
    </tr>
    <tr>
        <td class="chip"><span class="chip-label">Departemen</span><span class="chip-value">{{ $doc['recipient']['department'] }}</span></td>
        <td class="chip"><span class="chip-label">Jabatan</span><span class="chip-value">{{ $doc['recipient']['job_title'] }}</span></td>
    </tr>
    <tr>
        <td class="chip"><span class="chip-label">Jenis permintaan</span><span class="chip-value">{{ $doc['recipient']['type'] }}</span></td>
        <td class="chip"><span class="chip-label">Site / Cost code</span><span class="chip-value">{{ $doc['recipient']['site'] }}</span></td>
    </tr>
</table>

<div class="section">Persetujuan</div>
<table class="grid">
    <tr>
        <td class="chip"><span class="chip-label">Status</span><span class="chip-value">{{ $doc['status'] }}</span></td>
        <td class="chip"><span class="chip-label">Selesai</span><span class="chip-value">{{ $doc['completed_at'] }}</span></td>
    </tr>
    <tr>
        <td class="chip"><span class="chip-label">Kebutuhan khusus</span><span class="chip-value">{{ $doc['special_specification'] ? 'Ya' : 'Tidak' }}</span></td>
        <td class="chip"><span class="chip-label">Dasar proses</span><span class="chip-value">Pembuatan PR / MSD</span></td>
    </tr>
</table>

<div class="section">Perangkat &amp; Software</div>
<table class="items">
    <thead>
        <tr><th>Komponen</th><th>Rincian</th></tr>
    </thead>
    <tbody>
        <tr><td class="key">Perangkat utama</td><td>{{ $doc['device'] }}</td></tr>
        <tr><td class="key">Accessories</td><td>{{ $doc['accessories'] ? implode(', ', $doc['accessories']) : '-' }}{{ $doc['accessory_other_note'] ? ' ('.$doc['accessory_other_note'].')' : '' }}</td></tr>
        <tr><td class="key">Software standar</td><td>{{ $doc['software_standard'] ? implode(', ', $doc['software_standard']) : '-' }}</td></tr>
        <tr><td class="key">Software tambahan</td><td>{{ $doc['software_optional'] ? implode(', ', $doc['software_optional']) : '-' }}</td></tr>
    </tbody>
</table>

<div class="section">Deskripsi &amp; Tujuan</div>
<table class="items">
    <tbody>
        <tr><td class="key">Deskripsi</td><td>{!! nl2br(e($doc['description'])) !!}</td></tr>
        <tr><td class="key">Tujuan</td><td>{!! nl2br(e($doc['purpose'])) !!}</td></tr>
        @if ($doc['replacement_reason'])
            <tr><td class="key">Alasan penggantian</td><td>{{ $doc['replacement_reason'] }}{{ $doc['replacement_note'] ? ' — '.$doc['replacement_note'] : '' }}</td></tr>
        @endif
    </tbody>
</table>

<div class="section">Tanda tangan</div>
@foreach (array_chunk($doc['signatures'], 4) as $row)
    <table class="signatures">
        <tr>
            @foreach ($row as $signature)
                <td class="signature">
                    <div class="signature-title">{{ $signature['title'] }}</div>
                    <div class="stamp-box"><span class="stamp {{ $signature['requested'] ? 'requested' : '' }}">{{ $signature['requested'] ? 'Requested via eForm' : 'Approved via eForm' }}</span></div>
                    <div class="signature-name">{{ $signature['name'] }}</div>
                    <div class="signature-date">{{ $signature['date'] }}</div>
                </td>
            @endforeach
        </tr>
    </table>
@endforeach

<div class="note">Persetujuan ini bukan persetujuan pembelian. Spesifikasi teknis ditentukan internal oleh IT. Dokumen ini menjadi dasar pembuatan PR/MSD.</div>
</body>
</html>
