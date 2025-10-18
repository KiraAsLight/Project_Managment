<?php

/**
 * Download Excel Template
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';

require_login();

// Create simple Excel template using PHP
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="material_list_template.xlsx"');

// Simple CSV template as fallback
$template = "No.,AssyMarking,Rv,Name,Qty,Dimensions,Length (mm),Weight(kg),T.Weight(kg),Remarks
1,ASSY-001,A,Steel Beam,2,200x100x5,6000,25.5,51,Main structure
2,ASSY-002,B,Steel Plate,10,,,5.2,52,Connection plate
3,ASSY-003,,Bolt M16,50,,,,0.5,25,Connection bolt";

echo $template;
exit;

?>