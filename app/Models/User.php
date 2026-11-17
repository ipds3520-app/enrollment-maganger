<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Catatan keamanan: `role` sengaja TIDAK ada di fillable agar tidak bisa
 * diubah lewat mass assignment dari input publik (registrasi operator).
 * Role admin hanya dibuat melalui Artisan command.
 */
#[Fillable(['name', 'email', 'password', 'institution_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Instansi tempat operator ini bernaung (NULL untuk admin).
     *
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * Apakah akun ini admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Apakah akun ini operator instansi.
     */
    public function isOperator(): bool
    {
        return $this->role === 'operator';
    }

    /**
     * Normalisasi email: huruf kecil dan tanpa spasi di awal/akhir.
     *
     * Mencegah akun ganda yang hanya berbeda kapitalisasi, serta membuat
     * pencarian akun (login/reset password) konsisten.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => mb_strtolower(trim($value)),
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
