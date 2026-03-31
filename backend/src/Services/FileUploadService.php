<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Log\LoggerInterface;
use Psr\Http\Message\UploadedFileInterface;
use Ramsey\Uuid\Uuid;

class FileUploadService
{
    private LoggerInterface $logger;
    private array $config;

    public function __construct(LoggerInterface $logger, array $config)
    {
        $this->logger = $logger;
        $this->config = $config;
    }

    public function upload(UploadedFileInterface $file, string $folder = 'general'): array
    {
        // Validate file
        $error = $this->validateFile($file);
        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        // Get file extension
        $filename = $file->getClientFilename();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Generate unique filename
        $newFilename = Uuid::uuid4()->toString() . '.' . $extension;

        // Create folder if not exists
        $uploadPath = $this->config['path'] . '/' . $folder;
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        // Move uploaded file
        $fullPath = $uploadPath . '/' . $newFilename;

        try {
            $file->moveTo($fullPath);

            $relativePath = 'uploads/' . $folder . '/' . $newFilename;

            $this->logger->info("File uploaded", [
                'original' => $filename,
                'path' => $relativePath
            ]);

            return [
                'success' => true,
                'path' => $relativePath,
                'filename' => $newFilename,
                'original_name' => $filename,
                'size' => $file->getSize(),
                'mime_type' => $file->getClientMediaType()
            ];
        } catch (\Exception $e) {
            $this->logger->error("File upload failed", [
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'error' => 'Failed to save file'];
        }
    }

    public function uploadBase64(string $base64Data, string $folder = 'general', string $extension = 'jpg'): array
    {
        // Remove data URI prefix if present
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
            $extension = $matches[1];
            $base64Data = preg_replace('/^data:image\/\w+;base64,/', '', $base64Data);
        }

        // Decode base64
        $data = base64_decode($base64Data);
        if ($data === false) {
            return ['success' => false, 'error' => 'Invalid base64 data'];
        }

        // Check file size
        if (strlen($data) > $this->config['max_size']) {
            return ['success' => false, 'error' => 'File too large'];
        }

        // Generate unique filename
        $newFilename = Uuid::uuid4()->toString() . '.' . $extension;

        // Create folder if not exists
        $uploadPath = $this->config['path'] . '/' . $folder;
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        // Save file
        $fullPath = $uploadPath . '/' . $newFilename;

        try {
            file_put_contents($fullPath, $data);

            $relativePath = 'uploads/' . $folder . '/' . $newFilename;

            $this->logger->info("Base64 file uploaded", [
                'path' => $relativePath
            ]);

            return [
                'success' => true,
                'path' => $relativePath,
                'filename' => $newFilename,
                'size' => strlen($data)
            ];
        } catch (\Exception $e) {
            $this->logger->error("Base64 upload failed", [
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'error' => 'Failed to save file'];
        }
    }

    public function delete(string $path): bool
    {
        $fullPath = $this->config['path'] . '/../' . $path;

        if (file_exists($fullPath)) {
            $result = unlink($fullPath);
            if ($result) {
                $this->logger->info("File deleted", ['path' => $path]);
            }
            return $result;
        }

        return false;
    }

    private function validateFile(UploadedFileInterface $file): ?string
    {
        // Check upload error
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return $this->getUploadErrorMessage($file->getError());
        }

        // Check file size
        if ($file->getSize() > $this->config['max_size']) {
            $maxMb = $this->config['max_size'] / 1024 / 1024;
            return "File too large. Maximum size is {$maxMb}MB";
        }

        // Check file type
        $filename = $file->getClientFilename();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($extension, $this->config['allowed_types'])) {
            $allowed = implode(', ', $this->config['allowed_types']);
            return "Invalid file type. Allowed types: {$allowed}";
        }

        return null;
    }

    private function getUploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File too large',
            UPLOAD_ERR_PARTIAL => 'File upload incomplete',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
            default => 'Unknown upload error'
        };
    }
}
