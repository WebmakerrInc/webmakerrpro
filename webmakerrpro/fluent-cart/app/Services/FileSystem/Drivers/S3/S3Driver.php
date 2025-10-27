<?php

namespace FluentCart\App\Services\FileSystem\Drivers\S3;

use FluentCart\App\Models\ProductDownload;
use FluentCart\App\Modules\StorageDrivers\S3\S3 as S3StorageDriver;
use FluentCart\App\Modules\StorageDrivers\S3\S3Settings;
use FluentCart\App\Services\FileSystem\Drivers\BaseDriver;
use FluentCart\Framework\Support\Arr;

class S3Driver extends BaseDriver
{
    private string $accessKey;
    private string $secretKey;
    private string $bucket;
    private string $region;


    public function __construct(?string $dirPath = null, ?string $dirName = null)
    {
        parent::__construct($dirPath, $dirName);

        $getSettings = (new S3Settings())->get();

        $this->secretKey = Arr::get($getSettings, 'secret_key', '');
        $this->accessKey = Arr::get($getSettings, 'access_key', '');
        $this->bucket = Arr::get($getSettings, 'bucket', '');
        $this->region = Arr::get($getSettings, 'region', '');
        $this->storageDriver = new S3StorageDriver();
    }

    public function buckets()
    {
        return S3BucketList::get(
            $this->secretKey,
            $this->accessKey,
            $this->region
        );
    }

    public function uploadFile($localFilePath, $uploadToFilePath, $file, $params = [])
    {
        $fileSize = $file->toArray()['size_in_bytes'];

        $response = S3FileUploader::upload(
            $this->secretKey,
            $this->accessKey,
            Arr::get($params, 'bucket'),
            $this->region,
            $localFilePath,
            $uploadToFilePath
        );

        if (is_wp_error($response)) {
            return $response;
        }

        return [
            'message' => __('File Uploaded Successfully', 'fluent-cart'),
            'path'    => $response['path'],
            'file'    => [
                'driver' => 's3',
                'size' =>$fileSize,
                'bucket' => Arr::get($params, 'bucket'),
                'name'   => $response['path'],
            ],

        ];
    }


    public function listFiles(array $params = [])
    {
        return S3FileList::get(
            $this->secretKey,
            $this->accessKey,
            Arr::get($params, 'activeBucket'),
            $this->region,
            Arr::get($params, 'search', ''),
        );
    }

    protected function generatePresignedDownloadUrlOld(string $filePath, $expirationMinutes = 15, $bucket = null, $fileName = null): string
    {
        $this->bucket = $bucket;

        if(empty($fileName)) {
            $fileName = $filePath;
        }

        $fileName = explode('_____fluent-cart_____', $fileName)[0];
        $fileName = explode('__fluent-cart__', $fileName)[0];

        $expirationMinutes = is_numeric($expirationMinutes) ? $expirationMinutes : 15;
        $expires = time() + ($expirationMinutes * 60);
        $stringToSign = "GET\n\n\n{$expires}\n/{$this->bucket}/{$filePath}";
        $signature = urlencode(base64_encode(hash_hmac('sha1', $stringToSign, $this->secretKey, true)));
        return "https://{$this->bucket}.s3.{$this->region}.amazonaws.com/{$filePath}?AWSAccessKeyId={$this->accessKey}&Expires={$expires}&Signature={$signature}";
    }

    protected function generatePresignedDownloadUrl(string $filePath, $expirationMinutes = 15, $bucket = null, $fileName = null): string
    {
        $this->bucket = $bucket ?? $this->bucket;

        if (empty($fileName)) {
            $fileName = basename($filePath);
        }

        $fileName = explode('_____fluent-cart_____', $fileName)[0];
        $fileName = explode('__fluent-cart__', $fileName)[0];

        $expirationMinutes = is_numeric($expirationMinutes) ? (int)$expirationMinutes : 15;
        $expires = time() + ($expirationMinutes * 60);

        $stringToSign = "GET\n\n\n{$expires}\n/{$this->bucket}/{$filePath}";

        $queryParams = [
            'AWSAccessKeyId' => $this->accessKey,
            'Expires' => $expires,
        ];

        if (!empty($fileName)) {
            $contentDisposition = "attachment; filename=\"{$fileName}\"";
            $queryParams['response-content-disposition'] = $contentDisposition;

            // For Signature V1, the parameter value is NOT URL-encoded in string-to-sign
            $stringToSign .= '?response-content-disposition=' . $contentDisposition;
        }

        // Debug output
        //error_log("=== DEBUG S3 Presigned URL ===");
        //error_log("String to sign: " . $stringToSign);
        //error_log("String to sign (hex): " . bin2hex($stringToSign));
        //error_log("Content Disposition: " . ($contentDisposition ?? 'none'));
        //error_log("Encoded Content Disposition: " . ($encodedContentDisposition ?? 'none'));

        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->secretKey, true));
        $queryParams['Signature'] = $signature;

        //error_log("Generated Signature: " . $signature);
        //error_log("==============================");

        $url = "https://{$this->bucket}.s3.{$this->region}.amazonaws.com/{$filePath}";
        $queryString = http_build_query($queryParams);

        return $url . '?' . $queryString;
    }
    protected function retrieveFileForDownload(string $downloadableFilePath, $bucket = null)
    {
        $this->bucket = $bucket;
        $url = "https://{$this->bucket}.s3.{$this->region}.amazonaws.com/{$downloadableFilePath}";
        $date = gmdate('D, d M Y H:i:s T');
        $signature = base64_encode(hash_hmac('sha1', "GET\n\n\n{$date}\n/{$this->bucket}/{$downloadableFilePath}", $this->secretKey, true));
        $response = wp_remote_get($url, [
            'headers' => [
                "Date"          => $date,
                "Authorization" => "AWS {$this->accessKey}:{$signature}",
            ],
        ]);

        if (is_wp_error($response)) {
            return $response->get_error_message();
        }

        return $response['body'];
    }

    public function getSignedDownloadUrl(string $filePath, $bucket = null, $productDownload = null): string
    {
        $expirationMinutes = 7 * 24 * 60;
        apply_filters('fluent_cart/download_expiration_minutes', $expirationMinutes, [
            'file_path' => $filePath,
            'bucket'    => $bucket,
            'driver'    => 's3',
        ]);
        $fileName = null;
        if($productDownload instanceof ProductDownload){
            $fileName = $productDownload->file_name;
        }
        return $this->generatePresignedDownloadUrl($filePath, $expirationMinutes, $bucket, $fileName);
    }

    public function downloadFile(string $filePath, $fileName = null, $bucket = null)
    {
        $expirationMinutes = 7 * 24 * 60;
        apply_filters('fluent_cart/download_expiration_minutes', $expirationMinutes, [
            'file_path' => $filePath,
            'bucket'    => $bucket,
            'driver'    => 's3',
        ]);
        wp_redirect($this->generatePresignedDownloadUrl($filePath, $expirationMinutes, $bucket, $fileName));
        exit;
    }

    public function getFilePath(string $filePath, $fileName = null, $bucket = null)
    {
        return $this->retrieveFileForDownload($filePath, $bucket);
    }

    protected function getDefaultDirPath()
    {
        return $this->bucket;
    }

    public function deleteFile($filePath, $bucket = null)
    {
        $bucket = $bucket ?: $this->bucket;
        return S3FileDeleter::delete(
            $this->secretKey,
            $this->accessKey,
            $bucket,
            $this->region,
            $filePath
        );
    }
}
