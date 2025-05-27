<?php
// เช็คการรับข้อมูลจากฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password']; // รับรหัสผ่านจากฟอร์ม
    
    // ใช้ password_hash() เพื่อแฮชรหัสผ่าน
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    echo "รหัสผ่านที่แฮชแล้ว: " . $hashed_password; // แสดงรหัสผ่านที่แฮชแล้ว
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Password Hash</title>
</head>
<body>
    <h2>กรุณากรอกรหัสผ่านที่ต้องการแฮช</h2>
    <form method="post">
        <label for="password">รหัสผ่าน:</label>
        <input type="password" name="password" required>
        <button type="submit">แฮชรหัสผ่าน</button>
    </form>
</body>
</html>
<!-- SB-Admin2 CSS -->
    <link href="<?php echo BASE_URL; ?>/assets/sb-admin-2/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="<?php echo BASE_URL; ?>/assets/sb-admin-2/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/custom.css" rel="stylesheet">
    <script src="<?php echo BASE_URL; ?>/assets/sb-admin-2/vendor/jquery/jquery.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/sb-admin-2/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>