<?php
function generateTextImage() {
    $text = $_GET['text'] ?? '';
    if (empty($text)) {
        http_response_code(400);
        exit;
    }

    $tokens = json_decode(file_get_contents('tokens.jmdl'), true);
    $fonts = json_decode(file_get_contents('fonts.jmdl'), true);
    
    foreach (mb_str_split($text) as $char) {
        $found = false;
        foreach ($tokens as $token) {
            if ($token['word'] === $char) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            http_response_code(400);
            exit;
        }
    }

    $lang = 'fa';
    foreach (mb_str_split($text) as $char) {
        foreach ($tokens as $token) {
            if ($token['word'] === $char && $token['language'] === 'persian/farsi/iran') {
                $lang = 'fa';
                break 2;
            } elseif ($token['word'] === $char && $token['language'] === 'English') {
                $lang = 'en';
                break 2;
            }
        }
    }

    $langFonts = array_filter($fonts, function($font) use ($lang) {
        return $font['lang'] === $lang;
    });
    $langFonts = array_values($langFonts);
    $selectedFont = $langFonts[array_rand($langFonts)];
    
    $fontPath = ($lang === 'fa' ? 'PersianFonts/' : 'EnglishFonts/') . $selectedFont['font-name'];
    if (!file_exists($fontPath)) {
        http_response_code(500);
        exit;
    }

    $ratios = [
        [1, 1],
        [4, 3],
        [3, 4]
    ];
    $ratio = $ratios[array_rand($ratios)];
    $baseSize = 400;
    $width = $ratio[0] * $baseSize;
    $height = $ratio[1] * $baseSize;

    $image = imagecreatetruecolor($width, $height);

    $bgType = mt_rand(0, 1);
    $textColor = mt_rand(0, 1) ? imagecolorallocate($image, 255, 255, 255) : imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));

    if ($bgType === 0) {
        do {
            $bgColor = imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
        } while ($bgColor == $textColor);
        imagefill($image, 0, 0, $bgColor);
    } else {
        $gradientColors = mt_rand(0, 1) ? 2 : 4;
        $baseHue = mt_rand(0, 360);
        $colors = [];
        
        for ($i = 0; $i < $gradientColors; $i++) {
            $hue = $baseHue + mt_rand(-30, 30);
            $hue = max(0, min(360, $hue));
            $colors[] = hslToRgb($hue / 360, mt_rand(70, 100) / 100, mt_rand(40, 70) / 100);
        }
        
        $directions = ['horizontal', 'vertical', 'diagonal'];
        $direction = $directions[array_rand($directions)];
        createGradient($image, $colors, $direction, $width, $height);
    }

    $fontSize = min($width, $height) / 10;
    $angle = 0;
    
    $bbox = imagettfbbox($fontSize, $angle, $fontPath, $text);
    $textWidth = $bbox[2] - $bbox[0];
    $textHeight = $bbox[1] - $bbox[7];
    
    $x = ($width - $textWidth) / 2;
    $y = ($height - $textHeight) / 2 + $textHeight;

    $hasBorder = mt_rand(0, 1);
    $hasShadow = mt_rand(0, 1);

    if ($hasShadow) {
        $shadowColor = imagecolorallocate($image, mt_rand(0, 100), mt_rand(0, 100), mt_rand(0, 100));
        imagettftext($image, $fontSize, $angle, $x + 3, $y + 3, $shadowColor, $fontPath, $text);
    }

    if ($hasBorder) {
        $borderColor = imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
        for ($i = -2; $i <= 2; $i++) {
            for ($j = -2; $j <= 2; $j++) {
                if ($i != 0 || $j != 0) {
                    imagettftext($image, $fontSize, $angle, $x + $i, $y + $j, $borderColor, $fontPath, $text);
                }
            }
        }
    }

    imagettftext($image, $fontSize, $angle, $x, $y, $textColor, $fontPath, $text);

    header('Content-Type: image/png');
    imagepng($image);
    imagedestroy($image);
}

function hslToRgb($h, $s, $l) {
    $r = $l;
    $g = $l;
    $b = $l;
    $v = ($l <= 0.5) ? ($l * (1.0 + $s)) : ($l + $s - $l * $s);
    
    if ($v > 0) {
        $m = $l + $l - $v;
        $sv = ($v - $m) / $v;
        $h *= 6.0;
        $sextant = floor($h);
        $fract = $h - $sextant;
        $vsf = $v * $sv * $fract;
        $mid1 = $m + $vsf;
        $mid2 = $v - $vsf;
        
        switch ($sextant) {
            case 0:
                $r = $v; $g = $mid1; $b = $m;
                break;
            case 1:
                $r = $mid2; $g = $v; $b = $m;
                break;
            case 2:
                $r = $m; $g = $v; $b = $mid1;
                break;
            case 3:
                $r = $m; $g = $mid2; $b = $v;
                break;
            case 4:
                $r = $mid1; $g = $m; $b = $v;
                break;
            case 5:
                $r = $v; $g = $m; $b = $mid2;
                break;
        }
    }
    
    return [round($r * 255), round($g * 255), round($b * 255)];
}

function createGradient($image, $colors, $direction, $width, $height) {
    $colorCount = count($colors);
    
    switch ($direction) {
        case 'horizontal':
            for ($i = 0; $i < $width; $i++) {
                $ratio = $i / ($width - 1);
                $colorIndex = $ratio * ($colorCount - 1);
                $index1 = floor($colorIndex);
                $index2 = min($index1 + 1, $colorCount - 1);
                $fraction = $colorIndex - $index1;
                
                $r = $colors[$index1][0] + ($colors[$index2][0] - $colors[$index1][0]) * $fraction;
                $g = $colors[$index1][1] + ($colors[$index2][1] - $colors[$index1][1]) * $fraction;
                $b = $colors[$index1][2] + ($colors[$index2][2] - $colors[$index1][2]) * $fraction;
                
                $color = imagecolorallocate($image, $r, $g, $b);
                imageline($image, $i, 0, $i, $height, $color);
            }
            break;
            
        case 'vertical':
            for ($i = 0; $i < $height; $i++) {
                $ratio = $i / ($height - 1);
                $colorIndex = $ratio * ($colorCount - 1);
                $index1 = floor($colorIndex);
                $index2 = min($index1 + 1, $colorCount - 1);
                $fraction = $colorIndex - $index1;
                
                $r = $colors[$index1][0] + ($colors[$index2][0] - $colors[$index1][0]) * $fraction;
                $g = $colors[$index1][1] + ($colors[$index2][1] - $colors[$index1][1]) * $fraction;
                $b = $colors[$index1][2] + ($colors[$index2][2] - $colors[$index1][2]) * $fraction;
                
                $color = imagecolorallocate($image, $r, $g, $b);
                imageline($image, 0, $i, $width, $i, $color);
            }
            break;
            
        case 'diagonal':
            for ($i = 0; $i < $width; $i++) {
                for ($j = 0; $j < $height; $j++) {
                    $ratio = ($i + $j) / ($width + $height - 2);
                    $colorIndex = $ratio * ($colorCount - 1);
                    $index1 = floor($colorIndex);
                    $index2 = min($index1 + 1, $colorCount - 1);
                    $fraction = $colorIndex - $index1;
                    
                    $r = $colors[$index1][0] + ($colors[$index2][0] - $colors[$index1][0]) * $fraction;
                    $g = $colors[$index1][1] + ($colors[$index2][1] - $colors[$index1][1]) * $fraction;
                    $b = $colors[$index1][2] + ($colors[$index2][2] - $colors[$index1][2]) * $fraction;
                    
                    $color = imagecolorallocate($image, $r, $g, $b);
                    imagesetpixel($image, $i, $j, $color);
                }
            }
            break;
    }
}

generateTextImage();
?>