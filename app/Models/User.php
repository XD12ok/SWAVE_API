<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;

class User extends BaseModel
{
    use Notifiable;

    protected $collection = 'users';
    protected $guarded = [];

    protected $casts = [
        'emailVerified' => 'boolean',
        'emailVerifyExpiresAt' => 'datetime',
        'passwordResetExpiresAt' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function getAuthPassword()
    {
        return $this->passwordHash;
    }
}
