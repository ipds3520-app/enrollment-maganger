@extends('layouts.app')

@section('title', 'Detail Pengajuan Magang')

@section('content')
    @php
        $statusLabel = match ($internshipRequest->status) {
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => 'Menunggu diproses',
        };
        $statusClass = match ($internshipRequest->status) {
            'approved' => 'badge-success',
            'rejected' => 'badge-danger',
            default => 'badge-warning',
        };
    @endphp

    <div class="page-header">
        <div class="page-header-main">
            <h1 class="page-title">Detail Pengajuan Magang</h1>
            <p class="page-description">Periksa permohonan, data peserta, dan tetapkan keputusan admin.</p>
        </div>
        <div class="page-actions">
            <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
            <a class="btn btn-secondary" href="{{ route('admin.internship-requests.index') }}">
                @include('layouts.partials.icon', ['name' => 'arrow-left'])
                <span>Daftar pengajuan</span>
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">
            @include('layouts.partials.icon', ['name' => 'check-circle'])
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="page-stack">
        <section class="card">
            <div class="card-header">
                <h2 class="card-title">Institusi dan Pengaju</h2>
            </div>
            <div class="detail-grid">
                <div class="detail-item">
                    <p class="detail-label">Institusi</p>
                    <p class="detail-value">{{ $internshipRequest->institution->name }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Alamat</p>
                    <p class="detail-value muted">{{ $internshipRequest->institution->address ?: '—' }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Operator</p>
                    <p class="detail-value">{{ $internshipRequest->creator->name }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Email operator</p>
                    <p class="detail-value">{{ $internshipRequest->creator->email }}</p>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <h2 class="card-title">Informasi Permohonan</h2>
            </div>
            <div class="detail-grid">
                <div class="detail-item">
                    <p class="detail-label">Judul</p>
                    <p class="detail-value">{{ $internshipRequest->title }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Status</p>
                    <p class="detail-value">{{ $statusLabel }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Tanggal mulai</p>
                    <p class="detail-value">{{ $internshipRequest->start_date->format('d/m/Y') }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Tanggal selesai</p>
                    <p class="detail-value">{{ $internshipRequest->end_date->format('d/m/Y') }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Jumlah peserta diajukan</p>
                    <p class="detail-value">{{ $internshipRequest->participant_count ?? $internshipRequest->applicants->count() }} peserta</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Diajukan</p>
                    <p class="detail-value">{{ $internshipRequest->created_at?->format('d/m/Y H:i') ?? '—' }}</p>
                </div>
                @if ($internshipRequest->processed_by)
                    <div class="detail-item">
                        <p class="detail-label">Diproses oleh</p>
                        <p class="detail-value">{{ $internshipRequest->processor?->name ?? '—' }}</p>
                    </div>
                    <div class="detail-item">
                        <p class="detail-label">Waktu keputusan</p>
                        <p class="detail-value">{{ $internshipRequest->decided_at?->format('d/m/Y H:i') ?? '—' }}</p>
                    </div>
                @endif
                <div class="detail-item detail-item-wide">
                    <p class="detail-label">Deskripsi</p>
                    <p class="detail-value muted">{{ $internshipRequest->description ?: '—' }}</p>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Peserta Terdaftar</h2>
                    <p class="card-description">
                        {{ $internshipRequest->applicants->count() }} / {{ $internshipRequest->participant_count ?? $internshipRequest->applicants->count() }} peserta
                    </p>
                </div>
            </div>

            @if ($internshipRequest->applicants->isEmpty())
                <div class="empty-state">
                    @include('layouts.partials.icon', ['name' => 'users'])
                    <h3 class="empty-state-title">Belum ada peserta</h3>
                    <p class="empty-state-text">Peserta akan muncul setelah mendaftar menggunakan token yang diterbitkan.</p>
                </div>
            @else
                <div class="list-stack">
                    @foreach ($internshipRequest->applicants as $applicant)
                        <article class="list-item">
                            <div class="list-item-header">
                                <div>
                                    <h3 class="list-item-title">{{ $applicant->name }}</h3>
                                    <p class="list-item-subtitle">{{ $applicant->email }}</p>
                                </div>
                                @if ($applicant->sharedRegistrationClaim)
                                    @php
                                        $profileComplete = $applicant->sharedRegistrationClaim->status === 'completed';
                                    @endphp
                                    <span class="badge {{ $profileComplete ? 'badge-success' : 'badge-warning' }}">
                                        {{ $profileComplete ? 'Profil lengkap' : 'Profil belum lengkap' }}
                                    </span>
                                @endif
                            </div>

                            <div class="detail-grid">
                                <div class="detail-item">
                                    <p class="detail-label">Telepon</p>
                                    <p class="detail-value">{{ $applicant->phone ?: '—' }}</p>
                                </div>
                                <div class="detail-item">
                                    <p class="detail-label">Nomor induk</p>
                                    <p class="detail-value">{{ $applicant->student_number ?: '—' }}</p>
                                </div>
                                <div class="detail-item">
                                    <p class="detail-label">Jurusan</p>
                                    <p class="detail-value">{{ $applicant->school_major ?: '—' }}</p>
                                </div>
                                @if ($applicant->nik)
                                    <div class="detail-item">
                                        <p class="detail-label">NIK</p>
                                        <p class="detail-value">{{ $applicant->nik }}</p>
                                    </div>
                                    <div class="detail-item">
                                        <p class="detail-label">Asal kampus/sekolah</p>
                                        <p class="detail-value">{{ $applicant->school_name ?: '—' }}</p>
                                    </div>
                                    <div class="detail-item">
                                        <p class="detail-label">Alamat</p>
                                        <p class="detail-value">{{ $applicant->address ?: '—' }}</p>
                                    </div>
                                    <div class="detail-item">
                                        <p class="detail-label">Tanggal lahir</p>
                                        <p class="detail-value">{{ $applicant->birth_date?->format('d/m/Y') ?? '—' }}</p>
                                    </div>
                                    <div class="detail-item">
                                        <p class="detail-label">Jenis kelamin</p>
                                        <p class="detail-value">{{ $applicant->gender ?: '—' }}</p>
                                    </div>
                                @endif
                            </div>

                            @if ($internshipRequest->status === 'approved' && ! $internshipRequest->sharedRegistrationToken)
                                @php
                                    $tokenHistory = $applicant->registrationTokens->sortByDesc('id');
                                    $latestToken = $tokenHistory->first();
                                    $hasUsedToken = $tokenHistory->contains(fn ($token) => $token->status === 'used');
                                    $hasExpiredActiveToken = $tokenHistory->contains(fn ($token) => $token->status === 'active' && $token->expires_at->isPast());
                                    $hasAccount = in_array($applicant->id, $accountApplicantIds, true);
                                @endphp

                                <div class="list-item-section">
                                    <h4 class="card-title">Registration Token</h4>

                                    @forelse ($tokenHistory as $token)
                                        @php
                                            $tokenLabel = match ($token->status) {
                                                'active' => 'Aktif',
                                                'used' => 'Digunakan',
                                                'revoked' => 'Dicabut',
                                                default => ucfirst($token->status),
                                            };
                                            $tokenClass = match ($token->status) {
                                                'active' => 'badge-success',
                                                'revoked' => 'badge-danger',
                                                default => 'badge-neutral',
                                            };
                                        @endphp
                                        <div class="token-row">
                                            <span class="badge {{ $tokenClass }}">{{ $tokenLabel }}</span>
                                            <span>Kedaluwarsa: {{ $token->expires_at->format('d/m/Y H:i') }}</span>
                                            @if ($token->used_at)
                                                <span>Digunakan: {{ $token->used_at->format('d/m/Y H:i') }}</span>
                                            @endif
                                            @if ($token->status === 'active' && ! $hasAccount)
                                                <form method="POST" action="{{ route('admin.internship-requests.registration-tokens.revoke', [$internshipRequest, $token]) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">Cabut token</button>
                                                </form>
                                            @endif
                                        </div>
                                    @empty
                                        <p class="muted">Belum ada token untuk peserta ini.</p>
                                    @endforelse

                                    @if (! $hasAccount && ! $hasUsedToken && $latestToken && ($latestToken->status === 'revoked' || $hasExpiredActiveToken))
                                        <form method="POST" action="{{ route('admin.internship-requests.registration-tokens.replace', [$internshipRequest, $applicant]) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-secondary btn-sm">Terbitkan token pengganti</button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        @if ($internshipRequest->sharedRegistrationToken)
            @php
                $sharedToken = $internshipRequest->sharedRegistrationToken;
                $sharedStatusLabel = match ($sharedToken->status) {
                    'active' => 'Aktif',
                    'exhausted' => 'Kuota habis',
                    'revoked' => 'Dicabut',
                    default => ucfirst($sharedToken->status),
                };
                $sharedStatusClass = match ($sharedToken->status) {
                    'active' => 'badge-success',
                    'revoked' => 'badge-danger',
                    default => 'badge-neutral',
                };
            @endphp
            <section class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Shared Registration Token</h2>
                        <p class="card-description">Token dibagikan kepada peserta sesuai kuota yang disetujui.</p>
                    </div>
                    <span class="badge {{ $sharedStatusClass }}">{{ $sharedStatusLabel }}</span>
                </div>

                <div class="detail-grid">
                    <div class="detail-item">
                        <p class="detail-label">Referensi token</p>
                        <p class="detail-value">#{{ $sharedToken->getKey() }}</p>
                    </div>
                    <div class="detail-item">
                        <p class="detail-label">Kuota</p>
                        <p class="detail-value">{{ $sharedToken->quota }} peserta</p>
                    </div>
                    <div class="detail-item">
                        <p class="detail-label">Terpakai</p>
                        <p class="detail-value">{{ $sharedToken->used_count }}</p>
                    </div>
                    <div class="detail-item">
                        <p class="detail-label">Sisa kuota</p>
                        <p class="detail-value">{{ max(0, $sharedToken->quota - $sharedToken->used_count) }}</p>
                    </div>
                    <div class="detail-item">
                        <p class="detail-label">Diterbitkan</p>
                        <p class="detail-value">{{ $sharedToken->created_at?->format('d/m/Y H:i') ?? '—' }}</p>
                    </div>
                    <div class="detail-item">
                        <p class="detail-label">Kuota terakhir diubah oleh</p>
                        <p class="detail-value">{{ $sharedToken->quotaUpdater?->name ?? 'Belum pernah diubah' }}</p>
                    </div>
                    <div class="detail-item">
                        <p class="detail-label">Waktu perubahan kuota</p>
                        <p class="detail-value">{{ $sharedToken->quota_updated_at?->format('d/m/Y H:i') ?? '—' }}</p>
                    </div>
                </div>

                @if ($sharedToken->status === 'active' && $sharedToken->token_encrypted !== null)
                    <div class="field">
                        <label for="admin-shared-registration-token">Token untuk Operator</label>
                        <div class="token-box">
                            <input
                                id="admin-shared-registration-token"
                                class="input mono"
                                type="text"
                                readonly
                                value="{{ $sharedToken->token_encrypted }}"
                                autocomplete="off"
                                spellcheck="false"
                            >
                            <button
                                type="button"
                                class="btn btn-secondary"
                                data-copy-target="#admin-shared-registration-token"
                                data-copy-done="Token tersalin"
                            >
                                @include('layouts.partials.icon', ['name' => 'copy'])
                                <span data-copy-label aria-live="polite">Salin token</span>
                            </button>
                        </div>
                        <span class="field-hint">Bagikan token ini kepada Operator pemilik pengajuan.</span>
                    </div>
                @elseif ($sharedToken->token_encrypted === null)
                    <p class="card-note">Token ini berasal dari penerbitan lama dan hanya tersimpan sebagai hash, sehingga tidak dapat ditampilkan kembali.</p>
                @else
                    <p class="card-note">Token berstatus {{ $sharedStatusLabel }} dan tidak dapat digunakan untuk pendaftaran baru.</p>
                @endif

                <form method="POST" action="{{ route('admin.internship-requests.shared-registration-token.quota', $internshipRequest) }}" class="form-grid form-grid--inline">
                    @csrf
                    @method('PUT')
                    <div class="field">
                        <label for="quota">Ubah kuota peserta</label>
                        <input
                            class="input"
                            id="quota"
                            name="quota"
                            type="number"
                            min="{{ max(1, $sharedToken->used_count) }}"
                            max="2147483647"
                            step="1"
                            value="{{ old('quota', $sharedToken->quota) }}"
                            required
                            @error('quota') aria-invalid="true" @enderror
                        >
                        @error('quota')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                        <span class="field-hint">Minimal {{ $sharedToken->used_count }} sesuai jumlah profil yang telah selesai.</span>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-secondary">Simpan kuota</button>
                        <a class="btn btn-ghost" href="{{ route('admin.shared-registration-tokens.index') }}">Pantau semua token</a>
                    </div>
                </form>

                @if ($sharedToken->status === 'active')
                    <form method="POST" action="{{ route('admin.internship-requests.shared-registration-token.revoke', $internshipRequest) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm">Cabut shared token</button>
                    </form>
                @endif
            </section>
        @endif

        <section class="card">
            <div class="card-header">
                <h2 class="card-title">Proposal Magang</h2>
            </div>
            @if ($internshipRequest->proposal_path)
                @if (\App\Rules\GoogleDriveUrl::isValid($internshipRequest->proposal_path))
                    <a class="btn btn-secondary" href="{{ $internshipRequest->proposal_path }}" target="_blank" rel="noopener noreferrer">Lihat Proposal</a>
                    <p class="field-hint">Siapa pun yang memiliki link dapat mengakses dokumen sesuai pengaturan Google Drive.</p>
                @else
                    <a class="btn btn-secondary" href="{{ route('admin.internship-requests.proposal', $internshipRequest) }}">
                        @include('layouts.partials.icon', ['name' => 'download'])
                        <span>Unduh proposal PDF</span>
                    </a>
                @endif
            @else
                <p class="muted">Proposal belum tersedia untuk pengajuan ini.</p>
            @endif
        </section>

        @if ($internshipRequest->status === 'pending')
            <section class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Keputusan Admin</h2>
                        <p class="card-description">Keputusan berlaku untuk seluruh peserta pada pengajuan ini.</p>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="alert alert-error" role="alert">
                        @include('layouts.partials.icon', ['name' => 'alert-circle'])
                        <div>
                            <strong>Periksa kembali data berikut:</strong>
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.internship-requests.decision', $internshipRequest) }}">
                    @csrf
                    <div class="form-grid">
                        <div class="field">
                            <label for="decision">Keputusan <span class="req" aria-hidden="true">*</span></label>
                            <select id="decision" name="decision" required>
                                <option value="">Pilih keputusan</option>
                                <option value="approved" @selected(old('decision') === 'approved')>Setujui</option>
                                <option value="rejected" @selected(old('decision') === 'rejected')>Tolak</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="reply_letter_url">Link surat balasan Google Drive <span class="req" aria-hidden="true">*</span></label>
                            <input id="reply_letter_url" name="reply_letter_url" type="url" inputmode="url" value="{{ old('reply_letter_url') }}" placeholder="https://drive.google.com/file/d/..." required autocomplete="url" @error('reply_letter_url') aria-invalid="true" @enderror>
                            <span class="field-hint">Atur akses ke “Anyone with the link – Viewer”. Siapa pun yang memiliki link dapat membuka dokumen.</span>
                            @error('reply_letter_url') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Simpan keputusan</button>
                    </div>
                </form>
            </section>
        @elseif ($internshipRequest->reply_letter_path)
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">Surat Balasan</h2>
                </div>
                @if (\App\Rules\GoogleDriveUrl::isValid($internshipRequest->reply_letter_path))
                    <a class="btn btn-secondary" href="{{ $internshipRequest->reply_letter_path }}" target="_blank" rel="noopener noreferrer">Lihat Surat Balasan</a>
                    <p class="field-hint">Siapa pun yang memiliki link dapat mengakses dokumen sesuai pengaturan Google Drive.</p>
                @else
                    <a class="btn btn-secondary" href="{{ route('admin.internship-requests.reply-letter', $internshipRequest) }}">
                        @include('layouts.partials.icon', ['name' => 'download'])
                        <span>Unduh surat balasan PDF</span>
                    </a>
                @endif
            </section>
        @endif
    </div>
@endsection
