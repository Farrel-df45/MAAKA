<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TahunAjaran extends Model
{
    protected $table = 'tahun_ajarans';

    protected $fillable = ['nama', 'aktif'];

    protected $casts = ['aktif' => 'boolean'];

    // Tahun ajaran yang sedang berjalan (null jika belum ada)
    public static function aktif(): ?self
    {
        return static::where('aktif', true)->latest('id')->first();
    }

    public function riwayatKelas(): HasMany
    {
        return $this->hasMany(RiwayatKelas::class);
    }

    public function waliKelas(): HasMany
    {
        return $this->hasMany(WaliKelas::class);
    }
}
