<?php

// ini_set('display_errors', 1);
// error_reporting(E_ALL);

//точка входа

require_once 'vendor/autoload.php';

require_once 'app/Helpers/WordStyleHelper.php';
require_once 'app/Services/TableBuilderService.php';
require_once 'app/Services/WordExportService.php';
require_once 'app/Controllers/ExportController.php';

$controller = new ExportController();
$controller->handle();

exit;