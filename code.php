add_action( 'woocommerce_thankyou', function( $order_id ){
 $user_id = get_current_user_id();
 $driverLicense = get_user_meta($user_id, 'drivers_license', true);
   // echo get_user_meta($user_id, 'customer_id', true);

//$driverLicense = 'YOUR_DRIVER_LICENSE_HERE';
$authorization = getApiToken();
$client_id = 'client-id';

$url = 'https://api.treez.io/v2.0/dispensary/your-dispensary/customer/driverlicense/' . urlencode($driverLicense);

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

// Setting headers
$headers = [
    'Authorization: ' . $authorization,
    'client_id: ' . $client_id
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);

if ($response === false) {
    echo 'Error: ' . curl_error($ch);
} else {
    $responseData = json_decode($response, true);
    //echo "<pre>";print_r($responseData);die('test');
           if(isset($responseData['data']['customer_id'])){
                      $customerId = $responseData['data']['customer_id'];
                        $is_banned = $responseData['data']['banned'];
           //echo "<pre>";print_r($responseData['data']);die('test');
           $is_verified = $responseData['data']['verification_status'];
           
           
        if ($is_banned) {
        // Display an alert and redirect the user
        echo "<script>
                alert('You are banned from making purchases. Contact us for more details');
                window.location.href = 'https://your-site/contact-us/'; // Change this to the desired redirect URL
              </script>";
        $order = new WC_Order($order_id);
        $order->update_status('failed', 'Customer is banned.');
        return; // Exit the script since the customer is banned
    }
                      echo 'Customer ID: ' . $is_verified;
                      //$order = wc_get_order( $order_id );
                      //$order_id = 123; // Replace with your actual order ID
                      $order = new WC_Order($order_id);
                     // echo "<pre>";print_r($order);die('test');
                      // Extract necessary details from the order
                      $delivery_location = get_post_meta( $order_id, 'Delivery Location', true );
           //echo $delivery_location; die('jfhjfd');
                      $service_type = get_post_meta( $order_id, 'Delivery Type', true );
                      $serice_time = get_post_meta( $order_id, 'Select Time', true );
                      $order_date = $order->get_date_created();
                      $date_only = new DateTime($order_date);
                        echo $date_only->format('Y-m-d');
           
                      echo "test".$service_type;
                      $order_data = array(
                          'customer_id' => $customerId,
                          'items' => array()
                      );

                      $order_data['items'] = array(); // Initialize the items array

                      foreach ($order->get_items() as $item_id => $item) {
                          $product = $item->get_product();
                          
                          // Initialize the item array with basic details
                          $order_item = array(
                              'size_id' => $product->get_meta('size_id'), // Assuming you have a custom field for size ID
                              'quantity' => $item->get_quantity()
                          );
          // echo "<pre>";print_r($order_item);
                          // If there are additional sub-items or details within the product
                          // Let's assume these sub-items are stored in a custom field 'sub_items'
                          $sub_items = $product->get_meta('sub_items'); // This should return an array of sub-items
                          if (!empty($sub_items) && is_array($sub_items)) {
                              $order_item['sub_items'] = array();
                              
                              foreach ($sub_items as $sub_item) {
                                  // Assuming each sub-item is an associative array with relevant fields
                                  $order_item['sub_items'][] = array(
                                      'sub_item_id' => $sub_item['id'],
                                      'sub_item_quantity' => $sub_item['quantity'],
                                      'sub_item_description' => $sub_item['description'],
                                      // Add more sub-item details as needed
                                  );
                              }
                          }

                          // Add the item with its details to the order_data array
                          $order_data['items'][] = $order_item;
                      }

                      // Prepare the payload with the collected data
                    $payload_data = array(
                        'type' => $service_type, // Changed type to DELIVERY
                        'order_source' => 'ECOMMERCE',
                        'customer_id' => $customerId, // Replace with the actual customer ID if dynamic
                        'order_status' => ($is_verified == 'VERIFIED') ? 'AWAITING_PROCESSING' : $is_verified,
                        'items' => $order_data['items'],
                        'revenue_source' => 'your-dispensary online',
                        'scheduled_date' => $date_only->format('Y-m-d'). "T".$serice_time. ":00.000-00:00"
                    );
          // die('test');
                      // Check if delivery address exists
                      //$delivery_address = $order->get_meta('delivery_address');
                      if ($service_type == 'DELIVERY') {
                          $payload_data['delivery_address'] = array(
                              'street' => $delivery_location,
                              'city' => $order->get_billing_city(),
                              'county' => 'US',
                              'state' => $order->get_billing_state(),
                              'zip' => $order->get_billing_postcode()
                          );
                      }
                      echo $delivery_location;
 //echo "<pre>";print_r($payload_data);die('ddd');
                      // Encode payload to JSON
                      $payload = json_encode($payload_data);

                      // Send the payload via cURL
                      $curl = curl_init();

                      curl_setopt_array($curl, array(
                          CURLOPT_URL => 'https://api.treez.io/v2.0/dispensary/your-dispensary/ticket/detailticket',
                          CURLOPT_RETURNTRANSFER => true,
                          CURLOPT_ENCODING => '',
                          CURLOPT_MAXREDIRS => 10,
                          CURLOPT_TIMEOUT => 0,
                          CURLOPT_FOLLOWLOCATION => true,
                          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                          CURLOPT_CUSTOMREQUEST => 'POST',
                          CURLOPT_POSTFIELDS => $payload,
                          CURLOPT_HTTPHEADER => array(
                              'Authorization: ' . getApiToken(),
                              'client_id: client-id',
                              'Content-Type: application/json'
                          ),
                      ));

                      $response = curl_exec($curl);
                       $responseData_order = json_decode($response, true);
                      $purchase_limit = $responseData_order['resultReason'];
           
                    $result_code = $responseData_order['resultCode'];
                    if($result_code == 'SUCCESS'){
                        echo "<script>
            window.location.href = 'https://your-site/my-account/orders/'; // Change this to the desired redirect URL
          </script>";
                    }
                    
           if($result_code == 'FAIL'){
           
           echo "<script>
            alert('".$responseData_order['resultReason']."');
            window.location.href = 'https://your-site'; // Change this to the desired redirect URL
          </script>";
    $order = new WC_Order($order_id);
    $order->update_status('failed', 'Customer is banned.');
    return; // Exit the script since the customer is banned
           }
                      
                      //echo "<pre>";print_r($responseData_order);die('test');
                     
                         if($purchase_limit == 'PURCHASE_LIMIT_EXCEEDED'){
               echo "<script>
                alert('Purchase limit Exceeded. Please purchase as per limit');
                window.location.href = 'https://your-site'; // Change this to the desired redirect URL
              </script>";
        $order = new WC_Order($order_id);
        $order->update_status('failed', 'Customer is banned.');
        return; // Exit the script since the customer is banned
           }
                      

                      curl_close($curl);
                      echo $response;

                      
                   
               } else {
                   echo 'Customer ID not found';
               }
           }

           curl_close($ch);
               
           });
