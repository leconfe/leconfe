<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Profiles
    |--------------------------------------------------------------------------
    |
    | You can add as many as you want of profiles to use it in your application.
    |
    */

    'profiles' => [

        // 'default' => [
        //     'plugins' => 'advlist autoresize codesample directionality emoticons fullscreen hr image imagetools link lists media table toc wordcount code',
        //     'toolbar' => 'undo redo removeformat | formatselect fontsizeselect | bold italic | rtl ltr | alignjustify alignright aligncenter alignleft | numlist bullist | forecolor backcolor | blockquote table hr | image link code',
        //     'upload_directory' => 'tinyeditor',
        // ],

        'default' => [
            'plugins' => 'autoresize link wordcount',
            'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist',
            'upload_directory' => 'tinyeditor',
        ],

        'basic' => [
            'plugins' => 'autoresize link wordcount image code lists',
            'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist | image | code',
            'upload_directory' => 'tinyeditor',
        ],

        'advanced' => [
            'plugins' => 'advlist autoresize codesample directionality emoticons fullscreen hr image link lists media table toc wordcount code',
            'toolbar' => 'undo redo removeformat | formatselect fontsizeselect | bold italic | rtl ltr | alignjustify alignright aligncenter alignleft | numlist bullist | blockquote table hr | image link media | wordcount code',
            'upload_directory' => 'tinyeditor',
        ],

        /*
        |--------------------------------------------------------------------------
        | Custom Configs
        |--------------------------------------------------------------------------
        |
        | If you want to add custom configurations to directly tinymce
        | You can use custom_configs key as an array
        |
        */

        /*
          'default' => [
            'plugins' => 'advlist autoresize codesample directionality emoticons fullscreen hr image imagetools link lists media table toc wordcount',
            'toolbar' => 'undo redo removeformat | formatselect fontsizeselect | bold italic | rtl ltr | alignjustify alignright aligncenter alignleft | numlist bullist | forecolor backcolor | blockquote table toc hr | image link media codesample emoticons | wordcount fullscreen',
            'custom_configs' => [
                'allow_html_in_named_anchor' => true,
                'link_default_target' => '_blank',
                'codesample_global_prismjs' => true,
                'image_advtab' => true,
                'image_class_list' => [
                  [
                    'title' => 'None',
                    'value' => '',
                  ],
                  [
                    'title' => 'Fluid',
                    'value' => 'img-fluid',
                  ],
              ],
            ]
        ],
        */

        'email' => [
            'plugins' => 'autoresize directionality link wordcount lists code ',
            'toolbar' => 'formatselect fontsizeselect | removeformat | bold italic | rtl ltr | alignjustify alignright aligncenter alignleft | link | blockquote bullist numlist | code',
            'upload_directory' => 'tinyeditor',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Templates
    |--------------------------------------------------------------------------
    |
    | You can add as many as you want of templates to use it in your application.
    |
    | https://www.tiny.cloud/docs/plugins/opensource/template/#templates
    |
    | ex: TinyEditor::make('content')->profiles('template')->template('example')
    */

    'templates' => [

        'example' => [
            // content
            ['title' => 'Some title 1', 'description' => 'Some desc 1', 'content' => 'My content'],
            // url
            ['title' => 'Some title 2', 'description' => 'Some desc 2', 'url' => 'http://localhost'],
        ],

    ],
];
