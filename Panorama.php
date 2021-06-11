<?php
class Panorama {

    function __construct($fullFilePath) {
        $this->fullFilePath = $fullFilePath;
        $this->originalImage = new Imagick($fullFilePath);
    }

    function crop(Array $attributes = [], String $outputFilePath) {
        $attributes = array_replace_recursive([
            'width' => 600,
            'height' => 600,
            'yaw' => 0,
            'pitch' => 0,
            'roll' => 0,
            'fov' => 90,
            'file_type' => 'jpg'
        ], $attributes);
        extract($attributes);

        function enforceParameterLimits($value, $min, $max) {
            $value = min($value, $max);
            $value = max($value, $min);
            return $value;
        }
        $yaw = enforceParameterLimits($yaw, -180, 180);
        $pitch = enforceParameterLimits($pitch, -90, 90);
        $roll = enforceParameterLimits($roll, -180, 180);
        $fov = enforceParameterLimits($fov, 10, 120);
        $mime = in_array($mime, ['jpg', 'webp']) ? $mime : 'jpg';

        // recalibrate parameters for computation
        $pitch = enforceParameterLimits(-($pitch - 90), 0, 179);
        $yaw = enforceParameterLimits((90 - $yaw), -180, 180);
        $tempWidth = $width;
        $tempHeight = $height;
        if($roll != 0) {
            if(($roll % 90) == 0) {
                $tempWidth = $tempHeight = max($width, $height);
            } else {
                $rollRad = deg2rad($roll);
                $tempHeight = round(abs($width * sin($rollRad)) + abs($height * cos($rollRad)));
                $tempWidth = round(abs($width * cos($rollRad)) + abs($height * sin($rollRad)));
            }
        }

        $sourceHeight = $this->originalImage->getImageHeight();
        $sourceWidth = $this->originalImage->getImageWidth();
        $sourcePixels = $this->originalImage->exportImagePixels(0, 0, $sourceWidth, $sourceHeight, "RGB", Imagick::PIXEL_CHAR);

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
        for($i = 0; $i < $tempHeight; $i++) {
            for($j = 0; $j < $tempWidth; $j++) {
                $fx = $j / $tempWidth;
                $fy = $i / $tempHeight;
                
                $rayX = $camPlaneOriginX + ($fx * $camRightX) - ($fy * $camUpX);
                $rayY = $camPlaneOriginY + ($fx * $camRightY) - ($fy * $camUpY);
                $rayZ = $camPlaneOriginZ + ($fx * $camRightZ) - ($fy * $camUpZ);
                $rayNorm = 1.0 / sqrt(($rayX ** 2) + ($rayY ** 2) + ($rayZ ** 2));
                
                $theta = floor(($sourceHeight / M_PI) * acos($rayY * $rayNorm));
                $phi = floor((($sourceWidth / M_PI) / 2) * (atan2($rayZ, $rayX) + M_PI));
                
                $destOffset = 4 * (($i * $tempWidth) + $j);
                $sourceOffset = 3 * (($theta * $sourceWidth) + $phi);
                
                $outputPixels[$destOffset] = $sourcePixels[$sourceOffset];
                $outputPixels[$destOffset + 1] = $sourcePixels[$sourceOffset + 1];
                $outputPixels[$destOffset + 2] = $sourcePixels[$sourceOffset + 2];
            }
        }

        try {
            $finalWidth = $tempWidth;
            $finalHeight = $tempHeight;
            $imageMagickDest = new Imagick;
            $imageMagickDest->newImage($finalWidth, $finalHeight, 'gray');    
            $imageMagickDest->importImagePixels(0, 0, $finalWidth, $finalHeight, "RGB", Imagick::PIXEL_CHAR, $outputPixels);
            if($roll != 0) {
                $finalWidth = $width;
                $finalHeight = $height;
                $imageMagickDest->rotateImage(new ImagickPixel, $roll);
                $imageMagickDest->cropImage($finalWidth, $finalHeight, ($tempWidth - $finalWidth) / 2, ($tempHeight - $finalHeight) / 2);
            }
            $imageMagickDest->setImageFormat($mime);
            $imageMagickDest->setImageCompressionQuality($comp);
            $imageMagickDest->writeImage($outputFilePath);
            return true;
        } catch (Exception $e) { }
        return false;
    }
}
