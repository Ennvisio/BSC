  $(document).ready(function(){
    $.ajaxSetup({
      headers:{
        'X_CSRF_TOKEN':$('meta[name="_token"]').attr('content')
      }
    });
    $('tbody').delegate('.pending','click',function(){
      var id=$(this).data('id');
      var status=$(this).attr('title').toLowerCase();
      var tr_id=$(this).parent('td.action').parent('tr').attr('id');
      var sibling_button_ttl=$(this).siblings('button').attr('title').toLowerCase();
      var url='/application/status/'+status;
      swal({
       title: 'Are you sure?',
       text: "Pending this application!",
       type: 'question',
       showCancelButton: true,
       confirmButtonColor: '#3085d6',
       cancelButtonColor: '#d33',
       confirmButtonText: 'Yes, Do it!',
       showLoaderOnConfirm: true,
       preConfirm: function() {
         return new Promise(function() {
          $("#LoadingImage").css('display','flex');
          $.ajax({
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            type:'get',
            url:url,
            data:{'id':id},
            success:function(data){
              swal('Done!','Application is changed to Pending successfully','success').then(function(){
                $('#'+tr_id).find('.status_name').replaceWith('<span class="text-warning status_name">'+data[0]+'</span>');
                $('#'+tr_id).find('.vr_no').html('');
                $('#'+tr_id).find('.sl_no').html('');
                if(sibling_button_ttl=='positive'){
                  $('#'+tr_id).find('.pending').replaceWith('<button class="btn btn-danger btn-sm negative" data-id="'+id+'" title="Negative"><i class="far fa-calendar-minus" aria-hidden="true"></i>'
                    +'</button>');
                }
                else if(sibling_button_ttl=='negative'){
                  $('#'+tr_id).find('.pending').replaceWith('<button class="btn btn-success btn-sm positive" data-id="'+id+'" title="Positive"><i class="far fa-calendar-plus" aria-hidden="true">'
                    +'</button>');
                }
                $("#LoadingImage").css('display','none');
            // location.reload();
            console.log(data);
          });
            }
          });

        });
       },
       allowOutsideClick: false
     });
    });
    $(document).on('click','.negative',function(){
      var id=$(this).data('id');
      var tr_id=$(this).parent('td.action').parent('tr').attr('id');
      var sibling_button_ttl=$(this).siblings('button').attr('title').toLowerCase();
      var url='/update/status/negative';
      swal({
       title: 'Are you sure?',
       text: "Negative this application!",
       type: 'question',
       showCancelButton: true,
       confirmButtonColor: '#3085d6',
       cancelButtonColor: '#d33',
       confirmButtonText: 'Yes, Do it!',
       showLoaderOnConfirm: true,
       preConfirm: function() {
         return new Promise(function() {
          $("#LoadingImage").css('display','flex');
          $.ajax({
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            type:'get',
            url:url,
            data:{'id':id},
            success:function(response){
              console.log(response);
              $.ajax({
                url: '/send-queued-sms/'+response[0],
                type: 'get',
                data: {
                },
                dataType: 'json',
                contentType: "application/json; charset=utf-8",
              })
              swal('Done!','Application is changed to Negative successfully','success').then(function(){
                $('#'+tr_id).find('.status_name').replaceWith('<span class="text-danger status_name">'+response[1]+'</span>');
                $('#'+tr_id).find('.vr_no').html('');
                $('#'+tr_id).find('.sl_no').html('');
                if(sibling_button_ttl=='positive'){
                  $('#'+tr_id).find('.negative').replaceWith('<button class="btn btn-warning btn-sm btn-edit" data-id="'+id+'" title="Pending"><i class="fas fa-spinner" aria-hidden="true"></i>'
                    +'</button>');
                }
                else if(sibling_button_ttl=='pending'){
                  $('#'+tr_id).find('.negative').replaceWith('<button class="btn btn-success btn-sm positive" data-id="'+id+'" title="Positive"><i class="far fa-calendar-plus" aria-hidden="true">'
                    +'</button>');
                }
                $("#LoadingImage").css('display','none');
              });
            }
          });
        });
       },
       allowOutsideClick: false
     }); 
    });
    var id=null;  var url=null; var tr_id='';var sibling_button_ttl='';
    $(document).on('click', '.positive', function(){
      id=$(this).data('id');
      tr_id=$(this).parent('td.action').parent('tr').attr('id');
      sibling_button_ttl=$(this).siblings('button').attr('title').toLowerCase();
      url='/update/status/positive';
      $('input#data_id').val(id);
    });
    $('#confirmPositive_Form').on('submit', function(e){
      e.preventDefault();
      var form=$('#confirmPositive_Form');
      var formData=form.serialize();
      var type='post';
      swal({
       title: 'Are you sure?',
       text: "Positive this application!",
       type: 'question',
       showCancelButton: true,
       confirmButtonColor: '#3085d6',
       cancelButtonColor: '#d33',
       confirmButtonText: 'Yes, Do it!',
       showLoaderOnConfirm: true,
       preConfirm: function() {
         return new Promise(function() {
          $.ajax({
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            type:type,
            url:url,
            data:new FormData($("#confirmPositive_Form")[0]),
            processData:false,
            contentType:false,
            success:function(response){
              console.log(response);
              $.ajax({
                url: '/send-queued-sms/'+response[0],
                type: 'get',
                data: {
                },
                dataType: 'json',
                contentType: "application/json; charset=utf-8",
              });
          // location.reload();
          swal('Done!','Application is changed to Positive successfully','success').then(function(){
            $("[data-dismiss=modal]").trigger({ type: "click" });
            $('#confirmPositive_Form')[0].reset();
            $('#'+tr_id).find('.status_name').replaceWith('<span class="text-success status_name">'+response[1]+'</span>');
            $('#'+tr_id).find('.vr_no').html(response[3]);
            $('#'+tr_id).find('.sl_no').html(response[2]);
            if(sibling_button_ttl=='pending'){
              $('#'+tr_id).find('.positive').replaceWith('<button class="btn btn-danger btn-sm negative" data-id="'+id+'" title="Negative"><i class="far fa-calendar-minus" aria-hidden="true"></i>'
                +'</button>');
            }
            else if(sibling_button_ttl=='negative'){
              $('#'+tr_id).find('.positive').replaceWith('<button class="btn btn-warning btn-sm pending" data-id="'+id+'" title="Pending"><i class="fas fa-spinner" aria-hidden="true">'
                +'</button>');
            }
          });
        },
        error:function(response){
          swal('Oops...', 'Something went wrong!' , 'error');
        }
      });
        });
       },
       allowOutsideClick: false
     }); 
    });


    $('.student-photo').on('click',function(){
      $('.modal-title').text('Photo');
      $('img.app-page-show').attr('hidden',true);
      var file =$(this).data('photo');
      $('#file_show').attr('src',file);
    })
    $('.student-app').on('click',function(){
      $('.modal-title').text('Application Copy');
      $('img.app-page-show').attr('hidden',false).css({'margin-bottom':'10px'});
      var page1 =$(this).data('page1');
      var page2 =$(this).data('page2');
      var page3 =$(this).data('page3');
      var page4 =$(this).data('page4');
      $('#file_show').attr('src',page1);

      $('#file_show_page2').attr('src',page2);
      $('#file_show_page3').attr('src',page3);
      $('#file_show_page4').attr('src',page4);
    })
    $('.student-cert').on('click',function(){
      $('.modal-title').text('Certificate Copy');
      $('img.app-page-show').attr('hidden',true);
      var file =$(this).data('cert');
      $('#file_show').attr('src',file);
    })
    $('.police-ver-cert').on('click',function(){
      $('.modal-title').text('Police Verification Certificate Copy');
      $('img.app-page-show').attr('hidden',true);
      var file =$(this).data('plover');
      $('#file_show').attr('src',file);
    })
    $(document).on('change','#district_name',function(){
      $('img.police-st-field-loader').css({'display':'block'});
      $('option.ps_value').remove();
      var district_id=$(this).children("option:selected").val();
      url='/get-upazilas/'+district_id;
      $.ajax({
        type:'get',
        url:url,
        data:{
          "id":district_id
        },
        success:function(response){
          $('img.police-st-field-loader').css({'display':'none'});
          $.each( response, function( key, value ) {
            $('option.ps_value_default').after("<option class='ps_value' value='"+value['id']+"'>"+value['name']+"</option>");
          });
        }
      });
    })
  });

