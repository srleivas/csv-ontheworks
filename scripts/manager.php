<?php

require('../vendor/autoload.php');
require('Builder.php');

$sourceFolder = !empty($_POST['sourceFolder']) ? $_POST['sourceFolder'] : '';
$writeTofile = !empty($_POST['writeToFile']);
$separator = !empty($_POST['separator']) ? $_POST['separator'] : '';
$action = !empty($_POST['action']) ? $_POST['action'] : '';
$files = !empty($_FILES) ? $_FILES : [];

if ((empty($files) && empty($sourceFolder)) || empty($separator) || empty($action)) {
    header("HTTP/1.1 500 Internal Server Error");
}

switch ($separator) {
    case 'pipe':
        $_POST['separator'] = '|';
        break;
    case 'comma':
        $_POST['separator'] = ',';
        break;
    case 'tab':
        $_POST['separator'] = "\t";
        break;
    case 'hyphen':
        $_POST['separator'] = '-';
        break;
}

if (ini_set('memory_limit', '4000M') === false) {
    header("HTTP/1.1 500 Internal Server Error");
}

$builder = new Builder();
if ($writeTofile) $builder->setWriteToFile();

switch ($action) {
    case 'create_table':

        if (empty($files)) {
            $files = glob("{$sourceFolder}/*.csv");
        }

        foreach ($files as $file) {
            if (!empty($file['error'])) {
                header("HTTP/1.1 500 Internal Server Error");
                break;
            }

            if (is_string($file)) {
                $fileInfo = [];
                $fileInfo['tmp_name'] = $file;
                $fileInfo['name'] = basename($file);

                $file = $fileInfo;
            }

            $builder->setFile($file);
            $builder->build();
        }
        break;
    default:
        break;
}

//return $builder->getFilePath();