<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title></title>
  <style>
    @page {
        size: 100mm 260mm;
        margin: 5mm 5mm 5mm 5mm;
        font-family: Arial, sans-serif;
        font-size: 10px;
    }

    body {
        font-family: Arial, sans-serif;
        font-size: 10px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th, td {
        padding: 3px 4px;
    }

    .slip-wrapper {
        border: 1px solid #000;
        width: 100%;
        margin-bottom: 10mm;
        overflow: hidden;
    }

    .section-header {
        text-align: center;
        font-weight: bold;
        border-bottom: 1px solid #000;
        border-top: 1px solid #000;
        padding: 3px;
    }

    .row-line {
        width: 100%;
        border-collapse: collapse;
    }

    .row-line td {
        padding: 5px 4px;
    }

    .row-line .val {
        text-align: right;
    }

    .divider {
        border-top: 1px solid #000;
    }

    .company-block {
        text-align: center;
        padding: 4px 2px 2px 2px;
        border-bottom: 1px solid #000;
        line-height: 2;
    }

    .footer-row td {
        padding: 4px;
        padding-top: 10mm;
        vertical-align: bottom;
    }

    .double-underline {
        border-bottom: 3px double #000;
    }
  </style>
</head>
<body>

@php $check = 0 @endphp

@for ($slipcnt = 0; $slipcnt < count($emp_array); $slipcnt++)

    @if(isset($emp_array[$slipcnt]))

    @php $row = $emp_array[$slipcnt] @endphp

    @php
        $netbasicValue = ($row['BASIC'] + $row['BRA_I'] + $row['add_bra2']) - $row['NOPAY'];
        $totalearnValue = $row['OTHRS1'] + $row['OTHRS2'] + $row['ATTBONUS_W'] + $row['INCNTV_EMP'] + $row['INCNTV_DIR'];
        $totalearnValue += $row['ATTBONUS'];
        $totalearnValue += $row['add_other'];

        $netbasic = number_format((float)$netbasicValue, 2, '.', ',');
        $totalearn = number_format((float)$totalearnValue, 2, '.', ',');
        $grosspay = number_format((float)($netbasicValue + $totalearnValue), 2, '.', ',');
    @endphp

    <div class="slip-wrapper">

        {{-- 1. COMPANY DETAILS --}}
        <div class="company-block">
            <strong style="font-size:13px;">{{ $company_name }}</strong><br>
            <b>{{ $company_addr }}</b><br>
            <b>PAY ADVICE FOR THE MONTH OF : {{ $paymonth_name }}</b>
        </div>

        {{-- 2. EMPLOYEE DETAILS --}}
        <div class="section-header">EMPLOYEE DETAILS</div>
        <table class="row-line">
            <tr><td>NAME</td><td class="val">{{ $row['emp_first_name'] }}</td></tr>
            <tr><td>NIC NO</td><td class="val">{{ $row['emp_national_id'] }}</td></tr>
            <tr><td>EPF NO</td><td class="val">{{ $row['emp_epfno'] }}</td></tr>
            <tr><td>DEPARTMENT</td><td class="val">{{ $row['emp_department'] }}</td></tr>
            <tr><td>DESIGNATION</td><td class="val">{{ $row['emp_designation'] }}</td></tr>
        </table>

        {{-- 3. BANK DETAILS --}}
        <div class="section-header">BANK DETAILS</div>
        <table class="row-line">
            <tr><td>ACCOUNT NO</td><td class="val">{{ $row['bank_accno'] }}</td></tr>
            <tr><td>BANK NAME</td><td class="val">{{ $row['bank_name'] }}</td></tr>
            <tr><td>BRANCH</td><td class="val">{{ $row['bank_branch'] }}</td></tr>
        </table>

        {{-- 4. ATTENDANCE SUMMARY --}}
        <div class="section-header">ATTENDANCE SUMMARY</div>
        <table class="row-line">
            <tr>
                <td>WORKING DAYS</td>
                <td class="val">{{ number_format((float)$row['work_tot_days'], 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td>DAYS WORKED</td>
                <td class="val">{{ number_format((float)$row['work_week_days'], 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td>NO PAY DAYS</td>
                <td class="val">{{ number_format((float)$row['NOPAYCNT'], 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td>LATE ATTENDANCE H/M</td>
                <td class="val">00.00</td>
            </tr>
        </table>

        {{-- 5. SALARY BREAKDOWN --}}
        <div class="section-header">SALARY</div>
        <table class="row-line">
            <tr>
                <td>BASIC SALARY</td>
                <td class="val">{{ number_format((float)($row['BASIC'] + $row['BRA_I'] + $row['add_bra2']), 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td>NO PAY</td>
                <td class="val">{{ number_format((float)$row['NOPAY'], 2, '.', ',') }}</td>
            </tr>
            <tr class="divider">
                <td><b>NET BASIC</b></td>
                <td class="val"><b>{{ $netbasic }}</b></td>
            </tr>
        </table>

        {{-- RECEIVABLES --}}
        <div class="section-header">RECEIVABLES</div>
        <table class="row-line">
            <tr>
                <td>OVERTIME</td>
                <td style="text-align:center;">{{ number_format((float)$row['OTAMT1'], 2, '.', ',') }}</td>
                <td style="text-align:center;">{{ (float)$row['OTHRS1'] != 0 ? number_format((float)$row['OTHRS1'] / (float)$row['OTAMT1'], 2, '.', ',') : '00.00' }}</td>
                <td class="val">{{ number_format((float)$row['OTHRS1'], 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td>HOLIDAY</td>
                <td style="text-align:center;">{{ number_format((float)$row['OTAMT2'], 2, '.', ',') }}</td>
                <td style="text-align:center;">{{ (float)$row['OTHRS2'] != 0 ? number_format((float)$row['OTHRS2'] / (float)$row['OTAMT2'], 2, '.', ',') : '00.00' }}</td>
                <td class="val">{{ number_format((float)$row['OTHRS2'], 2, '.', ',') }}</td>
            </tr>
            @if((float)$row['ATTBONUS_W'] != 0)
            <tr>
                <td colspan="3">Living Exp. Allow.</td>
                <td class="val">{{ number_format((float)$row['ATTBONUS_W'], 2, '.', ',') }}</td>
            </tr>
            @endif
            @if((float)$row['ATTBONUS'] != 0)
            <tr>
                <td colspan="3">Attendance Allow.</td>
                <td class="val">{{ number_format((float)$row['ATTBONUS'], 2, '.', ',') }}</td>
            </tr>
            @endif
            @if((float)$row['INCNTV_EMP'] != 0)
            <tr>
                <td colspan="3">Perf. Based Incentive</td>
                <td class="val">{{ number_format((float)$row['INCNTV_EMP'], 2, '.', ',') }}</td>
            </tr>
            @endif
            @if((float)$row['INCNTV_DIR'] != 0)
            <tr>
                <td colspan="3">Other Allow.</td>
                <td class="val">{{ number_format((float)$row['INCNTV_DIR'], 2, '.', ',') }}</td>
            </tr>
            @endif
            <tr>
                <td colspan="3">Other Addition</td>
                <td class="val">{{ number_format((float)$row['add_other'], 2, '.', ',') }}</td>
            </tr>
            <tr class="divider">
                <td colspan="3"><b>TOTAL RECEIVABLES</b></td>
                <td class="val"><b>{{ $totalearn }}</b></td>
            </tr>
        </table>

        {{-- GROSS PAY --}}
        <table class="row-line">
            <tr style="border-top:1px solid #000;">
                <td><b>GROSS PAY</b></td>
                <td class="val"><b>{{ number_format((float)$row['tot_earn'], 2, '.', ',') }}</b></td>
            </tr>
        </table>

        {{-- DEDUCTIONS --}}
        <div class="section-header">DEDUCTIONS</div>
        <table class="row-line">
            <tr>
                <td>EPF 8%</td>
                <td class="val">{{ number_format((float)$row['EPF8'], 2, '.', ',') }}</td>
            </tr>
            @if((float)$row['ded_fund_1'] != 0)
            <tr>
                <td>Bank Charges</td>
                <td class="val">{{ number_format((float)$row['ded_fund_1'], 2, '.', ',') }}</td>
            </tr>
            @endif
            @if((float)$row['LOAN'] != 0)
            <tr>
                <td>LOAN</td>
                <td class="val">{{ number_format((float)$row['LOAN'], 2, '.', ',') }}</td>
            </tr>
            @endif
            @if((float)$row['PAYE'] != 0)
            <tr>
                <td>APIT</td>
                <td class="val">{{ number_format((float)$row['PAYE'], 2, '.', ',') }}</td>
            </tr>
            @endif
            @if((float)$row['sal_adv'] != 0)
            <tr>
                <td>ADVANCE</td>
                <td class="val">{{ number_format((float)$row['sal_adv'], 2, '.', ',') }}</td>
            </tr>
            @endif
            @if((float)$row['ded_IOU'] != 0)
            <tr>
                <td>Late Deduct.</td>
                <td class="val">{{ number_format((float)$row['ded_IOU'], 2, '.', ',') }}</td>
            </tr>
            @endif
            <tr>
                <td>Other Deductions</td>
                <td class="val">{{ number_format((float)$row['ded_other'], 2, '.', ',') }}</td>
            </tr>
            <tr class="divider">
                <td><b>TOTAL DEDUCTIONS</b></td>
                <td class="val"><b>{{ number_format((float)$row['tot_ded'], 2, '.', ',') }}</b></td>
            </tr>
        </table>

        {{-- NET SALARY --}}
        <table class="row-line">
            <tr style="border-top:2px solid #000;">
                <td><b>NET SALARY</b></td>
                <td class="val"><b><span>{{ number_format((float)$row['NETSAL'], 2, '.', ',') }}</span></b></td>
            </tr>
        </table>

        {{-- 6. EMPLOYER CONTRIBUTION --}}
        <div class="section-header">EMPLOYER CONTRIBUTION</div>
        <table class="row-line">
            <tr>
                <td>EPF 12%</td>
                <td class="val">{{ number_format((float)$row['EPF12'], 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td>ETF 3%</td>
                <td class="val">{{ number_format((float)$row['ETF3'], 2, '.', ',') }}</td>
            </tr>
        </table>

        {{-- 7. FOOTER --}}
        <table class="row-line footer-row" style="border-top:1px solid #000; margin-top:4px;">
            <tr>
                <td style="font-size:9px;">Printed On : {{ \Carbon\Carbon::now('Asia/Colombo')->format('d/m/Y H:i:s') }}</td>
                <td class="val" style="text-align:center;">
                    .......................................
                    <br>EMPLOYEE'S SIGNATURE
                </td>
            </tr>
        </table>

    </div>

    @endif

    @php $check++ @endphp

@endfor

</body>
</html>