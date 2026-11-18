@extends('layouts.app')

@section('title', 'Registration Token Diterbitkan')

@section('content')
    <h1>{{ ($replacement ?? false) ? 'Token Peserta Diterbitkan' : 'Pengajuan Disetujui' }}</h1>
    <p class="subtitle">{{ $internshipRequest->title }} · {{ $internshipRequest->institution->name }}</p>

    <section class="card" style="margin-bottom:18px">
        <p role="alert" style="margin:0 0 16px;color:#92400e;font-weight:600">
            Token asli hanya ditampilkan pada respons ini. Salin dan distribusikan dengan aman; token tidak dapat dilihat kembali.
        </p>

        <div style="display:grid;gap:14px">
            @foreach ($issuedTokens as $index => $item)
                <article style="padding:14px;border:1px solid #dde3ee;border-radius:10px">
                    <h2 style="font-size:1rem;margin:0 0 10px">{{ $item['applicant']->name }}</h2>
                    <dl class="detail">
                        <dt>Nomor induk</dt><dd>{{ $item['applicant']->student_number ?: '—' }}</dd>
                        <dt>Email</dt><dd>{{ $item['applicant']->email }}</dd>
                        <dt>Kedaluwarsa</dt><dd>{{ $item['expires_at']->format('d/m/Y H:i') }}</dd>
                    </dl>
                    <label for="registration-token-{{ $index }}">Registration token
                        <input id="registration-token-{{ $index }}" type="text" readonly value="{{ $item['token'] }}" autocomplete="off" spellcheck="false" style="display:block;width:100%;padding:10px;margin:5px 0 8px;border:1px solid #cbd5e1;border-radius:8px;font-family:monospace">
                    </label>
                    <button type="button" data-copy-token="registration-token-{{ $index }}" style="padding:8px 12px;border:0;border-radius:6px;background:#2563eb;color:#fff;cursor:pointer">Salin token</button>
                </article>
            @endforeach
        </div>
    </section>

    <p><a href="{{ route('admin.internship-requests.show', $internshipRequest) }}">Kembali ke detail pengajuan</a></p>

    <script>
        document.querySelectorAll('[data-copy-token]').forEach((button) => {
            button.addEventListener('click', async () => {
                const input = document.getElementById(button.dataset.copyToken);
                try {
                    await navigator.clipboard.writeText(input.value);
                    button.textContent = 'Token tersalin';
                } catch {
                    input.select();
                    document.execCommand('copy');
                    button.textContent = 'Token tersalin';
                }
            });
        });
    </script>
@endsection
