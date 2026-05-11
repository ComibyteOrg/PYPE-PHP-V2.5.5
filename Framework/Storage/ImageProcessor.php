<?php

namespace Framework\Storage;

class ImageProcessor
{
    private $image;
    private int $originalWidth;
    private int $originalHeight;
    private string $mimeType;
    private array $operations = [];
    private int $quality = 85;
    private string $format = '';
    private ?string $watermarkPath = null;
    private array $watermarkOptions = [
        'position' => 'bottom-right',
        'opacity' => 50,
        'padding' => 10,
    ];

    public static function make(string $path): self
    {
        $processor = new self();
        $processor->load($path);
        return $processor;
    }

    public static function fromContents(string $contents): self
    {
        $processor = new self();
        $processor->loadFromContents($contents);
        return $processor;
    }

    public function resize(int $width, ?int $height = null, string $mode = 'auto'): self
    {
        $this->operations[] = [
            'type' => 'resize',
            'width' => $width,
            'height' => $height,
            'mode' => $mode,
        ];
        return $this;
    }

    public function fit(int $width, int $height, string $position = 'center'): self
    {
        $this->operations[] = [
            'type' => 'fit',
            'width' => $width,
            'height' => $height,
            'position' => $position,
        ];
        return $this;
    }

    public function crop(int $width, int $height, int $x = 0, int $y = 0): self
    {
        $this->operations[] = [
            'type' => 'crop',
            'width' => $width,
            'height' => $height,
            'x' => $x,
            'y' => $y,
        ];
        return $this;
    }

    public function scale(float $factor): self
    {
        $this->operations[] = [
            'type' => 'scale',
            'factor' => $factor,
        ];
        return $this;
    }

    public function width(int $width): self
    {
        $ratio = $width / $this->originalWidth;
        $height = (int) ($this->originalHeight * $ratio);
        return $this->resize($width, $height);
    }

    public function height(int $height): self
    {
        $ratio = $height / $this->originalHeight;
        $width = (int) ($this->originalWidth * $ratio);
        return $this->resize($width, $height);
    }

    public function square(int $size): self
    {
        return $this->fit($size, $size);
    }

    public function thumbnail(int $size): self
    {
        return $this->fit($size, $size, 'center');
    }

    public function rotate(float $degrees): self
    {
        $this->operations[] = [
            'type' => 'rotate',
            'degrees' => $degrees,
        ];
        return $this;
    }

    public function flip(string $direction = 'horizontal'): self
    {
        $this->operations[] = [
            'type' => 'flip',
            'direction' => $direction,
        ];
        return $this;
    }

    public function blur(int $amount = 5): self
    {
        $this->operations[] = [
            'type' => 'blur',
            'amount' => $amount,
        ];
        return $this;
    }

    public function brightness(int $value): self
    {
        $this->operations[] = [
            'type' => 'brightness',
            'value' => $value,
        ];
        return $this;
    }

    public function contrast(int $value): self
    {
        $this->operations[] = [
            'type' => 'contrast',
            'value' => $value,
        ];
        return $this;
    }

    public function grayscale(): self
    {
        $this->operations[] = ['type' => 'grayscale'];
        return $this;
    }

    public function sharpen(int $amount = 3): self
    {
        $this->operations[] = [
            'type' => 'sharpen',
            'amount' => $amount,
        ];
        return $this;
    }

    public function watermark(string $path, array $options = []): self
    {
        $this->watermarkPath = $path;
        $this->watermarkOptions = array_merge($this->watermarkOptions, $options);
        return $this;
    }

    public function quality(int $quality): self
    {
        $this->quality = max(1, min(100, $quality));
        return $this;
    }

    public function format(string $format): self
    {
        $format = strtolower($format);
        if (in_array($format, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'])) {
            $this->format = $format === 'jpeg' ? 'jpg' : $format;
        }
        return $this;
    }

    public function toJpeg(int $quality = 85): self
    {
        return $this->format('jpg')->quality($quality);
    }

    public function toPng(): self
    {
        return $this->format('png');
    }

    public function toWebp(int $quality = 85): self
    {
        return $this->format('webp')->quality($quality);
    }

    public function save(?string $path = null): string|false
    {
        $image = $this->image;

        foreach ($this->operations as $operation) {
            $image = match ($operation['type']) {
                'resize' => $this->applyResize($image, $operation),
                'fit' => $this->applyFit($image, $operation),
                'crop' => $this->applyCrop($image, $operation),
                'scale' => $this->applyScale($image, $operation),
                'rotate' => $this->applyRotate($image, $operation),
                'flip' => $this->applyFlip($image, $operation),
                'blur' => $this->applyBlur($image, $operation),
                'brightness' => $this->applyBrightness($image, $operation),
                'contrast' => $this->applyContrast($image, $operation),
                'grayscale' => $this->applyGrayscale($image),
                'sharpen' => $this->applySharpen($image, $operation),
                default => $image,
            };
        }

        if ($this->watermarkPath && file_exists($this->watermarkPath)) {
            $image = $this->applyWatermark($image);
        }

        $format = $this->format ?: $this->getExtensionFromMimeType($this->mimeType);
        $format = $format === 'jpeg' ? 'jpg' : $format;

        if ($path === null) {
            $path = tempnam(sys_get_temp_dir(), 'img_') . '.' . $format;
        }

        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $saved = match ($format) {
            'jpg', 'jpeg' => imagejpeg($image, $path, $this->quality),
            'png' => imagepng($image, $path, min(9, (int) (($this->quality - 1) / 11))),
            'webp' => imagewebp($image, $path, $this->quality),
            'gif' => imagegif($image, $path),
            'bmp' => imagebmp($image, $path, $this->quality >= 80),
            default => imagejpeg($image, $path, $this->quality),
        };

        imagedestroy($image);

        return $saved ? $path : false;
    }

    public function output(?string $format = null): string
    {
        ob_start();

        $image = $this->image;
        foreach ($this->operations as $operation) {
            $image = match ($operation['type']) {
                'resize' => $this->applyResize($image, $operation),
                'fit' => $this->applyFit($image, $operation),
                'crop' => $this->applyCrop($image, $operation),
                'scale' => $this->applyScale($image, $operation),
                'rotate' => $this->applyRotate($image, $operation),
                'flip' => $this->applyFlip($image, $operation),
                'blur' => $this->applyBlur($image, $operation),
                'brightness' => $this->applyBrightness($image, $operation),
                'contrast' => $this->applyContrast($image, $operation),
                'grayscale' => $this->applyGrayscale($image),
                'sharpen' => $this->applySharpen($image, $operation),
                default => $image,
            };
        }

        if ($this->watermarkPath && file_exists($this->watermarkPath)) {
            $image = $this->applyWatermark($image);
        }

        $format = $format ?: $this->format ?: $this->getExtensionFromMimeType($this->mimeType);
        $format = $format === 'jpeg' ? 'jpg' : $format;

        match ($format) {
            'jpg', 'jpeg' => imagejpeg($image, null, $this->quality),
            'png' => imagepng($image, null, min(9, (int) (($this->quality - 1) / 11))),
            'webp' => imagewebp($image, null, $this->quality),
            'gif' => imagegif($image),
            default => imagejpeg($image, null, $this->quality),
        };

        imagedestroy($image);

        return ob_get_clean();
    }

    public function getWidth(): int
    {
        return imagesx($this->image);
    }

    public function getHeight(): int
    {
        return imagesy($this->image);
    }

    public function getOriginalWidth(): int
    {
        return $this->originalWidth;
    }

    public function getOriginalHeight(): int
    {
        return $this->originalHeight;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    private function load(string $path): void
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("Image file not found: {$path}");
        }

        $info = getimagesize($path);
        if ($info === false) {
            throw new \InvalidArgumentException("Invalid image file: {$path}");
        }

        $this->mimeType = $info['mime'];
        $this->originalWidth = $info[0];
        $this->originalHeight = $info[1];

        $this->image = match ($info[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_PNG => imagecreatefrompng($path),
            IMAGETYPE_GIF => imagecreatefromgif($path),
            IMAGETYPE_WEBP => imagecreatefromwebp($path),
            IMAGETYPE_BMP => imagecreatefrombmp($path),
            default => throw new \InvalidArgumentException("Unsupported image type: {$info['mime']}"),
        };
    }

    private function loadFromContents(string $contents): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'img_');
        file_put_contents($tempFile, $contents);

        try {
            $this->load($tempFile);
        } finally {
            @unlink($tempFile);
        }
    }

    private function applyResize($image, array $op)
    {
        $width = $op['width'];
        $height = $op['height'] ?? null;
        $mode = $op['mode'];

        if ($height === null || $mode === 'auto') {
            $ratio = $width / $this->originalWidth;
            $height = (int) ($this->originalHeight * $ratio);
        }

        $new = imagecreatetruecolor($width, $height);
        if ($this->mimeType === 'image/png') {
            imagealphablending($new, false);
            imagesavealpha($new, true);
        }

        imagecopyresampled($new, $image, 0, 0, 0, 0, $width, $height, $this->originalWidth, $this->originalHeight);

        $this->originalWidth = $width;
        $this->originalHeight = $height;

        imagedestroy($image);
        return $new;
    }

    private function applyFit($image, array $op)
    {
        $targetWidth = $op['width'];
        $targetHeight = $op['height'];

        $origRatio = $this->originalWidth / $this->originalHeight;
        $targetRatio = $targetWidth / $targetHeight;

        if ($origRatio > $targetRatio) {
            $newWidth = (int) ($targetHeight * $origRatio);
            $newHeight = $targetHeight;
        } else {
            $newWidth = $targetWidth;
            $newHeight = (int) ($targetWidth / $origRatio);
        }

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        if ($this->mimeType === 'image/png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $this->originalWidth, $this->originalHeight);

        $final = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($this->mimeType === 'image/png') {
            imagealphablending($final, false);
            imagesavealpha($final, true);
            $transparent = imagecolorallocatealpha($final, 0, 0, 0, 127);
            imagefill($final, 0, 0, $transparent);
        }

        $positions = [
            'top-left' => [0, 0],
            'top' => [($targetWidth - $newWidth) / 2, 0],
            'top-right' => [$targetWidth - $newWidth, 0],
            'left' => [0, ($targetHeight - $newHeight) / 2],
            'center' => [($targetWidth - $newWidth) / 2, ($targetHeight - $newHeight) / 2],
            'right' => [$targetWidth - $newWidth, ($targetHeight - $newHeight) / 2],
            'bottom-left' => [0, $targetHeight - $newHeight],
            'bottom' => [($targetWidth - $newWidth) / 2, $targetHeight - $newHeight],
            'bottom-right' => [$targetWidth - $newWidth, $targetHeight - $newHeight],
        ];

        $pos = $positions[$op['position']] ?? $positions['center'];

        imagecopy($final, $resized, (int) $pos[0], (int) $pos[1], 0, 0, $newWidth, $newHeight);

        $this->originalWidth = $targetWidth;
        $this->originalHeight = $targetHeight;

        imagedestroy($resized);
        imagedestroy($image);
        return $final;
    }

    private function applyCrop($image, array $op)
    {
        $new = imagecreatetruecolor($op['width'], $op['height']);
        if ($this->mimeType === 'image/png') {
            imagealphablending($new, false);
            imagesavealpha($new, true);
        }

        imagecopy($new, $image, 0, 0, $op['x'], $op['y'], $op['width'], $op['height']);

        $this->originalWidth = $op['width'];
        $this->originalHeight = $op['height'];

        imagedestroy($image);
        return $new;
    }

    private function applyScale($image, array $op)
    {
        $newWidth = (int) ($this->originalWidth * $op['factor']);
        $newHeight = (int) ($this->originalHeight * $op['factor']);

        return $this->applyResize($image, ['width' => $newWidth, 'height' => $newHeight, 'mode' => 'auto']);
    }

    private function applyRotate($image, array $op)
    {
        $rotated = imagerotate($image, -$op['degrees'], 0);
        $this->originalWidth = imagesx($rotated);
        $this->originalHeight = imagesy($rotated);
        imagedestroy($image);
        return $rotated;
    }

    private function applyFlip($image, array $op)
    {
        $mode = $op['direction'] === 'horizontal' ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL;
        imageflip($image, $mode);
        return $image;
    }

    private function applyBlur($image, array $op)
    {
        for ($i = 0; $i < $op['amount']; $i++) {
            imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
        }
        return $image;
    }

    private function applyBrightness($image, array $op)
    {
        imagefilter($image, IMG_FILTER_BRIGHTNESS, min(255, max(-255, $op['value'])));
        return $image;
    }

    private function applyContrast($image, array $op)
    {
        imagefilter($image, IMG_FILTER_CONTRAST, min(100, max(-100, -$op['value'])));
        return $image;
    }

    private function applyGrayscale($image)
    {
        imagefilter($image, IMG_FILTER_GRAYSCALE);
        return $image;
    }

    private function applySharpen($image, array $op)
    {
        $matrix = [
            [-1, -1, -1],
            [-1, 8 + $op['amount'], -1],
            [-1, -1, -1],
        ];
        $divisor = $op['amount'];
        $offset = 0;

        imageconvolution($image, $matrix, $divisor, $offset);
        return $image;
    }

    private function applyWatermark($image)
    {
        $watermark = imagecreatefrompng($this->watermarkPath);
        if (!$watermark) {
            return $image;
        }

        $wmWidth = imagesx($watermark);
        $wmHeight = imagesy($watermark);
        $padding = $this->watermarkOptions['padding'];

        switch ($this->watermarkOptions['position']) {
            case 'top-left':
                $x = $padding;
                $y = $padding;
                break;
            case 'top-right':
                $x = $this->originalWidth - $wmWidth - $padding;
                $y = $padding;
                break;
            case 'bottom-left':
                $x = $padding;
                $y = $this->originalHeight - $wmHeight - $padding;
                break;
            default:
                $x = $this->originalWidth - $wmWidth - $padding;
                $y = $this->originalHeight - $wmHeight - $padding;
        }

        $opacity = (int) (($this->watermarkOptions['opacity'] / 100) * 127);
        imagecopymerge($image, $watermark, $x, $y, 0, 0, $wmWidth, $wmHeight, 100 - $opacity);
        imagedestroy($watermark);

        return $image;
    }

    private function getExtensionFromMimeType(string $mimeType): string
    {
        return match ($mimeType) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            default => 'jpg',
        };
    }
}
