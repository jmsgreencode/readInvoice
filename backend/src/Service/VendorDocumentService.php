<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\VendorDocumentRepository;
use App\Exception\AppException;
use App\Logging\StructuredLogger;

class VendorDocumentService
{
    private VendorDocumentRepository $docRepo;
    private StructuredLogger $logger;
    private string $storagePath;

    public function __construct(VendorDocumentRepository $docRepo, StructuredLogger $logger, string $storagePath)
    {
        $this->docRepo = $docRepo;
        $this->logger = $logger;
        $this->storagePath = $storagePath;
    }

    public function getDocuments(int $vendorId): array
    {
        return $this->docRepo->findByVendor($vendorId);
    }

    public function uploadDocument(int $vendorId, array $uploadedFile, string $documentType, int $uploadedBy, ?string $expiryDate = null): int
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }

        $originalName = $uploadedFile->getClientFilename();
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $storedName = sprintf('%d_%s.%s', $vendorId, bin2hex(random_bytes(8)), $extension);
        $destPath = $this->storagePath . '/' . $storedName;

        $uploadedFile->moveTo($destPath);

        $id = $this->docRepo->create([
            'vendor_id' => $vendorId,
            'document_type' => $documentType,
            'file_name' => $originalName,
            'file_path' => $destPath,
            'file_size' => $uploadedFile->getSize(),
            'mime_type' => $uploadedFile->getClientMediaType(),
            'uploaded_by' => $uploadedBy,
            'expiry_date' => $expiryDate,
        ]);

        $this->logger->info('Vendor document uploaded', ['vendor_id' => $vendorId, 'doc_id' => $id, 'type' => $documentType]);
        return $id;
    }

    public function deleteDocument(int $documentId): void
    {
        $doc = $this->docRepo->findById($documentId);
        if ($doc && file_exists($doc['file_path'])) {
            unlink($doc['file_path']);
        }
        $this->docRepo->delete($documentId);
    }
}
