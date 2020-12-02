<?php

namespace CreateMissingThumbnails\Form\Admin;

use Zend\Form\Form;

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
