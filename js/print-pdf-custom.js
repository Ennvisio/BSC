var date = new Date();
var hours = date.getHours() > 12 ? date.getHours() - 12 : date.getHours();
var am_pm = date.getHours() >= 12 ? "pm" : "am";
hours = hours < 10 ? "0" + hours : hours;
var minutes = date.getMinutes() < 10 ? "0" + date.getMinutes() : date.getMinutes();
var seconds = date.getSeconds() < 10 ? "0" + date.getSeconds() : date.getSeconds();
ctime = hours + ":" + minutes + ":" + seconds + "" + am_pm;

var d = new Date();
var weekday = new Array(7);
weekday[0] = "Sunday";
weekday[1] = "Monday";
weekday[2] = "Tuesday";
weekday[3] = "Wednesday";
weekday[4] = "Thursday";
weekday[5] = "Friday";
weekday[6] = "Saturday";
var dayName = weekday[d.getDay()];

var cMonth = new Date().getMonth()+1;
var cMonth = cMonth < 10 ? '0'+cMonth : cMonth;


var cuDate = new Date().getDate() + '/'+ cMonth + '/' + new Date().getFullYear() +' '+ ctime;
var cuDateRev = new Date().getFullYear()+'/'+cMonth+'/'+new Date().getDate();

var pptitle = $(".pptitle").text();
var logo = $(".site-logo").attr("src");
var style = "{{url('/css/print-style.css')}}";

// Print_Users
function print_this() {  
    var mywindow = window.open('', 'PRINT');
    var is_chrome = Boolean(mywindow.chrome);

    mywindow.document.write('<html><head style="padding:0; margin:0 auto"><title>'+pptitle+'</title>');
    
    mywindow.document.write('<style>table{width: 100%;font-size:14px;border-collapse:collapse;margin-bottom:15px; zoom: 100% }table th{ text-transform: uppercase!important;border:1px solid #000;}table th,table td{padding:5px 10px;text-align:center;border:1px solid #000;}.footersign {text-align: center;font-size: 13px; position:relative; top:50px;width:98%;display: flex;font-weight:700;margin-top:25px; justify-content: space-between; align-item:center;} .footersign span{border-top:1px solid black;padding-top:5px;} table#example tbody tr td:last-child{display: none;} img { max-width: 100%; height: auto; }.action,.tdfile{display:none;} /*      print-header      */ .print-header{ position:relative;width:100%; margin:0 auto 5px;background:#fff;top:0;z-index:999; text-align:center; display:block;zoom:90%;} .print-header .title-wrap .line1,.print-header .title-wrap .line2,.print-header .title-wrap .line3,.print-header .title-wrap .line4{text-transform:uppercase;font-size:17px;font-weight:700; line-height:1.3;color:#000;letter-spacing:.5px;margin: 0 auto;}.print-header .title-wrap .line1,.print-header .title-wrap .line3{border-bottom:1px solid #000;line-height:1;display:inline-block}.print-header .title-wrap .line3{font-size:14px;margin:5px auto}.print-header .title-wrap .line3 span{display:inline-block;width:10px} .print-header .title-wrap .line4{font-size:15px;margin-bottom:15px} .print-header .vessel-info{display:none;text-align:left;padding-left:15px;font-size:14px}.print-header .vessel-info strong{text-transform:uppercase;font-weight:700}.print-header .vessel-info span{margin-right:15px}                     /* print header */  #order-print-header1 .od-title, #order-print-header1 .od-title .title-center{display:flex; justify-content:space-around;align-content:center;flex-flow:row wrap}#order-print-header1 .od-title .title-center{flex-flow:column;align-content:center;justify-content:center} #order-print-header1 .od-title .logo,#order-print-header1 .od-title .title-right{width:72px} img { max-width: 100%; height: auto; }   </style>');
    mywindow.document.write('</head><body>');

    mywindow.document.write(document.getElementById('order-print-header1').outerHTML); 
    mywindow.document.write(document.getElementById('example').outerHTML); 
         
    mywindow.document.write('</body></html>');

    if (is_chrome) {
        setTimeout(function () { // wait until all resources loaded 
            mywindow.document.close(); // necessary for IE >= 10
            mywindow.focus(); // necessary for IE >= 10*/
            mywindow.print();
            mywindow.close();
        }, 250);
    }
    else {
        mywindow.document.close(); // necessary for IE >= 10
        mywindow.focus(); // necessary for IE >= 10*/
        mywindow.print();
        mywindow.close();
    }

    return true;
}

// order_list / requisition list
function order_list() {  
    var mywindow = window.open('', 'PRINT');
    var is_chrome = Boolean(mywindow.chrome);

    mywindow.document.write('<html><head style="padding:0; margin:0 auto"><title>'+pptitle+'</title>');
    
    mywindow.document.write('<style> body{zoom: 100%; }    /* print header */  .print-header { position:relative;width:100%;margin:0 auto 10px; background:#fff;top:0;z-index:999; text-align:center;display:block;zoom:90%;} .print-header .title-wrap .line1,.print-header .title-wrap .line2,.print-header .title-wrap .line3,.print-header .title-wrap .line4{text-transform:uppercase;font-size:17px;font-weight:700;line-height:1.3;color:#000;letter-spacing:.5px;margin: 0 auto;}.print-header .title-wrap .line1,.print-header .title-wrap .line3{border-bottom:1px solid #000;line-height:1;display:inline-block} .print-header .title-wrap .line3{font-size:14px;margin:5px auto} .print-header .title-wrap .line3 span{display:inline-block;width:10px}.print-header .title-wrap .line4{font-size:15px;margin:10px auto 0px}      /* print header */  #order-print-header1 .od-title, #order-print-header1 .od-title .title-center{display:flex;justify-content:space-around;align-content:center;flex-flow:row wrap}#order-print-header1 .od-title .title-center{flex-flow:column;align-content:center;justify-content:center} #order-print-header1 .od-title .logo,#order-print-header1 .od-title .title-right{width:72px} img { max-width: 100%; height: auto; }  /* table css */  table{ width: 100%;font-size:14px;border-collapse:collapse;margin-bottom:15px;border :1px solid #000;} table th{ text-transform: uppercase!important;} table th,table td{padding:5px 10px;text-align:center;border:1px solid #000;} .action,.tdfile {display:none;} </style>');
    mywindow.document.write('</head><body>');

    mywindow.document.write(document.getElementById('order-print-header1').outerHTML); 
    mywindow.document.write(document.getElementById('example').outerHTML); 
         
    mywindow.document.write('</body></html>');

    if (is_chrome) {
        setTimeout(function () { // wait until all resources loaded 
            mywindow.document.close(); // necessary for IE >= 10
            mywindow.focus(); // necessary for IE >= 10*/
            mywindow.print();
            mywindow.close();
        }, 250);
    }
    else {
        mywindow.document.close(); // necessary for IE >= 10
        mywindow.focus(); // necessary for IE >= 10*/
        mywindow.print();
        mywindow.close();
    }

    return true;
}


// print_vehical_info
function print_vehical_info() {  
    var mywindow = window.open('', 'PRINT');
    var is_chrome = Boolean(mywindow.chrome);

    mywindow.document.write('<html><head style="padding:0; margin:0 auto"><title>'+pptitle+'</title>');
    
    mywindow.document.write('<style>table{width: 100%;font-size:14px;border-collapse:collapse;margin-bottom:15px;}table th{ text-transform: uppercase!important;}table th,table td{padding:2px 10px 2 0;border:0px solid #000;}.footersign {text-align: center;font-size: 13px; position:relative; top:50px;width:98%;display: flex;font-weight:700;margin-top:25px; justify-content: space-between; align-item:center;} .footersign span{border-top:1px solid black;padding-top:5px;} img { max-width: 100%; height: auto; }.action,.tdfile{display:none;}  .print-header{position:relative;width:100%;margin:0 auto;background:#fff;top:0;z-index:999;text-align:center;display:block;zoom:90%;}.print-header .title-wrap .line1,.print-header .title-wrap .line2,.print-header .title-wrap .line3,.print-header .title-wrap .line4{text-transform:uppercase;font-size:17px;font-weight:700;line-height:1.3;color:#000;letter-spacing:.5px;margin: 0 auto;}.print-header .title-wrap .line1,.print-header .title-wrap .line3{border-bottom:1px solid #000;line-height:1;display:inline-block}.print-header .title-wrap .line3{font-size:14px;margin:5px auto}.print-header .title-wrap .line3 span{display:inline-block;width:10px}.print-header .title-wrap .line4{font-size:15px;margin-bottom:15px}.p_lebel{width: 40%;}.p_dot{}.no-break h4{margin-bottom: 10px; } table { page-break-inside:auto } tr { page-break-inside:avoid; page-break-after:auto } thead { display:table-header-group } tfoot { display:table-footer-group }</style>');
    mywindow.document.write('</head><body>');

    mywindow.document.write(document.getElementById('order-print-header').outerHTML); 
    mywindow.document.write(document.getElementById('privew-wrapper').outerHTML); 
      
    mywindow.document.write('</body></html>');

    if (is_chrome) {
        setTimeout(function () { // wait until all resources loaded 
            mywindow.document.close(); // necessary for IE >= 10
            mywindow.focus(); // necessary for IE >= 10*/
            mywindow.print();
            mywindow.close();
        }, 250);
    }
    else {
        mywindow.document.close(); // necessary for IE >= 10
        mywindow.focus(); // necessary for IE >= 10*/
        mywindow.print();
        mywindow.close();
    }

    return true;
}


$(document).on('click','button.print-order', function(e){
    e.preventDefault();
    var tableid=$('div.card').find('table').attr('id');
    var req_date=$('input#Requisition_Date').val();
    var req_no=$('input[name="Requisition_No"]').val();
    var port_name=$('input[name="Port_Name"]').val();
    var vessel_name=$('select[name="Vessel_Name"]').children("option:selected").text();
   
    $('span.req_date').text(req_date);
    $('span.vessel_name').text(vessel_name);
    $('span.req_no').text(req_no);
    $('span.port').text(port_name);
    print_order(tableid);
})

$(document).on('click','.print-order-details', function(e){
    e.preventDefault();
    var tableid=$('div.card').find('table').attr('id');
    print_order(tableid);
})

// print_order
function print_order( tableid ) {  
    var mywindow = window.open('', 'PRINT');
    var is_chrome = Boolean(mywindow.chrome);

    mywindow.document.write('<html><head style="padding:0; margin:0 auto"><title>'+pptitle+'</title>');
    
    mywindow.document.write('<style>table{width: 100%;font-size:12px;border-collapse:collapse;margin-bottom:15px; }table th{ text-transform: uppercase!important;}table th,table td{font-size: 12px;padding:5px 10px;text-align:center;border:1px solid #000;} table#example1 tbody tr td:last-child{display: none;} img { max-width: 100%; height: auto; }.action,.tdfile{display:none;}  .print-header{position:relative;width:100%;margin:0 auto;background:#fff;top:0;z-index:999;text-align:center;display:block;}.print-header .title-wrap .line1,.print-header .title-wrap .line2,.print-header .title-wrap .line3,.print-header .title-wrap .line4x{text-transform:uppercase;font-size:13px!important;font-weight:700;line-height:1.3;color:#000;letter-spacing:.5px;margin: 0 auto;}.print-header .title-wrap .line1,.print-header .title-wrap .line3{border-bottom:1px solid #000;line-height:1;display:inline-block}.print-header .title-wrap .line3{font-size:12px;margin:5px auto}.print-header .title-wrap .line3 span{display:inline-block;width:10px}.print-header .title-wrap .line4x{font-size:15px;margin-bottom:15px}.print-header .vessel-info{text-align:left;padding-left:15px;font-size:14px} #order-print-header2 {text-transform:uppercase;font-size:12px;} #order-print-header2 strong{text-transform:uppercase;font-weight:700}.print-header .vessel-info span{margin-right:15px}table.office-use-table{width:calc(100% - 0px);margin:0 0 15px;box-sizing:border-box}table.office-use-table td{border:1px solid #000;font-size:14px;padding: 5px 0;}  table.office-use-table td.office_use {width:70px;text-transform:uppercase;padding:5px;color:#000;text-align:center;box-sizing:border-box;}table.office-use-table td.office_use_form{ calc( 100% - 70px );}table.office-use-table td p{font-size:14px;text-align:left;margin: 3px 5px;color:#000}table.office-use-table td p span{display:inline-block;border-bottom:1px dashed #000;width:125px}table.office-use-table td p span.checked_by{width:140px} table.office-use-table td p span.passed{width:124px} table.office-use-table td p span.invitation{width:227px} table.office-use-table td p span.approved_date{width:262px} table.office-use-table td p span.delevered_obdate{width:201px} table.office-use-table td p span.bil_rdate { width:85px}table.office-use-table td p span.pua_date {width:85px} table.office-use-table td p span.pfp_date{width:85px;} #order-print-header2 {display:flex;justify-content:space-between;}    .OrderDetailsTable tr td:nth-child(1){width:10px} .OrderDetailsTable{}.OrderDetailsTable tr td:nth-child(2){width:90px}    /* print header */  #order-print-header1 .od-title, #order-print-header1 .od-title .title-center{display:flex;justify-content:space-around;align-content:center;flex-flow:row wrap}#order-print-header1 .od-title .title-center{flex-flow:column;align-content:center;justify-content:center}#order-print-header1 .od-title .logo,#order-print-header1 .od-title .title-right{width:72px}     /* table css */ .item-name-print {display:inherit;}.item-name,.item-cat{display:none; }.item-name-td,.item-unit{ text-transform:uppercase;}.item-name-td {text-align:left;}    /*footer css */     #order-print-footer1{display:block;text-align:left}.footer-notes{padding:15px 0;font-size:12px}.footer-notes ul{list-style:none;padding-left:0;margin-bottom:0}.footer-notes ul li{padding-left:15px;font-size:12px}.signs-master-chief {display:flex;justify-content:center; flex-flow: row wrap; align-items:flex-end;padding:15px;border-bottom:0px solid #ddd;margin:-45px 15px 0px}.chief-officer,.master-chief{height:auto;display:flex; justify-content:center;flex-flow:column wrap;align-items:center;padding:15px 15px 0;font-size:14px}.seal,.sign{padding:0 15px}#order-print-footer2 {display:block}.signs-master-chief-admin,.signs-master-chief {display:flex;flex-flow: row wrap; justify-content:center;align-items: flex-end;} .signs-master-chief-admin >div,.signs-master-chief>div { width:30%;box-sizing:border-box; align-content: center;} .signer-name {text-transform:uppercase;display:inline-block; font-size:16px;margin:5px auto}.seal{height:105px; overflow:hidden;} .sign {max-height:95px;width: 100%; overflow:hidden;margin-top:15px; text-align: center;} .sign img{max-height:95px; max-width: 100%;height: auto;} </style>');
    mywindow.document.write('</head><body style="zoom:90%">');

    mywindow.document.write(document.getElementById('order-print-header1').outerHTML); 
    mywindow.document.write(document.getElementById('order-print-header2').outerHTML);  
    mywindow.document.write(document.getElementById('order-print-header3').outerHTML);
    mywindow.document.write(document.getElementById(tableid).outerHTML); 
    mywindow.document.write(document.getElementById('order-print-footer1').outerHTML);
    // mywindow.document.write(document.getElementById('order-print-footer2').outerHTML);
        
    mywindow.document.write('</body></html>');

    if (is_chrome) {
        setTimeout(function () { // wait until all resources loaded 
            mywindow.document.close(); // necessary for IE >= 10
            mywindow.focus(); // necessary for IE >= 10*/
            mywindow.print();
            mywindow.close();
        }, 500);
    }
    else {
        mywindow.document.close(); // necessary for IE >= 10
        mywindow.focus(); // necessary for IE >= 10*/
        mywindow.print();
        mywindow.close();
    }
    return true;
}

// print_cert
$(document).on('click','.btn-pcert', function(e){
    e.preventDefault();
    print_cert();
})

function print_cert() {  
    var mywindow = window.open('', 'PRINT');
    var title = $('#exampleModalLabel').text();
    var file_src = $('#fileShowImg').attr('src');
    var is_chrome = Boolean(mywindow.chrome);

    mywindow.document.write('<html><head style="padding:0; margin:0 auto"><title>'+title+'</title>');
    
    mywindow.document.write('<style> </style>');
    mywindow.document.write('</head><body>');

    mywindow.document.write('<h3 style="font-size:30px; text-align: center;">'+title+'</h3>');
    mywindow.document.write('<iframe id="fileShowImg" src="'+file_src+'" width="100%" height="842" frameborder="0"></iframe>');
    // mywindow.document.write(document.getElementById('certificate-photo').outerHTML); 
        
    mywindow.document.write('</body></html>');

    if (is_chrome) {
        setTimeout(function () { // wait until all resources loaded 
            mywindow.document.close(); // necessary for IE >= 10
            mywindow.focus(); // necessary for IE >= 10*/
            mywindow.print();
            mywindow.close();
        }, 500);
    }
    else {
        mywindow.document.close(); // necessary for IE >= 10
        mywindow.focus(); // necessary for IE >= 10*/
        mywindow.print();
        mywindow.close();
    }
    return true;
}


var logo64 = $(".logo-base64").html();
// Datatable + PDF function with customization
$(document).ready(function() {
    $('.line4').text($('.pptitle').text());

    var table = $('#example').DataTable( {
    aLengthMenu: [[10, 25, 50, 75, 100, -1], [10, 25, 50, 75, 100, "All"]],
    iDisplayLength: 10,
    buttons: [
    {
        extend: 'pdfHtml5',
        text: 'Pdf',
        title: "",
        titleAttr: 'PDF',
        extension: ".pdf",
        pageSize: 'A4',
        filename: pptitle,  
        customize: function (doc) {
            // console.log(doc.content);                
            //doc.content[0].table.widths = 
            // Array(doc.content[0].table.body[0].length + 1).join('*').split('');
            doc.content[0].table.widths = [12, 60, 120, 80, 80, 60, 60];


            doc.styles = {
                tableHeader: {
                    bold: true,
                    fontSize: 8,
                    color: 'black',
                    alignment: 'center'

                },
                defaultStyle: {
                    fontSize: 8,
                    color: 'black',
                    alignment: 'center'
                }
                
            }

            doc.defaultStyle.fontSize = 8;

            var objLayout = {};
            objLayout['hLineWidth'] = function(i) { return .5; };
            objLayout['vLineWidth'] = function(i) { return .5; };
            objLayout['hLineColor'] = function(i) { return '#333'; };
            objLayout['vLineColor'] = function(i) { return '#333'; };
            objLayout['paddingLeft'] = function(i) { return 3; };
            objLayout['paddingRight'] = function(i) { return 3; };
            doc.content[0].layout = objLayout;

                // margin: [left, top, right, bottom]
                // Header Section
                doc.content.splice(0, 0, {

                    columns: [
                    {
                        image: logo64,
                        alignment: 'left',
                        width: 35,
                        margin: [5, 5, 0, -5],
                    },
                    {
                        text: 'Bangladesh Shipping Corporation' + '\n' + 'Ship Repair Department',
                        bold: true,
                        margin: [25, 0, 0, 0],
                    },
                    {
                        text: '',
                        alignment: 'right',
                        width: 75
                    }
                    ],
                    fontSize: 12,
                    alignment: 'center',
                });

                // Title
                doc.content.splice(1, 0, {

                    columns: [
                    {
                        text: pptitle,
                        bold: true,
                        alignment: 'center',
                        fontSize: 11
                    }
                    ],
                });

                doc.content.splice(2, 0, {

                    columns: [
                    {
                        text: 'NO:'+cuDateRev,
                        alignment: 'left'
                    },
                    {
                        text: dayName+' '+cuDate,
                        alignment: 'right'
                    }
                    ],
                    margin: [0, 0, 0, 5],
                    fontSize: 8,
                });


                // Create a footer
                doc['footer']=(function(page, pages) {
                    return {
                        text: ['Page ', { text: page.toString() },  '/', { text: pages.toString() }],
                        alignment: 'center',
                        italics: true,
                        fontSize: 10,
                    }
                });


            },
            exportOptions: {
                columns: [0,1,2,3,4,5,6]
            }
        },
        ]
    } );



    table.buttons().container().appendTo( '.right-buttons' );

    // If data table empty
    var cell_empty = $("#example tbody > tr > td").html();
    if( cell_empty == "No data available in table" ){        
        table.buttons().disable();
        $('.print-p').addClass('disabled').attr("disabled", 'disabled');
    } else {        
        table.buttons().enable();
    }

    $("#example").parent("div").css({'overflow':'auto'})

} );