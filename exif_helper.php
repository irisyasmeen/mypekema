<?php
/**
 * Utility to extract and format EXIF data from JPEG/TIFF images
 */
class ExifHelper
{

    /**
     * Extracts relevant EXIF data (Date/Time and GPS) and returns it as a JSON string.
     * Returns null if no EXIF data is found or if parsing fails.
     * 
     * @param string $filePath Full path to the image file
     * @return string|null JSON string of EXIF data or null
     */
    public static function extractExifAsJson($filePath)
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $exif = @exif_read_data($filePath, 'ANY_TAG', true);
        if ($exif === false) {
            return null;
        }

        $extractedData = [];

        // 1. Extract Date/Time Original
        if (isset($exif['EXIF']['DateTimeOriginal'])) {
            $extractedData['DateTimeOriginal'] = $exif['EXIF']['DateTimeOriginal'];
        }

        // 2. Extract GPS Coordinates
        if (
            isset($exif['GPS']['GPSLatitude']) && isset($exif['GPS']['GPSLatitudeRef']) &&
            isset($exif['GPS']['GPSLongitude']) && isset($exif['GPS']['GPSLongitudeRef'])
        ) {

            $latitude = self::getGpsCoordinate($exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLatitudeRef']);
            $longitude = self::getGpsCoordinate($exif['GPS']['GPSLongitude'], $exif['GPS']['GPSLongitudeRef']);

            if ($latitude !== false && $longitude !== false) {
                $extractedData['GPS'] = [
                    'Latitude' => $latitude,
                    'Longitude' => $longitude,
                    'MapLink' => "https://www.google.com/maps?q={$latitude},{$longitude}"
                ];
            }
        }

        // Return JSON explicitly, or null if empty
        return !empty($extractedData) ? json_encode($extractedData) : null;
    }

    /**
     * Helper to convert EXIF coordinate arrays and reference to decimal degrees
     */
    private static function getGpsCoordinate($coordinateArray, $hemisphereRef)
    {
        if (count($coordinateArray) < 3)
            return false;

        $degrees = self::evalCoordinate($coordinateArray[0]);
        $minutes = self::evalCoordinate($coordinateArray[1]);
        $seconds = self::evalCoordinate($coordinateArray[2]);

        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

        $hemisphereRef = strtoupper(trim($hemisphereRef));
        if ($hemisphereRef == 'S' || $hemisphereRef == 'W') {
            $decimal *= -1;
        }

        return $decimal;
    }

    /**
     * Evaluates an EXIF rational fraction (e.g., '54/1') to a decimal number
     */
    private static function evalCoordinate($coordinateString)
    {
        $parts = explode('/', $coordinateString);
        if (count($parts) <= 0)
            return 0;
        if (count($parts) == 1)
            return (float) $parts[0];

        $denominator = (float) $parts[1];
        if ($denominator == 0)
            return 0; // Avoid division by zero

        return (float) $parts[0] / $denominator;
    }
}
?>