<?php

return [

    'column_toggle' => [
        'heading' => 'Kolona',
    ],

    'columns' => [
        'text' => [
            'actions' => [
                'collapse_list' => 'Fshehi :count të tjerë',
                'expand_list' => 'Shfaq :count të tjerë',
            ],
            'more_list_items' => 'dhe :count të tjerë',
        ],
    ],

    'fields' => [
        'bulk_select_page' => [
            'label' => 'Zgjidh/shlyej përzgjedhjen e të gjitha elementeve për veprimin masiv.',
        ],
        'bulk_select_record' => [
            'label' => 'Zgjidh/shlyej zgjedhjen e elementit :key për veprimin masiv.',
        ],
        'bulk_select_group' => [
            'label' => 'Zgjidh/shlyej përzgjedhjen e grupit :title për veprimin masiv.',
        ],
        'search' => [
            'label' => 'Kërkoni',
            'placeholder' => 'Kërkoni',
            'indicator' => 'Kërkim',
        ],
    ],

    'summary' => [
        'heading' => 'Përmbledhje',
        'subheadings' => [
            'all' => 'Të gjitha :label',
            'group' => 'Përmbledhje :group',
            'page' => 'Kjo faqe',
        ],
        'summarizers' => [
            'average' => [
                'label' => 'Mesatarja',
            ],
            'count' => [
                'label' => 'Numri',
            ],
            'sum' => [
                'label' => 'Totali',
            ],
        ],
    ],

    'actions' => [
        'disable_reordering' => [
            'label' => 'Përfundo rindërtimin e renditjes së të dhënave',
        ],
        'enable_reordering' => [
            'label' => 'Rindërto të dhënat',
        ],
        'filter' => [
            'label' => 'Filtro',
        ],
        'group' => [
            'label' => 'Grupi',
        ],
        'open_bulk_actions' => [
            'label' => 'Veprimet',
        ],
        'toggle_columns' => [
            'label' => 'Zgjidh kolonat',
        ],
    ],

    'empty' => [
        'heading' => 'Nuk u gjetën të dhëna',
        'description' => 'Krijoni :model për të filluar.',
    ],

    'filters' => [

        'actions' => [

            'apply' => [
                'label' => 'Apliko filterin',
            ],

            'remove' => [
                'label' => 'Hiq filterin',
            ],

            'remove_all' => [
                'label' => 'Hiq të gjitha filtrot',
                'tooltip' => 'Hiq të gjitha filtrot',
            ],

            'reset' => [
                'label' => 'Rivendos filterin',
            ],

        ],

        'heading' => 'Filtrat',

        'indicator' => 'Filtër aktiv',

        'multi_select' => [
            'placeholder' => 'Të gjitha',
        ],

        'select' => [
            'placeholder' => 'Të gjitha',
        ],

        'trashed' => [

            'label' => 'Të dhëna të fshira',

            'only_trashed' => 'Vetëm të dhënat e fshira',

            'with_trashed' => 'Me të dhënat e fshira',

            'without_trashed' => 'Pa të dhënat e fshira',

        ],

    ],

    'grouping' => [

        'fields' => [

            'group' => [
                'label' => 'Grupi sipas',
                'placeholder' => 'Pa grupim',
            ],

            'direction' => [

                'label' => 'Renditja e grupit',

                'options' => [
                    'asc' => 'Rritëse',
                    'desc' => 'Zbritëse',
                ],

            ],

        ],

    ],

    'reorder_indicator' => 'Zvarrit dhe lësho të dhënat në rend',

    'selection_indicator' => [

        'selected_count' => ':count të dhëna të zgjedhura',

        'actions' => [

            'select_all' => [
                'label' => 'Zgjidh të gjitha (:count)',
            ],

            'deselect_all' => [
                'label' => 'Hiq zgjedhjen e të gjitha',
            ],

        ],

    ],

    'sorting' => [

        'fields' => [

            'column' => [
                'label' => 'Rendit sipas',
            ],

            'direction' => [

                'label' => 'Drejtimi i rendit',

                'options' => [
                    'asc' => 'Rritëse',
                    'desc' => 'Zbritëse',
                ],

            ],

        ],

    ],
];
