<?php
class Panorama {
    const MIN_WIDTH = 16;
    const MAX_WIDTH = 640;

    const MIN_HEIGHT = 16;
    const MAX_HEIGHT = 640;

    const MIN_YAW = -180;
    const MAX_YAW = 180;

    const MIN_PITCH = -90;
    const MAX_PITCH = 90;

    function __construct($fullFilePath) {
        $this->fullFilePath = $fullFilePath;
        $this->originalImage = new Imagick($fullFilePath);
    }

    private function createOuterImage($width, $height, $yaw, $pitch, $fov) {
        $sourceHeight = $this->originalImage->getImageHeight();
        $sourceWidth = $this->originalImage->getImageWidth();
        
        $ratioUp = 2 * tan(deg2rad($fov) / 2);
        $ratioRight = $ratioUp * (($sourceWidth / $sourceHeight) / 2);

        $yawRad = deg2rad($yaw);

        $pitchRad = deg2rad($pitch);
        $camDirX = sin($pitchRad) * sin($yawRad);
        $camDirY = cos($pitchRad);
        $camDirZ = sin($pitchRad) * cos($yawRad);

        $pitchUpRad = deg2rad($pitch - 90);
        $camUpX = $ratioUp * sin($pitchUpRad) * sin($yawRad);
        $camUpY = $ratioUp * cos($pitchUpRad);
        $camUpZ = $ratioUp * sin($pitchUpRad) * cos($yawRad);

        $yawRightRad = deg2rad($yaw - 90);
        $camRightX = $ratioRight * sin($yawRightRad);
        $camRightY = 0.0;
        $camRightZ = $ratioRight * cos($yawRightRad);

        $camPlaneOriginX = $camDirX + ($camUpX / 2) - ($camRightX / 2);
        $camPlaneOriginY = $camDirY + ($camUpY / 2) - ($camRightY / 2);
        $camPlaneOriginZ = $camDirZ + ($camUpZ / 2) - ($camRightZ / 2);

        $outputPixels = [];
        $sourcePixels = $this->originalImage->exportImagePixels(0, 0, $sourceWidth, $sourceHeight, "RGB", Imagick::PIXEL_CHAR);
        for($i = 0; $i < $height; $i++) {
            for($j = 0; $j < $width; $j++) {
                $fx = $j / $width;
                $fy = $i / $height;
                
                $rayX = $camPlaneOriginX + ($fx * $camRightX) - ($fy * $camUpX);
                $rayY = $camPlaneOriginY + ($fx * $camRightY) - ($fy * $camUpY);
                $rayZ = $camPlaneOriginZ + ($fx * $camRightZ) - ($fy * $camUpZ);
                $rayNorm = 1.0 / sqrt(($rayX ** 2) + ($rayY ** 2) + ($rayZ ** 2));
                
                $theta = floor(($sourceHeight / M_PI) * acos($rayY * $rayNorm));
                $phi = floor((($sourceWidth / M_PI) / 2) * (atan2($rayZ, $rayX) + M_PI));
                
                $destOffset = 4 * (($i * $width) + $j);
                $sourceOffset = 3 * (($theta * $sourceWidth) + $phi);
                
                $outputPixels[$destOffset] = $sourcePixels[$sourceOffset];
                $outputPixels[$destOffset + 1] = $sourcePixels[$sourceOffset + 1];
                $outputPixels[$destOffset + 2] = $sourcePixels[$sourceOffset + 2];
            }
        }

        $imageMagick = new Imagick;
        $imageMagick->newImage($width, $height, 'gray');    
        $imageMagick->importImagePixels(0, 0, $width, $height, "RGB", Imagick::PIXEL_CHAR, $outputPixels);

        $sourcePixels = [];
        
        return $imageMagick;
    }

    function crop(Array $attributes = [], bool $saveFile = false, String $outputFilePath = '') {
        $attributes = array_replace_recursive([
            'width' => self::MAX_WIDTH,
            'height' => self::MAX_HEIGHT,
            'yaw' => 0,
            'pitch' => 0,
            'roll' => 0,
            'fov' => 90,
            'fileType' => 'jpg',
            'compressionRate' => 80,
        ], $attributes);
        extract($attributes);

        function enforceParameterLimits($value, $min, $max) {
            $value = min($value, $max);
            $value = max($value, $min);
            return $value;
        }

        $width = enforceParameterLimits($width, self::MIN_WIDTH, self::MAX_WIDTH);
        $height = enforceParameterLimits($height, self::MIN_HEIGHT, self::MAX_HEIGHT);
        $yaw = enforceParameterLimits($yaw, self::MIN_YAW, self::MAX_YAW);
        $pitch = enforceParameterLimits($pitch, self::MIN_PITCH, self::MAX_PITCH);
        $roll = enforceParameterLimits($roll, -180, 180);
        $fov = enforceParameterLimits($fov, 10, 120);
        $fileType = in_array($fileType, ['jpg', 'webp']) ? $fileType : 'jpg';

        // recalibrate parameters for computation
        $outerWidth = round((cos(deg2rad(45)) * self::MAX_WIDTH) * 2);
        $outerHeight = round((cos(deg2rad(45)) * self::MAX_HEIGHT) * 2);
        $pitch = enforceParameterLimits(-($pitch - 90), 0, 179);
        $yaw = enforceParameterLimits((90 - $yaw), -180, 180);
        $fov += 10;

        $imageMagick = $this->createOuterImage($outerWidth, $outerHeight, $yaw, $pitch, $fov);

        if($roll != 0) {
            $imageMagick->rotateImage(new ImagickPixel, $roll);
        }
        $imageMagick->cropImage($width, $height, ($outerWidth - $width) / 2, ($outerHeight - $height) / 2);

        $imageMagick->setImageFormat($fileType);
        $imageMagick->setImageCompressionQuality($compressionRate);
        if($saveFile) {
            $imageMagick->writeImage($outputFilePath);
            return true;
        } else {
            return $imageMagick;
        }
    }
}
