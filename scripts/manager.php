<?php

require('../vendor/autoload.php');
require('Builder.php');

$action = !empty($_POST['action']) ? $_POST['action'] : '';
$separator = !empty($_POST['separator']) ? $_POST['separator'] : '';
$files = !empty($_FILES) ? $_FILES : [];

if (empty($files) || empty($separator) || empty($action)) {
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


$builder = new Builder();

switch ($action) {
    case 'create_table':
        foreach ($files as $file) {
            $builder->setFile($file);
            $builder->build();
        }
        break;
    default:
        break;
}

//return $builder->getFilePath();