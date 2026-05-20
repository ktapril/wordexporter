<?php

require_once 'vendor/autoload.php';
require_once 'app/Controllers/DeleteTemplateController.php';

header('Content-Type: application/json');

$controller = new DeleteTemplateController();
$controller->handle();

exit;