<?php

namespace NFC\UploadBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Image;

class UploadController extends Controller
{
    private $uploadFile = null;

    public function uploadAction()
    {
        $request = $this->get('request');
        $this->uploadFile = $request->files->get('file');
        if (is_null($this->uploadFile)) {
            die('File not found!');
        }

        $data = array(
            'success' => false,
            'error' => 'Upload error'
        );

        if ($this->uploadFile->isValid() && ($request->get('secure_token') === $this->get('session')->get('secure_token'))) {

            $filesConfig = $this->container->getParameter('nfc_upload.types');
            $fileSettings = $filesConfig[$request->get('type', 'default')];
            $sessionAttr = $request->get('field');
            $siteWebDir = $this->container->getParameter('nfc_upload.web_dir');
            $validator = $this->getFileValidator($fileSettings);

            if (!$validator) {
                $data = array(
                    'success' => false,
                    'error' => 'To upload files on a website, you need to have JavaScript enabled in your browser'
                );

                return new JsonResponse($data);
            }

            $errorList = $this->get('validator')->validateValue($this->uploadFile, $validator);

            if (count($errorList) == 0) {

                $uploadDir = $this->get('kernel')->getRootDir() . '/../'.$siteWebDir.$fileSettings['upload_dir'];

                if (!is_dir($uploadDir))
                    mkdir($uploadDir, 0777, true);

                $fileName = 'file'.uniqid().'.'.$this->uploadFile->getClientOriginalExtension();


                $this->uploadFile->move($uploadDir, $fileName);

                $fileObj = new \stdClass();
                $fileObj->path =  $uploadDir.'/'.$fileName;
                $fileObj->extension = $this->uploadFile->getClientOriginalExtension();

                $filesInfo = new \SplObjectStorage();
                if ($this->get('session')->has('file_upload_' . $sessionAttr)) {
                    $filesInfo->unserialize($this->get('session')->get('file_upload_' . $sessionAttr));
                }
                $filesInfo->type = $request->get('type', 'default');
                $filesInfo->attach($fileObj);

                $this->get('session')->set('file_upload_' . $sessionAttr, $filesInfo->serialize());


                $data = array(
                    'success' => true,
                    'file' => $fileSettings['upload_dir'].'/'.$fileName,
                    'name' => $this->uploadFile->getClientOriginalName()
                );
            } else {
                $data = array(
                    'success' => false,
                    'error' => $errorList[0]->getMessage()
                );
            }

        }

        return new JsonResponse($data);
    }

    protected function getFileValidator($settings)
    {
        $fileConstraint = null;

        $formats = explode(",", $settings['format']);
        $match = false;

        foreach ($formats as $format) {
            if (strtolower($format) == $this->uploadFile->getClientOriginalExtension())
                $match = true;
        }

        if (!$match)
            return false;

        if ($settings['type'] == 'file') {
            $fileConstraint = new File();
            $fileConstraint->maxSize = $settings['max_size'];
            $fileConstraint->mimeTypes = $settings['mime_type'];
        } elseif ($settings['type'] == 'image') {
            $fileConstraint = new Image();
            $fileConstraint->maxSize = $settings['max_size'];
        }

        if (is_null($fileConstraint))
            throw new \Exception('Not found file type in configuration!');
        return $fileConstraint;
    }
}