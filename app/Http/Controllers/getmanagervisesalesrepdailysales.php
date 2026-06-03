<?php
require_once('../connection/db.php');
$newarray = array();

$salesManagerId = $_POST['salesManagerId'];
$today = date('Y-m-d');

$getEmployees = "SELECT `idtbl_employee`, `name` FROM `tbl_employee` WHERE `tbl_sales_manager_idtbl_sales_manager` = '$salesManagerId'";
$resultEmployees = $conn->query($getEmployees);

if ($resultEmployees->num_rows > 0) {
    while ($rowEmployee = $resultEmployees->fetch_assoc()) {
        $empId = $rowEmployee['idtbl_employee'];
        $empName = $rowEmployee['name'];

       $getdailytot = "SELECT 
                COALESCE(SUM(co.nettotal), 0) AS dailyTot
                FROM tbl_customer_order co
                WHERE co.status = '1'
                AND DATE(co.insertdatetime) = DATE(CURDATE())
                AND co.tbl_employee_idtbl_employee = '$empId'";
        $resultgetdailytot = $conn->query($getdailytot);
        $dailytotal = 0;

        if ($row = $resultgetdailytot->fetch_assoc()) {
            $dailytotal = $row['dailyTot'];
        }

        $sqlgetalltot = "SELECT 
        COALESCE(SUM(nettotal), 0) AS fullalltot
    FROM tbl_employee e
                    LEFT JOIN tbl_customer_order co 
                        ON co.tbl_employee_idtbl_employee = e.idtbl_employee
                        AND co.status = '1'
                    AND MONTH(co.date) = MONTH(CURDATE()) 
    AND YEAR(co.date) = YEAR(CURDATE())
                    WHERE e.idtbl_employee = '$empId'
                    GROUP BY e.idtbl_employee;";


        $resultgetalltot = $conn->query($sqlgetalltot);
        $fullalltot = 0;

        if ($row = $resultgetalltot->fetch_assoc()) {
            $fullalltot = $row['fullalltot'];
        }
        // get employee payed amount for the entire employee's sales total unpaid
        // $employeeTotalUnpaid = "SELECT SUM(`ud`.`nettotal`) AS 'allunpaidtot'
        //                 FROM `tbl_invoice` AS `u`
        //                 LEFT JOIN `tbl_customer_order` AS `ud` ON `u`.`tbl_customer_order_idtbl_customer_order` = `ud`.`idtbl_customer_order`
        //                 WHERE `u`.`status`='1' 
        //                 AND `u`.`paymentcomplete`='0'
        //                 AND `ud`.`tbl_employee_idtbl_employee`='$empId'
        //                 GROUP BY `ud`.`tbl_employee_idtbl_employee`";
        // $resultTotalUnpaid = $conn->query($employeeTotalUnpaid);
        // $allunpaidtot = 0;

        // if ($row = $resultTotalUnpaid->fetch_assoc()) {
        //     $allunpaidtot = $row['allunpaidtot'];
        // }

        // $sqlpayedamount = "SELECT COALESCE(SUM(`ue`.`payamount`), 0) AS 'totpayedamount'
        //         FROM `tbl_invoice` AS `u`
        //         LEFT JOIN `tbl_customer_order` AS `ud` ON `u`.`tbl_customer_order_idtbl_customer_order` = `ud`.`idtbl_customer_order`
        //         LEFT JOIN `tbl_invoice_payment_has_tbl_invoice` AS `ue` ON `ue`.`tbl_invoice_idtbl_invoice` = `u`.`idtbl_invoice`
        //         WHERE `u`.`status`='1' 
        //         AND `u`.`paymentcomplete`='0'
        //         -- AND MONTH(`u`.`date`) = MONTH(CURDATE()) 
        //         AND `ud`.`tbl_employee_idtbl_employee`='$empId'
        //         GROUP BY `ud`.`tbl_employee_idtbl_employee`";

        // $resultpayedamount = $conn->query($sqlpayedamount);
        // $fullpayedamount = 0; 

        // if ($row = $resultpayedamount->fetch_assoc()) {
        //     $fullpayedamount = $row['totpayedamount'];
        // }
        // $balance = $allunpaidtot - $fullpayedamount;

        // Get accepted returns for today for this employee
        $sqlAcceptedReturns = "SELECT COALESCE(SUM(total), 0) AS acceptedReturns
            FROM tbl_return
            WHERE status = '1'
            AND acceptance_status = '1'
            AND MONTH(returndate) = MONTH(CURDATE()) 
            AND YEAR(returndate) = YEAR(CURDATE())
            AND tbl_employee_idtbl_employee = '$empId'";
        $resultAcceptedReturns = $conn->query($sqlAcceptedReturns);
        $acceptedReturns = 0;
        if ($row = $resultAcceptedReturns->fetch_assoc()) {
            $acceptedReturns = $row['acceptedReturns'];
        }

        // total outstanding balance calculation

        $employeeTotalUnpaidQuery = "SELECT 
            COALESCE(
                SUM(
                    i.nettotal - COALESCE(p.total_payment, 0)
                ), 
            0) AS total_outstanding

        FROM tbl_customer_order AS co

        LEFT JOIN tbl_invoice AS i 
            ON i.tbl_customer_order_idtbl_customer_order = co.idtbl_customer_order

        LEFT JOIN (
            SELECT tbl_invoice_idtbl_invoice, 
                SUM(payamount) AS total_payment
            FROM tbl_invoice_payment_has_tbl_invoice
            GROUP BY tbl_invoice_idtbl_invoice
        ) p 
            ON p.tbl_invoice_idtbl_invoice = i.idtbl_invoice

        WHERE co.status = '1'
        AND i.status = '1'
        AND co.date BETWEEN '1999-01-01' AND CURDATE()
        AND co.delivered = '1'
        AND co.tbl_employee_idtbl_employee = '$empId'
        AND i.paymentcomplete = '0'
        ";

        $resultTotalUnpaid = $conn->query($employeeTotalUnpaidQuery);
        $employeeTotalUnpaid = 0;
        if ($row = $resultTotalUnpaid->fetch_assoc()) {
            $employeeTotalUnpaid = $row['total_outstanding'] !== null ? $row['total_outstanding'] : 0;
        }
        if ($fullalltot == null) {
            $fullalltot = 0;
        }
        if ($dailytotal == null) {
            $dailytotal = 0;
        }
        // if ($employeeTotalUnpaid == null) {
        //     $balance = 0;
        // } else {
        //     $balance = $employeeTotalUnpaid;
        // }

        $response = array(
            "empId" => $empId,
            "empName" => $empName,
            "fulltotal" => $fullalltot,
            "dailytotal" => $dailytotal,
            "outstandingtotal" => (float) $employeeTotalUnpaid,
            "acceptedReturns" => (float) $acceptedReturns,
            "date" => $today
        );

        $newarray[] = $response;
    }
} else {
    $newarray[] = array("error" => "No employees found");
}

echo json_encode($newarray);

?>