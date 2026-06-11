<?php $page_stitle = 'Report on Employee O.T. Hours - Multi Offset'; ?>
@extends('layouts.app')

@section('content')

    <main>

        <div class="page-header shadow">
             <div class="container-fluid d-none d-sm-block shadow">
                   @include('layouts.attendant&leave_nav_bar')
             </div>
             <div class="container-fluid">
                 <div class="page-header-content py-3 px-2">
                     <h1 class="page-header-title ">
                         <div class="page-header-icon"><i class="fa-light fa-calendar-pen"></i></div>
                         <span>Approved OT</span>
                     </h1>
                 </div>
             </div>
         </div>

        <div class="container-fluid mt-2 p-0 p-2">
            <div class="card">
                <div class="card-body p-0 p-2">
                     <div class="col-12">
                         <div class="center-block fix-width scroll-inner">
                            <div class="col-md-12">
                                <button class="btn btn-warning btn-sm filter-btn float-right px-3" type="button"
                                    data-toggle="offcanvas" data-target="#offcanvasRight"
                                    aria-controls="offcanvasRight"><i class="fas fa-filter mr-1"></i> Filter
                                    Options</button>
                            </div><br><br>
                             <div class="daily_table">
                                 <table class="table table-striped table-bordered table-sm small nowrap w-100" id="ot_report_dt">
                                     <thead>
                                         <tr id="dt_head">
                                         </tr>
                                     </thead>
                                     <tbody>
                                     </tbody>
                                 </table>
                             </div>
                             <div class="month_table">
                                 <table class="table table-striped table-bordered table-sm small nowrap w-100"
                                     id="ot_report_monthly_dt">
                                     <thead>
                                         <tr id="dt_head_month">
                                         </tr>
                                     </thead>
                                     <tbody>
                                     </tbody>
                                 </table>
                             </div>

                         </div>
                     </div>
                    {{ csrf_field() }}
                </div>
            </div>
            @include('layouts.filter_menu_offcanves')
        </div>

    </main>
    <!-- Modal Area End -->

@endsection

@section('script')

    <script>
        $(document).ready(function () {

            $('#attendant_menu_link').addClass('active');
            $('#attendant_menu_link_icon').addClass('active');
            $('#attendantmaster').addClass('navbtnactive');

            let company = $('#company');
            let department = $('#department');
            let employee = $('#employee');
            let location = $('#location');
            let type = $('#type');

          

            company.select2({
                placeholder: 'Select...',
                width: '100%',
                allowClear: true,
                ajax: {
                    url: '{{url("company_list_sel2")}}',
                    dataType: 'json',
                    data: function(params) {
                        return {
                            term: params.term || '',
                            page: params.page || 1
                        }
                    },
                    cache: true
                }
            });

            department.select2({
                placeholder: 'Select...',
                width: '100%',
                allowClear: true,
                ajax: {
                    url: '{{url("department_list_sel2")}}',
                    dataType: 'json',
                    data: function(params) {
                        return {
                            term: params.term || '',
                            page: params.page || 1,
                            company: company.val()
                        }
                    },
                    cache: true
                }
            });

            employee.select2({
                placeholder: 'Select...',
                width: '100%',
                allowClear: true,
                ajax: {
                    url: '{{url("employee_list_sel2")}}',
                    dataType: 'json',
                    data: function(params) {
                        return {
                            term: params.term || '',
                            page: params.page || 1,
                            company: company.val(),
                            department: department.val()
                        }
                    },
                    cache: true
                }
            });

            location.select2({
                placeholder: 'Select...',
                width: '100%',
                allowClear: true,
                ajax: {
                    url: '{{url("location_list_sel2")}}',
                    dataType: 'json',
                    data: function(params) {
                        return {
                            term: params.term || '',
                            page: params.page || 1
                        }
                    },
                    cache: true
                }
            });

            type.on('change',function(e) {
                let type_val = $(this).val();
                if(type_val == 'Daily'){
                    $('.div_month').css('display','none');
                    $('.div_date_range').css('display','block');
                    $('#month').val('');
                }else{
                    $('.div_month').css('display','block');
                    $('.div_date_range').css('display','none');
                    $('#from_date').val('');
                    $('#to_date').val('');
                }
            });
            $('.div_month').css('display','none');

           let temp_department = '';
            let temp_employee = '';
            let temp_location = '';
            let temp_from_date = '';
            let temp_to_date = '';
            let temp_type = 'Daily';
            let temp_month = '';
            load_dt(temp_department,temp_employee,temp_location,temp_from_date,temp_to_date,temp_type,temp_month);
            
            load_dt('', '', '', '', '');

            function load_dt(department, employee, location, from_date, to_date) {

                $('#dt_head').html(
                    '<th>EMP NO</th>' +
                    '<th>EMP NAME</th>' +
                    '<th>DATE</th>' +
                    '<th>DEPARTMENT</th>' +
                    '<th>OT HOURS</th>' +
                    '<th>HOUR RATE</th>' +
                    '<th>OT</th>'
                    // '<th>ACTION</th>'
                );

                $('#ot_report_dt').DataTable({
                    dom: "<'row'<'col-sm-4 mb-sm-0 mb-2'B><'col-sm-2'l><'col-sm-6'f>>" +
                        "<'row'<'col-sm-12'tr>>" +
                        "<'row'<'col-sm-5'i><'col-sm-7'p>>",
                    buttons: [
                        { extend: 'csv',   className: 'btn btn-success btn-sm', title: 'Approved OT', text: '<i class="fas fa-file-csv mr-2"></i> CSV' },
                        { extend: 'pdf',   className: 'btn btn-danger btn-sm',  title: 'Approved OT', text: '<i class="fas fa-file-pdf mr-2"></i> PDF', orientation: 'landscape', pageSize: 'legal' },
                        { extend: 'print', className: 'btn btn-primary btn-sm', title: 'Approved OT', text: '<i class="fas fa-print mr-2"></i> Print' }
                    ],
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: scripturl + '/CeylonOt/ceylon_ot_approved_list.php',
                        type: "POST",
                        data: { department, employee, location, from_date, to_date, _token: $('meta[name="csrf-token"]').attr('content') }
                    },
                    columns: [
                        { data: 'emp_id' },
                        { data: 'employee_display' },
                        { data: 'date' },
                        { data: 'department' },
                        { data: 'ot_hours' },
                        { data: 'hour_rate' },
                        { data: 'ot' }
                        // {
                        //     data: null,
                        //     className: 'text-right',
                        //     render: function (data, type, full) {
                        //         return '<a href="javascript:void(0)" class="delete_btn btn btn-danger btn-sm" data-id="' + full.id + '"><i class="far fa-trash-alt"></i></a>';
                        //     }
                        // }
                    ],
                    bDestroy: true,
                    order: [[2, 'desc']]
                });
            }

            $('#from_date').on('change', function() {
                let fromDate = $(this).val();
                $('#to_date').attr('min', fromDate); 
            });

            $('#to_date').on('change', function() {
                let toDate = $(this).val();
                $('#from_date').attr('max', toDate); 
            });

            $('#formFilter').on('submit', function(e) {
                e.preventDefault();
                load_dt(
                    $('#department').val(),
                    $('#employee').val(),
                    $('#location').val(),
                    $('#from_date').val(),
                    $('#to_date').val()
                );
                closeOffcanvasSmoothly();
            });

            $(document).on('click','.delete_btn',async function(e){

                e.preventDefault();
                let id = $(this).data('id');
                let btn = $(this);
                var r = await Otherconfirmation("You want to remove this ? ");

                if(r == true){

                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    });
                    $.ajax({
                        url: "{{url('/ceylon_ot_approved_delete')}}",
                        type: "POST",
                        data: {
                            'id': id
                        },
                        success: function (data) {
                              const actionObj = {
                                    icon: 'fas fa-trash-alt',
                                    title: '',
                                    message: 'Record Remove Successfully',
                                    url: '',
                                    target: '_blank',
                                    type: 'danger'
                                };
                                const actionJSON = JSON.stringify(actionObj, null, 2);
                                actionreload(actionJSON);

                        }

                    });

                }
            });

        });
    </script>

@endsection

