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

        // Validate MIME type using finfo (more secure than extension check)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file->getFile()->getPathname());
        finfo_close($finfo);

        $allowedMimeTypes = $this->getAllowedMimeTypes();
        $filename = $file->getClientFilename();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            $allowed = implode(', ', $this->config['allowed_types']);
            return "Invalid file type (MIME: {$mimeType}). Allowed types: {$allowed}";
        }

        // Additional validation for images
        if (str_starts_with($mimeType, 'image/')) {
            $this->validateImage($file);
        }

        // TODO: Virus scanning (requires clamscan/ClamAV)
        // if (extension_loaded('clamav')) {
        //     $scanResult = $this->scanFile($file->getFile()->getPathname());
        //     if (!$scanResult['clean']) {
        //         return "File failed virus scan: " . $scanResult['virus'];
        //     }
        // }

        return null;
    }

    private function getAllowedMimeTypes(): array
    {
        $map = [];
        foreach ($this->config['allowed_types'] as $type) {
            match ($type) {
                'jpg', 'jpeg' => $map[] = 'image/jpeg',
                'png' => $map[] = 'image/png',
                'gif' => $map[] = 'image/gif',
                'webp' => $map[] = 'image/webp',
                'pdf' => $map[] = 'application/pdf',
            };
        }
        return array_unique($map);
    }

    private function validateImage(UploadedFileInterface $file): void
    {
        // Check image dimensions to prevent decompression bombs
        $pathname = $file->getFile()->getPathname();
        $size = getimagesize($pathname);
        if ($size === false) {
            throw new \RuntimeException('Invalid image file');
        }

        [$width, $height] = $size;

        // Reject extremely large dimensions (e.g., > 10000px)
        if ($width > 10000 || $height > 10000) {
            throw new \RuntimeException('Image dimensions too large');
        }

        // Check file size vs dimensions (image bombs have small file but huge dimensions)
        if ($file->getSize() < 1000 && ($width > 5000 || $height > 5000)) {
            throw new \RuntimeException('Suspicious image file');
        }
    }

    // Virus scanning method (requires clamav extension or clamscan binary)
    private function scanFile(string $filepath): array
    {
        $output = [];
        $returnVar = 0;
        exec("clamscan " . escapeshellarg($filepath), $output, $returnVar);

        $clean = ($returnVar === 0);
        $virus = $clean ? null : implode(', ', array_filter($output, fn($line) => str_contains($line, 'FOUND')));

        return ['clean' => $clean, 'virus' => $virus];
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
