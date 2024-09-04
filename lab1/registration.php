<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "my_database";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Database 'my_database' not found.");
    }
} catch (Exception $e) {
    echo "<div style='color: red; font-weight: bold; font-size:30;'>Error: " . $e->getMessage() . "</div>";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);


    if (!empty($name) && !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param('sss', $name, $email, $hashedPassword);

            if ($stmt->execute()) {
                echo "New user created successfully";
            } else {
                echo "Error: " . $stmt->error;
            }

            $stmt->close();
        } catch (Exception $e) {
            echo "<div style='color: red; font-weight: bold; font-size:30;'>Error: " . $e->getMessage() . "</div>";
            exit();
        }
    } else {
        echo "Please enter a valid name and email.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User</title>
</head>

<body>
    <h1>Create New User</h1>
    <form method="post" action="">
        Name: <input type="text" name="username" required><br>
        Email: <input type="email" name="email" required><br>
        Password: <input type="password" name="password" required><br> <input type="submit" value="Submit">
    </form>

    <div> <a href = "http://localhost/PHPlabs/lab1/auth.php">Войти </a> </div>
</body>

</html>