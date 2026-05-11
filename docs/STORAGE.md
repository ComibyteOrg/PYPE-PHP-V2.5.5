# Cloud Storage & File Management Guide

## Overview

Pype PHP's storage system provides a unified interface for working with files across multiple cloud providers and local filesystems. Switch between Local, S3/MinIO, Cloudinary, Google Cloud Storage, and Azure Blob Storage with a single line change.

---

## Table of Contents

1. [Configuration](#configuration)
2. [Multi-Disk Storage](#multi-disk-storage)
3. [Local Storage](#local-storage)
4. [S3/MinIO Storage](#s3minio-storage)
5. [Cloudinary Storage](#cloudinary-storage)
6. [Google Cloud Storage](#google-cloud-storage)
7. [Azure Blob Storage](#azure-blob-storage)
8. [Signed/Temporary URLs](#signedtemporary-urls)
9. [Image Manipulation](#image-manipulation)
10. [File Validation Pipeline](#file-validation-pipeline)
11. [CDN Integration](#cdn-integration)
12. [Upload Queues](#upload-queues)
13. [Helper Functions](#helper-functions)

---

## Configuration

Configure storage disks in your bootstrap file:

```php
use Framework\Storage\Disk;

Disk::configure([
    'local' => [
        'driver' => 'local',
        'root' => storage_path('uploads'),
        'url' => env('APP_URL') . '/storage',
    ],

    's3' => [
        'driver' => 's3',
        'bucket' => env('AWS_BUCKET'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'access_key' => env('AWS_ACCESS_KEY_ID'),
        'secret_key' => env('AWS_SECRET_ACCESS_KEY'),
        'endpoint' => env('AWS_ENDPOINT'), // Optional (for MinIO)
        'use_path_style' => env('AWS_USE_PATH_STYLE', false),
        'url' => null, // Optional custom URL
    ],

    'cloudinary' => [
        'driver' => 'cloudinary',
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
        'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET'),
    ],

    'gcs' => [
        'driver' => 'gcs',
        'bucket' => env('GOOGLE_CLOUD_BUCKET'),
        'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
        'service_account_key' => env('GOOGLE_CLOUD_SERVICE_ACCOUNT_KEY'), // JSON string
    ],

    'azure' => [
        'driver' => 'azure',
        'account_name' => env('AZURE_STORAGE_ACCOUNT_NAME'),
        'account_key' => env('AZURE_STORAGE_ACCOUNT_KEY'),
        'container' => env('AZURE_CONTAINER', 'uploads'),
    ],
], 'local'); // Default disk
```

### Environment Variables

```env
# Local (default)
STORAGE_DISK=local

# S3
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=my-bucket
AWS_ENDPOINT=https://minio.example.com  # Optional (MinIO)
AWS_USE_PATH_STYLE=true                 # For MinIO

# Cloudinary
CLOUDINARY_CLOUD_NAME=your_cloud
CLOUDINARY_API_KEY=your_key
CLOUDINARY_API_SECRET=your_secret
CLOUDINARY_UPLOAD_PRESET=your_preset

# Google Cloud Storage
GOOGLE_CLOUD_PROJECT_ID=your_project
GOOGLE_CLOUD_BUCKET=your_bucket
GOOGLE_CLOUD_SERVICE_ACCOUNT_KEY='{"type":"service_account",...}'

# Azure
AZURE_STORAGE_ACCOUNT_NAME=your_account
AZURE_STORAGE_ACCOUNT_KEY=your_key
AZURE_CONTAINER=uploads
```

---

## Multi-Disk Storage

Switch between disks seamlessly:

```php
use Framework\Storage\Disk;

// Use default disk
$disk = Disk::local();

// Use specific disk
$s3 = Disk::s3();
$cloudinary = Disk::cloudinary();
$gcs = Disk::gcs();
$azure = Disk::azure();

// By name
$disk = Disk::get('s3');
```

---

## Local Storage

The default driver for local filesystem operations.

```php
$disk = Disk::local();

// Upload files
$disk->put('avatars/user.jpg', $imageData);
$disk->putFile('avatars/user.jpg', '/tmp/upload.jpg');
$disk->putFileAs('avatars', $_FILES['avatar'], 'john.jpg');

// Read files
$contents = $disk->get('avatars/user.jpg');
$stream = $disk->stream('avatars/user.jpg');

// Check existence
if ($disk->exists('avatars/user.jpg')) { /* ... */ }
if ($disk->missing('avatars/user.jpg')) { /* ... */ }

// File info
$size = $disk->size('avatars/user.jpg');
$modified = $disk->lastModified('avatars/user.jpg');

// List files
$files = $disk->files('avatars');
$allFiles = $disk->allFiles('avatars');
$dirs = $disk->directories('avatars');

// Copy, move, delete
$disk->copy('avatars/user.jpg', 'backup/user.jpg');
$disk->move('avatars/user.jpg', 'avatars/john.jpg');
$disk->delete('avatars/user.jpg');
$disk->deleteDirectory('avatars/old');
```

---

## S3/MinIO Storage

Full S3-compatible driver that also works with MinIO.

```php
$s3 = Disk::s3();

// Upload
$s3->put('photos/2024/image.jpg', $data, [
    'visibility' => 'public',  // or 'private'
    'content_type' => 'image/jpeg',
]);

$s3->putFile('documents/report.pdf', '/tmp/report.pdf');

// Download
$s3->download('documents/report.pdf', 'annual-report.pdf');

// Check existence
if ($s3->exists('photos/2024/image.jpg')) {
    $url = $s3->url('photos/2024/image.jpg');
    echo $url;
    // https://my-bucket.s3.us-east-1.amazonaws.com/photos/2024/image.jpg
}

// List objects
$files = $s3->files('photos/2024');
$allFiles = $s3->allFiles('photos');

// Copy between locations
$s3->copy('photos/old.jpg', 'photos/new.jpg');
$s3->move('photos/draft.jpg', 'photos/published.jpg');

// Delete
$s3->delete('photos/old.jpg');
$s3->delete(['file1.jpg', 'file2.jpg']); // Multiple
$s3->deleteDirectory('photos/archive');
```

### MinIO Configuration

```php
's3' => [
    'driver' => 's3',
    'bucket' => env('MINIO_BUCKET'),
    'region' => 'us-east-1',
    'access_key' => env('MINIO_ACCESS_KEY'),
    'secret_key' => env('MINIO_SECRET_KEY'),
    'endpoint' => env('MINIO_ENDPOINT'), // e.g., https://minio.example.com
    'use_path_style' => true,
],
```

---

## Cloudinary Storage

Automatic resource type detection (image, video, raw) with transformation support.

```php
$cloudinary = Disk::cloudinary();

// Upload image
$cloudinary->putFile('avatars/user.jpg', '/tmp/photo.jpg', [
    'folder' => 'avatars',
    'tags' => ['user', 'profile'],
]);

// Upload video
$cloudinary->putFile('videos/intro.mp4', '/tmp/intro.mp4');

// Upload document (raw)
$cloudinary->putFile('documents/terms.pdf', '/tmp/terms.pdf');

// Get URL
$url = $cloudinary->url('avatars/user.jpg');
// https://res.cloudinary.com/{cloud}/image/upload/avatars/user.jpg
```

### Image Transformations

```php
// With transformation URL
$thumbUrl = $cloudinary->transformUrl('avatars/user.jpg', [
    'width' => 200,
    'height' => 200,
    'crop' => 'fill',
    'gravity' => 'face',
]);

$blurredUrl = $cloudinary->transformUrl('avatars/user.jpg', [
    'effect' => 'blur:500',
    'quality' => 80,
]);

$grayscaleUrl = $cloudinary->transformUrl('avatars/user.jpg', [
    'effect' => 'grayscale',
    'width' => 400,
]);

// Chain multiple transformations
$transformedUrl = $cloudinary->transformUrl('product.jpg', [
    'width' => 800,
    'height' => 600,
    'crop' => 'fill',
    'quality' => 'auto',
    'format' => 'webp',
]);
```

---

## Google Cloud Storage

Native GCS integration with OAuth2 authentication.

```php
$gcs = Disk::gcs();

// Upload
$gcs->put('documents/report.pdf', $pdfData, [
    'content_type' => 'application/pdf',
    'visibility' => 'private',
]);

// Download
$gcs->download('documents/report.pdf', 'my-report.pdf');

// List files
$files = $gcs->allFiles('documents');

// Copy/move
$gcs->copy('documents/draft.pdf', 'documents/final.pdf');
$gcs->move('documents/temp.pdf', 'documents/archived.pdf');

// Delete
$gcs->delete('documents/old.pdf');
```

---

## Azure Blob Storage

Microsoft Azure Blob Storage integration.

```php
$azure = Disk::azure();

// Upload
$azure->put('uploads/image.jpg', $data, [
    'content_type' => 'image/jpeg',
]);

// Download
$azure->download('uploads/image.jpg', 'download.jpg');

// List blobs
$blobs = $azure->files('uploads');

// Copy/move
$azure->copy('uploads/image.jpg', 'uploads/copy.jpg');
$azure->move('uploads/draft.jpg', 'uploads/published.jpg');

// Delete
$azure->delete('uploads/old.jpg');
```

---

## Signed/Temporary URLs

Generate time-limited URLs for private files.

```php
// S3/MinIO
$s3 = Disk::s3();
$url = $s3->temporaryUrl('private/contract.pdf', 3600); // 1 hour
$url = $s3->temporaryUrl('private/contract.pdf', 86400); // 24 hours

// At specific time
$url = $s3->temporaryUrlAt('private/contract.pdf', new DateTime('+2 hours'));

// Cloudinary
$cloudinary = Disk::cloudinary();
$url = $cloudinary->temporaryUrl('private/report.pdf', time() + 3600);

// Azure
$azure = Disk::azure();
$url = $azure->temporaryUrl('private/contract.pdf', time() + 3600);

// Local (token-based)
$disk = Disk::local();
$url = $disk->temporaryUrl('private/contract.pdf', 3600);
```

---

## Image Manipulation

Built-in image processor using GD (no dependencies).

### Basic Operations

```php
use Framework\Storage\ImageProcessor;

// Load image
$img = ImageProcessor::make('path/to/photo.jpg');

// Resize (maintains aspect ratio)
$img->resize(800)->save('path/to/resized.jpg');

// Resize with specific dimensions
$img->resize(800, 600)->save('path/to/resized.jpg');

// Fit (crop + resize to exact dimensions)
$img->fit(400, 400)->save('path/to/square.jpg');
$img->fit(400, 400, 'center')->save('path/to/square.jpg');

// Position options: 'center', 'top', 'bottom', 'top-left', 'top-right', etc.

// Crop
$img->crop(200, 200, 50, 50)->save('path/to/cropped.jpg');

// Width/height only (auto aspect ratio)
$img->width(600)->save('path/to/width-only.jpg');
$img->height(400)->save('path/to/height-only.jpg');

// Square thumbnail
$img->thumbnail(150)->save('path/to/thumb.jpg');

// Scale
$img->scale(0.5)->save('path/to/half.jpg'); // 50%
```

### Adjustments

```php
$img = ImageProcessor::make('path/to/photo.jpg');

// Rotate
$img->rotate(90)->save('path/to/rotated.jpg');

// Flip
$img->flip('horizontal')->save('path/to/flipped.jpg');
$img->flip('vertical')->save('path/to/flipped.jpg');

// Blur
$img->blur(10)->save('path/to/blurred.jpg');

// Brightness (-255 to 255)
$img->brightness(50)->save('path/to/brighter.jpg');

// Contrast (-100 to 100)
$img->contrast(30)->save('path/to/contrast.jpg');

// Grayscale
$img->grayscale()->save('path/to/gray.jpg');

// Sharpen
$img->sharpen(5)->save('path/to/sharper.jpg');
```

### Format Conversion

```php
$img = ImageProcessor::make('path/to/photo.png');

// Convert to JPEG (smaller size)
$img->toJpeg(85)->save('path/to/photo.jpg');

// Convert to WebP (modern, smaller)
$img->toWebp(80)->save('path/to/photo.webp');

// Keep as PNG (lossless)
$img->toPng()->save('path/to/photo.png');

// Set quality (1-100)
$img->format('jpg')->quality(90)->save('path/to/quality.jpg');
```

### Watermarking

```php
$img = ImageProcessor::make('path/to/photo.jpg');

$img->watermark('path/to/logo.png', [
    'position' => 'bottom-right', // 'top-left', 'top-right', 'bottom-left'
    'opacity' => 50,              // 0-100
    'padding' => 20,              // Pixels from edge
])->save('path/to/watermarked.jpg');
```

### Chaining Operations

```php
ImageProcessor::make('path/to/original.jpg')
    ->resize(1200)
    ->quality(85)
    ->watermark('path/to/logo.png', ['position' => 'bottom-right'])
    ->format('webp')
    ->save('path/to/processed.webp');
```

### Output to Browser

```php
$img = ImageProcessor::make('path/to/photo.jpg')
    ->resize(800)
    ->quality(85);

header('Content-Type: image/jpeg');
echo $img->output();
```

### From String Contents

```php
$img = ImageProcessor::fromContents($imageData)
    ->resize(400)
    ->save('path/to/saved.jpg');
```

---

## File Validation Pipeline

Multi-layer file validation: extension, MIME, content inspection, and double-extension detection.

### Basic Validation

```php
use Framework\Storage\FileValidator;

$validator = FileValidator::make()
    ->allowedExtensions(['jpg', 'png', 'webp'])
    ->allowedMimes(['image/jpeg', 'image/png', 'image/webp'])
    ->maxSize(5242880) // 5MB
    ->minSize(1024);   // 1KB

if ($validator->validate($_FILES['avatar'])) {
    // File is safe, proceed with upload
} else {
    $errors = $validator->errors();
}
```

### Image-Only Validation

```php
$validator = FileValidator::make()
    ->imageOnly()
    ->maxSize(10485760); // 10MB

$validator->validate($_FILES['photo']);
```

### Content Verification

```php
$validator = FileValidator::make()
    ->allowedExtensions(['jpg', 'png'])
    ->checkContent(true)  // Scan for malicious content (default)
    ->forbiddenPatterns(['<?php', '<script', 'eval('])
    ->checkDoubleExtensions(true); // Block file.php.jpg

$validator->validate($_FILES['upload']);
```

### Quick Validation Function

```php
if (validate_file($_FILES['avatar'], [
    'extensions' => ['jpg', 'png', 'gif'],
    'mimes' => ['image/jpeg', 'image/png', 'image/gif'],
    'max_size' => 5242880,
    'image' => true,
])) {
    // Safe to upload
}
```

---

## CDN Integration

Automatic CDN URL rewriting for any content.

### Configuration

```php
use Framework\Storage\CdnRewriter;

CdnRewriter::configure([
    'enabled' => true,
    'default' => 's3',
    'disks' => [
        's3' => [
            'url' => 'https://my-bucket.s3.amazonaws.com',
            'cdn_url' => 'https://cdn.example.com',
        ],
        'local' => [
            'url' => env('APP_URL') . '/storage',
            'cdn_url' => 'https://cdn.example.com',
        ],
    ],
]);
```

### Rewriting URLs

```php
// Rewrite a single URL
$cdnUrl = CdnRewriter::rewrite('https://my-bucket.s3.amazonaws.com/image.jpg', 's3');
// Returns: https://cdn.example.com/image.jpg

// Rewrite a path
$cdnUrl = CdnRewriter::rewritePath('uploads/avatar.jpg', 's3');
// Returns: https://cdn.example.com/uploads/avatar.jpg

// Rewrite HTML content
$html = '<img src="/storage/image.jpg">';
$cdnHtml = CdnRewriter::rewriteContent($html, 'local');
// Returns: <img src="https://cdn.example.com/image.jpg">
```

### Helper Functions

```php
$cdnUrl = cdn_url($disk->url('image.jpg'), 's3');
$cdnHtml = cdn_content($viewHtml, 'local');
```

---

## Upload Queues

Queue large file uploads for background processing.

### Enqueue Upload

```php
use Framework\Storage\UploadQueue;

$queue = new UploadQueue();

// Queue a single upload
$jobId = $queue->enqueue(
    disk: 's3',
    path: 'videos/intro.mp4',
    localPath: '/tmp/uploaded_video.mp4',
    options: ['visibility' => 'public']
);

// Queue multiple uploads
$jobIds = $queue->enqueueMultiple([
    ['disk' => 's3', 'path' => 'videos/v1.mp4', 'local_path' => '/tmp/v1.mp4'],
    ['disk' => 's3', 'path' => 'videos/v2.mp4', 'local_path' => '/tmp/v2.mp4'],
]);
```

### Process Queue

```php
// Process all pending jobs
$results = $queue->process();

// Process specific job
$result = $queue->process($jobId);
```

### Monitor Jobs

```php
// Check status
$status = $queue->status($jobId);
// Returns: ['id' => ..., 'status' => 'pending', 'attempts' => 0, ...]

// List jobs
$allJobs = $queue->list();
$pendingJobs = $queue->list('pending');

// Count pending
$count = $queue->pendingCount();

// Cancel
$queue->cancel($jobId);

// Retry failed job
$queue->retry($jobId);

// Clean up completed jobs older than 7 days
$cleaned = $queue->clearCompleted(7);
```

### Helper Function

```php
$jobId = queue_upload('s3', 'videos/large.mp4', '/tmp/video.mp4');
```

---

## Helper Functions

| Function | Purpose |
|----------|---------|
| `storage()` | Get default disk instance |
| `storage_disk('s3')` | Get specific disk |
| `storage_url('path/file.jpg')` | Get file URL |
| `storage_temp_url('path/file.jpg', 3600)` | Get signed temporary URL |
| `validate_file($file, $rules)` | Validate uploaded file |
| `process_image('path/to/image.jpg')` | Create image processor |
| `cdn_url($url)` | Rewrite URL for CDN |
| `cdn_content($html)` | Rewrite content URLs for CDN |
| `queue_upload('s3', 'path', '/tmp/file')` | Queue upload for processing |

---

## Quick Reference

| Class | Purpose | Location |
|-------|---------|----------|
| `Disk` | Storage facade for all drivers | `Framework/Storage/Disk.php` |
| `LocalDriver` | Local filesystem driver | `Framework/Storage/Drivers/LocalDriver.php` |
| `S3Driver` | AWS S3 / MinIO driver | `Framework/Storage/Drivers/S3Driver.php` |
| `CloudinaryDriver` | Cloudinary driver | `Framework/Storage/Drivers/CloudinaryDriver.php` |
| `GcsDriver` | Google Cloud Storage driver | `Framework/Storage/Drivers/GcsDriver.php` |
| `AzureDriver` | Azure Blob Storage driver | `Framework/Storage/Drivers/AzureDriver.php` |
| `ImageProcessor` | Image manipulation (GD) | `Framework/Storage/ImageProcessor.php` |
| `FileValidator` | Multi-layer file validation | `Framework/Storage/FileValidator.php` |
| `CdnRewriter` | CDN URL rewriting | `Framework/Storage/CdnRewriter.php` |
| `UploadQueue` | Background upload queue | `Framework/Storage/UploadQueue.php` |
