<?php

require_once 'vendor/autoload.php';

require_once 'app/Controllers/TemplateController.php';

header('Content-Type: application/json');

$controller = new TemplateController();
$controller->handle();

exit;