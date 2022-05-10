<?php

namespace App\service;

use Gedmo\Sluggable\Util\Urlizer;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Asset\Context\RequestStackContext;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploaderHelper
{
    /**
     * @var string
     */
    private $filesystem;
    const ARTICLE_IMAGE = 'article_image';
    /**
     * @var RequestStackContext
     */
    private $requestStackContext;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(FilesystemInterface $publicUploadFilesystem, RequestStackContext $requestStackContext, LoggerInterface $logger)
    {
        $this->filesystem = $publicUploadFilesystem;
        $this->requestStackContext = $requestStackContext;
        $this->logger = $logger;
    }

    public function UploadArticleImage(File $file, ?string $existingFilename): string
    {
        if ($file instanceof UploadedFile) {
            $originalFilename = $file->getClientOriginalName();
        } else {
            $originalFilename = $file->getFilename();
        }
        $newFilename = Urlizer::urlize(pathinfo($originalFilename, PATHINFO_FILENAME)) . '-' . uniqid() . '.' . $file->guessExtension();

        $stream = fopen($file->getPathname(), 'r');
        $result = $this->filesystem->writeStream(
            self::ARTICLE_IMAGE . '/' . $newFilename,
            $stream
        );

        if ($result === false) {
            throw new \Exception(sprintf('Could not write uploaded file "%s"', $newFilename));
        }

        if (is_resource($stream)) {
            fclose($stream);
        }

        if ($existingFilename) {
            try {
                $result = $this->filesystem->delete(self::ARTICLE_IMAGE . '/' . $existingFilename);
                if ($result === false) {
                    throw new \Exception(sprintf('Could not delete uploaded file "%s"', $existingFilename));
                }
            } catch (FileNotFoundException $e) {
                $this->logger->alert(sprintf('Old upload file "%s" was missing when trying to delete', $existingFilename));
            }

        }

        return $newFilename;
    }

    public function getPublicPath(string $path): string
    {
        return $this->requestStackContext->getBasePath() . '/uploads/' . $path;
    }
}