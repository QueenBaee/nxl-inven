<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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

    /**
     * Check if user has given role (supports 'owner', 'staff', etc.).
     */
    public function hasRole(string|array $roles): bool
    {
        $roles = (array) $roles;
        $userRole = $this->role ?? 'owner';

        return in_array($userRole, $roles, true);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Izinkan semua akun yang ada di database untuk login ke panel admin
        return true;

        // (Opsional) Jika ingin lebih aman, batasi hanya untuk email tertentu:
        // return $this->email === 'admin@fitnet.my.id';
    }
}
