<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (empty($_GET['repository_path'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required parameter: 'repository_path'"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$excludedDirs = [];
if ($data['exclude_dirs']) {
    $excludedDirs = $data['exclude_dirs'];
}

$basePath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $_GET['repository_path']);

$filePaths = get_all_file_paths($basePath, $basePath, $excludedDirs);

$data = ['files' => []];
foreach ($filePaths as $filePath) {
    $normalizedPath = str_replace('\\', '/', $filePath);
    $pathInfo = pathinfo($normalizedPath);
    $data['files'][] = [
        'path' => $normalizedPath,
        'size' => format_size_units(filesize($basePath.DIRECTORY_SEPARATOR.$filePath)),
        'extension' => $pathInfo['extension'],
        'lastChange' => date('d.m.Y H:i:s', filemtime($basePath.DIRECTORY_SEPARATOR.$filePath))
    ];
}

echo json_encode($data);

/**
 * Get all relative file paths from dir.
 * @param string $dir
 * @param string $basePath
 * @param array $excludedDirs
 * @return array
 */
function get_all_file_paths(string $dir, string $basePath, array $excludedDirs): array {
    $dirIterator = new DirectoryIterator($dir);
    $filePaths = array();
    
    foreach ($dirIterator as $fileInfo) {
        $fileName = $fileInfo->getFilename();
        $pathName = $fileInfo->getPathname();
        $isDir = $fileInfo->isDir();
        
        if (
            $fileInfo->isDot() ||
            is_excluded_dir($pathName, $excludedDirs)
        ) {
            continue;
        }
        
        if (
            (strpos($fileName, 'lang_') !== false && strlen($fileName) > 30) ||
            $fileInfo->getExtension() === 'txt'
        ) {
            continue;
        }
        
        if ($isDir) {
            $filePaths = array_merge($filePaths, get_all_file_paths($dir.DIRECTORY_SEPARATOR.$fileName, $basePath, $excludedDirs));
        } else {
            $filePath = ltrim(str_replace(__DIR__, '', $pathName), DIRECTORY_SEPARATOR);
            $filePaths[] = str_replace($basePath.DIRECTORY_SEPARATOR, '', $filePath);
        }
    }
    
    return $filePaths;
}

function is_excluded_dir($path, $excludedDirs): bool {
    if (strpos($path, DIRECTORY_SEPARATOR) !== false) {
        $_ = explode(DIRECTORY_SEPARATOR, $path);
        foreach ($_ as $dir) {
            if (in_array($dir, $excludedDirs)) {
                return true;
            }
        }
    } elseif (in_array($path, $excludedDirs)) {
        return true;
    }
    return false;
}

function format_size_units($bytes): string {
    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2).' GB';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2).' MB';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2).' KB';
    } elseif ($bytes > 1) {
        $bytes = $bytes.' bytes';
    } elseif ($bytes == 1) {
        $bytes = $bytes.' byte';
    } else {
        $bytes = '0 bytes';
    }
    return $bytes;
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
