<?php

namespace CreateMissingThumbnails;

return [
    'controllers' => [
        'invokables' => [
            'CreateMissingThumbnails\Controller\Admin\Index' => Controller\Admin\IndexController::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            'CreateMissingThumbnails\Form\Admin\CreateMissingThumbnailsForm' => Form\Admin\CreateMissingThumbnailsForm::class,
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'Create missing thumbnails', // @translate
                'route' => 'admin/create-missing-thumbnails',
                'resource' => 'CreateMissingThumbnails\Controller\Admin\Index',
                'privilege' => 'index',
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'create-missing-thumbnails' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/create-missing-thumbnails',
                            'defaults' => [
                                '__NAMESPACE__' => 'CreateMissingThumbnails\Controller\Admin',
                                'controller' => 'Index',
                                'action' => 'index',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
];
