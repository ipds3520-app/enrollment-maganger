@extends('layouts.app')

@section('title', 'Shared Registration Token Diterbitkan')

@section('content')
    <h1>Pengajuan Disetujui</h1>
    <p class="subtitle">{{ $internshipRequest->title }} · {{ $internshipRequest->institution->name }}</p>

    <section class="card" style="margin-bottom:18px">
        <p role="alert" style="margin:0 0 16px;color:#92400e;font-weight:600">
            Token plaintext hanya ditampilkan pada hasil penerbitan ini. Salin dan bagikan kepada Operator secara aman. Token tidak dapat dilihat kembali dari halaman detail.
        </p>
        <dl class="detail">
            <dt>Kuota awal</dt><dd>{{ $sharedToken->quota }} peserta</dd>
            <dt>Jumlah terpakai</dt><dd>{{ $sharedToken->used_count }}</dd>
        </dl>
        <label for="shared-registration-token">Shared registration token
            <input id="shared-registration-token" type="text" readonly value="{{ $plaintextToken }}" autocomplete="off" spellcheck="false" style="display:block;width:100%;padding:10px;margin:5px 0 8px;border:1px solid #cbd5e1;border-radius:8px;font-family:monospace">
        </label>
        <button type="button" id="copy-shared-token" style="padding:8px 12px;border:0;border-radius:6px;background:#2563eb;color:#fff;cursor:pointer">Salin token</button>
        <p class="muted">Setelah diteruskan kepada Operator, Operator dapat membagikan token yang sama kepada peserta sesuai kuota.</p>
    </section>

    <p><a href="{{ route('admin.internship-requests.show', $internshipRequest) }}">Kembali ke detail pengajuan</a></p>

    <script>
        document.getElementById('copy-shared-token').addEventListener('click', async () => {
            const input = document.getElementById('shared-registration-token');
            try {
                await navigator.clipboard.writeText(input.value);
            } catch {
                input.select();
                document.execCommand('copy');
            }
            document.getElementById('copy-shared-token').textContent = 'Token tersalin';
        });
    </script>
@endsection
