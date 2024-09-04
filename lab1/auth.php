<?php
session_start();

// Подключение к базе данных
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "my_database";

$conn = new mysqli($servername, $username, $password, $dbname);

// Проверка подключения
if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}

// Обработка формы входа
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $stmt->bind_result($userId, $hashedPassword);
                $stmt->fetch();

                if (password_verify($password, $hashedPassword)) {
                    // Успешная авторизация
                    $_SESSION['id'] = $userId;
                    header("Location: tables.php");
                    exit();
                } else {
                    $error = "Неверный пароль. Пожалуйста, попробуйте снова.";
                }
            } else {
                $error = "Пользователь с таким email не найден.";
            }

            $stmt->close();
        } else {
            $error = "Неверный формат email.";
        }
    } else {
        $error = "Пожалуйста, введите email и пароль.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Авторизация</title>
</head>

<body>
    <h1>Авторизация</h1>

    <?php if (isset($error)): ?>
        <div style="color: red;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="">
        Email: <input type="email" name="email" required><br>
        Пароль: <input type="password" name="password" required><br>
        <input type="submit" name="login" value="Войти">
    </form>

    <div><a href="registration.php">Регистрация</a></div>
</body>

</html>