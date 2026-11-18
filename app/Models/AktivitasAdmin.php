<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AktivitasAdmin extends Model
{
    protected $fillable = ['user_id', 'admin_nama', 'aksi', 'objek', 'keterangan'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
