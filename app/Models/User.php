<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $use_factory
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 *
 * @method static UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 *
 * @property string|null $code
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCode($value)
 *
 * @property-read Collection<int, Order> $orders
 * @property-read int|null $orders_count
 *
 * @mixin \Eloquent
 */
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;

    /** Ruolo con accesso completo (utenti, configurazioni, azzeramento numerazione). */
    public const ROLE_ADMIN = 'admin';

    /** Ruolo operativo di cassa. */
    public const ROLE_CASSA = 'cassa';

    /** Ruolo di sala: vede solo gli ordini (elenco e dettaglio) e il venduto per coda. */
    public const ROLE_CAMERIERI = 'camerieri';

    /**
     * {@inheritDoc}
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'code',
    ];

    /**
     * {@inheritDoc}
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * {@inheritDoc}
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    public function isCassa(): bool
    {
        return $this->hasRole(self::ROLE_CASSA);
    }

    /**
     * Un cameriere non e' un operatore del pannello: apre solo l'elenco ordini
     * e il venduto per coda (vedi RestrictCameriereAccess). Un admin che avesse
     * anche questo ruolo resta admin, altrimenti si chiuderebbe fuori da solo.
     */
    public function isCameriere(): bool
    {
        return $this->hasRole(self::ROLE_CAMERIERI) && ! $this->isAdmin();
    }

    /**
     * @return HasMany<Order,$this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
