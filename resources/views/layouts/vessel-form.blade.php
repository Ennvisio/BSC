    <div class="vessel-form">
        <div class="tab-content" id="myTabContent">
            <!-- tab-pane -->                            
            <div class="tab-pane fade show active" id="vasel-ginfo" role="tabpanel" aria-labelledby="vasel-ginfo-tab">  
                <div class="bsc-form-wrapper vasel-ginfo-wrapper">
                    <div class="form-header">Vessel General Info</div>
                    <form id="vessel_gen_info">   
                        @csrf     
                        <div class="row justify-content-center form-group mt-3">
                            <div class="col-md-11 alert alert-danger alert-dismissible fade show form_error" style="display:none" role="alert">
                                <strong>Error Submission!!</strong> Please correct following info and resubmit. 
                                <label>    </label>
                                <button type="button" class="close close_error_alert">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        </div>                            
                        <div class="form-group row mt-4">
                            <label for="vessel_name" class="col-sm-3 col-form-label">Name of Vassel<span class="required">*</span> :</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" name="vessel_name" placeholder="" >
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="owner_name" class="col-sm-3 col-form-label">Name of Owner<span class="required">*</span> :</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" name="owner_name" placeholder="" >
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="owner_address" class="col-sm-3 col-form-label">Address of Owner<span class="required">*</span> :</label>
                            <div class="col-sm-9">
                                <textarea class="form-control" name="owner_address" placeholder="" ></textarea>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="manager_name" class="col-sm-3 col-form-label">Name of Manager<span class="required">*</span> :</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" name="manager_name" placeholder="" >
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="manager_address" class="col-sm-3 col-form-label">Address of Manager<span class="required">*</span> :</label>
                            <div class="col-sm-9">
                                <textarea class="form-control" name="manager_address" placeholder="" ></textarea>
                            </div>
                        </div>

                        <hr>

                        <div class="form-group row">
                            <label for="master_name" class="col-sm-3 col-form-label">Name of Master<span class="required">*</span> :</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" name="master_name" placeholder="" >
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="master_cert_no" class="col-sm-3 col-form-label">Certificate No. of Master<span class="required">*</span> :</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" name="master_certificate_no" placeholder="" >
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="master_cert_validity" class="col-sm-3 col-form-label">Certificate Validity of Master<span class="required">*</span> :</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control date" name="master_certificate_validity" placeholder="" >
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="ch_eng_name" class="col-sm-3 col-form-label">Name of Ch. Eng.<span class="required">*</span> :</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" name="cheif_engineer_name" placeholder="" >
                            </div>
                        </div>

                        <div class="form-group row"> 
                            <label for="ch_eng_cert_no" class="col-sm-3 col-form-label">Certificate Number of Ch. Eng<span class="required">*</span> :</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" name="cheif_engineer_certificate_no" placeholder="" >
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="ch_eng_cert_validity" class="col-sm-3 col-form-label">Certificate Validity of Ch. Eng.<span class="required">*</span> :</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control date" name="cheif_engineer_certificate_validity" placeholder="" >
                            </div>
                        </div>
                        <div class="form-group row mt-4">
                            <div class="col-sm-12 text-center">
                                <button type="submit" class="btn btn-success btn-gninfo">Save Vessel General Info</button>
                            </div>                                        
                        </div>
                    </form>
                </div>                                  
            </div>
            <!-- ./tab-pane -->
        </div>
        <!-- ./tab-content -->

    </div>