<?php

require_once '../vendor/autoload.php';

use Models\Upload;

$upload = new Upload();

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["pdfFile"])) {
    $upload->uploadFile(($_FILES["pdfFile"]));
}

phpinfo();