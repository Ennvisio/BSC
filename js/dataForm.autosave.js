$.ajaxSetup({
  headers:{
    'X_CSRF_TOKEN':$('meta[name="_token"]').attr('content')
  }
});
$(document).ready(function() {
  var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');
  function readURL(input) {
    imgId = '#prev_'+ $(input).attr('id');
    if (input.files && (input.files[0].size / 1024 / 1024) < 0.25) {
      var reader = new FileReader();
      reader.onload = function (e) {
        $(imgId).attr('src', e.target.result);
        $(imgId).attr('hidden',false);
        $(imgId).css({'display':'inline-block','height':'auto','padding':'10px 0px','max-width':'70%','width': 'auto','max-height': '200px'});
        $('img.app_page').css({'height':'220px'});
      }
      reader.readAsDataURL(input.files[0]);
      $(input).siblings('div.err_msg').attr('hidden',true);
    }
    else{
      $(input).val('');  
      $(imgId).attr('hidden',true);
      $(input).siblings('div .err_msg').find('span').text('File size exceeds 300 KB.Please reduce file size less than 300 kb');
      $(input).siblings('div .err_msg').attr('hidden',false);
    }
  }
  $("form.form input[type='file']").change(function(){            
    readURL(this);
    $(this).on('click',function(){
      imgId = '#prev_'+ $(this).attr('id');
      $(imgId).attr('hidden',true);
    })
  });

  var table = $('#example').DataTable();
  var orderTable = $('#example1').DataTable();
  var id=null; var tr_id=null;  var tr_sl=null;
  $(document).on('submit','#survey_add_form',function(event){
    event.preventDefault();
    $("#survey_add_form .form_error").css('display','none');
    $("#survey_add_form .form_error p").remove();
    $('body').addClass("loading");
    var form=$('#survey_add_form');
    var formData=form.serialize();
    var url='/survey/store';
    var type='post';
    $.ajax({
      type:type,
      url:url,
      data:new FormData($("#survey_add_form")[0]),
      processData:false,
      contentType:false,
      success:function(response)
      {
        toastr.success( 'New Survey Created Successfully!','Well Done!')
        var  idx= table.rows().count();
        idx++;
        var rowNode = table
        .row.add( ['<b class="serial">'+idx+'</b>', response[1]['serial'], response[1]['name'],response[1]['society_name'],response[1]['survey_date'],response[1]['survey_exp_date'],response[1]['vessel']['name'], 
          '<div class="action"><button class="btn btn-info mr-1 edit-survey" data-id="'+response[1]['id']+'" data-toggle="modal" data-target="#edit_template_modal"><i class="fas fa-edit"></i></button>'+
          '<button class="btn btn-danger delete-survey" data-id="'+response[1]['id']+'" data-toggle="modal" data-target="#delete_template_modal"><i class="fas fa-trash-alt"></i></button></div>'] )
        .order([0, 'dsc']).draw()
        .node().id = 'survey-'+response[1]['id'];
        $( rowNode )
        .css( 'color', 'green' )
        .animate( { color: 'red' } );
        swal('Congratulation!',response[0],'success').then(function() {
          $('#survey_add_form')[0].reset();
          $("[data-dismiss=modal]").trigger({ type: "click" });
          $("#survey_add_form .form_error").css('display','none');
        });
      },
      error: function(errors)
      {
        console.log(errors.responseJSON.errors);
        toastr.warning("Error! Check Your Form Information Please.");
        $.each(errors.responseJSON.errors, function(key, value){
          $('#survey_add_form .form_error').show();
          $('#survey_add_form .form_error').append('<p style="margin-bottom:5px;">'+value+'</p>');
          $('html, body').stop().animate({
            scrollTop: $('.alert-danger').offset().top - 150
          }, 500); 
        });
      }
    });
  }); 
  $(document).on('click','.edit-survey',function(e){
    e.preventDefault();
    var id = $(this).data('id');
    var url='/survey/'+id;
    var type='get';
    tr = $(this).parent().parent();
    tr_id=tr.attr('id');
    tr_sl = $('#'+tr_id +" .serial").text();
    $.ajax({
      type:type,
      url:url,
      data:{},
      processData:false,
      contentType:false,
      success:function(res)
      {
        $( "option.vessel_opt" ).each(function() {
          if($(this).val() == res['vessel']['id']){
            $(this).attr('selected','true');
          };
        });
        $('#survey_edit_form .Survey_Name').val(res['name'])
        $('#survey_edit_form .Survey_Society').val(res['society_name'])
        $('#survey_edit_form .Survey_Date').val(res['survey_date'])
        $('#survey_edit_form .Survey_Expire_Date').val(res['survey_exp_date'])
        $('#survey_edit_form .Survey_Id').val(res['id'])
      },
      error: function(errors)
      {
        toastr.warning("Error! Something went wrong.");
      }
    });
  })
  $(document).on('submit','#survey_edit_form',function(event){
    event.preventDefault();
    $("#survey_edit_form .form_error").css('display','none');
    $("#survey_edit_form .form_error p").remove();
    $('body').addClass("loading");
    var form=$('#survey_edit_form');
    var formData=form.serialize();
    var url='/survey/update';
    var type='post';
    $.ajax({
      type:type,
      url:url,
      data:new FormData($("#survey_edit_form")[0]),
      processData:false,
      contentType:false,
      success:function(response)
      {
        toastr.success( 'Survey Info Updated Successfully!','Well Done!')
        $('#survey_edit_form')[0].reset();
        var rData = [
        '<b class="serial">'+tr_sl+'</b>',
        response[1]['serial'],
        response[1]['name'],
        response[1]['society_name'],
        response[1]['survey_date'],
        response[1]['survey_exp_date'], 
        response[1]['vessel']['name'],
        '<button class="btn btn-info edit-survey mr-1" data-id="'+response[1]['id']+'" data-toggle="modal" data-target="#edit_template_modal"><i class="fas fa-edit"></i></button><button class="btn btn-danger delete-survey" data-id="'+response[1]['id']+'" data-toggle="modal" data-target="#delete_template_modal"><i class="fas fa-trash-alt"></i></button>'
        ];
        table
        .row( 'tr#'+tr_id )
        .data(rData)
        .draw();
        swal('Excellent!',response[0],'success').then(function() {
          $("[data-dismiss=modal]").trigger({ type: "click" });
          $('#survey_edit_form')[0].reset();
        });
      },
      error: function(errors)
      {
        console.log(errors.responseJSON.errors);
        toastr.warning("Error! Check Your Form Information Please.");
        $.each(errors.responseJSON.errors, function(key, value){
          $('#survey_edit_form .form_error').show();
          $('#survey_edit_form .form_error').append('<p style="margin-bottom:5px;">'+value+'</p>');
          $('html, body').stop().animate({
            scrollTop: $('.alert-danger').offset().top - 150
          }, 500); 
        });
      }
    });
  }); 
// delete survey function()
$(document).on('click', '.delete-survey', function(){
  var tr = $(this).parents('tr');
  var tr_id=tr.attr('id');
        // alert(tr_id+ ' '+ 'tr#'+tr_id);
        var id = $(this).data('id');
        swal({
          title: 'Are you sure?',
          text: "You want to delete this survey!",
          type: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Yes, delete it!',
          showLoaderOnConfirm: true,
          preConfirm: function() {
            return new Promise(function(resolve) {
              $.ajax({
                url: '/survey/delete',
                type: 'post',
                data: {
                  _token: CSRF_TOKEN,
                  'id':id,
                },
                dataType: 'json'
              })
              .done(function(response){
                swal('Congratulation!',response[0],'success').then(function(){
                  table
                  .row("tr#"+tr_id)
                  .remove()
                  .draw();
                });
              })
              .fail(function(response){
                swal('Oops...', 'Something went wrong!' , 'error');
              });
            });
          },
          allowOutsideClick: false
        });
      });
 // delete Vessel function()
 $(document).on('click', '.delete-vessel', function(){
   var tr = $(this).parents('tr');
   var tr_id=tr.attr('id');
   var id = $(this).data('id');
   swal({
     title: 'Are you sure?',
     text: "You want to delete this Vessel!",
     type: 'warning',
     showCancelButton: true,
     confirmButtonColor: '#3085d6',
     cancelButtonColor: '#d33',
     confirmButtonText: 'Yes, delete it!',
     showLoaderOnConfirm: true,
     preConfirm: function() {
       return new Promise(function(resolve) {
         $.ajax({
           url: '/vessel/delete',
           type: 'post',
           data: {
             _token: CSRF_TOKEN,
             'id':id,
           },
           dataType: 'json'
         })
         .done(function(response){
           swal('Congratulation!',response[0],'success').then(function(){
             table
             .row("tr#"+tr_id)
             .remove()
             .draw();
           });
         })
         .fail(function(response){
           swal('Oops...', 'Something went wrong!' , 'error');
         });
       });
     },
     allowOutsideClick: false
   });
 });
// Add Certificate Function 
$(document).on('submit','#certificate_add_form',function(event){
  event.preventDefault();
  $("#certificate_add_form .form_error").css('display','none');
  $("#certificate_add_form .form_error p").remove();
  $('body').addClass("loading");
  var form=$('#certificate_add_form');
  var formData=form.serialize();
  var url='/certificate/store';
  var type='post';
  $.ajax({
    type:type,
    url:url,
    data:new FormData($("#certificate_add_form")[0]),
    processData:false,
    contentType:false,
    success:function(response)
    {
      toastr.success( 'New Certificate Added Successfully!','Well Done!')
      var  idx= table.rows().count();
      idx++;
      var rowNode = table
      .row.add( ['<b class="serial">'+idx+'</b>', response[1]['name'],response[1]['issue_auth'],response[1]['issue_date'],response[1]['exp_date'],response[1]['vessel']['name'],
        '<button type="button" class="cert_file btn btn-info" data-toggle="modal" data-target="#fileShowModal" data-file="'+response[2]+ '/' +response[1]['cert_copy']+'" data-name="'+response[1]['name']+'"><i class="fas fa-eye"></i> Show File</button>',
        '<div class="action"><button class="btn btn-info mr-1 edit-certificate" data-id="'+response[1]['id']+'" data-toggle="modal" data-target="#edit_template_modal"><i class="fas fa-edit"></i></button>'+
        '<button class="btn btn-danger delete-certificate" data-id="'+response[1]['id']+'" data-toggle="modal" data-target="#delete_template_modal"><i class="fas fa-trash-alt"></i></button></div>'])
      .order([0, 'dsc']).draw()
      .node().id = 'certificate-'+response[1]['id'];
      $( rowNode )
      .css( 'color', 'green' )
      .animate( { color: 'red' } );
      swal('Congratulation!',response[0],'success').then(function() {
        $('#certificate_add_form')[0].reset();
        $("[data-dismiss=modal]").trigger({ type: "click" });
        $("#certificate_add_form .form_error").css('display','none');
      });
    },
    error: function(errors)
    {
      console.log(errors.responseJSON.errors);
      toastr.warning("Error! Check Your Form Information Please.");
      $.each(errors.responseJSON.errors, function(key, value){
        $('#certificate_add_form .form_error').show();
        $('#certificate_add_form .form_error').append('<p style="margin-bottom:5px;">'+value+'</p>');
        $('html, body').stop().animate({
          scrollTop: $('.alert-danger').offset().top - 150
        }, 500); 
      });
    }
  });
}); 

$(document).on('click','.cert_file',function(e){
  e.preventDefault();
  $('#fileShowModal .modal-title span').text($(this).data('name'));
  $('#fileShowModal #fileShowImg').attr('src',$(this).data('file'));
})
$(document).on('click','.edit-certificate',function(e){
  e.preventDefault();
  var id = $(this).data('id');
  var url='/certificate/'+id;
  var type='get';
  tr = $(this).parent().parent();
  tr_id=tr.attr('id');
  tr_sl = $('#'+tr_id +" .serial").text();
  $.ajax({
    type:type,
    url:url,
    data:{},
    processData:false,
    contentType:false,
    success:function(res)
    {

      $( "option.vessel_opt" ).each(function() {
        if($(this).val() == res[0]['vessel']['id']){
          $(this).attr('selected','true');
        };
      });
      $('#certificate_edit_form .Certificate_Name').val(res[0]['name'])
      $('#certificate_edit_form .Issuing_Authority').val(res[0]['issue_auth'])
      $('#certificate_edit_form .Issue_Date').val(res[0]['issue_date'])
      $('#certificate_edit_form .Certificate_Expire_Date').val(res[0]['exp_date'])
      $('#certificate_edit_form img#prev_image1exist').attr('src',res[1]+'/'+res[0]['cert_copy'])
      $('#certificate_edit_form .Cert_Id').val(res[0]['id'])
    },
    error: function(errors)
    {
      toastr.warning("Error! Something went wrong.");
    }
  });
})
$(document).on('submit','#certificate_edit_form',function(event){
  event.preventDefault();
  $("#certificate_edit_form .form_error").css('display','none');
  $("#certificate_edit_form .form_error p").remove();
  $('body').addClass("loading");
  var form=$('#certificate_edit_form');
  var formData=form.serialize();
  var url='/certificate/update';
  var type='post';
  $.ajax({
    type:type,
    url:url,
    data:new FormData($("#certificate_edit_form")[0]),
    processData:false,
    contentType:false,
    success:function(response)
    {
      toastr.success( 'Certificate Info Updated Successfully!','Well Done!')
      $('#certificate_edit_form')[0].reset();
      var rData = [
      '<b class="serial">'+tr_sl+'</b>',
      response[1]['name'],
      response[1]['issue_auth'],
      response[1]['issue_date'],
      response[1]['exp_date'],
      response[1]['vessel']['name'], 
      '<button type="button" class="cert_file btn btn-info" data-toggle="modal" data-target="#fileShowModal" data-file="'+response[2]+ '/' +response[1]['cert_copy']+'"><i class="fas fa-eye"></i> Show File</button>',
      '<button class="btn btn-info edit-certificate mr-1" data-id="'+response[1]['id']+'" data-toggle="modal" data-target="#edit_template_modal"><i class="fas fa-edit"></i></button><button class="btn btn-danger delete-certificate" data-id="'+response[1]['id']+'" data-toggle="modal" data-target="#delete_template_modal"><i class="fas fa-trash-alt"></i></button>'
      ];
      table
      .row( 'tr#'+tr_id )
      .data(rData)
      .draw();
      swal('Excellent!',response[0],'success').then(function() {
        $("[data-dismiss=modal]").trigger({ type: "click" });
        $('#certificate_edit_form')[0].reset();
      });
    },
    error: function(errors)
    {
      console.log(errors.responseJSON.errors);
      toastr.warning("Error! Check Your Form Information Please.");
      $.each(errors.responseJSON.errors, function(key, value){
        $('#certificate_edit_form .form_error').show();
        $('#certificate_edit_form .form_error').append('<p style="margin-bottom:5px;">'+value+'</p>');
        $('html, body').stop().animate({
          scrollTop: $('.alert-danger').offset().top - 150
        }, 500); 
      });
    }
  });
}); 
// delete survey function()
$(document).on('click', '.delete-certificate', function(){
  var tr = $(this).parents('tr');
  var tr_id=tr.attr('id');
        // alert(tr_id+ ' '+ 'tr#'+tr_id);
        var id = $(this).data('id');
        swal({
          title: 'Are you sure?',
          text: "You want to delete this Certificate!",
          type: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Yes, delete it!',
          showLoaderOnConfirm: true,
          preConfirm: function() {
            return new Promise(function(resolve) {
              $.ajax({
                url: '/certificate/delete',
                type: 'post',
                data: {
                  _token: CSRF_TOKEN,
                  'id':id,
                },
                dataType: 'json'
              })
              .done(function(response){
                swal('Congratulation!',response[0],'success').then(function(){
                  table
                  .row("tr#"+tr_id)
                  .remove()
                  .draw();
                });
              })
              .fail(function(response){
                swal('Oops...', 'Something went wrong!' , 'error');
              });
            });
          },
          allowOutsideClick: false
        });
      });
// Add Category Function 
$(document).on('submit','#category_add_form',function(event){
  event.preventDefault();
  $("#category_add_form .form_error").css('display','none');
  $("#category_add_form .form_error p").remove();
  $('body').addClass("loading");
  var form=$('#category_add_form');
  var formData=form.serialize();
  var url='/category/store';
  var type='post';
  $.ajax({
    type:type,
    url:url,
    data:new FormData($("#category_add_form")[0]),
    processData:false,
    contentType:false,
    success:function(response)
    {
      toastr.success( 'New Category Added Successfully!','Well Done!')
      var  idx= table.rows().count();
      idx++;
      var rowNode = table
      .row.add( ['<b class="serial">'+idx+'</b>', response[1]['name'], response[1]['symbol'], response[1]['created_by'], response[1]['updated_by'],
        '<div class="action"><button class="btn btn-info mr-1 edit-category" data-id="'+response[1]['id']+'"  data-name="'+response[1]['name']+'"  data-symbol="'+response[1]['symbol']+'" data-toggle="modal" data-target="#edit_template_modal"><i class="fas fa-edit"></i></button>'+
        '<button class="btn btn-danger delete-category" data-id="'+response[1]['id']+'" data-toggle="modal" data-target="#delete_template_modal"><i class="fas fa-trash-alt"></i></button></div>'])
      .order([0, 'dsc']).draw()
      .node().id = 'category-'+response[1]['id'];
      $( rowNode )
      .css( 'color', 'green' )
      .animate( { color: 'red' } );
      swal('Excellent!',response[0],'success').then(function() {
        $('#category_add_form')[0].reset();
        $("[data-dismiss=modal]").trigger({ type: "click" });
        $("#category_add_form .form_error").css('display','none');
      });
    },
    error: function(errors)
    {
      console.log(errors.responseJSON.errors);
      toastr.warning("Error! Check Your Form Information Please.");
      $.each(errors.responseJSON.errors, function(key, value){
        $('#category_add_form .form_error').show();
        $('#category_add_form .form_error').append('<p style="margin-bottom:5px;">'+value+'</p>');
        $('html, body').stop().animate({
          scrollTop: $('.alert-danger').offset().top - 150
        }, 500); 
      });
    }
  });
}); 

$(document).on('click','.edit-category',function(e){
  e.preventDefault();
  $('#category_edit_form .Category_Id').val($(this).data('id'));
  $('#category_edit_form .Category_Name').val($(this).data('name'));
  $('#category_edit_form .Category_Symbol').val($(this).data('symbol'));
  tr = $(this).parent().parent();
  tr_id=tr.attr('id');
  tr_sl = $('#'+tr_id +" .serial").text();
})
$(document).on('submit','#category_edit_form',function(event){
  event.preventDefault();
  $("#category_edit_form .form_error").css('display','none');
  $("#category_edit_form .form_error p").remove();
  $('body').addClass("loading");
  var form=$('#category_edit_form');
  var formData=form.serialize();
  var url='/category/update';
  var type='post';
  $.ajax({
    type:type,
    url:url,
    data:new FormData($("#category_edit_form")[0]),
    processData:false,
    contentType:false,
    success:function(response)
    {
      toastr.success( 'Category Info Updated Successfully!','Well Done!')
      $('#category_edit_form')[0].reset();
      var rData = [
      '<b class="serial">'+tr_sl+'</b>',
      response[1]['name'],
      response[1]['symbol'],
      response[1]['created_by'],
      response[1]['updated_by'],
      '<button class="btn btn-info edit-category mr-1" data-id="'+response[1]['id']+'" data-name="'+response[1]['name']+'"  data-symbol="'+response[1]['symbol']+'" data-toggle="modal" data-target="#edit_template_modal"><i class="fas fa-edit"></i></button><button class="btn btn-danger delete-category" data-id="'+response[1]['id']+'" data-toggle="modal" data-target="#delete_template_modal"><i class="fas fa-trash-alt"></i></button>'
      ];
      table
      .row( 'tr#'+tr_id )
      .data(rData)
      .draw();
      swal('Excellent!',response[0],'success').then(function() {
        $("[data-dismiss=modal]").trigger({ type: "click" });
        $('#category_edit_form')[0].reset();
      });
    },
    error: function(errors)
    {
      console.log(errors.responseJSON.errors);
      toastr.warning("Error! Check Your Form Information Please.");
      $.each(errors.responseJSON.errors, function(key, value){
        $('#category_edit_form .form_error').show();
        $('#category_edit_form .form_error').append('<p style="margin-bottom:5px;">'+value+'</p>');
        $('html, body').stop().animate({
          scrollTop: $('.alert-danger').offset().top - 150
        }, 500); 
      });
    }
  });
}); 
// delete survey function()
$(document).on('click', '.delete-category', function(){
  var tr = $(this).parents('tr');
  var tr_id=tr.attr('id');
        // alert(tr_id+ ' '+ 'tr#'+tr_id);
        var id = $(this).data('id');
        swal({
          title: 'Are you sure?',
          text: "You want to delete this Category!",
          type: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Yes, delete it!',
          showLoaderOnConfirm: true,
          preConfirm: function() {
            return new Promise(function(resolve) {
              $.ajax({
                url: '/category/delete',
                type: 'post',
                data: {
                  _token: CSRF_TOKEN,
                  'id':id,
                },
                dataType: 'json'
              })
              .done(function(response){
                swal('Congratulation!',response[0],'success').then(function(){
                  table
                  .row("tr#"+tr_id)
                  .remove()
                  .draw();
                });
              })
              .fail(function(response){
                swal('Oops...', 'Something went wrong!' , 'error');
              });
            });
          },
          allowOutsideClick: false
        });
      });

$(document).on('click','.close_error_alert',function(){
  $(this).parent().hide();
});


$(document).on('submit','#vessel_gen_info',function(e){
  e.preventDefault();
  $("#vessel_gen_info .form_error").css('display','none');
  $("#vessel_gen_info .form_error p").remove();
  $('body').addClass("loading");
  var form=$('#vessel_gen_info');
  var formData=form.serialize();
  var url='/vessel/store/gen-info';
  var type='post';
  $.ajax({
    type:type,
    url:url,
    data:new FormData($("#vessel_gen_info")[0]),
    processData:false,
    contentType:false,
    success:function(response)
    {
      toastr.success('General Info Added Successfully!','Well Done!')

      swal('Congratulation!',response[0],'success').then(function() {
       // $('#vessel_gen_info')[0].reset();
       $("#vessel_gen_info .form_error").css('display','none');
       window.location.href="/home/vessel";
     });
    },
    error: function(errors)
    {
      console.log(errors.responseJSON.errors);
      toastr.warning("Error! Check Your Form Information Please.");
      $.each(errors.responseJSON.errors, function(key, value){
        $('#vessel_gen_info .form_error').show();
        $('#vessel_gen_info .form_error').append('<p style="margin-bottom:5px;">'+value+'</p>');
        $('html, body').stop().animate({
          scrollTop: $('#vessel_gen_info .form_error').offset().top - 150
        }, 500); 
      });
    }
  });
});


  //Done by Abd

  $(document).on('submit','#v-particulars-form',function(e){
    e.preventDefault();
    var vessel_id=  $("#v-particulars-form").data('id');
    $("#v-particulars-form .form_error").css('display','none');
    $("#v-particulars-form .form_error p").remove();
    $('body').addClass("loading");
    var form=$('#v-particulars-form');
    var formData=form.serialize();

    var url='/vessel/store/particular-detail';
    var type='post';
    $.ajax({
      type:type,
      url:url,
      data:new FormData($("#v-particulars-form")[0]),
      processData:false,
      contentType:false,
      success:function(response)
      {
        toastr.success('Vessel Particular Details Info Added Successfully!','Well Done!')

        swal('Congratulation!',response[0],'success').then(function() {
          // $('#vessel_gen_info')[0].reset();
          $("#v-particulars-form .form_error").css('display','none');
          // window.location.href="/home/vessel";
          $('.active').parent().next('li').find('a').tab('show');
        });

        $('#framework-description-tab').css({'pointer-events':'','cursor':'','opacity':''});
      },
      error: function(errors)
      {
        console.log(errors.responseJSON.errors);
        toastr.warning("Error! Check Your Form Information Please.");
        $.each(errors.responseJSON.errors, function(key, value){
          $('#v-particulars-form .form_error').show();
          $('#v-particulars-form .form_error').append('<p style="margin-bottom:5px;">'+value+'</p>');
          $('html, body').stop().animate({
            scrollTop: $('#v-particulars-form .form_error').offset().top - 150
          }, 500);
        });
      }
    });
  });

  $(document).on('submit','#v-freamework-and-description-form',function(e){
    e.preventDefault();
    $("#v-freamework-and-description-form .form_error").css('display','none');
    $("#v-freamework-and-description-form .form_error p").remove();
    $('body').addClass("loading");
    var form=$('#v-freamework-and-description-form');
    var formData=form.serialize();
    var url='/vessel/store/frame-work';
    var type='post';
    $.ajax({
      type:type,
      url:url,
      data:new FormData($("#v-freamework-and-description-form")[0]),
      processData:false,
      contentType:false,
      success:function(response)
      {
        toastr.success('General Info Added Successfully!','Well Done!')

        swal('Congratulation!',response[0],'success').then(function() {
          // $('#vessel_gen_info')[0].reset();
          $("#v-freamework-and-description-form .form_error").css('display','none');
          $('.active').parent().next('li').find('a').tab('show');
          //window.location.href="/home/vessel";
        });
        $('#dimension-tab').css({'pointer-events':'','cursor':'','opacity':''});
      },
      error: function(errors)
      {
        console.log(errors.responseJSON.errors);
        toastr.warning("Error! Check Your Form Information Please.");
        $.each(errors.responseJSON.errors, function(key, value){
          $('#v-freamework-and-description-form .form_error').show();
          $('#v-freamework-and-description-form .form_error').append('<p style="margin-bottom:5px;">'+value+'</p>');
          $('html, body').stop().animate({
            scrollTop: $('#v-freamework-and-description-form .form_error').offset().top - 150
          }, 500);
        });
      }
    });
  });


  $(document).on('submit','#v-dimension-form',function(e){
    e.preventDefault();
    $("#v-dimension-form .form_error").css('display','none');
    $("#v-dimension-form .form_error p").remove();
    $('body').addClass("loading");
    var form=$('#v-dimension-form');
    var formData=form.serialize();
    var url='/vessel/store/dimension';
    var type='post';
    $.ajax({
      type:type,
      url:url,
      data:new FormData($("#v-dimension-form")[0]),
      processData:false,
      contentType:false,
      success:function(response)
      {
        toastr.success('Vessel Dimension Info Added Successfully!','Well Done!')

        swal('Congratulation!',response[0],'success').then(function() {
          // $('#vessel_gen_info')[0].reset();
          $("#v-dimension-form .form_error").css('display','none');
          $('.active').parent().next('li').find('a').tab('show');
          //window.location.href="/home/vessel";
        });
        $('#main-engines-tab').css({'pointer-events':'','cursor':'','opacity':''});
      },
      error: function(errors)
      {
        console.log(errors.responseJSON.errors);
        toastr.warning("Error! Check Your Form Information Please.");
        $.each(errors.responseJSON.errors, function(key, value){
          $('#v-dimension-form .form_error').show();
          $('#v-dimension-form .form_error').append('<p style="margin-bottom:5px;">'+value+'</p>');
          $('html, body').stop().animate({
            scrollTop: $('#v-dimension-form .form_error').offset().top - 150
          }, 500);
        });
      }
    });
  });


  $(document).on('submit','#v-engine-form',function(e){
    e.preventDefault();
    $("#v-engine-form .form_error").css('display','none');
    $("#v-engine-form .form_error p").remove();
    $('body').addClass("loading");
    var form=$('#v-engine-form');
    var formData=form.serialize();
    var url='/vessel/store/engine';
    var type='post';
    $.ajax({
      type:type,
      url:url,
      data:new FormData($("#v-engine-form")[0]),
      processData:false,
      contentType:false,
      success:function(response)
      {
        toastr.success('Vessel Engine Info Added Successfully!','Well Done!')

        swal('Congratulation!',response[0],'success').then(function() {
          // $('#vessel_gen_info')[0].reset();
          $("#v-engine-form .form_error").css('display','none');
          $('.active').parent().next('li').find('a').tab('show');
          //window.location.href="/home/vessel";
        });

        $('#boilers-tab').css({'pointer-events':'','cursor':'','opacity':''});
      },
      error: function(errors)
      {
        console.log(errors.responseJSON.errors);
        toastr.warning("Error! Check Your Form Information Please.");
        $.each(errors.responseJSON.errors, function(key, value){
          $('#v-engine-form .form_error').show();
          $('#v-engine-form .form_error').append('<p style="margin-bottom:5px;">'+value+'</p>');
          $('html, body').stop().animate({
            scrollTop: $('#v-engine-form .form_error').offset().top - 150
          }, 500);
        });
      }
    });
  });


  $(document).on('submit','#v-boiler-form',function(e){
    e.preventDefault();
    $("#v-boiler-form .form_error").css('display','none');
    $("#v-boiler-form .form_error p").remove();
    $('body').addClass("loading");
    var form=$('#v-boiler-form');
    var formData=form.serialize();
    var url='/vessel/store/boiler';
    var type='post';
    $.ajax({
      type:type,
      url:url,
      data:new FormData($("#v-boiler-form")[0]),
      processData:false,
      contentType:false,
      success:function(response)
      {
        toastr.success('Vessel Boiler Info Added Successfully!','Well Done!')

        swal('Congratulation!',response[0],'success').then(function() {
          // $('#vessel_gen_info')[0].reset();
          $("#v-boiler-form .form_error").css('display','none');
          //$('.active').parent().next('li').find('a').tab('show');
          window.location.href="/home/vessel";
        });
      },
      error: function(errors)
      {
        console.log(errors.responseJSON.errors);
        toastr.warning("Error! Check Your Form Information Please.");
        $.each(errors.responseJSON.errors, function(key, value){
          $('#v-boiler-form .form_error').show();
          $('#v-boiler-form .form_error').append('<p style="margin-bottom:5px;">'+value+'</p>');
          $('html, body').stop().animate({
            scrollTop: $('#v-boiler-form .form_error').offset().top - 150
          }, 500);
        });
      }
    });
  });


  $(document).on('submit','#v-geninfo-form',function(e){
    e.preventDefault();
    $("#v-geninfo-form .form_error").css('display','none');
    $("#v-geninfo-form .form_error p").remove();
    $('body').addClass("loading");
    var form=$('#v-geninfo-form');
    var formData=form.serialize();
    var url='/vessel/store/geninfo';
    var type='post';
    $.ajax({
      type:type,
      url:url,
      data:new FormData($("#v-geninfo-form")[0]),
      processData:false,
      contentType:false,
      success:function(response)
      {
        toastr.success('Vessel General Info Added Successfully!','Well Done!')

        swal('Congratulation!',response[0],'success').then(function() {
          // $('#vessel_gen_info')[0].reset();
          $("#v-geninfo-form .form_error").css('display','none');
          //$('.active').parent().next('li').find('a').tab('show');
          $('.active').parent().next('li').find('a').tab('show');
          //window.location.href="/home/vessel";
        });
      },
      error: function(errors)
      {
        console.log(errors.responseJSON.errors);
        toastr.warning("Error! Check Your Form Information Please.");
        $.each(errors.responseJSON.errors, function(key, value){
          $('#v-geninfo-form .form_error').show();
          $('#v-geninfo-form .form_error').append('<p style="margin-bottom:5px;">'+value+'</p>');
          $('html, body').stop().animate({
            scrollTop: $('#v-geninfo-form .form_error').offset().top - 150
          }, 500);
        });
      }
    });
  });



  //Done by Abd End

  $(document).on('click','.back',function(){
    $('.active').parent().prev('li').find('a').tab('show');

    $('html, body').stop().animate({
      scrollTop: $('.form-wrap').offset().top - 150
    }, 500); 
  })

// Add Item Function 
$(document).on('submit','#item_add_form',function(event){
  event.preventDefault();
  $("#item_add_form .form_error").css('display','none');
  $("#item_add_form .form_error p").remove();
  $('body').addClass("loading");
  var form=$('#item_add_form');
  var formData=form.serialize();
  var url='/item/store';
  var type='post';
  $.ajax({
    type:type,
    url:url,
    data:new FormData($("#item_add_form")[0]),
    processData:false,
    contentType:false,
    success:function(response)
    {
      console.log(response);
      toastr.success( 'New Item Added Successfully!','Well Done!')
      var  idx= table.rows().count();
      idx++;
      var rowNode = table
      .row.add( ['<b class="serial">'+idx+'</b>', response[1]['impa_code'], response[1]['name'], response[1]['unit'], response[2]['name'], response[1]['created_by'], response[1]['updated_by'],
        '<div class="action"><button class="btn btn-info mr-1 edit-item" data-id="'+response[1]['id']+'" data-toggle="modal" data-target="#edit_template_modal"><i class="fas fa-edit"></i></button>'+
        '<button class="btn btn-danger delete-item" data-id="'+response[1]['id']+'" data-toggle="modal" data-target="#delete_template_modal"><i class="fas fa-trash-alt"></i></button></div>'])
      .order([0, 'dsc']).draw()
      .node().id = 'item-'+response[1]['id'];
      $( rowNode )
      .css( 'color', 'green' )
      .animate( { color: 'red' } );
      swal('Excellent!',response[0],'success').then(function() {
        $('#item_add_form')[0].reset();
        $("[data-dismiss=modal]").trigger({ type: "click" });
        $("#item_add_form .form_error").css('display','none');
      });
    },
    error: function(errors)
    {
      console.log(errors.responseJSON.errors);
      toastr.warning("Error! Check Your Form Information Please.");
      $.each(errors.responseJSON.errors, function(key, value){
        $('#item_add_form .form_error').show();
        $('#item_add_form .form_error').append('<p style="margin-bottom:5px;">'+value+'</p>');
        $('html, body').stop().animate({
          scrollTop: $('.alert-danger').offset().top - 150
        }, 500); 
      });
    }
  });
}); 

$(document).on('click','.edit-item',function(e){
  e.preventDefault();
  var id = $(this).data('id');
  var url='/item/'+id;
  var type='get';
  tr = $(this).parent().parent();
  tr_id=tr.attr('id');
  tr_sl = $('#'+tr_id +" .serial").text();
  $.ajax({
    type:type,
    url:url,
    data:{},
    processData:false,
    contentType:false,
    success:function(res)
    {
      $( "option.cat_opt" ).each(function() {
        if($(this).val() == res[0]['category']['id']){
          $(this).attr('selected','true');
        };
      });
      $('#item_edit_form .item_Name').val(res[0]['name'])
      $('#item_edit_form .impa_code').val(res[0]['impa_code'])
      $('#item_edit_form .measurement_unit').val(res[0]['unit'])
      $('#item_edit_form .item_id').val(res[0]['id'])
    },
    error: function(errors)
    {
      toastr.warning("Error! Something went wrong.");
    }
  });
})
$(document).on('submit','#item_edit_form',function(event){
  event.preventDefault();
  $("#item_edit_form .form_error").css('display','none');
  $("#item_edit_form .form_error p").remove();
  $('body').addClass("loading");
  var form=$('#item_edit_form');
  var formData=form.serialize();
  var url='/item/update';
  var type='post';
  $.ajax({
    type:type,
    url:url,
    data:new FormData($("#item_edit_form")[0]),
    processData:false,
    contentType:false,
    success:function(response)
    {
      toastr.success( 'Item Updated Successfully!','Well Done!')
      $('#item_edit_form')[0].reset();
      var rData = [
      '<b class="serial">'+tr_sl+'</b>',
      response[1]['impa_code'],
      response[1]['name'],
      response[1]['unit'],
      response[2]['name'],
      response[1]['created_by'], 
      response[1]['updated_by'], 
      '<button class="btn btn-info edit-item mr-1" data-id="'+response[1]['id']+'" data-toggle="modal" data-target="#edit_template_modal"><i class="fas fa-edit"></i></button><button class="btn btn-danger delete-item" data-id="'+response[1]['id']+'" data-toggle="modal" data-target="#delete_template_modal"><i class="fas fa-trash-alt"></i></button>'
      ];
      table
      .row( 'tr#'+tr_id )
      .data(rData)
      .draw();
      swal('Excellent!',response[0],'success').then(function() {
        $("[data-dismiss=modal]").trigger({ type: "click" });
        $('#item_edit_form')[0].reset();
      });
    },
    error: function(errors)
    {
      console.log(errors.responseJSON.errors);
      toastr.warning("Error! Check Your Form Information Please.");
      $.each(errors.responseJSON.errors, function(key, value){
        $('#item_edit_form .form_error').show();
        $('#item_edit_form .form_error').append('<p style="margin-bottom:5px;">'+value+'</p>');
        $('html, body').stop().animate({
          scrollTop: $('.alert-danger').offset().top - 150
        }, 500); 
      });
    }
  });
}); 
// delete survey function()
$(document).on('click', '.delete-item', function(){
  var tr = $(this).parents('tr');
  var tr_id=tr.attr('id');
        // alert(tr_id+ ' '+ 'tr#'+tr_id);
        var id = $(this).data('id');
        swal({
          title: 'Are you sure?',
          text: "You want to delete this Item!",
          type: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Yes, delete it!',
          showLoaderOnConfirm: true,
          preConfirm: function() {
            return new Promise(function(resolve) {
              $.ajax({
                url: '/item/delete',
                type: 'post',
                data: {
                  _token: CSRF_TOKEN,
                  'id':id,
                },
                dataType: 'json'
              })
              .done(function(response){
                swal('Wel Done!',response[0],'success').then(function(){
                  table
                  .row("tr#"+tr_id)
                  .remove()
                  .draw();
                });
              })
              .fail(function(response){
                swal('Oops...', 'Something went wrong!' , 'error');
              });
            });
          },
          allowOutsideClick: false
        });
      });
$(document).on('change','#cate_name',function(){
  $('img.police-st-field-loader').css({'display':'block'});
  var cat_id=$(this).children("option:selected").val();
  $('option.item_opt').remove();
  url='/get-items/'+cat_id;
  $.ajax({
    type:'get',
    url:url,
    data:{
      "id":cat_id
    },
    success:function(response){
      $('img.police-st-field-loader').css({'display':'none'});
      $.each( response, function( key, value ) {
        $('option.item_opt_default').after("<option class='item_opt' value='"+value['id']+"' data-unit='"+value['unit']+"' data-impa='"+value['impa_code']+"' >"+value['name']+"</option>");
      });
      $('option.searched_item_opt').remove();
    }
  });
})
var orderInfo=[];
$(document).on('click','#add_item_button',function(e){
  e.preventDefault();
  var port_name=$('input[name="Port_Name"]').val();
  var vessel_name=$('select[name="Vessel_Name"]').children("option:selected").text();
  var vessel_id=$('select[name="Vessel_Name"]').children("option:selected").val();
  var category_name=$('select#cate_name').children("option:selected").text();
  var cat_id=$('select#cate_name').children("option:selected").val();
  orderInfo = [
  vessel_name,
  vessel_id,
  port_name,
  category_name,
  cat_id
  ];
  $('option.item_opt').remove();
  url='/get-items/'+cat_id;
  $.ajax({
    type:'get',
    url:url,
    data:{
      "id":cat_id
    },
    success:function(response){
          // $('img.police-st-field-loader').css({'display':'none'});
          $.each( response, function( key, value ) {
            $('option.item_opt_default').after("<option class='item_opt' value='"+value['id']+"' data-unit='"+value['unit']+"' data-impa='"+value['impa_code']+"' >"+value['name']+"</option>");
          });
        }
      });
});
$(document).on('click','#order_add',function(e){
  e.preventDefault();
  var item_name =$('#Item_Name').children("option:selected").text();
  var item_impa =$('#Item_Name').children("option:selected").data('impa');
  var unit =$('#Item_Name').children("option:selected").data('unit');
  var item_id =$('#Item_Name').children("option:selected").val();
  var cat_name =$('#cate_name').children("option:selected").text();
  var cat_id =$('#cate_name').children("option:selected").val();
  var qty =$('#item_qty').val();
  var exist_item_count=0;

  if(qty!='' && item_id!=''){
    $('#example1 > tbody  > tr').each(function() {
      if($(this).attr('id') == 'row_ordered_item-'+item_id){
        exist_item_count+=1;
      }
    });
    if(exist_item_count == 0){
      var  idx= orderTable.rows().count();
      idx++;
      var rowNode = orderTable
      .row.add( ['<b class="serial">'+idx+'</b>', item_impa, item_name +'<input type="hidden" name="item_id[]" value="'+item_id+'">' , '<span class="added_qty">'+qty+'</span>' + '<input type="hidden" class="form-control qty-edit" name="item_qty[]" value="'+qty+'">', unit, cat_name, 
        '<button class="btn btn-info mr-1 edit-order-item" data-id="'+item_id+'" data-toggle="modal" data-target="#edit_template_modal"><i class="fas fa-edit"></i></button>'+
        '<button class="btn btn-danger delete-order-item" data-id="'+item_id+'" data-toggle="modal" data-target="#delete_template_modal"><i class="fas fa-trash-alt"></i></button>'])
      .order([0, 'dsc']).draw()
      .node().id = 'row_ordered_item-'+item_id;
      var rows = $('#example1 tbody').html();
      localStorage.setItem('orderItemRows', rows);
      localStorage.setItem('orderInfo', JSON.stringify(orderInfo));
      $('#item_qty').val(1)
      $('#Item_Name').prop('selectedIndex',0)


    }else{
      alert('this item already added');
    }
  }  else{
    return false;
  }
});

var orderedItemRows = localStorage.getItem('orderItemRows');
if(orderedItemRows!=null){
  orderTable.rows.add($(orderedItemRows)).draw();
 // $('table.orderedItemTable tbody').append(orderedItemRows);
}
var edititemRowId='';
var editItemname='';
var editItemId='';
var editImpa='';
var editUnit='';
var editCat='';
var edititemRowId='';
$(document).on('click','.edit-order-item',function(e){
  e.preventDefault();
  edititemRowId=$(this).parent().parent().attr('id');
  $('tr#'+edititemRowId+ ' .qty-edit').attr('type','number');
  $('tr#'+edititemRowId+ ' .added_qty').hide();
  editImpa=$('tr#'+edititemRowId +' td').eq(1).text();
  editItemname=$('tr#'+edititemRowId +' td').eq(2).text();
  editItemId=$(this).data('id');
  editUnit=$('tr#'+edititemRowId +' td').eq(4).text();
  editCat=$('tr#'+edititemRowId +' td').eq(5).text();
  // orderTable
  // .row("tr#"+itemRowId)
  // .remove()
  // .draw();
  // swal('Done!','Ordered Item Removed Successfully','success').then(function() {
  // });

});

$(document).on('keyup', '.qty-edit' ,function(e) {
  e.preventDefault();
  var value = $(this).val();
  edititemRowId=$(this).parent().parent().attr('id');
  if(e.which == 13) {

  $('tr#'+edititemRowId+ ' .qty-edit').attr('type','hidden');
  $('tr#'+edititemRowId+ ' .added_qty').text(value).show();


    tr = $(this).parent().parent();
    tr_id=tr.attr('id');
    tr_sl = $('#'+tr_id +" .serial").text();
    var rData = [
    '<b class="serial">'+tr_sl+'</b>',
    editImpa,
    editItemname +'<input type="hidden" name="item_id[]" value="'+editItemId+'">',
    '<span class="added_qty">'+value+'</span>' + '<input type="hidden" class="form-control qty-edit" name="item_qty[]" value="'+value+'">',
    editUnit,
    editCat,
    '<button class="btn btn-info mr-1 edit-order-item" data-id="'+editItemId+'" data-toggle="modal" data-target="#edit_template_modal"><i class="fas fa-edit"></i></button>'+
    '<button class="btn btn-danger delete-order-item" data-id="'+editItemId+'" data-toggle="modal" data-target="#delete_template_modal"><i class="fas fa-trash-alt"></i></button>'
    ];
    orderTable
    .row( 'tr#'+tr_id )
    .data(rData)
    .draw();
    localStorage.removeItem('orderItemRows');
    var rows = $('table.orderedItemTable tbody').html();
    var  idx= orderTable.rows().count();
    if(idx>0){
      localStorage.setItem('orderItemRows', rows);
    }
  }
});



$(document).on('click','.delete-order-item',function(e){
  e.preventDefault();
  if (!confirm('Are you sure?')) {
    return false;
  }else{
    var itemRowId=$(this).parent().parent().attr('id');
    orderTable
    .row("tr#"+itemRowId)
    .remove()
    .draw();
    swal('Done!','Ordered Item Removed Successfully','success').then(function() {
    });
    localStorage.removeItem('orderItemRows');
    var rows = $('table.orderedItemTable tbody').html();
    var  idx= orderTable.rows().count();
    if(idx>0){
      localStorage.setItem('orderItemRows', rows);
    }
  }
});

$(document).on('submit','#add_order_form',function(event){
  event.preventDefault();
  $("#add_order_form .form_error").css('display','none');
  $("#add_order_form .form_error p").remove();
  $('body').addClass("loading");
  var form=$('#add_order_form');
  var formData=form.serialize();
  var url='/order/store';
  var type='post';
  $.ajax({
    type:type,
    url:url,
    data:new FormData($("#add_order_form")[0]),
    processData:false,
    contentType:false,
    success:function(response)
    {
      if(response[0]=="success"){
        toastr.success( 'Order Created Successfully!','Well Done!')
        localStorage.removeItem('orderItemRows');
        localStorage.removeItem('orderInfo');
        swal('Excellent!',response[1],'success').then(function() {
          $('#add_order_form')[0].reset();
          window.location.href='/home/order';
        });
      }else if(response[0]=="fail"){
        toastr.warning( response[1],'Error!');
        swal('Fail!',response[1],'warning').then(function() {
        });
      }
    },
    error: function(errors)
    {
      console.log(errors.responseJSON.errors);
      toastr.warning("Error! Check Your Form Information Please.");
      $.each(errors.responseJSON.errors, function(key, value){
        $('#add_order_form .form_error').show();
        $('#add_order_form .form_error').append('<p style="margin-bottom:5px;">'+value+'</p>');
        $('html, body').stop().animate({
          scrollTop: $('.alert-danger').offset().top - 150
        }, 500); 
      });
    }
  });
});



// approve order 
$(document).on('click', '#approve_order', function(){
  var id = $(this).data('id');
  swal({
    title: 'Are you sure?',
    text: "You want to approve this Requisition!",
    type: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, approve it!',
    showLoaderOnConfirm: true,
    preConfirm: function() {
      return new Promise(function(resolve) {
        $.ajax({
          url: '/order/approve',
          type: 'post',
          data: {
            _token: CSRF_TOKEN,
            'id':id,
          },
          dataType: 'json'
        })
        .done(function(response){
          swal('Congratulation!',response[0],'success').then(function(){
            window.location.href='/approved/requisition'
          });
        })
        .fail(function(response){
          swal('Oops...', 'Something went wrong!' , 'error');
        });
      });
    },
    allowOutsideClick: false
  });
});
$(document).on('click','input#ship_user, input#ship_user_edit',function () {
  if (this.checked){
    $('div.for_ship_user').attr('hidden',false);
    $('div.all_user').attr('hidden',false);
    $('option.admin_role').attr('hidden',true);
    $('option.ship_role').attr('hidden',false);
    $('input.vessel_not_for_admin').attr('disabled',true);
     // $('form#user_add_form')[0].reset();
     $('.User_Role').prop('selectedIndex',0);
     $('.Vessel_Name').prop('selectedIndex',0);
   }else{
     $('div.for_ship_user').attr('hidden',true);
     $('div.all_user').attr('hidden',false);
     $('option.admin_role').attr('hidden',false);
     $('option.ship_role').attr('hidden',true);
     $('input.vessel_not_for_admin').attr('disabled',false);
    // $('form#user_add_form')[0].reset();
    $('.User_Role').prop('selectedIndex',0);
    $('.Vessel_Name').prop('selectedIndex',0);
  }
});
$(document).on('click','input#admin_user, input#admin_user_edit',function () {
  if (this.checked){
    $('div.for_ship_user').attr('hidden',true);
    $('div.all_user').attr('hidden',false);
    $('option.admin_role').attr('hidden',false);
    $('option.ship_role').attr('hidden',true);
    $('input.vessel_not_for_admin').attr('disabled',false);
    $('.User_Role').prop('selectedIndex',0);
    $('.Vessel_Name').prop('selectedIndex',0);
     // $('form#user_add_form')[0].reset();
   }else{
     $('div.for_ship_user').attr('hidden',false);
     $('div.all_user').attr('hidden',false);
     $('option.admin_role').attr('hidden',true);
     $('option.ship_role').attr('hidden',false);
     $('input.vessel_not_for_admin').attr('disabled',true);
     // $('form#user_add_form')[0].reset();
     $('.User_Role').prop('selectedIndex',0);
     $('.Vessel_Name').prop('selectedIndex',0);
   }
 }); 


// Add Item Function 
$(document).on('submit','#user_add_form',function(event){
  event.preventDefault();
  $("#user_add_form .form_error").css('display','none');
  $("#user_add_form .form_error p").remove();
  $('body').addClass("loading");
  var form=$('#user_add_form');
  var formData=form.serialize();
  var url='/user/store';
  var type='post';
  $.ajax({
    type:type,
    url:url,
    data:new FormData($("#user_add_form")[0]),
    processData:false,
    contentType:false,
    success:function(response)
    {
      toastr.success( 'New User Added Successfully!','Well Done!')
      var  idx= table.rows().count();
      idx++;
      var rowNode = table
      .row.add( ['<b class="serial">'+idx+'</b>', response[1]['name'], response[1]['email'], response[2]['role'], response[3], response[2]['created_by']+ '<br>' +response[2]['created_at'], response[2]['updated_by'] +'<br>'+response[2]['updated_at'],
        '<button class="btn btn-info mr-1 edit-user" data-id="'+response[1]['id']+'" data-toggle="modal" data-target="#edit_template_modal"><i class="fas fa-edit"></i></button>'+
        '<button class="btn btn-danger delete-user" data-id="'+response[1]['id']+'" data-toggle="modal" data-target="#delete_template_modal"><i class="fas fa-trash-alt"></i></button>'])
      .order([0, 'dsc']).draw()
      .node().id = 'user-'+response[1]['id'];
      $( rowNode )
      .css( 'color', 'green' )
      .animate( { color: 'red' } );
      swal('Excellent!',response[0],'success').then(function() {
        $('#user_add_form')[0].reset();
        $("[data-dismiss=modal]").trigger({ type: "click" });
        $("#user_add_form .form_error").css('display','none');
      });
    },
    error: function(errors)
    {
      console.log(errors.responseJSON.errors);
      toastr.warning("Error! Check Your Form Information Please.");
      $.each(errors.responseJSON.errors, function(key, value){
        $('#user_add_form .form_error').show();
        $('#user_add_form .form_error').append('<p style="margin-bottom:5px;">'+value+'</p>');
        $('html, body').stop().animate({
          scrollTop: $('.alert-danger').offset().top - 150
        }, 500); 
      });
    }
  });
}); 
// delete survey function()
$(document).on('click', '.delete-user', function(){
  var tr = $(this).parents('tr');
  var tr_id=tr.attr('id');
        // alert(tr_id+ ' '+ 'tr#'+tr_id);
        var id = $(this).data('id');
        swal({
          title: 'Are you sure?',
          text: "You want to delete this User!",
          type: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Yes, delete it!',
          showLoaderOnConfirm: true,
          preConfirm: function() {
            return new Promise(function(resolve) {
              $.ajax({
                url: '/user/delete',
                type: 'post',
                data: {
                  _token: CSRF_TOKEN,
                  'id':id,
                },
                dataType: 'json'
              })
              .done(function(response){
                swal('Wel Done!',response[0],'success').then(function(){
                  table
                  .row("tr#"+tr_id)
                  .remove()
                  .draw();
                });
              })
              .fail(function(response){
                swal('Oops...', 'Something went wrong!' , 'error');
              });
            });
          },
          allowOutsideClick: false
        });
      });

// delete survey function()
$(document).on('click', '#update_order_status', function(e){
  e.preventDefault();
        var id = $(this).data('id');
        var status = $('#order_status').children("option:selected").val();
        swal({
          title: 'Are you sure?',
          text: "You want to update this order status!",
          type: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Yes, update it!',
          showLoaderOnConfirm: true,
          preConfirm: function() {
            return new Promise(function(resolve) {
              $.ajax({
                url: '/order/status/update',
                type: 'post',
                data: {
                  _token: CSRF_TOKEN,
                  'id':id,
                  'status':status,
                },
                dataType: 'json'
              })
              .done(function(response){
                swal('Wel Done!',response[0],'success').then(function(){
                  window.location.href='/delivered/requisition';
              })
              .fail(function(response){
                swal('Oops...', 'Something went wrong!' , 'error');
              });
            });
            });
          },
          allowOutsideClick: false
        });
      });
$(document).on('click','.edit-user',function(e){
  e.preventDefault();
  var id = $(this).data('id');
  var url='/user/'+id;
  var type='get';
  tr = $(this).parent().parent();
  tr_id=tr.attr('id');
  tr_sl = $('#'+tr_id +" .serial").text();
  $.ajax({
    type:type,
    url:url,
    data:{},
    processData:false,
    contentType:false,
    success:function(res)
    {
      if( res[1]['vessel_id']>0){
        $('#ship_user_edit').attr('checked',true);
        $('option.admin_role').attr('hidden',true);
        $('option.ship_role').attr('hidden',false);
        $( "option.vessel_opt" ).each(function() {
          if($(this).val() == res[1]['vessel_id']){
            $(this).attr('selected','true');
          };
        });
      }else{
        $('div.for_ship_user').attr('hidden',true);
        $('#admin_user_edit').attr('checked',true);
        $('#user_edit_form .vessel_not_for_admin').attr('disabled',false);
        $('option.admin_role').attr('hidden',false);
        $('option.ship_role').attr('hidden',true);
      }
      // $( "option.vessel_opt" ).each(function() {
      //   if($(this).val() == res[1]['vessel_id']){
      //     $(this).attr('selected','true');
      //   };
      // });
      $( "option.role_opt" ).each(function() {
        if($(this).val() == res[1]['role']){
          $(this).attr('selected','true');
        };
      });
      $('#user_edit_form .User_Role').val(res[1]['role'])
      $('#user_edit_form .Email').val(res[0]['email'])
      $('#user_edit_form .User_Name').val(res[0]['name'])
      $('#user_edit_form .user_id').val(res[0]['id'])
    },
    error: function(errors)
    {
      toastr.warning("Error! Something went wrong.");
    }
  });
})

$(document).on('submit','#user_edit_form',function(event){
  event.preventDefault();
  $("#user_edit_form .form_error").css('display','none');
  $("#user_edit_form .form_error p").remove();
  $('body').addClass("loading");
  var form=$('#user_edit_form');
  var formData=form.serialize();
  var url='/user/update';
  var type='post';
  $.ajax({
    type:type,
    url:url,
    data:new FormData($("#user_edit_form")[0]),
    processData:false,
    contentType:false,
    success:function(response)
    {
      toastr.success( 'User Updated Successfully!','Well Done!')
      $('#user_edit_form')[0].reset();
      var rData = [
      '<b class="serial">'+tr_sl+'</b>',
      response[1]['name'],
      response[1]['email'],
      response[2]['role'],
      response[3], 
      response[2]['created_by']+'<br>'+response[2]['created_at'], 
      response[2]['updated_by']+'<br>'+response[2]['updated_at'],  
      '<button class="btn btn-info edit-user mr-1" data-id="'+response[1]['id']+'" data-toggle="modal" data-target="#edit_template_modal"><i class="fas fa-edit"></i></button><button class="btn btn-danger delete-user" data-id="'+response[1]['id']+'" data-toggle="modal" data-target="#delete_template_modal"><i class="fas fa-trash-alt"></i></button>'
      ];
      table
      .row( 'tr#'+tr_id )
      .data(rData)
      .draw();
      swal('Excellent!',response[0],'success').then(function() {
        $("[data-dismiss=modal]").trigger({ type: "click" });
        $('#user_edit_form')[0].reset();
      });
    },
    error: function(errors)
    {
      console.log(errors.responseJSON.errors);
      toastr.warning("Error! Check Your Form Information Please.");
      $.each(errors.responseJSON.errors, function(key, value){
        $('#user_edit_form .form_error').show();
        $('#user_edit_form .form_error').append('<p style="margin-bottom:5px;">'+value+'</p>');
        $('html, body').stop().animate({
          scrollTop: $('.alert-danger').offset().top - 150
        }, 500); 
      });
    }
  });
}); 

$(document).on('submit','form#deliveredQtyForm',function(event){
  event.preventDefault();
  $('body').addClass("loading");
  var form=$('#deliveredQtyForm');
  var formData=form.serialize();
  var url='/item-qty/update';
  var type='post';
  $.ajax({
    type:type,
    url:url,
    data:new FormData($("#deliveredQtyForm")[0]),
    processData:false,
    contentType:false,
    success:function(response)
    {
      toastr.success( 'Delivered Quantity Updated Successfully!','Well Done!')
      // $('#deliveredQtyForm')[0].reset();
      swal('Excellent!',response[0],'success').then(function() {
        $("[data-dismiss=modal]").trigger({ type: "click" });
      });
    },
    error: function(errors)
    {
      console.log(errors.responseJSON.errors);
      toastr.warning("Error! Check Your Form Information Please.");
    }
  });
}); 
$(document).on('click','#indSave',function(e){
  e.preventDefault();
  var role = $(this).data('role');
  if(role=='am-ssm'){
    var itemId = $(this).parent('td').prev('td.deliver_qty').find('input').data('id');
    var itemValue = $(this).parent('td').prev('td.deliver_qty').find('input').val();  
  }else{
    var itemId = $(this).parent('td').prev('td.rcv_qty').find('input').data('id');
    var itemValue = $(this).parent('td').prev('td.rcv_qty').find('input').val();

  }
  var url='/single-qty/update';
  var type='post';
  $.ajax({
    url: url,
    type: type,
    data: {
      _token: CSRF_TOKEN,
      'itemId':itemId,
      'itemValue':itemValue,
    },
    dataType: 'json',
    success:function(response)
    {
      toastr.success( 'Delivered Quantity Updated Successfully!','Well Done!')
      // $('#deliveredQtyForm')[0].reset();
      swal('Excellent!',response[0],'success').then(function() {
        $("[data-dismiss=modal]").trigger({ type: "click" });
      });
    },
    error: function(errors)
    {
      console.log(errors.responseJSON.errors);
      toastr.warning("Error! Check Your Form Information Please.");
    }
  })
});

}); // End document.ready




