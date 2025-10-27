<?php

namespace FluentCart\App\Services\FileSystem\Drivers\Local;

use FluentCart\App\App;
use FluentCart\App\Helpers\Helper;
use FluentCart\App\Modules\StorageDrivers\BaseStorageDriver;
use FluentCart\App\Modules\StorageDrivers\Local\Local as LocalStorageDriver;
use FluentCart\App\Services\FileSystem\Drivers\BaseDriver;
use FluentCart\Framework\Support\Str;

class LocalDriver extends BaseDriver
{
    public function getDirName(): string
    {
        return $this->dirName;
    }


    public function __construct(?string $dirPath = null, ?string $dirName = null)
    {
        parent::__construct($dirPath, $dirName);
        $this->dirName = $dirName ?? 'fluent-cart';
        $this->dirPath = $dirPath ?? $this->getDefaultDirPath();
        $this->storageDriver = new LocalStorageDriver();
        $this->ensureDirectoryExist();

    }

    protected function getDefaultDirPath(): string
    {
        return wp_get_upload_dir()['basedir'] . DIRECTORY_SEPARATOR . $this->getDirName();
    }

    private function ensureDirectoryExist()
    {
        $uploadDirectory = $this->getDefaultDirPath();
        if (!is_dir($uploadDirectory)) {
            mkdir($uploadDirectory);
        }
    }

    public function listFiles(array $params = []): array
    {
        $filesArray = [];
        $searchTerm = $params['search'] ?? '';

        $files = scandir($this->getDefaultDirPath());

        $maxFile = App::request()->get('per_page', 10);

        $fileCount = 1;

        foreach ($files as $file) {
            // Skip hidden files (starting with dot)
            if (str_starts_with($file, '.')) {
                continue;
            }

            if (str_ends_with($file, '.php')) {
                continue;
            }

            // Apply search filter if provided
            if (!empty($searchTerm) && stripos($file, $searchTerm) === false) {
                continue;
            }

            $filePath = $this->getDefaultDirPath() . '/' . $file;
            if (is_file($filePath)) {
                $filesArray[] = [
                    'name' => $file,
                    'size' => filesize($filePath),
                    'driver' => 'local',
                    'bucket' => ''
                ];
            }

            $fileCount++;
            if($fileCount > $maxFile) {
                break;
            }
        }

        return $filesArray;
    }

    public function uploadFile($localFilePath, $uploadToFilePath, $file, $params = [])
    {
        //"__fluent-cart__1755232250".length = 25
        $fileSize = $file->toArray()['size_in_bytes'];
        $uploadToFilePath = $uploadToFilePath . '__fluent-cart__.' . (time()) . '.' . $file->getClientOriginalExtension();
        $isUploaded = move_uploaded_file($localFilePath, $this->getDefaultDirPath() . DIRECTORY_SEPARATOR . $uploadToFilePath);
        if(!$isUploaded) {
            return new \WP_Error('failed_to_upload', __('Failed to upload file', 'fluent-cart'));
        }
        return [
            'message' => __('File Uploaded Successfully', 'fluent-cart'),
            'path' => $uploadToFilePath,
            'file' => [
                'driver' => 'local',
                'size' => $fileSize,
                'name' => $uploadToFilePath,
                'bucket' => ''
            ]
        ];
    }

    public function getSignedDownloadUrl(string $filePath, $bucket = null, $productDownload = null): string
    {
        return Helper::generateDownloadFileLink($productDownload);
    }

    public function downloadFile(string $filePath, $fileName = null)
    {
        $file = "{$this->dirPath}/{$filePath}";
        if (ob_get_level()) {
            ob_end_clean();
        }

        if(!file_exists($file)) {
            return new \WP_Error('file_not_found', __('File not found', 'fluent-cart'));
        }
        $fileSize = filesize($file);
        $fileName = $fileName ?? basename($filePath);
        $fileName = explode('_____fluent-cart_____', $fileName)[0];
        $fileName = explode('__fluent-cart__', $fileName)[0];
        $this->setDownloadHeader($fileName ?? basename($filePath), $file, $fileSize);
        readfile($file);
        exit;
    }

    public function getFilePath(string $filePath, $fileName = null): string
    {
        return "{$this->dirPath}/{$filePath}";
    }

    protected function retrieveFileForDownload(string $downloadableFilePath)
    {
        // TODO: Implement retrieveFileForDownload() method.
    }

    public function deleteFile(string $filePath)
    {
        $fullPath = $this->getFilePath($filePath);
        
        if (!file_exists($fullPath)) {
            return new \WP_Error('file_not_found', __('File not found', 'fluent-cart'));
        }
        
        if (!is_file($fullPath)) {
            return new \WP_Error('not_a_file', __('Path is not a file', 'fluent-cart'));
        }
        
        if (unlink($fullPath)) {
            return [
                'message' => __('File Deleted Successfully', 'fluent-cart'),
                'driver' => 'local',
                'path' => $filePath
            ];
        }
        
        return new \WP_Error('failed_to_delete', __('Failed to delete file', 'fluent-cart'));
    }
}
