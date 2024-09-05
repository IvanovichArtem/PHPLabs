<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Некорректный email.";
    } elseif (strlen($password) < 6) {
        $error = "Пароль должен быть не менее 6 символов.";
    } elseif ($password !== $confirm_password) {
        $error = "Пароли не совпадают.";
    } else {
        $hash_password = password_hash($password, PASSWORD_BCRYPT);
        try{

            $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
            if ($stmt->execute([$email, $hash_password])) {
                header("Location: index.php");
                exit;
            } else {
                $error = "Ошибка при регистрации.";
            }
        }catch(PDOException $e){
            $error = "Ощибка БД: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Регистрация</title>
</head>
<body>
<h2>Регистрация</h2>
<?php if (!empty($error)): ?>
    <p style="color:red;"><?= $error ?></p>
<?php endif; ?>
<form method="post">
    <label>Email:</label><br>
    <input type="email" name="email" required><br><br>
    
    <label>Пароль:</label><br>
    <input type="password" name="password" required><br><br>

    <label>Подтвердите пароль:</label><br>
    <input type="password" name="confirm_password" required><br><br>

    <button type="submit">Зарегистрироваться</button>
</form>
</body>
</html>
