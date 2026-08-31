<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * BaseModel — dasar semua model MongoDB di SWAVE.
 *
 * Menambahkan alias `_id` pada hasil serialisasi (toArray/toJson/response),
 * sehingga API konsisten dengan SPA yang membaca field `_id` (mis. order._id,
 * charm._id, category._id). Driver MongoDB Laravel mengekspos kunci primary
 * sebagai `id`; tanpa perubahan ini field `_id` absen dari respons JSON.
 */
abstract class BaseModel extends Model
{
    protected function getArrayableAttributes(): array
    {
        $attributes = parent::getArrayableAttributes();

        if (!array_key_exists('_id', $attributes)) {
            if (array_key_exists('id', $attributes)) {
                $attributes['_id'] = $attributes['id'];
            } elseif ($this->getKey()) {
                $attributes['_id'] = $this->getKey();
            }
        }

        return $attributes;
    }
}
