<?php
/**
 * Image Content Service
 * Handles image content validation including nudity and violence detection
 */

require_once __DIR__ . '/../config/app.php';

class ImageContentService {
    
    // File size limits (in bytes)
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
    private const MIN_FILE_SIZE = 1024; // 1KB
    
    // Image dimension limits
    private const MAX_WIDTH = 4000;
    private const MAX_HEIGHT = 4000;
    private const MIN_WIDTH = 100;
    private const MIN_HEIGHT = 100;
    
    // Allowed MIME types
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg', 
        'image/png',
        'image/gif',
        'image/webp'
    ];
    
    // Banned content patterns (basic keyword detection)
    private const BANNED_TEXT_PATTERNS = [
        '/\b(nude|naked|sex|porn|xxx)\b/i',
        '/\b(violence|blood|weapon|gun|knife)\b/i',
        '/\b(drug|cocaine|heroin|weed)\b/i'
    ];
    
    // Suspicious file patterns
    private const SUSPICIOUS_PATTERNS = [
        '/\.(exe|bat|cmd|scr|com|pif|vbs|js)$/i',
        '/^\./i', // Hidden files
        '/[<>:"|?*]/i' // Special characters
    ];
    
    public function __construct() {
        // Initialize any external APIs or models here
    }
    
    /**
     * Validate and scan uploaded image for content safety
     */
    public function validateImage($uploadedFile, $filename = null) {
        $result = [
            'valid' => false,
            'safe' => false,
            'errors' => [],
            'warnings' => [],
            'metadata' => []
        ];
        
        try {
            // Step 1: Basic file validation
            $basicValidation = $this->validateBasicFile($uploadedFile, $filename);
            if (!$basicValidation['valid']) {
                $result['errors'] = array_merge($result['errors'], $basicValidation['errors']);
                return $result;
            }
            
            // Step 2: Image format validation
            $imageValidation = $this->validateImageFormat($uploadedFile['tmp_name']);
            if (!$imageValidation['valid']) {
                $result['errors'] = array_merge($result['errors'], $imageValidation['errors']);
                return $result;
            }
            
            $result['metadata'] = $imageValidation['metadata'];
            
            // Step 3: Content safety checks
            $contentSafety = $this->checkContentSafety($uploadedFile['tmp_name'], $filename);
            if (!$contentSafety['safe']) {
                $result['warnings'] = array_merge($result['warnings'], $contentSafety['warnings']);
                $result['errors'] = array_merge($result['errors'], $contentSafety['errors']);
                
                // Decide if warnings should block upload (configurable)
                if (!empty($contentSafety['errors'])) {
                    return $result;
                }
            }
            
            $result['valid'] = true;
            $result['safe'] = $contentSafety['safe'];
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Image validation error: " . $e->getMessage());
            $result['errors'][] = 'Image validation failed due to server error';
            return $result;
        }
    }
    
    /**
     * Basic file validation
     */
    private function validateBasicFile($file, $filename) {
        $result = ['valid' => true, 'errors' => []];
        
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $result['valid'] = false;
            $result['errors'][] = 'File upload failed';
            return $result;
        }
        
        // Check file size
        $fileSize = $file['size'] ?? filesize($file['tmp_name']);
        if ($fileSize > self::MAX_FILE_SIZE) {
            $result['valid'] = false;
            $result['errors'][] = 'File size too large (max ' . round(self::MAX_FILE_SIZE / 1024 / 1024, 1) . 'MB)';
        }
        
        if ($fileSize < self::MIN_FILE_SIZE) {
            $result['valid'] = false;
            $result['errors'][] = 'File size too small (min ' . round(self::MIN_FILE_SIZE / 1024, 1) . 'KB)';
        }
        
        // Check MIME type
        $mimeType = $file['type'] ?? mime_content_type($file['tmp_name']);
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            $result['valid'] = false;
            $result['errors'][] = 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed';
        }
        
        // Check filename for suspicious patterns
        $checkFilename = $filename ?? $file['name'] ?? '';
        foreach (self::SUSPICIOUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $checkFilename)) {
                $result['valid'] = false;
                $result['errors'][] = 'Suspicious filename detected';
                break;
            }
        }
        
        return $result;
    }
    
    /**
     * Validate image format and extract metadata
     */
    private function validateImageFormat($imagePath) {
        $result = ['valid' => true, 'errors' => [], 'metadata' => []];
        
        // Get image info
        $imageInfo = @getimagesize($imagePath);
        if ($imageInfo === false) {
            $result['valid'] = false;
            $result['errors'][] = 'Invalid image format';
            return $result;
        }
        
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $type = $imageInfo[2];
        
        // Check dimensions
        if ($width > self::MAX_WIDTH || $height > self::MAX_HEIGHT) {
            $result['valid'] = false;
            $result['errors'][] = "Image dimensions too large (max {self::MAX_WIDTH}x{self::MAX_HEIGHT})";
        }
        
        if ($width < self::MIN_WIDTH || $height < self::MIN_HEIGHT) {
            $result['valid'] = false;
            $result['errors'][] = "Image dimensions too small (min {self::MIN_WIDTH}x{self::MIN_HEIGHT})";
        }
        
        // Check image type
        $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
        if (!in_array($type, $allowedTypes)) {
            $result['valid'] = false;
            $result['errors'][] = 'Unsupported image format';
        }
        
        // Extract metadata
        $result['metadata'] = [
            'width' => $width,
            'height' => $height,
            'type' => image_type_to_mime_type($type),
            'file_size' => filesize($imagePath),
            'aspect_ratio' => round($width / $height, 2)
        ];
        
        // Check for EXIF data (for additional metadata)
        if (function_exists('exif_read_data') && in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM])) {
            $exif = @exif_read_data($imagePath);
            if ($exif) {
                $result['metadata']['has_exif'] = true;
                
                // Extract safe EXIF data
                if (isset($exif['DateTime'])) {
                    $result['metadata']['date_taken'] = $exif['DateTime'];
                }
                if (isset($exif['Make'])) {
                    $result['metadata']['camera_make'] = $exif['Make'];
                }
                if (isset($exif['Model'])) {
                    $result['metadata']['camera_model'] = $exif['Model'];
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Check image content for safety (basic implementation)
     */
    private function checkContentSafety($imagePath, $filename) {
        $result = ['safe' => true, 'warnings' => [], 'errors' => []];
        
        // Step 1: Filename text analysis
        $textSafety = $this->checkTextSafety($filename ?? '');
        if (!$textSafety['safe']) {
            $result['warnings'] = array_merge($result['warnings'], $textSafety['warnings']);
        }
        
        // Step 2: Basic image analysis
        $imageSafety = $this->performBasicImageAnalysis($imagePath);
        if (!$imageSafety['safe']) {
            $result['warnings'] = array_merge($result['warnings'], $imageSafety['warnings']);
            $result['errors'] = array_merge($result['errors'], $imageSafety['errors']);
        }
        
        // Step 3: External API check (if configured)
        if (AppConfig::get('image_moderation.enabled', false)) {
            $apiSafety = $this->checkWithExternalAPI($imagePath);
            if (!$apiSafety['safe']) {
                $result['warnings'] = array_merge($result['warnings'], $apiSafety['warnings']);
                $result['errors'] = array_merge($result['errors'], $apiSafety['errors']);
            }
        }
        
        $result['safe'] = empty($result['errors']);
        
        return $result;
    }
    
    /**
     * Check text content for banned patterns
     */
    private function checkTextSafety($text) {
        $result = ['safe' => true, 'warnings' => []];
        
        foreach (self::BANNED_TEXT_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                $result['safe'] = false;
                $result['warnings'][] = 'Filename contains potentially inappropriate content';
                break;
            }
        }
        
        return $result;
    }
    
    /**
     * Basic image analysis (rule-based)
     */
    private function performBasicImageAnalysis($imagePath) {
        $result = ['safe' => true, 'warnings' => [], 'errors' => []];
        
        try {
            // Get image resource
            $imageInfo = getimagesize($imagePath);
            $image = null;
            
            switch ($imageInfo[2]) {
                case IMAGETYPE_JPEG:
                    $image = @imagecreatefromjpeg($imagePath);
                    break;
                case IMAGETYPE_PNG:
                    $image = @imagecreatefrompng($imagePath);
                    break;
                case IMAGETYPE_GIF:
                    $image = @imagecreatefromgif($imagePath);
                    break;
                default:
                    // Skip analysis for unsupported types
                    return $result;
            }
            
            if (!$image) {
                $result['warnings'][] = 'Unable to analyze image content';
                return $result;
            }
            
            // Basic color analysis
            $colorAnalysis = $this->analyzeImageColors($image, $imageInfo[0], $imageInfo[1]);
            
            // Check for suspicious color patterns
            if ($colorAnalysis['skin_tone_percentage'] > 60) {
                $result['warnings'][] = 'High skin tone content detected - manual review may be required';
            }
            
            if ($colorAnalysis['red_percentage'] > 40) {
                $result['warnings'][] = 'High red content detected - check for inappropriate content';
            }
            
            // Clean up
            imagedestroy($image);
            
        } catch (Exception $e) {
            error_log("Image analysis error: " . $e->getMessage());
            $result['warnings'][] = 'Image content analysis failed';
        }
        
        return $result;
    }
    
    /**
     * Analyze image colors for suspicious patterns
     */
    private function analyzeImageColors($image, $width, $height) {
        $skinTonePixels = 0;
        $redPixels = 0;
        $totalPixels = 0;
        
        // Sample every 10th pixel for performance
        $sampleRate = 10;
        
        for ($x = 0; $x < $width; $x += $sampleRate) {
            for ($y = 0; $y < $height; $y += $sampleRate) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                // Detect skin tone (basic heuristic)
                if ($this->isSkinTone($r, $g, $b)) {
                    $skinTonePixels++;
                }
                
                // Detect high red content
                if ($r > 180 && $r > $g + 50 && $r > $b + 50) {
                    $redPixels++;
                }
                
                $totalPixels++;
            }
        }
        
        return [
            'skin_tone_percentage' => $totalPixels > 0 ? ($skinTonePixels / $totalPixels) * 100 : 0,
            'red_percentage' => $totalPixels > 0 ? ($redPixels / $totalPixels) * 100 : 0,
            'total_sampled_pixels' => $totalPixels
        ];
    }
    
    /**
     * Basic skin tone detection
     */
    private function isSkinTone($r, $g, $b) {
        // Simple skin tone detection based on RGB values
        // This is a basic heuristic and not foolproof
        return (
            $r > 95 && $g > 40 && $b > 20 &&
            max($r, $g, $b) - min($r, $g, $b) > 15 &&
            abs($r - $g) > 15 && $r > $g && $r > $b
        );
    }
    
    /**
     * Check with external moderation API (placeholder)
     */
    private function checkWithExternalAPI($imagePath) {
        $result = ['safe' => true, 'warnings' => [], 'errors' => []];
        
        // Placeholder for external API integration
        // Examples: Google Cloud Vision API, AWS Rekognition, etc.
        
        $apiEndpoint = AppConfig::get('image_moderation.api_endpoint');
        $apiKey = AppConfig::get('image_moderation.api_key');
        
        if (empty($apiEndpoint) || empty($apiKey)) {
            return $result; // Skip if not configured
        }
        
        try {
            // Mock implementation - replace with actual API call
            $mockResponse = [
                'safe_search' => [
                    'adult' => 'VERY_UNLIKELY',
                    'violence' => 'UNLIKELY',
                    'racy' => 'POSSIBLE'
                ]
            ];
            
            // Process API response
            if ($mockResponse['safe_search']['adult'] === 'LIKELY' || 
                $mockResponse['safe_search']['adult'] === 'VERY_LIKELY') {
                $result['errors'][] = 'Adult content detected';
                $result['safe'] = false;
            }
            
            if ($mockResponse['safe_search']['violence'] === 'LIKELY' || 
                $mockResponse['safe_search']['violence'] === 'VERY_LIKELY') {
                $result['errors'][] = 'Violent content detected';
                $result['safe'] = false;
            }
            
            if ($mockResponse['safe_search']['racy'] === 'LIKELY') {
                $result['warnings'][] = 'Potentially inappropriate content detected';
            }
            
        } catch (Exception $e) {
            error_log("External API check failed: " . $e->getMessage());
            $result['warnings'][] = 'External content check failed';
        }
        
        return $result;
    }
    
    /**
     * Get image content summary
     */
    public function getImageSummary($imagePath) {
        $summary = [
            'file_size' => filesize($imagePath),
            'mime_type' => mime_content_type($imagePath),
            'safe' => true,
            'analysis_date' => date('Y-m-d H:i:s')
        ];
        
        $imageInfo = @getimagesize($imagePath);
        if ($imageInfo) {
            $summary['width'] = $imageInfo[0];
            $summary['height'] = $imageInfo[1];
            $summary['aspect_ratio'] = round($imageInfo[0] / $imageInfo[1], 2);
        }
        
        return $summary;
    }
}
