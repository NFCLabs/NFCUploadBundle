<?php

namespace NFC\UploadBundle\Services;

use NFC\UploadBundle\Services\PHPImageWorkshop\ImageWorkshop;

class FileHandler
{
    protected $session;
    protected $config;
    protected $sessionAttr;

    public function __construct(\Symfony\Component\HttpFoundation\Session\SessionInterface $session, $config)
    {
        $this->session = $session;
        $this->config = $config;
    }

    public function handleFilesAndSave($field, $dir, $json = false)
    {
        $this->sessionAttr = 'file_upload_'.$field;
        if ($this->session->has($this->sessionAttr))
        {
            $filesInfo = new \SplObjectStorage();
            $filesInfo->unserialize($this->session->get($this->sessionAttr));

            if ($this->config[$filesInfo->type]['type'] == 'file') {
                $result = $this->saveFiles($filesInfo, $dir);
            } elseif ($this->config[$filesInfo->type]['type'] == 'image') {
                $result = $this->saveImages($filesInfo, $dir, $this->config[$filesInfo->type]['thumbnails']);
            } else {
                throw new \Exception('Unrecognized file type!');
            }

            $this->clearSessionAttr();

            if ($json)
                $result = json_encode($result);

            return $result;
        } else {
            return false;
        }
    }

    private function saveFiles($filesInfo, $dir)
    {
        $this->checkDir($dir);

        $result = array();

        // TODO make uniqe files name from config
        foreach ($filesInfo as $file)
        {
            $fileName = uniqid();
            rename($file->path, $dir.'/'.$fileName.'.'.$file->extension);
            $result[] = $fileName.'.'.$file->extension;
        }

        return $result;
    }

    private function saveImages($filesInfo, $dir, $thumbs)
    {

        $this->checkDir($dir);
        $result = array();
        $i = 1;
        foreach ($filesInfo as $file)
        {
            foreach ($thumbs as $key => $thumb)
            {
                // http://phpimageworkshop.com/documentation.html
                $layer = ImageWorkshop::initFromPath($file->path);

                if (isset($thumb['action']) == true) {
                    switch ($thumb['action']) {
                        case "exact_resize":
                            $layer->resizeInPixel($thumb['width'], $thumb['height'], true, 0, 0, 'MM');
                            break;
                        case "landscape_resize":
                            $layer->resizeInPixel($thumb['width'], null, true);
                            break;
                        case "portrait_resize":
                            $layer->resizeInPixel(null, $thumb['height'], true);
                            break;
                        case "exact_crop":
                            if ($thumb['width']/$thumb['height'] < 1) {
                                $resize = $thumb['width'];
                            } else {
                                $resize = $thumb['height'];
                            }
                            $layer->resizeByNarrowSideInPixel($resize, true);
                            $layer->cropInPixel($thumb['width'], $thumb['height'], 0, 0, "MM");
                            break;
                        default:
                            $layer->resizeInPixel($thumb['width'], $thumb['height'], false); //exact, without props
                            break;
                    }
                }

                if (isset($thumb['watermark']) == true) {
                    $watermarkLayer = ImageWorkshop::initFromPath(__DIR__.'/../Resources/public/images/'.$thumb['watermark']);
                    $watermarkLayer->opacity($thumb['opacity']);
                    $layer->addLayerOnTop($watermarkLayer, $thumb['padding'], $thumb['padding'], $thumb['position']);
                }

                $this->checkDir($dir.'/'.$i);
                $name = $key.uniqid().'.'.$thumb['format'];
                $layer->save($dir.'/'.$i, $name, false, null, $thumb['quality']);

                $result[] = $i.'/'.$name;
            }
            $i++;
            unlink($file->path);
        }
        return $result;

    }

    private function checkDir($dir)
    {
        if (!is_dir($dir))
            mkdir($dir, 0777, true);
        chmod($dir, 0777);

    }

    private function clearSessionAttr()
    {
        $this->session->remove($this->sessionAttr);
    }

}