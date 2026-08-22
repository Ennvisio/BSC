    <div class="content" style="background: #e9ead9">
        <div class="animated fadeIn">
            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="card counter">
                        <div class="card-body">
                            <a href="{{url('/status/positive')}}">
                                <div class="stat-widget-five">
                                    <div class="stat-icon dib flat-color-1">
                                        <i class="far fa-calendar-plus"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="text-left dib">
                                            <div class="stat-text"><span class="count positive"></span></div>
                                            <div class="stat-heading">Positive</div>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="card counter">
                        <div class="card-body">
                            <a href="{{url('/status/negative')}}">
                                <div class="stat-widget-five">
                                    <div class="stat-icon dib flat-color-2">
                                        <i class="far fa-calendar-minus"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="text-left dib">
                                            <div class="stat-text"><span class="count negative"></span></div>
                                            <div class="stat-heading">Negative</div>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card counter">
                        <div class="card-body">
                            <a href="{{url('/status/pending')}}">
                                <div class="stat-widget-five">
                                    <div class="stat-icon dib flat-color-3">
                                        <i class="fas fa-spinner"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="text-left dib">
                                            <div class="stat-text"><span class="count pending"></span></div>
                                            <div class="stat-heading">Pending</div>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="card counter">
                        <div class="card-body">
                            <a href="{{url('/category/port-staff')}}">
                                <div class="stat-widget-five">
                                    <div class="stat-icon dib flat-color-4" style="color: #d6bd0c">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="text-left dib">
                                            <div class="stat-text"><span class="count"></span></div>
                                            <div class="stat-heading">Port Staff</div>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="card counter">
                        <div class="card-body">
                            <a href="{{url('/category/port-user')}}">
                                <div class="stat-widget-five">
                                    <div class="stat-icon dib flat-color-4" style="color: #66bb6a">
                                       <i class="fas fa-user"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="text-left dib">
                                            <div class="stat-text"><span class="count"></span></div>
                                            <div class="stat-heading">Port User</div>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="card counter">
                        <div class="card-body">
                            <a href="{{url('/status/all')}}">
                                <div class="stat-widget-five">
                                    <div class="stat-icon dib flat-color-4">
                                        <img style="width: 58px;height: auto;" src="{{url('images/xxx046-512.png')}}">
                                    </div>
                                    <div class="stat-content">
                                        <div class="text-left dib">
                                            <div class="stat-text"><span class="count">00</span></div>
                                            <div class="stat-heading">Total</div>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="clearfix"></div>
            <div class="orders">
                <div class="row justify-content-center">
<!--                  @yield('content')
                 @yield('pie-chart') -->
             </div>
         </div>
         <!-- /.orders -->
     </div>
     <!-- .animated -->
 </div>