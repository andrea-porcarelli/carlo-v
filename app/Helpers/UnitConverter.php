<?php

namespace App\Helpers;

class UnitConverter
{
    const FACTORS = [
        'g'  => ['base' => 'kg', 'factor' => 0.001],
        'kg' => ['base' => 'kg', 'factor' => 1.0],
        'ml' => ['base' => 'cl', 'factor' => 0.1],
        'cl' => ['base' => 'cl', 'factor' => 1.0],
        'l'  => ['base' => 'cl', 'factor' => 100.0],
        'pz' => ['base' => 'pz', 'factor' => 1.0],
    ];

    /**
     * Converte un valore dall'unità indicata all'unità base (kg / cl / pz).
     */
    public static function toBase(float $value, string $unit): float
    {
        $factor = self::FACTORS[$unit]['factor'] ?? 1.0;
        return $value * $factor;
    }

    /**
     * Ritorna le unità compatibili con una data unità base.
     * Es. compatibleUnits('kg') → ['g', 'kg']
     */
    public static function compatibleUnits(string $baseUnit): array
    {
        return array_keys(array_filter(
            self::FACTORS,
            fn($info) => $info['base'] === $baseUnit
        ));
    }

    /**
     * Ritorna l'unità base associata a un'unità di input.
     */
    public static function baseOf(string $unit): string
    {
        return self::FACTORS[$unit]['base'] ?? $unit;
    }
}