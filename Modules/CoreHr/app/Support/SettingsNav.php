<?php

namespace Modules\CoreHr\Support;

final class SettingsNav
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function items(string $active): array
    {
        return [
            [
                'icon' => '🏦',
                'label' => 'Banks',
                'route' => route('settings.banks.index'),
                'active' => $active === 'banks',
            ],
            [
                'icon' => '🌿',
                'label' => 'Bank branches',
                'route' => route('settings.bank-branches.index'),
                'active' => $active === 'bank-branches',
            ],
            [
                'icon' => '📄',
                'label' => 'Document types',
                'route' => route('settings.document-types.index'),
                'active' => $active === 'document-types',
            ],
        ];
    }
}
