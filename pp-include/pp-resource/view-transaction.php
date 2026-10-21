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
        if(isset($_GET['ref'])){
            $ref = escape_string($_GET['ref']);

            $response_transaction_checker = json_decode(getData($db_prefix.'transaction','WHERE id= "'.$ref.'" AND transaction_status NOT IN ("initialize")'),true);
            if($response_transaction_checker['status'] == true){
                
            }else{
?>
                <script>
                    load_content('Transaction','transaction','nav-btn-transaction');
                </script>
<?php
                exit();
            }
        }else{
            echo 'System is under maintenance. Please try again later.';
            exit();
        }
?>
        <!-- Page Header -->
        <div class="page-header">
          <div class="row align-items-end">
            <div class="col-sm mb-2 mb-sm-0">
              <h1 class="page-header-title">View Transaction</h1>
            </div>
            <!-- End Col -->
            
            <div class="col-auto">
                <div class="d-sm-flex justify-content-lg-end align-items-sm-center">
                  <a class="btn btn-outline-danger btn-sm mb-2 mb-sm-0 me-2 btn-bulk-action-delete" href="javascript:void(0)" onclick="bulk_action('btn-bulk-action-delete', 'delete')">
                    <i class="bi-trash"></i> Delete
                  </a>
                  <a class="btn btn-primary btn-sm mb-2 mb-sm-0 me-2 btn-bulk-action-approved" href="javascript:void(0)" onclick="bulk_action('btn-bulk-action-approved', 'approved')">
                    <i class="bi-check-circle"></i> Approved
                  </a>
                  
                  <a class="btn btn-warning btn-sm mb-2 mb-sm-0 me-2 btn-bulk-action-refund" href="javascript:void(0)" onclick="bulk_action('btn-bulk-action-refund', 'refund')">
                    <i class="bi-arrow-90deg-left"></i> Refund
                  </a>
                  
                  <a class="btn btn-success btn-sm mb-2 mb-sm-0 me-2 btn-bulk-action-send-ipn" href="javascript:void(0)" onclick="bulk_action('btn-bulk-action-send-ipn', 'send-ipn')">
                    <i class="bi-send"></i> Send IPN
                  </a>
                </div>
            </div>
          </div>
          <!-- End Row -->
        </div>
        <!-- End Page Header -->
        
        <div class="row justify-content-center">
          <div class="col-lg-9">
            <div class="d-grid gap-3 gap-lg-5">
              <!-- Card -->
              <span class="response-bulk-action"></span>
              
              <div class="card">
                <div class="card-header">
                  <h2 class="card-title h4">Transaction Status</h2>
                </div>
                <!-- Body -->
                <div class="card-body">
                  <!-- Form -->
                    <!-- Form -->
                    <div class="row">
                      <div class="col-sm-4 mb-3">
                        <label class="form-label fw-medium text-dark">Payment ID</label>
                        <div class="fw-bold text-dark"><?php echo $response_transaction_checker['response'][0]['pp_id']?></div>
                      </div>
                      
                      <div class="col-sm-4 mb-3">
                        <label class="form-label fw-medium text-dark">Date</label>
                        <div class="fw-bold text-dark"><?php echo convertDateTime($response_transaction_checker['response'][0]['created_at'])?></div>
                      </div>
                      
                      <div class="col-sm-4">
                        <label class="form-label fw-medium text-dark">Status</label>
                        <div>
                            <?php
                                if ($response_transaction_checker['response'][0]['transaction_status'] == 'completed') {
                                    echo '<span class="badge bg-primary">Completed</span>';
                                } elseif ($response_transaction_checker['response'][0]['transaction_status'] == 'pending') {
                                    echo '<span class="badge bg-warning text-dark">Pending</span>';
                                } elseif ($response_transaction_checker['response'][0]['transaction_status'] == 'failed') {
                                    echo '<span class="badge bg-danger">Failed</span>';
                                } elseif ($response_transaction_checker['response'][0]['transaction_status'] == 'refunded') {
                                    echo '<span class="badge bg-warning text-dark">Refunded</span>';
                                } else {
                                    echo '<span class="badge bg-dark">Unknown</span>';
                                }
                            ?>
                        </div>
                      </div>
                    </div>
                    <!-- End Form -->
                </div>
                <!-- End Body -->
              </div>
              <!-- End Card -->
        

              <!-- Card -->
              <div class="card">
                <div class="card-header">
                    <ul class="nav nav-pills justify-content-left" role="tablist">
                      <li class="nav-item">
                        <a class="nav-link active" id="nav-one-transaction-details-tab" data-bs-toggle="pill" href="#nav-one-transaction-details" role="tab" aria-controls="nav-one-transaction-details" aria-selected="true" style="padding: 10px;">
                          <div class="d-flex align-items-center">
                            <i class="bi-receipt" style="margin-right: 5px;"></i> Transaction Details
                          </div>
                        </a>
                      </li>
                      <li class="nav-item">
                        <a class="nav-link" id="nav-two-customer-tab" data-bs-toggle="pill" href="#nav-two-customer" role="tab" aria-controls="nav-two-customer" aria-selected="false" style="padding: 10px;">
                          <div class="d-flex align-items-center">
                            <i class="bi-people" style="margin-right: 5px;"></i> Customer
                          </div>
                        </a>
                      </li>
                      
                      <?php
                          if($response_transaction_checker['response'][0]['transaction_product_name'] !== "--"){
                      ?>
                              <li class="nav-item">
                                <a class="nav-link" id="nav-two-product-tab" data-bs-toggle="pill" href="#nav-two-product" role="tab" aria-controls="nav-two-product" aria-selected="false" style="padding: 10px;">
                                  <div class="d-flex align-items-center">
                                    <i class="bi-handbag-fill" style="margin-right: 5px;"></i> Product
                                  </div>
                                </a>
                              </li>
                      <?php
                          }
                      ?>
                    </ul>
                </div>
                <!-- Body -->
                <div class="card-body">
                    <div class="tab-content">
                      <div class="tab-pane fade show active" id="nav-one-transaction-details" role="tabpanel" aria-labelledby="nav-one-transaction-details-tab">
                          <div class="card">
                            <div class="card-header">
                                <h2 class="card-title h4">Transaction Information</h2>
                            </div>

                            <div class="card-body">
                                <div class="row">
                                  <div class="col-sm-4 mb-3">
                                    <label class="form-label fw-medium text-dark">Payment Method</label>
                                    <div class="fw-bold text-dark"><?php echo $response_transaction_checker['response'][0]['payment_method']?></div>
                                  </div>
                                  
                                  <div class="col-sm-4 mb-3">
                                    <label class="form-label fw-medium text-dark">Payment Curreny</label>
                                    <div class="fw-bold text-dark"><?php echo $response_transaction_checker['response'][0]['transaction_currency']?></div>
                                  </div>
                                  
                                  <div class="col-sm-4 mb-3">
                                    <label class="form-label fw-medium text-dark">Sender</label>
                                    <div class="fw-bold text-dark"><?php echo $response_transaction_checker['response'][0]['payment_sender_number']?></div>
                                  </div>
                                  
                                  <?php
                                      if($response_transaction_checker['response'][0]['payment_verify_way'] == "slip"){
                                  ?>
                                          <div class="col-sm-4 mb-3">
                                            <label class="form-label fw-medium text-dark">Transaction Slip</label><br>
                                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#transaction-slip">View</button>
                                          </div>
                                                                              
                                            <div class="modal fade" id="transaction-slip" tabindex="-1" role="dialog" aria-labelledby="transaction-slip" aria-hidden="true">
                                              <div class="modal-dialog" role="document">
                                                <div class="modal-content">
                                                  <div class="modal-header">
                                                    <h5 class="modal-title" id="transaction-slip">Transaction Slip</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                  </div>
                                                  <div class="modal-body">
                                                      <span class="response-transaction-slip"></span>
                                                  </div>
                                                  <div class="modal-footer">
                                                    <button type="button" class="btn btn-white" data-bs-dismiss="modal">Close</button>
                                                  </div>
                                                </div>
                                              </div>
                                            </div>
                                            
                                            <script>
                                                function load_transaction_slip(){
                                                    <?php
                                                        $slip_url = (string) ($response_transaction_checker['response'][0]['payment_verify_id'] ?? '');
                                                        if (preg_match('#^https?://[^\s"\'<>]+\.pdf$#i', $slip_url)) {
                                                            $slip_url = htmlspecialchars($slip_url, ENT_QUOTES);
                                                    ?>
                                                    $('.response-transaction-slip').html('<iframe src="<?php echo $slip_url?>" style="width:100%;height:420px;border:0;"></iframe><center><a href="<?php echo $slip_url?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm mt-2">Open PDF</a></center>');
                                                    return;
                                                    <?php
                                                        }
                                                    ?>
                                                    $('.response-transaction-slip').html('<center><img src="<?php if(isset($response_transaction_checker['response'][0]['payment_verify_id'])){if($response_transaction_checker['response'][0]['payment_verify_id'] == "--"){echo 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';}else{echo $response_transaction_checker['response'][0]['payment_verify_id'];};}else{echo 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';}?>" style="margin-top: 10px;max-width: 200px;"></center>');
                                                }
                                                load_transaction_slip();
                                            </script>
                                  <?php
                                      }else{
                                  ?>
                                          <div class="col-sm-4 mb-3">
                                            <label class="form-label fw-medium text-dark">Transaction Id</label>
                                            <div class="fw-bold text-dark"><?php echo $response_transaction_checker['response'][0]['payment_verify_id']?></div>
                                          </div>
                                  <?php
                                      }
                                  ?>
                                </div>
                                <!-- End Form -->
                            </div>
                          </div>

                          <div class="card mt-3">
                            <div class="card-header">
                                <h2 class="card-title h4">Financial Details</h2>
                            </div>

                            <div class="card-body">
                                <div class="row">
                                  <div class="col-sm-4 mb-3">
                                    <label class="form-label fw-medium text-dark">Amount</label>
                                    <div class="fw-bold text-dark"><?php echo $response_transaction_checker['response'][0]['transaction_currency'].' '.number_format($response_transaction_checker['response'][0]['transaction_amount'],2)?></div>
                                  </div>
                                  
                                  <div class="col-sm-4 mb-3">
                                    <label class="form-label fw-medium text-dark">Processing Fee</label>
                                    <div class="fw-bold text-dark"><?php echo $response_transaction_checker['response'][0]['transaction_currency'].' '.number_format($response_transaction_checker['response'][0]['transaction_fee'],2)?></div>
                                  </div>
                                  
                                  <div class="col-sm-4 mb-3">
                                    <label class="form-label fw-medium text-dark">Total amount</label>
                                    <div class="fw-bold text-dark"><?php echo $response_transaction_checker['response'][0]['transaction_currency'].' '.number_format($response_transaction_checker['response'][0]['transaction_amount']+$response_transaction_checker['response'][0]['transaction_fee'],2)?></div>
                                  </div>

                                  <div class="col-sm-4 mb-3">
                                    <label class="form-label fw-medium text-dark">Refunded amount</label>
                                    <div class="fw-bold text-dark"><?php echo $response_transaction_checker['response'][0]['transaction_currency'].' '.number_format($response_transaction_checker['response'][0]['transaction_refund_amount'],2)?></div>
                                  </div>
                                  
                                  <div class="col-sm-4 mb-3">
                                    <label class="form-label fw-medium text-dark">Net Amount</label>
                                    <div class="fw-bold text-dark"><strong><?php echo $response_transaction_checker['response'][0]['transaction_currency'].' '.number_format($response_transaction_checker['response'][0]['transaction_amount']+$response_transaction_checker['response'][0]['transaction_fee']-$response_transaction_checker['response'][0]['transaction_refund_amount'],2)?></strong></div>
                                  </div>
                                </div>
                            </div>
                          </div>
                      </div>
                    
                      <div class="tab-pane fade" id="nav-two-customer" role="tabpanel" aria-labelledby="nav-two-customer-tab">
                            <div class="row">
                              <div class="col-sm-4 mb-3">
                                <label class="form-label fw-medium text-dark">Name</label>
                                <div class="fw-bold text-dark"><?php echo $response_transaction_checker['response'][0]['c_name']?></div>
                              </div>
                              
                              <div class="col-sm-4 mb-3">
                                <label class="form-label fw-medium text-dark">Email or Mobile</label>
                                <div class="fw-bold text-dark"><?php echo $response_transaction_checker['response'][0]['c_email_mobile']?></div>
                              </div>
                            </div>
                      </div>
                      
                      <?php
                          if($response_transaction_checker['response'][0]['transaction_product_name'] !== "--"){
                      ?>
                              <div class="tab-pane fade" id="nav-two-product" role="tabpanel" aria-labelledby="nav-two-product-tab">
                                    <div class="row">
                                      <div class="col-sm-6 mb-3">
                                        <label class="form-label fw-medium text-dark">Name</label>
                                        <div class="fw-bold text-dark"><?php echo $response_transaction_checker['response'][0]['transaction_product_name']?></div>
                                      </div>
                                      
                                      <div class="col-sm-6 mb-3">
                                        <label class="form-label fw-medium text-dark">Description</label>
                                        <div class="fw-bold text-dark"><?php echo $response_transaction_checker['response'][0]['transaction_product_description']?></div>
                                      </div>
                                      
                                        <?php
                                            $raw = $response_transaction_checker['response'][0]['transaction_product_meta'];
                                            $first_decode = json_decode($raw, true);
                                        
                                            // Handle possible double-encoding
                                            if (is_string($first_decode)) {
                                                $transaction_product_meta = json_decode($first_decode, true);
                                            } else {
                                                $transaction_product_meta = $first_decode;
                                            }
                                        
                                            if (is_array($transaction_product_meta)) {
                                                foreach ($transaction_product_meta as $key => $value) {
                                        ?>
                                                    <div class="col-sm-6 mb-3">
                                                        <label class="form-label fw-medium text-dark"><?php echo htmlspecialchars(str_replace('_', ' ', $key)); ?></label>
                                                        <div class="fw-bold text-dark">
                                                            <?php
                                                                if (is_array($value)) {
                                                                    // Handle arrays like Screenshot
                                                                    foreach ($value as $item) {
                                                                        if (filter_var($item, FILTER_VALIDATE_URL)) {
                                                                            echo "<a href='" . htmlspecialchars($item) . "' target='_blank'>View Image</a><br>";
                                                                        } else {
                                                                            echo htmlspecialchars(json_encode($item));
                                                                        }
                                                                    }
                                                                } else {
                                                                    echo htmlspecialchars($value);
                                                                }
                                                            ?>
                                                        </div>
                                                    </div>
                                        <?php
                                                }
                                            } else {
                                                echo "<div class='text-danger'>Invalid transaction product meta data.</div>";
                                            }
                                        ?>
                                    </div>
                              </div>
                      <?php
                          }
                      ?>
                    </div>
                </div>
              </div>
              <!-- End Card -->
              
            <div id="stickyBlockEndPoint"></div>
          </div>
        </div>
        <!-- End Row -->
        
        <script> 
            function bulk_action(bclass, action){
                var btn_value = document.querySelector("."+bclass).innerHTML;
                
                $("."+bclass).html('<div class="spinner-border spinner-border-sm" role="status"> <span class="visually-hidden">Loading...</span> </div>');
                $("."+bclass).prop("disabled", true);
    
                var ids = [<?php echo $_GET['ref'];?>];

                var formData = new FormData();
formData.append('action', 'pp_bulk_action_transaction');
                        formData.append('action_name', action);
                        formData.append('ids', ids);
                        formData.append('csrf_token', window.PP_CSRF_TOKEN || '');
                        
                        $.ajax({
                            type: "POST",
                            url: "/admin/transaction",
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
                            load_content('Transaction','view-transaction?ref=<?php echo $_GET['ref'];?>','nav-btn-transaction');
                        }
                    }
                });
            }
        </script>
<?php
    }
?>