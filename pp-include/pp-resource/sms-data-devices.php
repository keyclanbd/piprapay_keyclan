<?php
    if (file_exists(__DIR__."/../../pp-config.php")) {
        if (file_exists(__DIR__.'/../../maintenance.lock')) {
            if (file_exists(__DIR__.'/../../pp-include/pp-maintenance.php')) {

            }else{
                die('System is under maintenance. Please try again later.');
            }
            exit();
        }else{
            if (file_exists(__DIR__.'/../../pp-include/pp-controller.php')) {
                if (file_exists(__DIR__.'/../../pp-include/pp-view.php')) {
    
                }else{
                    echo 'System is under maintenance. Please try again later.';
                    exit();
                }
            }else{
                echo 'System is under maintenance. Please try again later.';
                exit();
            }
            
            if (file_exists(__DIR__.'/../../pp-include/pp-model.php')) {
                include(__DIR__."/../../pp-include/pp-model.php");
            }else{
                echo 'System is under maintenance. Please try again later.';
                exit();
            }
        }
    }else{
        echo 'System is under maintenance. Please try again later.';
        exit();
    }
    
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }
    
    if(isset($global_user_login) && $global_user_login == true){
?>
          <!-- Page Header -->
          <div class="page-header">
            <div class="row align-items-center">
              <div class="col">
                <h1 class="page-header-title">Devices</h1>
              </div>
              <!-- End Col -->
              <div class="col-auto">
                  <a class="btn btn-success" onclick="load_content('SMS Data','sms-data','nav-btn-sms-data')"><i class="bi-phone me-1"></i> SMS Data</a>
                  <a class="btn btn-success" data-bs-toggle="offcanvas" data-bs-target="#connect-android-app" aria-controls="connect-android-app"><i class="bi-wifi me-1"></i> Connect Android App</a>
              </div>
            </div>
            <!-- End Row -->
          </div>
          <!-- End Page Header -->

          <div class="row justify-content-end mb-3 bulk-manage-tab" style="display: none">
            <div class="col-lg">
              <!-- Datatable Info -->
              <div id="datatableCounterInfo" style="">
                <div class="d-sm-flex justify-content-lg-end align-items-sm-center">
                  <span class="d-block d-sm-inline-block fs-5 me-3 mb-2 mb-sm-0">
                    <span id="bulk-manage-tab-counter">20</span>
                    Selected
                  </span>
                  <a class="btn btn-outline-danger btn-sm mb-2 mb-sm-0 me-2 btn-bulk-action-delete" href="javascript:void(0)" onclick="bulk_action('btn-bulk-action-delete', 'delete')">
                    <i class="bi-trash"></i> Delete
                  </a>
                  <a class="btn btn-primary btn-sm mb-2 mb-sm-0 me-2 btn-bulk-action-connected" href="javascript:void(0)" onclick="bulk_action('btn-bulk-action-connected', 'approve')">
                    <i class="bi-check-circle"></i> Approve
                  </a>
                  <a class="btn btn-danger btn-sm mb-2 mb-sm-0 me-2 btn-bulk-action-disconnected" href="javascript:void(0)" onclick="bulk_action('btn-bulk-action-disconnected', 'revoke')">
                    <i class="bi-wifi-off"></i> Remove
                  </a>
                </div>
              </div>
              
              <span class="response-bulk-action"></span>
            </div>
          </div>
          
          
      <!-- Card -->
      <div class="card">
        <!-- Header -->
        <div class="card-header">
          <div class="row justify-content-between align-items-center flex-grow-1">
            <div class="col-md">
              <div class="d-flex justify-content-between align-items-center">
                <h4 class="card-header-title">Devices List</h4>
              </div>
            </div>
            <!-- End Col -->
    
            <div class="col-auto">
              <!-- Filter -->
              <div class="row align-items-sm-center">
                <div class="col-sm-auto">
                  <div class="row align-items-center gx-0">
                    <div class="col">
                      <span class="text-secondary me-2">Status:</span>
                    </div>
                    <!-- End Col -->
    
                    <div class="col-auto">
                      <!-- Select -->
                      <div class="tom-select-custom tom-select-custom-end">
                        <select class="js-select form-select form-select-sm form-select-borderless sms_status" data-hs-tom-select-options='{
                                  "searchInDropdown": false,
                                  "hideSearch": true,
                                  "dropdownWidth": "10rem"
                                }' onchange="loadDataTable()">
                          <option value="all" selected>All</option>
                          <option value="pending">Waiting for approval</option>
                          <option value="active">Connected</option>
                          <option value="paused">Paused</option>
                          <option value="revoked">Removed</option>
                        </select>
                      </div>
                      <!-- End Select -->
                    </div>
                    <!-- End Col -->
                  </div>
                  <!-- End Row -->
                </div>
                <!-- End Col -->
    
                <div class="col-md">
                  <form>
                    <!-- Search -->
                    <div class="input-group input-group-merge input-group-flush">
                      <div class="input-group-prepend input-group-text">
                        <i class="bi-search"></i>
                      </div>
                      <input id="datatableSearch" type="search" class="form-control" placeholder="Search" aria-label="Search" onkeyup="loadDataTable()">
                    </div>
                    <!-- End Search -->
                  </form>
                </div>
                <!-- End Col -->
              </div>
              <!-- End Filter -->
            </div>
            <!-- End Col -->
          </div>
          <!-- End Row -->
        </div>
        <!-- End Header -->

        <!-- Table -->
        <div class="table-responsive datatable-custom">
          <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
            <thead class="thead-light">
              <tr>
                  <th scope="col" class="table-column-pe-0"><input type="checkbox" id="select-all" class="form-check-input"></th>
                  <th>Device</th>
                  <th>Status</th>
                  <th>Match Code</th>
                  <th>Last Seen</th>
                  <th>Received</th>
                  <th>Saved</th>
                  <th>Missing</th>
                  <th>Paired On</th>
                  <th>Action</th>
              </tr>
            </thead>
            <tbody id="datatable">
                
            </tbody>
          </table>
        </div>
        <!-- End Table -->

        <!-- Footer -->
        <div class="card-footer">
          <div class="row justify-content-center justify-content-sm-between align-items-sm-center">
            <div class="col-sm mb-2 mb-sm-0">
              <div class="d-flex justify-content-center justify-content-sm-start align-items-center">
                <span class="me-2">Showing:</span> <div class="tom-select-custom" id="showing-result"></div> <span class="text-secondary me-2" style="margin-left:8px;">of</span> <span id="total-result"></span>
              </div>
            </div>
            <!-- End Col -->

            <div class="col-sm-auto">
              <div class="d-flex justify-content-center justify-content-sm-end">
                <nav id="datatablePagination" aria-label="Activity pagination">
                    <div class="dataTables_paginate" id="datatable_paginate">
                        <ul id="datatable_pagination" class="pagination datatable-custom-pagination">
                            <li class="paginate_item page-item" id="prev-page">
                                <a class="paginate_button previous page-link" href="javascript:void(0)">
                                    <span aria-hidden="true">Prev</span>
                                </a>
                            </li>
                            <span id="page-numbers" style=" display: flex; "></span>
                            <li class="paginate_item page-item" id="next-page">
                                <a class="paginate_button next page-link" href="javascript:void(0)">
                                    <span aria-hidden="true">Next</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </nav>
              </div>
            </div>
            <!-- End Col -->
          </div>
          <!-- End Row -->
        </div>
        <!-- End Footer -->
      </div>
      <!-- End Card -->

        <div class="offcanvas offcanvas-end" tabindex="-1" id="connect-android-app" aria-labelledby="connect-android-app" style="max-width: 650px; width: 100%;">
          <div class="offcanvas-header">
            <h5 id="connect-android-app">Connect Android App</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
          </div>
          <div class="offcanvas-body" style=" padding: 10px; ">
                <div class="card border-0 shadow-sm mb-4">
                  <div class="card-body">
                    <h2 class="h4 fw-bold text-dark mb-2">Secure phone pairing</h2>
                    <p class="text-secondary small mb-3">For the KC Payment app. In the app, enter the code below, then approve the phone in the Devices list. The code works once and expires in 5 minutes.</p>
                    <button type="button" class="btn btn-primary btn-sm" onclick="appDeviceCreateCode()"><i class="bi bi-key me-1"></i> Create pairing code</button>
                    <div id="app-device-code" class="h3 fw-bold mt-3 mb-0" style="letter-spacing: 2px;"></div>
                  </div>
                </div>
          </div>
        </div>

        <script> 
            function appDevicePost(data, done) {
                data.csrf_token = window.PP_CSRF_TOKEN || '';
                $.post("https://<?php echo $_SERVER['HTTP_HOST']?>/admin/dashboard", data, function (response) {
                    done(typeof response === 'string' ? JSON.parse(response) : response);
                });
            }

            function appDeviceCreateCode() {
                appDevicePost({ action: 'pp_app_device_code' }, function (result) {
                    document.getElementById('app-device-code').textContent = result.code || 'Could not create a code';
                });
            }

            function appDeviceUpdate(id, operation) {
                if (operation === 'revoke' && !confirm('Remove this phone? It will stop forwarding immediately.')) {
                    return;
                }
                appDevicePost({ action: 'pp_app_device_update', id: id, operation: operation }, function () {
                    loadDataTable(typeof currentPage === 'number' ? currentPage : 1);
                });
            }

            // A phone that paired while this panel was open shows up once it's closed.
            document.getElementById('connect-android-app').addEventListener('hidden.bs.offcanvas', function () {
                loadDataTable();
            });
        </script>
        
        
      <script>
            $("#select-all").click(function(){
                $(".select-box").prop('checked', this.checked);
                
                var selectedCount = $(".select-box:checked").length;
                
                document.querySelector("#bulk-manage-tab-counter").innerHTML = selectedCount;
                
                if(selectedCount === 0){
                    document.querySelector(".bulk-manage-tab").style.display = "none";
                }else{
                    document.querySelector(".bulk-manage-tab").style.display = "flex";
                }
            });
            
            $(document).on('click', '.select-box', function() {
                var selectedCount = $(".select-box:checked").length;
                
                document.querySelector("#bulk-manage-tab-counter").innerHTML = selectedCount;
                
                if(selectedCount === 0){
                    document.querySelector(".bulk-manage-tab").style.display = "none";
                }else{
                    document.querySelector(".bulk-manage-tab").style.display = "flex";
                }
            });
  
            function loadDataTable(page = 1) {
                var search = document.querySelector("#datatableSearch").value;
                var sms_status = document.querySelector(".sms_status").value;
                
                const tbody = $("#datatable");
                tbody.empty();
        
                 tbody.html(`              
                    <tr class="odd">
                        <td valign="top" colspan="10" class="dataTables_empty">
                            <div class="text-center p-4">
                                  <div class="spinner-border text-primary" role="status"> <span class="visually-hidden">Loading...</span> </div>
                            </div>
                        </td>
                    </tr>
                `);
                
              $.ajax({
                type: "POST",
                url: "/admin/dashboard",
                data: { action: "pp_sms_data_devices", page: page, search: search, sms_status: sms_status, csrf_token: window.PP_CSRF_TOKEN || '' },
                success: function (data) {
                    tbody.empty();
                    
                  const dedata = JSON.parse(data);
            
                  if (dedata.total === 0) {
                    tbody.html(`
                        <tr class="odd">
                            <td valign="top" colspan="10" class="dataTables_empty">
                                <div class="text-center p-4">
                                      <img class="mb-3" src="https://<?php echo $_SERVER['HTTP_HOST']?>/pp-external/assets/admin/assets/svg/illustrations/oc-error.svg" alt="Image Description" style="width: 10rem;" data-hs-theme-appearance="default">
                                      <img class="mb-3" src="https://<?php echo $_SERVER['HTTP_HOST']?>/pp-external/assets/admin/assets/svg/illustrations-light/oc-error.svg" alt="Image Description" style="width: 10rem;" data-hs-theme-appearance="dark">
                                      <p class="mb-0">No data to show</p>
                                </div>
                            </td>
                        </tr>
                    `);
                    totalPages = 1;
                  } else {
                    totalPages = dedata.totalPages;
                    dedata.data.forEach(tx => {
                      tbody.append(`
                        <tr>
                          <td><input type="checkbox" class="form-check-input select-box" value="${tx.id || "--"}"></td>
                          <td>${tx.name || "--"}</td>
                          <td>
                              ${(() => {
                                switch (tx.status) {
                                  case 'active':
                                    return tx.paused ? '<span class="badge bg-secondary">Paused</span>' : '<span class="badge bg-primary">Connected</span>';
                                  case 'pending':
                                    return '<span class="badge bg-warning text-dark">Waiting for approval</span>';
                                  case 'revoked':
                                    return '<span class="badge bg-danger">Removed</span>';
                                  default:
                                    return '<span class="badge bg-dark">Unknown</span>';
                                }
                              })()}
                          </td>
                          <td>${tx.match_code || "--"}</td>
                          <td>${tx.last_seen || "--"}</td>
                          <td>${tx.received}</td>
                          <td>${tx.forwarded}</td>
                          <td>${tx.missing}</td>
                          <td>${tx.created_at || "--"}</td>
                          <td>
                              ${tx.status === 'pending' ? `<button type="button" class="btn btn-success btn-sm me-1" onclick="appDeviceUpdate(${tx.id}, 'approve')">Approve</button>` : ''}
                              ${tx.status !== 'revoked' ? `<button type="button" class="btn btn-outline-danger btn-sm" onclick="appDeviceUpdate(${tx.id}, 'revoke')">Remove</button>` : ''}
                          </td>
                        </tr>
                      `);
                    });
                    totalPages = dedata.totalPages;
                    currentPage = dedata.currentPage;
                    
                    document.querySelector("#total-result").innerHTML = dedata.total;
                    document.querySelector("#showing-result").innerHTML = dedata.showing;
                    
                    renderPagination();
                  }
                }
              });
            }
            
            loadDataTable();
            
            function renderPagination() {
                const pageNumbers = document.getElementById("page-numbers");
                pageNumbers.innerHTML = "";
            
                // Show up to 5 pages for simplicity
                const start = Math.max(1, currentPage - 2);
                const end = Math.min(totalPages, currentPage + 2);
            
                for (let i = start; i <= end; i++) {
                    const li = document.createElement("li");
                    li.className = `paginate_item page-item ${i === currentPage ? 'active' : ''}`;
                    li.innerHTML = `<a class="paginate_button page-link" href="javascript:void(0)">${i}</a>`;
                    li.addEventListener("click", () => loadDataTable(i));
                    pageNumbers.appendChild(li);
                }
            
                // Enable/disable prev/next
                document.getElementById("prev-page").classList.toggle("disabled", currentPage === 1);
                document.getElementById("next-page").classList.toggle("disabled", currentPage === totalPages);
            }
            
            document.getElementById("prev-page").addEventListener("click", () => {
                if (currentPage > 1) loadDataTable(currentPage - 1);
            });
            
            document.getElementById("next-page").addEventListener("click", () => {
                if (currentPage < totalPages) loadDataTable(currentPage + 1);
            });
        
            function bulk_action(bclass, action){
                var btn_value = document.querySelector("."+bclass).innerHTML;
                
                $("."+bclass).html('<div class="spinner-border spinner-border-sm" role="status"> <span class="visually-hidden">Loading...</span> </div>');
                $("."+bclass).prop("disabled", true);
    
                var ids = [];
                $(".select-box:checked").each(function(){
                    ids.push($(this).val());
                });
            
                var selectedCount = ids.length;
            
                if (selectedCount === 0) {
                    $(".response-bulk-action").html('<div class="alert alert-danger" style="margin-top: 20px; margin-bottom: -1px;" role="alert">Please select at least one record to delete.</div>');
                }else{
                    var formData = new FormData();
                    formData.append('action', 'pp_bulk_action_devices');
                    formData.append('action_name', action);
                    formData.append('ids', ids);
                    formData.append('csrf_token', window.PP_CSRF_TOKEN || '');
                    
                    $.ajax({
                        type: "POST",
                        url: "/admin/sms-data-devices",
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function (data) {
                            console.log(data);
                            $("."+bclass).prop("disabled", false);
                            $("."+bclass).html(btn_value);
            
                            var response = JSON.parse(data);
        
                            if (response.status === "false") {
                                $(".response-bulk-action").html('<div class="alert alert-danger" style="margin-top: 20px; margin-bottom: -1px;" role="alert">'+response.message+'</div>');
                            } else {
                                $(".response-bulk-action").html('');
                                load_content('SMS Data','sms-data-devices','nav-btn-sms-data');
                            }
                        }
                    });
                }
            }
            
                function initialize111() {
                    new HSFormSearch('.js-form-search')
            
                    HSBsDropdown.init()
        
            
                    new HsNavScroller('.js-nav-scroller')
            
                    HSCore.components.HSDropzone.init('.js-dropzone')

            
                    HSBsDropdown.init()
                    
            
                    HSCore.components.HSTomSelect.init('.js-select')
            
                    HSCore.components.HSClipboard.init('.js-clipboard')
              }
              
              initialize111();
      </script>
<?php
    }
?>