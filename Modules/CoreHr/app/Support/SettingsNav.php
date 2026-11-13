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
                'label' => 'Banks',
                'route' => route('settings.banks.index'),
                'active' => $active === 'banks',
            ],
            [
                'label' => 'Bank branches',
                'route' => route('settings.bank-branches.index'),
                'active' => $active === 'bank-branches',
            ],
            [
                'label' => 'Document types',
                'route' => route('settings.document-types.index'),
                'active' => $active === 'document-types',
            ],
        ];
    }
}
