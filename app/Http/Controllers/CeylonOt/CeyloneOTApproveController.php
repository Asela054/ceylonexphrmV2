<?php

namespace App\Http\Controllers\CeylonOt;

use App\EmployeeTermPayment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\OTApproveController;

class CeyloneOTApproveController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $permission = $user->can('ot-approve');
        if (!$permission) {
            return response()->json(['error' => 'UnAuthorized'], 401);
        }

        $remunerations=DB::table('remunerations')->select('*')->where('remuneration_type', 'Addition')->get();
        return view('CeylonOt.OTApprove', compact('remunerations'));
    }

    public function generateot(Request $request)
    {
        $user = Auth::user();
        $permission = $user->can('ot-approve');
        if (!$permission) {
            return response()->json(['error' => 'UnAuthorized'], 401);
        }

        $company    = $request->get('company');
        $department = $request->get('department');
        $employee   = $request->get('employee');
        $from_date  = $request->get('from_date');
        $to_date    = $request->get('to_date');

        $query = DB::table('employees')
            ->select(
                'employees.id as emp_auto_id',
                'employees.emp_id',
                'employees.emp_name_with_initial',
                'employees.emp_gender',
                'employees.emp_department',
                'departments.name as department_name'
            )
            ->leftJoin('departments', 'employees.emp_department', '=', 'departments.id')
            ->where('employees.deleted', 0)
            ->where('employees.is_resigned', 0);

        if ($employee != '') {
            $query->where('employees.emp_id', $employee);
        }
        if ($company != '') {
            $query->where('employees.emp_company', $company);
        }
        if ($department != '') {
            $query->where('employees.emp_department', $department);
        }

        // Only include employees who have attendance in the date range
        $query->whereExists(function ($sub) use ($from_date, $to_date) {
            $sub->select(DB::raw(1))
                ->from('attendances')
                ->whereColumn('attendances.uid', 'employees.emp_id')
                ->whereBetween('attendances.date', [$from_date, $to_date]);
        });

        $query->groupBy(
            'employees.id',
            'employees.emp_id',
            'employees.emp_name_with_initial',
            'employees.emp_gender',
            'employees.emp_department',
            'departments.name'
        );

        $results = $query->get();

        $otController = new OTApproveController();
        $data = [];

        foreach ($results as $record) {
            // Get all attendance dates for this employee in range
            $attendanceDates = DB::table('attendances')
                ->where('uid', $record->emp_id)
                ->whereBetween('date', [$from_date, $to_date])
                ->select('date',
                    DB::raw('MIN(timestamp) as first_checkin'),
                    DB::raw('MAX(timestamp) as lasttimestamp')
                )
                ->groupBy('date')
                ->get();

            $overallTotal = 0;
            $dateCount    = $attendanceDates->count();
            $totalCount    = $dateCount;

            foreach ($attendanceDates as $attDate) {
                $date    = $attDate->date;
                $dayOfWeek = \Carbon\Carbon::parse($date)->dayOfWeek; // 0=Sun, 6=Sat

                // Determine hour rate
                // Check emp_production_allocation for this date
                $allocation = DB::table('emp_production_allocation')
                    ->join('departments', 'emp_production_allocation.department_id', '=', 'departments.id')
                    ->where('emp_production_allocation.emp_id', $record->emp_id)
                    ->where('emp_production_allocation.date', $date)
                    ->where('emp_production_allocation.status', 1)
                    ->select('departments.name as dept_name')
                    ->first();

                if ($allocation) {
                    $deptName = $allocation->dept_name;
                } else {
                    $deptName = $record->department_name;
                }

                // Sunday=0, Monday=1 => 270/250; Tue-Sat => 180/165
                $isSundayOrMonday = in_array($dayOfWeek, [0, 1]);

                if ($deptName === 'Factory') {
                    $hourRate = $isSundayOrMonday ? 270 : 180;
                } elseif ($deptName === 'Packing') {
                    $hourRate = $isSundayOrMonday ? 250 : 165;
                } else {
                    $hourRate = 0;
                }

                // Get OT hours
                $resolved     = $this->resolveShiftAndOtHours($record->emp_id, $date, $attDate, $record->emp_department);
                $ot_hours     = $resolved['ot_hours'];

                $overallTotal += $hourRate * $ot_hours;
            }

            $data[] = [
                'emp_auto_id'           => $record->emp_auto_id,
                'emp_id'                => $record->emp_id,
                'emp_name_with_initial' => $record->emp_name_with_initial,
                'department_name'       => $record->department_name,
                'date_count'            => $dateCount,
                'overall_total'         => round($overallTotal, 2),
            ];
        }

        return response()->json([
            'data'            => $data,
            'recordsTotal'    => count($data),
            'recordsFiltered' => count($data),
        ]);
    }


    public function approveot(Request $request)
    {
        $permission = \Auth::user()->can('ot-approve');
        if (!$permission) {
            abort(403);
        }

        $dataarry       = $request->input('dataarry');
        $remunitiontype = $request->input('remunitiontype');
        $from_date      = $request->input('from_date');
        $to_date        = $request->input('to_date');

        $current_date_time = Carbon::now()->toDateTimeString();
        $errors = [];

        foreach ($dataarry as $row) {

            $empid         = $row['empid'];
            $empname       = $row['emp_name'];
            $overall_total = $row['overall_total'];
            $autoid        = $row['emp_auto_id'];

            // Save day-by-day to ceylon_ot_approved
            $dailyDetails = $this->getDailyOtDetails($empid, $from_date, $to_date);

            foreach ($dailyDetails as $detail) {
                $exists = DB::table('ceylon_ot_approved')
                    ->where('emp_id', $empid)
                    ->whereDate('date', $detail['date'])
                    ->where('department_id', $detail['department_id'])
                    ->first();

                if ($exists) {
                    DB::table('ceylon_ot_approved')
                        ->where('id', $exists->id)
                        ->update([
                            'ot_hours'   => $detail['ot_hours'],
                            'hour_rate'  => $detail['hour_rate'],
                            'ot'         => $detail['ot'],
                            'status'     => 1,
                            'updated_by' => Auth::id(),
                            'updated_at' => $current_date_time,
                        ]);
                } else {
                    DB::table('ceylon_ot_approved')->insert([
                        'emp_id'        => $empid,
                        'department_id' => $detail['department_id'],
                        'date'          => $detail['date'],
                        'ot_hours'      => $detail['ot_hours'],
                        'hour_rate'     => $detail['hour_rate'],
                        'ot'            => $detail['ot'],
                        'status'        => 1,
                        'created_by'    => Auth::id(),
                        'created_at'    => $current_date_time,
                        'updated_at'    => $current_date_time,
                    ]);
                }
            }

            //Save overall_total to employee_term_payments
            $profiles = DB::table('payroll_profiles')
                ->join('payroll_process_types', 'payroll_profiles.payroll_process_type_id', '=', 'payroll_process_types.id')
                ->where('payroll_profiles.emp_id', $autoid)
                ->select('payroll_profiles.id as payroll_profile_id')
                ->first();

            if (!$profiles) {
                $errors[] = "No payroll profile found for employee: {$empname}";
                continue;
            }

            $paysliplast = DB::table('employee_payslips')
                ->select('emp_payslip_no')
                ->where('payroll_profile_id', $profiles->payroll_profile_id)
                ->where('payslip_cancel', 0)
                ->orderBy('id', 'desc')
                ->first();

            $newpaylispno = $paysliplast ? ($paysliplast->emp_payslip_no + 1) : 1;

            if ($overall_total != 0) {

                $termpaymentcheck = DB::table('employee_term_payments')
                    ->select('id')
                    ->where('payroll_profile_id', $profiles->payroll_profile_id)
                    ->where('emp_payslip_no', $newpaylispno)
                    ->where('remuneration_id', $remunitiontype)
                    ->first();

                if ($termpaymentcheck) {
                    DB::table('employee_term_payments')
                        ->where('id', $termpaymentcheck->id)
                        ->update([
                            'payment_amount' => $overall_total,
                            'payment_cancel' => '0',
                            'updated_by'     => Auth::id(),
                            'updated_at'     => $current_date_time,
                        ]);
                } else {
                    $termpayment                     = new EmployeeTermPayment();
                    $termpayment->remuneration_id    = $remunitiontype;
                    $termpayment->payroll_profile_id = $profiles->payroll_profile_id;
                    $termpayment->emp_payslip_no     = $newpaylispno;
                    $termpayment->payment_amount     = $overall_total;
                    $termpayment->payment_cancel     = 0;
                    $termpayment->created_by         = Auth::id();
                    $termpayment->created_at         = $current_date_time;
                    $termpayment->save();
                }
            }
        }

        if (!empty($errors)) {
            return response()->json([
                'success' => 'OT Data approved with some issues.',
                'errors'  => $errors,
            ]);
        }

        return response()->json(['success' => 'OT Data is successfully Approved']);
    }

    public function getOtDetails(Request $request)
    {
        $permission = \Auth::user()->can('ot-approve');
        if (!$permission) {
            return response()->json(['error' => 'UnAuthorized'], 401);
        }

        $emp_id    = $request->input('emp_id');
        $from_date = $request->input('from_date');
        $to_date   = $request->input('to_date');

        $employee = DB::table('employees')
            ->leftJoin('departments', 'employees.emp_department', '=', 'departments.id')
            ->where('employees.emp_id', $emp_id)
            ->select('employees.emp_name_with_initial', 'employees.emp_gender', 'employees.emp_department', 'departments.name as department_name')
            ->first();

        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        $attendanceDates = DB::table('attendances')
            ->where('uid', $emp_id)
            ->whereBetween('date', [$from_date, $to_date])
            ->select('date',
                DB::raw('MIN(timestamp) as first_checkin'),
                DB::raw('MAX(timestamp) as lasttimestamp')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $dates      = [];
        $totalOT    = 0;

        foreach ($attendanceDates as $attDate) {
            $date      = $attDate->date;
            $dayOfWeek = \Carbon\Carbon::parse($date)->dayOfWeek;

            // Determine hour rate
            $allocation = DB::table('emp_production_allocation')
                ->join('departments', 'emp_production_allocation.department_id', '=', 'departments.id')
                ->where('emp_production_allocation.emp_id', $emp_id)
                ->where('emp_production_allocation.date', $date)
                ->where('emp_production_allocation.status', 1)
                ->select('departments.name as dept_name')
                ->first();

            if ($allocation) {
                $deptName = $allocation->dept_name;
            } else {
                $deptName = $employee->department_name;
            }

            $isSundayOrMonday = in_array($dayOfWeek, [0, 1]);

            if ($deptName === 'Factory') {
                $hourRate = $isSundayOrMonday ? 270 : 180;
            } elseif ($deptName === 'Packing') {
                $hourRate = $isSundayOrMonday ? 250 : 165;
            } else {
                $hourRate = 0;
            }

            // --- Get ot info ---
            $resolved = $this->resolveShiftAndOtHours($emp_id, $date, $attDate, $employee->emp_department);
            $ot_hours = $resolved['ot_hours'];

            $dayOT = round($hourRate * $ot_hours, 2);
            $totalOT += $dayOT;

            $dates[] = [
                'date'            => $date,
                'department_name' => $deptName,
                'ot_hours'        => $ot_hours,
                'hour_rate'       => $hourRate,
                'incentive'       => $dayOT,
            ];
        }

        return response()->json([
            'emp_name'        => $employee->emp_name_with_initial,
            'total_incentive' => round($totalOT, 2),
            'dates'           => $dates,
        ]);
    }

    private function getDailyOtDetails(string $emp_id, string $from_date, string $to_date): array
    {
        $employee = DB::table('employees')
            ->leftJoin('departments', 'employees.emp_department', '=', 'departments.id')
            ->where('employees.emp_id', $emp_id)
            ->select('employees.emp_department', 'departments.name as department_name', 'departments.id as department_id')
            ->first();

        if (!$employee) {
            return [];
        }

        $attendanceDates = DB::table('attendances')
            ->where('uid', $emp_id)
            ->whereBetween('date', [$from_date, $to_date])
            ->select('date',
                DB::raw('MIN(timestamp) as first_checkin'),
                DB::raw('MAX(timestamp) as lasttimestamp')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $details = [];

        foreach ($attendanceDates as $attDate) {
            $date      = $attDate->date;
            $dayOfWeek = Carbon::parse($date)->dayOfWeek;

            $allocation = DB::table('emp_production_allocation')
                ->join('departments', 'emp_production_allocation.department_id', '=', 'departments.id')
                ->where('emp_production_allocation.emp_id', $emp_id)
                ->where('emp_production_allocation.date', $date)
                ->where('emp_production_allocation.status', 1)
                ->select('departments.name as dept_name', 'departments.id as dept_id')
                ->first();

            if ($allocation) {
                $deptName = $allocation->dept_name;
                $deptId   = $allocation->dept_id;
            } else {
                $deptName = $employee->department_name;
                $deptId   = $employee->department_id;
            }

            $isSundayOrMonday = in_array($dayOfWeek, [0, 1]);

            if ($deptName === 'Factory') {
                $hourRate = $isSundayOrMonday ? 270 : 180;
            } elseif ($deptName === 'Packing') {
                $hourRate = $isSundayOrMonday ? 250 : 165;
            } else {
                $hourRate = 0;
            }

            $resolved = $this->resolveShiftAndOtHours($emp_id, $date, $attDate, $employee->emp_department);
            $ot_hours = $resolved['ot_hours'];
            $dayOT    = round($hourRate * $ot_hours, 2);

            $details[] = [
                'date'          => $date,
                'department_id' => $deptId,
                'ot_hours'      => $ot_hours,
                'hour_rate'     => $hourRate,
                'ot'            => $dayOT,
            ];
        }

        return $details;
    }

    private function resolveShiftAndOtHours(string $emp_id, string $date, object $attDate, int $emp_department): array
    {
        $shift_detail = DB::table('employeeshiftdetails')
            ->join('shift_types', 'employeeshiftdetails.shift_id', '=', 'shift_types.id')
            ->select('employeeshiftdetails.*', 'shift_types.onduty_time', 'shift_types.offduty_time', 'shift_types.id as shiftid')
            ->where('employeeshiftdetails.emp_id', $emp_id)
            ->whereDate('employeeshiftdetails.date_from', '<=', $date)
            ->whereDate('employeeshiftdetails.until_time', '>=', $date)
            ->first();

        if ($shift_detail) {
            $on_duty_time     = $shift_detail->onduty_time;
            $off_duty_time    = $shift_detail->offduty_time;
            $emp_shift_id     = $shift_detail->shiftid;
            $shift_until_time = $shift_detail->until_time;
        } else {
            $roster_detail = DB::table('employee_roster_details')
                ->join('shift_types', 'employee_roster_details.shift_id', '=', 'shift_types.id')
                ->select('employee_roster_details.*', 'shift_types.onduty_time', 'shift_types.offduty_time', 'shift_types.id as shiftid')
                ->where('employee_roster_details.emp_id', $emp_id)
                ->where('employee_roster_details.work_date', $date)
                ->first();

            if ($roster_detail) {
                $on_duty_time     = $roster_detail->onduty_time;
                $off_duty_time    = $roster_detail->offduty_time;
                $emp_shift_id     = $roster_detail->shiftid;
                $shift_until_time = null;
            } else {
                $empShift = DB::table('employees')
                    ->leftJoin('shift_types', 'employees.emp_shift', '=', 'shift_types.id')
                    ->where('employees.emp_id', $emp_id)
                    ->select('shift_types.onduty_time', 'shift_types.offduty_time', 'employees.emp_shift as shift_id')
                    ->first();

                $on_duty_time     = $empShift->onduty_time  ?? null;
                $off_duty_time    = $empShift->offduty_time ?? null;
                $emp_shift_id     = $empShift->shift_id     ?? null;
                $shift_until_time = null;
            }
        }

        $ot_result = (new \App\Attendance)->get_ot_hours_by_date(
            $emp_id,
            $attDate->lasttimestamp,
            $attDate->first_checkin,
            $date,
            $on_duty_time,
            $off_duty_time,
            $emp_department,
            $emp_shift_id,
            $shift_until_time
        );

        $ot_hours = 0;
        if (!empty($ot_result['ot_breakdown'])) {
            foreach ($ot_result['ot_breakdown'] as $breakdown) {
                $ot_hours += floatval($breakdown['hours'] ?? 0);
            }
        }

        return ['ot_hours' => $ot_hours, 'ot_result' => $ot_result];
    }


}
