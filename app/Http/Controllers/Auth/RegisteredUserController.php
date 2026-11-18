<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Tampilkan halaman pendaftaran operator.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Daftarkan operator beserta institusi barunya.
     *
     * Setiap pendaftaran SELALU membuat record institusi baru — tidak ada
     * pencarian atau penggabungan berdasarkan nama, sehingga nama institusi
     * boleh sama dan data milik operator lain tidak pernah ditimpa.
     *
     * `role` dan `institution_id` ditetapkan server; tidak pernah berasal
     * dari input pengguna.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data): User {
            $institution = Institution::create([
                'name' => $data['institution_name'],
                'type' => $data['institution_type'],
                'address' => $data['institution_address'] ?? null,
                'phone' => $data['institution_phone'] ?? null,
                'email' => $data['institution_email'] ?? null,
            ]);

            return User::forceCreate([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'], // cast 'hashed' → otomatis di-hash
                'role' => 'operator',
                'institution_id' => $institution->id,
            ]);
        });

        // Auto-login agar operator dapat langsung membuka dashboard.
        Auth::guard('operator')->login($user);

        return redirect()->route('operator.dashboard');
    }
}
