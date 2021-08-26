<?php

namespace CreateMissingThumbnails\Form\Admin;

use Laminas\Form\Form;

class CreateMissingThumbnailsForm extends Form
{
    public function init()
    {
        $this->add([
            'type' => 'submit',
            'name' => 'submit',
            'attributes' => [
                'value' => 'Start thumbnails creation', // @translate
            ],
        ]);
    }
}
