<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $url = filter_var($_POST['url'], FILTER_SANITIZE_URL);
    $compression = isset($_POST['compression']) ? (int) $_POST['compression'] : 80;

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(['message' => 'Neplatná URL']);
        exit;
    }

    $headers = @get_headers($url);
    if (!$headers || strpos($headers[0], '200') === false) {
        echo json_encode(['message' => 'Nelze stáhnout stránku - URL není dostupné']);
        exit;
    }

    $html = @file_get_contents($url);

    if (!$html) {
        echo json_encode(['message' => 'Nelze stáhnout stránku']);
        exit;
    }

    preg_match_all('/<img[^>]+(?:src|data-src)=["\']([^"\']+)["\']/i', $html, $matches);
    $imageUrls = array_unique($matches[1]);

    if (empty($imageUrls)) {
        echo json_encode(['message' => 'Žádné obrázky nenalezeny']);
        exit;
    }

    $timestamp = date('Ymd_His');
    $domain = parse_url($url, PHP_URL_HOST);
    $safeDomain = preg_replace('/[^a-zA-Z0-9]/', '_', $domain);
    $resultDir = "results/{$safeDomain}_{$timestamp}";
    
    if (!file_exists($resultDir)) {
        mkdir($resultDir, 0777, true);
    }

    $zipFile = "$resultDir/{$safeDomain}_{$timestamp}_optimized.zip";
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        echo json_encode(['message' => 'Nelze vytvořit ZIP soubor']);
        exit;
    }

    $originalSize = 0;
    $optimizedSize = 0;

    foreach ($imageUrls as $imageUrl) {
        if (strpos($imageUrl, 'http') === false) {
            $imageUrl = rtrim($url, '/') . '/' . ltrim($imageUrl, '/');
        }

        $imageData = @file_get_contents($imageUrl);
        if (!$imageData) continue;

        $originalSize += strlen($imageData);

        $pathInfo = pathinfo(parse_url($imageUrl, PHP_URL_PATH));
        $filename = preg_replace('/[^a-zA-Z0-9]/', '_', $pathInfo['filename']) . '.webp';

        $imagePath = "$resultDir/$filename";
        $image = createImageFromFile($imageData, $imageUrl);
        
        if ($image) {
            if (imagepalettetotruecolor($image)) {
                imagewebp($image, $imagePath, $compression);
                imagedestroy($image);
                $zip->addFile($imagePath, $filename);
                $optimizedSize += filesize($imagePath);
            }
        }
    }

    $zip->close();

    array_map('unlink', glob("$resultDir/*.webp"));
    sleep(1);
    if (count(glob("$resultDir/*")) === 0) {
        rmdir($resultDir);
    }

    $savedSize = $originalSize - $optimizedSize;
    $compressionPercentage = ($originalSize > 0) ? round(($savedSize / $originalSize) * 100, 2) : 0;

    echo json_encode([
        'message' => '<i class="fa-solid fa-check" style="color: #007a50"></i> Obrázky byly optimalizovány!',
        'download' => $zipFile,
        'originalSize' => round($originalSize / 1024, 2) . ' KB',
        'optimizedSize' => round($optimizedSize / 1024, 2) . ' KB',
        'savedSize' => round($savedSize / 1024, 2) . ' KB',
        'compressionPercentage' => $compressionPercentage . '%'
    ]);
}

function createImageFromFile($imageData, $imageUrl) {
    $ext = strtolower(pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
    
    $tmpFile = tempnam(sys_get_temp_dir(), 'img');
    file_put_contents($tmpFile, $imageData);
    
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $image = @imagecreatefromjpeg($tmpFile);
            break;
        case 'png':
            $image = @imagecreatefrompng($tmpFile);
            break;
        case 'gif':
            $image = @imagecreatefromgif($tmpFile);
            break;
        default:
            $image = @imagecreatefromstring($imageData);
    }
    
    unlink($tmpFile);
    return $image;
}

?>
