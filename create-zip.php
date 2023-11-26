<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

$jsonData = file_get_contents("php://input");
$data = json_decode($jsonData, true);

if ($data['action'] === 'BUILD_ZIP' && $data['rootDir']) {
    $dirPath = 'temp'.DIRECTORY_SEPARATOR.'zip_builder_dir_'.time();
    mkdir($dirPath, 777);
    
    $absolutePathPrefix = normalize_path($data['rootDir']).DIRECTORY_SEPARATOR;
    
    foreach ($data['files'] as $filePath) {
        $absoluteFilePath = normalize_path($absolutePathPrefix.$filePath);
        
        $zipFilePath = $dirPath.DIRECTORY_SEPARATOR.str_replace($absolutePathPrefix, '', $absoluteFilePath);
        
        if (!is_dir(dirname($zipFilePath))) {
            mkdir(dirname($zipFilePath), 777, true);
        }
        
        copy($absoluteFilePath, $zipFilePath);
    }
    
    $zipArchivePath = 'temp/zip_builder_'.time().'.zip';
    $zipArchivePath = 'C:/Dev/zip-builder/src/temp/zip_builder_'.time().'.zip';
    unlink($zipArchivePath);
    $zipSuccess = zip($dirPath, $zipArchivePath);
    
    $status = $zipSuccess ? 'success' : 'error';
    
    $absoluteDirPath = realpath($dirPath);
    $dirSize = round(dir_size($absoluteDirPath) / 1024, 2);
    $absoluteZipPath = realpath($zipArchivePath);
    $zipArchiveSize = round(filesize($absoluteZipPath) / 1024, 2);
    
    echo json_encode(
        array(
            'status' => $status,
            'message' => array(
                'dir' => "Folder Created in: {$absoluteDirPath} ({$dirSize} KB)",
                'zip' => "ZIP Created in: {$absoluteZipPath} ({$zipArchiveSize} KB)"
            ),
            'filePaths' => array(
                'dir' => $dirPath,
                'zip' => $zipArchivePath
            )
        )
    );
    
    exit;
}

function debug($text = '', $variable = '', $file = 'debug.txt') {
    try {
        $dateTime = new DateTimeImmutable();
        $handle = fopen($file, 'ab+');
        fwrite($handle, $dateTime->format('d.m.Y H:i:s.u')." $text ".print_r($variable, true)."\n");
        fclose($handle);
    } catch (Exception $e) {
        $handle = fopen($file, 'ab+');
        fwrite($handle, date('d.m.Y H:i:s.u')." $text ".print_r($variable, true)."\n");
        fclose($handle);
    }
}

/**
 * Zip a dir to the specified location.
 * @param $source
 * @param $destination
 * @return bool
 */
function zip($source, $destination) {
    if (!extension_loaded('zip') || !file_exists($source)) {
        return false;
    }
    $zipArchive = new ZipArchive();
    if ($zipArchive->open($destination, ZipArchive::CREATE)) {
        $source = realpath($source);
        if (is_dir($source)) {
            $iterator = new RecursiveDirectoryIterator($source);
            $iterator->setFlags(RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);
            foreach ($files as $file) {
                $file = realpath($file->getPathname());
                $filePath = ltrim(str_replace($source, '', $file), DIRECTORY_SEPARATOR);
                if (is_dir($file)) {
                    $zipArchive->addEmptyDir($filePath);
                } elseif (is_file($file)) {
                    $zipArchive->addFromString($filePath, file_get_contents($file));
                }
            }
        } elseif (is_file($source)) {
            $zipArchive->addFromString(basename($source), file_get_contents($source));
        }
    }
    return $zipArchive->close();
}

/**
 * Recursively get dir size.
 * @param $dir
 * @return int
 */
function dir_size($dir) {
    $size = 0;
    foreach (glob(rtrim($dir, '/').'/*', GLOB_NOSORT) as $each) {
        $size += is_file($each) ? filesize($each) : dir_size($each);
    }
    return $size;
}

function normalize_path($path) {
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}
