<?php

namespace Modules\CoreHr\Support;

/** Official Sri Lanka provinces and districts for hire / personal forms. */
final class SriLankaGeo
{
    /** @return list<string> */
    public static function provinces(): array
    {
        return [
            'Western',
            'Central',
            'Southern',
            'Northern',
            'Eastern',
            'North Western',
            'North Central',
            'Uva',
            'Sabaragamuwa',
        ];
    }

    /** @return list<string> */
    public static function districts(): array
    {
        return [
            'Ampara',
            'Anuradhapura',
            'Badulla',
            'Batticaloa',
            'Colombo',
            'Galle',
            'Gampaha',
            'Hambantota',
            'Jaffna',
            'Kalutara',
            'Kandy',
            'Kegalle',
            'Kilinochchi',
            'Kurunegala',
            'Mannar',
            'Matale',
            'Matara',
            'Monaragala',
            'Mullaitivu',
            'Nuwara Eliya',
            'Polonnaruwa',
            'Puttalam',
            'Ratnapura',
            'Trincomalee',
            'Vavuniya',
        ];
    }

    /** @return array<string, list<string>> */
    public static function districtsByProvince(): array
    {
        return [
            'Western' => ['Colombo', 'Gampaha', 'Kalutara'],
            'Central' => ['Kandy', 'Matale', 'Nuwara Eliya'],
            'Southern' => ['Galle', 'Matara', 'Hambantota'],
            'Northern' => ['Jaffna', 'Kilinochchi', 'Mannar', 'Mullaitivu', 'Vavuniya'],
            'Eastern' => ['Ampara', 'Batticaloa', 'Trincomalee'],
            'North Western' => ['Kurunegala', 'Puttalam'],
            'North Central' => ['Anuradhapura', 'Polonnaruwa'],
            'Uva' => ['Badulla', 'Monaragala'],
            'Sabaragamuwa' => ['Kegalle', 'Ratnapura'],
        ];
    }

    /** @return list<string> */
    public static function districtsForProvince(string $province): array
    {
        return self::districtsByProvince()[$province] ?? self::districts();
    }
}
