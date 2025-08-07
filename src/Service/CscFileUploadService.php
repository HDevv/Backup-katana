<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CscFileUploadService
{
    private string $uploadDirectory;
    private array $allowedExtensions;
    private int $maxFileSize;

    public function __construct(ParameterBagInterface $params)
    {
        $this->uploadDirectory = $params->get('csc_upload_directory');
        $this->allowedExtensions = $params->get('csc_allowed_extensions');
        $this->maxFileSize = $params->get('csc_max_file_size');
    }

    /**
     * Upload multiple files and return their information
     * @param UploadedFile[] $files
     * @return array
     */
    public function uploadFiles(array $files): array
    {
        $uploadedFiles = [];
        
        // Créer le répertoire s'il n'existe pas
        if (!is_dir($this->uploadDirectory)) {
            mkdir($this->uploadDirectory, 0755, true);
        }

        foreach ($files as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $uploadedFiles[] = $this->uploadSingleFile($file);
            }
        }

        return $uploadedFiles;
    }

    private function uploadSingleFile(UploadedFile $file): array
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->guessExtension();
        
        // Générer un nom de fichier unique
        $safeFilename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalFilename);
        $newFilename = $safeFilename . '_' . uniqid() . '.' . $extension;

        // Déplacer le fichier
        $file->move($this->uploadDirectory, $newFilename);

        return [
            'original_name' => $file->getClientOriginalName(),
            'filename' => $newFilename,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'path' => $this->uploadDirectory . '/' . $newFilename,
            'web_path' => '/uploads/csc/' . $newFilename,
            'uploaded_at' => new \DateTime(),
        ];
    }

    public function isValidFile(UploadedFile $file): bool
    {
        if (!$file->isValid()) {
            return false;
        }

        $extension = strtolower($file->guessExtension());
        if (!in_array($extension, $this->allowedExtensions)) {
            return false;
        }

        if ($file->getSize() > $this->maxFileSize) {
            return false;
        }

        return true;
    }

    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }

    public function getAllowedExtensions(): array
    {
        return $this->allowedExtensions;
    }
}
