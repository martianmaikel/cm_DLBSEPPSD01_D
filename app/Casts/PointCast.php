<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PointCast implements CastsAttributes
{
    /**
     * Convert PostGIS EWKB hex to [lng, lat] array.
     *
     * EWKB Point format (little-endian):
     *   1 byte  — byte order (0x01 = LE)
     *   4 bytes — type with SRID flag (0x20000001)
     *   4 bytes — SRID (4326 = 0x000010E6)
     *   8 bytes — X / longitude (double)
     *   8 bytes — Y / latitude  (double)
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        $binary = @hex2bin($value);

        if ($binary === false || strlen($binary) < 25) {
            return null;
        }

        // Skip 9 bytes: 1 (byte order) + 4 (type) + 4 (SRID)
        $coords = unpack('dlng/dlat', substr($binary, 9));

        if ($coords === false) {
            return null;
        }

        return [$coords['lng'], $coords['lat']];
    }

    /**
     * Convert [lng, lat] array to PostGIS geography via ST_MakePoint.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value) && count($value) === 2) {
            return DB::raw(sprintf(
                "ST_SetSRID(ST_MakePoint(%F, %F), 4326)::geography",
                (float) $value[0],
                (float) $value[1]
            ));
        }

        // Pass through raw WKB or Expression values
        return $value;
    }
}
