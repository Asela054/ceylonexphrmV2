<?php
session_start();
// Include the EmployeeHelper class
use App\Helpers\EmployeeHelper;
use App\Helpers\UserHelper;

// Correct path resolution for Laravel - use base path or proper autoloading
require_once(__DIR__ . '/../../../app/Helpers/EmployeeHelper.php');
require_once(__DIR__ . '/../../../app/Helpers/UserHelper.php');


// DB table to use
$table = 'ceylon_ot_approved';

// Table's primary key
$primaryKey = 'id';


// indexes
$columns = array(
    array( 'db' => '`u`.`id`', 'dt' => 'id', 'field' => 'id' ),
    array( 'db' => '`u`.`emp_id`', 'dt' => 'emp_id', 'field' => 'emp_id' ),
    array( 'db' => '`u`.`date`', 'dt' => 'date', 'field' => 'date' ),
    array( 'db' => '`u`.`ot_hours`', 'dt' => 'ot_hours', 'field' => 'ot_hours' ),
    array( 'db' => '`u`.`hour_rate`', 'dt' => 'hour_rate', 'field' => 'hour_rate' ),
    array( 'db' => '`u`.`ot`', 'dt' => 'ot', 'field' => 'ot' ),
    array( 'db' => '`dep`.`name`', 'dt' => 'department', 'field' => 'name' ),
    array( 'db' => '`emp`.`emp_name_with_initial`', 'dt' => 'emp_name_with_initial', 'field' => 'emp_name_with_initial', 'visible' => false ),
    array( 'db' => '`emp`.`calling_name`', 'dt' => 'calling_name', 'field' => 'calling_name', 'visible' => false ),
    array( 'db' => '`emp`.`emp_id`', 'dt' => 'employee_display', 'field' => 'emp_id',
        'formatter' => function($d, $row) {
            $employee = (object)[
                'emp_name_with_initial' => $row['emp_name_with_initial'],
                'calling_name' => $row['calling_name'],
                'emp_id' => $row['emp_id']
            ];
            return EmployeeHelper::getDisplayName($employee);
        }
    )
);

// SQL server connection information
require('../config.php');
require('../ssp.customized.class.php');

$sql_details = array(
	'user' => $db_username,
	'pass' => $db_password,
	'db'   => $db_name,
	'host' => $db_host
);

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * If you just want to use the basic configuration for DataTables with PHP
 * server-side, there is no need to edit below this line.
 */


$department = $_POST['department'];
$employee = $_POST['employee'];
$location = $_POST['location'];
$from_date = $_POST['from_date'];
$to_date = $_POST['to_date'];


$joinQuery = "FROM `ceylon_ot_approved` AS `u`
    LEFT JOIN `employees` AS `emp` ON `emp`.`emp_id` = `u`.`emp_id`
    LEFT JOIN `departments` AS `dep` ON `dep`.`id` = `u`.`department_id`";
	
	$extraWhere = "`u`.`status` != 3";

	if (!empty($department)) {
        $extraWhere .= " AND `emp`.`emp_department` = '$department'";
    }
    if (!empty($employee)) {
        $extraWhere .= " AND `emp`.`emp_id` = '$employee'";
    }
    if (!empty($location)) {
        $extraWhere .= " AND `emp`.`emp_location` = '$location'";
    }
    if (!empty($from_date) && !empty($to_date)) {
        $extraWhere .= " AND `u`.`date` BETWEEN '$from_date' AND '$to_date'";
    }

// new filter based on user access rights
$userId = UserHelper::getLoggedInUserId();

if ($userId) {
    $mysqli = new mysqli($db_host, $db_username, $db_password, $db_name);
    
    if ($mysqli->connect_error) {
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
    
    $accessibleEmployeeIds = UserHelper::getAccessibleEmployeeIds($userId, $mysqli);

	$companyIds = [];
    $companyQuery = "SELECT company_id FROM user_has_companies WHERE user_id = ?";
    $stmt = $mysqli->prepare($companyQuery);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $companyIds[] = $row['company_id'];
    }
    $stmt->close();
    
    if (!empty($companyIds)) {
        $companyIdsList = implode(',', array_map('intval', $companyIds));
        $extraWhere .= " AND `employees`.`emp_company` IN ($companyIdsList)";
    }


	if (!empty($accessibleEmployeeIds)) {
		$empIds = implode(',', array_map('intval', $accessibleEmployeeIds));
		$extraWhere .= " AND emp.emp_id IN ($empIds)";
	} else {
		$extraWhere .= " AND 1 = 0";
	}
	$mysqli->close();
}
// end of new filter

echo json_encode(
	SSP::simple( $_POST, $sql_details, $table, $primaryKey, $columns, $joinQuery, $extraWhere)
);
