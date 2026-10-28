<?php
// api.php - Main Router
require_once __DIR__ . '/api_core/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Routing to Auth Handler
if (in_array($action, ['register', 'verify_otp', 'resend_otp', 'forgot_password', 'reset_password', 'login', 'google_login', 'logout', 'check_session'])) {
    require_once __DIR__ . '/api_core/auth_handler.php';
}
// Routing to Order Handler
else if (in_array($action, ['create_order', 'upload_payment', 'get_orders', 'update_order_status', 'midtrans_notification', 'check_payment_status', 'admin_stats'])) {
    require_once __DIR__ . '/api_core/order_handler.php';
}
// Routing to Template/Invitation Handler
else if (in_array($action, ['get_templates', 'save_template', 'delete_template', 'save_invitation', 'get_my_invitation', 'get_invitation', 'submit_rsvp', 'get_rsvp'])) {
    require_once __DIR__ . '/api_core/template_handler.php';
}
// Invalid action
else {
    echo json_encode(["success" => false, "message" => "Aksi tidak valid"]);
}
?>
