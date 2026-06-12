<?php
namespace App\Http\Controllers\CeylonOt;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use Illuminate\Support\Facades\DB;

class CeyloneApprovedOTController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if (!$user->can('ot-approve')) {
            return response()->json(['error' => 'UnAuthorized'], 401);
        }
        return view('CeylonOt.ApprovedOT');
    }

    // public function list(Request $request)
    // {
    //     $user = Auth::user();
    //     if (!$user->can('ot-approve')) {
    //         return response()->json(['error' => 'UnAuthorized'], 401);
    //     }

    //     $query = DB::table('ceylon_ot_approved as u')
    //         ->join('employees as emp', 'emp.emp_id', '=', 'u.emp_id')
    //         ->join('departments as dep', 'dep.id', '=', 'u.department_id')
    //         ->select(
    //             'u.id',
    //             'u.emp_id',
    //             'u.date',
    //             'u.ot_hours',
    //             'u.hour_rate',
    //             'u.ot',
    //             'emp.emp_name_with_initial',
    //             'emp.calling_name',
    //             'dep.name as department'
    //         )
    //         ->where('u.status', '!=', 3);

    //     if ($request->filled('department')) {
    //         $query->where('emp.emp_department', $request->department);
    //     }
    //     if ($request->filled('employee')) {
    //         $query->where('emp.emp_id', $request->employee);
    //     }
    //     if ($request->filled('location')) {
    //         $query->where('emp.emp_location', $request->location);
    //     }
    //     if ($request->filled('from_date') && $request->filled('to_date')) {
    //         $query->whereBetween('u.date', [$request->from_date, $request->to_date]);
    //     }

    //     $data = $query->get()->map(function ($row) {
    //         $row->employee_display = $row->calling_name
    //             ? $row->calling_name . ' (' . $row->emp_id . ')'
    //             : $row->emp_name_with_initial . ' (' . $row->emp_id . ')';
    //         return $row;
    //     });

    //     return response()->json(['data' => $data]);
    // }

    public function ceylon_ot_approved_delete(Request $request)
    {
        $user = Auth::user();
        if (!$user->can('ot-approve')) {
            return response()->json(['error' => 'UnAuthorized'], 401);
        }

        DB::table('ceylon_ot_approved')
            ->where('id', $request->id)
            ->update(['status' => 3, 'updated_by' => $user->id]);

        return response()->json(['success' => true]);
    }
}