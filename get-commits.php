<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

const DEBUG = false;

switch (true) {
    case empty($_GET['git_executable_path']):
        echo json_encode(['error' => 'Missing git_executable_path']);
        exit;
    
    case empty($_GET['repository_path']):
        echo json_encode(['error' => 'Missing repository_path']);
        exit;
    
    case empty($_GET['action']):
        echo json_encode(['error' => 'Missing action']);
        exit;
    
    default:
        define('REPOSITORY_PATH', $_GET['repository_path'].'/.git');
        define('GIT_EXECUTABLE_PATH', '"'.$_GET['git_executable_path'].'"');
        $action = $_GET['action'];
        break;
}

switch (strtoupper($action)) {
    case 'COMMITS':
        $args = [
            $_GET['since'] ?: null,
            $_GET['until'] ?: null,
            $_GET['author'] ?: null,
            $_GET['grep'] ?: null
        ];
        
        $commits = get_commits(...$args);
        
        $filePath = 'temp'.DIRECTORY_SEPARATOR.'commits_'.time().'.json';
        
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 777, true);
        }
        
        $fp = fopen($filePath, 'w');
        fwrite($fp, json_encode(['commits' => $commits], JSON_PRETTY_PRINT));
        fclose($fp);
        
        if (file_exists($filePath)) {
            $jsonContent = file_get_contents($filePath);
            $data = json_decode($jsonContent, true);
            if ($data !== null) {
                echo json_encode($data);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error decoding JSON']);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'File not found']);
        }
        
        exit;
    
    case 'AUTHORS':
        $authors = get_commit_authors();
        echo json_encode($authors);
        exit;
}

function get_commits($since, $until, $author, $grep): array {
    $argList = [
        '--git-dir='.REPOSITORY_PATH,
        'log',
        '--pretty="format:%h | %an | %ad | %s"',
        '--date="format:%d.%m.%Y %H:%M:%S"',
        $since ? '--since="'.$since.'"' : '',
        $until ? '--until="'.$until.'"' : '',
        $author ? '--author="'.$author.'"' : '',
        $grep ? '--grep="'.$grep.'"' : ''
    ];
    $out = execute_git_command($argList);
    
    if (empty($out)) {
        return [];
    }
    
    $commitList = explode("\n", $out);
    
    $parsedCommits = [];
    foreach ($commitList as $commit) {
        list($sha, $author, $datetime, $message) = explode(' | ', $commit);
        
        $files = get_commit_files_by_sha($sha);
        
        $parsedCommits[] = [
            'sha' => $sha,
            'author' => $author,
            'datetime' => $datetime,
            'message' => $message,
            'files' => $files
        ];
    }
    
    return $parsedCommits;
}

function get_commit_files_by_sha($sha): array {
    $parentSha = get_parent_commit_sha($sha);
    
    if ($parentSha) {
        $range = '"'.trim($parentSha).'^..'.$sha.'"';
    } else {
        $range = $sha;
    }
    
    $argList = [
        '--git-dir='.REPOSITORY_PATH,
        'diff',
        '--name-only',
        $range
    ];
    $out = execute_git_command($argList);
    return array_filter(explode("\n", $out));
}

function get_parent_commit_sha($sha): string {
    $argList = [
        '--git-dir='.REPOSITORY_PATH,
        'rev-parse',
        $sha.'^'
    ];
    return execute_git_command($argList);
}

function get_commit_authors(): array {
    $argList = [
        '--git-dir='.REPOSITORY_PATH,
        'log',
        '--pretty="%aN <%aE>"'
    ];
    $out = execute_git_command($argList);
    
    if (empty($out)) {
        return [];
    }
    
    $authorList = explode("\n", $out);
    
    $authors = [];
    foreach ($authorList as $author) {
        if (!in_array($author, $authors) && !empty($author)) {
            $authors[] = $author;
        }
    }
    
    return $authors;
}

function execute_git_command(array $argList): string {
    $args = implode(' ', $argList);
    $command = GIT_EXECUTABLE_PATH.' '.$args;
    $out = shell_exec($command);
    print_command($command, $out);
    return is_null($out) ? '' : $out;
}

function print_command($command, $out): void {
    if (!DEBUG) return;
    echo "Executing command:<br>{$command}<br>";
    echo "Out:<br>{$out}<br><br>";
}
