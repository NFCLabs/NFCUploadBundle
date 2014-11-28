<?php

namespace NFC\UploadBundle\Form;

use Symfony\Component\Form\AbstractType,
    Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class FileUploadType extends AbstractType
{
    private $secureToken;
    public function __construct($csrfProvider, \Symfony\Component\HttpFoundation\Session\SessionInterface $session)
    {
        $this->secureToken = $csrfProvider->generateCsrfToken('');
        $session->set('secure_token', $this->secureToken);
    }


    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['file_type'] = $options['file_type'];
        $view->vars['template'] = $options['template'];
        $view->vars['extensions'] = $options['extensions'];
        $view->vars['multi_selection'] = $options['multi_selection'];
        $view->vars['btn_name'] = $options['btn_name'];
        $view->vars['secure_token'] = $this->secureToken;
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'compound' => false,
            'multi_selection' => false,
            'class' => 'upload',
            'btn_name' => 'Upload',
            'file_type' => 'default',
            'template' => 'FileUploadBundle:Upload:default.html.twig',
            'extensions' => ''
        ));
    }

    public function getParent()
    {
        return 'form';
    }

    public function getName()
    {
        return 'file_upload_type';
    }
}