<?php
Route::get('/', function () {
	return redirect('/login');
});

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');
Route::get('/home/vessel', 'HomeController@getVessels')->name('get.vessels');
Route::get('/home/certificate', 'HomeController@getCertificates')->name('get.certificate');
Route::get('/home/survey', 'HomeController@getSurvey')->name('get.all.survey');
Route::post('/survey/store', 'HomeController@storeSurvey')->name('store.one.survey');
Route::get('/survey/{id}', 'HomeController@getOneSurvey')->name('get.one.survey');
Route::post('/survey/update', 'HomeController@updateOneSurvey')->name('update.one.survey');
Route::post('/survey/delete', 'HomeController@deleteOneSurvey')->name('delete.one.survey');
Route::post('/certificate/store', 'HomeController@certificateStore')->name('store.one.certificate');
Route::get('/certificate/{id}', 'HomeController@getOneCertificate')->name('get.one.certificate');
Route::post('/certificate/update', 'HomeController@updateOneCertificate')->name('update.one.certificate');
Route::post('/certificate/delete', 'HomeController@deleteOneCertificate')->name('delete.one.certificate');
Route::get('/vessel/add', 'HomeController@addVesselForm')->name('get.vesselAdd.form');
Route::post('/vessel/store/gen-info', 'HomeController@storeVesselGenInfo')->name('store.vessel.genInfo');
Route::get('/home/item', 'HomeController@getItem')->name('get.all.item');
Route::get('/home/category', 'HomeController@getCategory')->name('get.all.category');
Route::post('/category/store', 'HomeController@storeCategory')->name('store.one.category');
Route::post('/category/update', 'HomeController@updateCategory')->name('update.one.category');
Route::post('/category/delete', 'HomeController@deleteCategory')->name('delete.one.category');
Route::get('/vessel-detail-add/{id}', 'HomeController@addVesselDetail')->name('add.vessel.detail');
Route::get('/vessel-edit/{id}', 'HomeController@editVessel')->name('edit.vessel');

Route::post('/item/store', 'HomeController@storeItem')->name('store.item');
Route::get('/item/{id}', 'HomeController@getOneItem')->name('get.one.item');
Route::post('/item/update', 'HomeController@updateOneItem')->name('update.one.item');
Route::post('/item/delete', 'HomeController@deleteOneItem')->name('delete.one.item');
Route::get('/get-items/{cat_id}', 'HomeController@getItemsByCat')->name('items.by.category');

//Done By Abd
Route::post('/vessel/store/particular-detail','HomeController@storeVessParticularDetail');
Route::post('/vessel/store/frame-work','HomeController@storeVessFramDescription');
Route::post('/vessel/store/dimension','HomeController@storeDimension');
Route::post('/vessel/store/engine','HomeController@storeEngine');
Route::post('/vessel/store/boiler','HomeController@storeBoiler');
Route::post('/vessel/store/geninfo','HomeController@storeGeninfo');
Route::post('/vessel/delete','HomeController@deleteVessel')->name('delete.vessel');

Route::get('/vessel-view/{vessel_id}', 'HomeController@viewVesselDetail')->name('view.vessel.detail');

 
Route::group(['middleware' => 'member'],function(){
	Route::post('/order/store', 'HomeController@storeOrder')->name('store.order');
	Route::get('/create/order', 'HomeController@createOrder')->name('add.new.order');
	Route::get('/home/created-orders', 'HomeController@createdOrders');
});
Route::get('/home/order', 'HomeController@getOrder')->name('get.all.order');//superadmin // operator

Route::get('/order/detail/{order_id}', 'HomeController@viewOrderDetail')->name('view.order.detail');
/* 
  Order Detail Shown Should be Restricted.......... do later 
*/
  /* Role Based Access/Action  */
  Route::get('/pending/requisition', 'RoleController@pendingRequisition');
  Route::post('/order/approve', 'RoleController@approveRequisition');
  Route::get('/approved/requisition', 'RoleController@approvedRequisition');

  Route::get('/home/trash', 'HomeController@allTrash');

  Route::get('/catalog/import', 'ItemImportController@create')->name('catalog.import.form');
  Route::post('/catalog/import', 'ItemImportController@store')->name('catalog.import.store');
  Route::get('/catalog/import/history', 'ItemImportController@history')->name('catalog.import.history');

  Route::get('/catalog/browse', 'CatalogController@browse')->name('catalog.browse');
  Route::get('/catalog/browse/children/{parentId?}', 'CatalogController@children')->name('catalog.browse.children');
  Route::get('/catalog/browse/items/{groupId}', 'CatalogController@items')->name('catalog.browse.items');

  Route::get('/home/user', 'HomeController@getUser');
  Route::post('/user/store', 'HomeController@storeUser');
  Route::post('/user/delete', 'HomeController@deleteUser');
  Route::get('/user/{id}', 'HomeController@getOneUser');
  Route::post('/user/update', 'HomeController@updateOneUser');
  Route::post('/item-qty/update', 'HomeController@addDelQty');
  Route::post('/single-qty/update', 'HomeController@addsingleDelQty');

  Route::post('/search/order', 'HomeController@searchOrder');
  Route::get('/profile','HomeController@getProfile');
  Route::post('/change/password','HomeController@changePassword');
  Route::post('/change/file','HomeController@changeFile');
  Route::get('/delivered/requisition','HomeController@deliverReqForAll');
  Route::get('/received/requisition','HomeController@rcvReqForAll');
  Route::post('/order/status/update','HomeController@updateStatusByAM');
  
  Route::post('/restore','HomeController@restore');
  
  Route::post('/permanent-delete','HomeController@permanentDelete');
  Route::post('/search/survey','HomeController@searchSurvey');
  Route::post('/search/certificate','HomeController@searchCertificate');

  Route::post('/order/forward','RoleController@forwardToAgm');

  // Route::get('/home/deliver/order', 'HomeController@deliverReq');
