<?php
// Подключение к базе данных
$servername = "localhost"; // Замените на ваш сервер
$username = "root";     // Замените на ваше имя пользователя
$password = "";     // Замените на ваш пароль
$dbname = "my_database";       // Замените на имя вашей базы данных

$conn = new mysqli($servername, $username, $password, $dbname);

// Проверка подключения
if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}

// Получение списка таблиц
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

// Обработка выбора таблицы
// Обработка выбора таблицы
$data = [];
$message = "";
if (isset($_POST['table'])) {
    $table = $_POST['table'];

    if ($table === "") {
        $message = "Пожалуйста, выберите таблицу.";
    } else {
        $dataResult = $conn->query("SELECT * FROM `$table`");
        while ($row = $dataResult->fetch_assoc()) {
            $data[] = $row;
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Выбор таблицы</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        .message {
            color: red;
        }
    </style>
</head>

<body>

    <h1>Выберите таблицу</h1>
    <form method="POST">
        <select name="table">
            <option value="">-- Выберите таблицу --</option>
            <?php foreach ($tables as $table): ?>
                <option value="<?= htmlspecialchars($table) ?>"><?= htmlspecialchars($table) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Загрузить</button>
    </form>

    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php elseif (!empty($data)): ?>
        <h2>Данные из таблицы: <?= htmlspecialchars($table) ?></h2>
        <table>
            <thead>
                <tr>
                    <?php foreach ($data[0] as $key => $value): ?>
                        <th><?= htmlspecialchars($key) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                            <td><?= htmlspecialchars($cell) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</body>

</html>