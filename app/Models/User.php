<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'permissions',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'remember_token',
    ];

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
            'permissions' => 'array',
        ];
    }

    /**
     * Available operator permissions.
     */
    public static function availablePermissions(): array
    {
        return [
            'take_orders'  => 'Prendere comande',
            'view_orders'  => 'Visualizzare comande',
            'cash_payment' => 'Pagamento contanti',
            'pos_payment'  => 'Pagamento POS',
            'close_bills'  => 'Chiudere i conti',
        ];
    }

    /**
     * Check if the user has a given permission.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->role === 'admin') {
            return true;
        }
        return in_array($permission, $this->permissions ?? []);
    }

    public function roles(): array
    {
        return [
            'admin' => 'Amministratore',
            'operator' => 'Operatore',
        ];
    }

    public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()->logFillable();
}
}
