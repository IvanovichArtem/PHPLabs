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

// Проверка авторизации
if (!isset($_SESSION['id'])) {
    header("Location: auth.php");
    exit();
}

// Получение списка таблиц
$tables = [];
$result = $conn->query("SELECT table_name FROM information_schema.tables WHERE table_schema = '$dbname' AND table_name != 'users';");
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

// Получение списка авторов (если таблица называется `author`)
$authors = [];
$authorResult = $conn->query("SELECT id, name FROM author");
while ($row = $authorResult->fetch_assoc()) {
    $authors[] = $row;
}

// Обработка выбора таблицы и CRUD операций
$data = [];
$message = "";
$table = "";

if (isset($_POST['table'])) {
    $table = $_POST['table'];

    if ($table === "") {
        $message = "Пожалуйста, выберите таблицу.";
    } else {
        $dataResult = $conn->query("SELECT * FROM `$table`");
        if ($dataResult) {
            while ($row = $dataResult->fetch_assoc()) {
                $data[] = $row;
            }
        } else {
            $message = "Ошибка запроса данных: " . $conn->error;
        }
    }
}

// Добавление новой записи
if (isset($_POST['create'])) {
    $columns = array_keys($_POST['data']);
    $values = array_map([$conn, 'real_escape_string'], array_values($_POST['data']));

    // Удаление id из списка колонок и значений, если он есть
    if (($key = array_search('id', $columns)) !== false) {
        unset($columns[$key]);
        unset($values[$key]);
    }

    $sql = "INSERT INTO `$table` (" . implode(", ", $columns) . ") VALUES ('" . implode("', '", $values) . "')";
    
    if ($conn->query($sql) === TRUE) {
        $message = "Новая запись успешно добавлена.";
    } else {
        $message = "Ошибка добавления записи: " . $conn->error;
    }
}

// Обновление записи
if (isset($_POST['update'])) {
    $setClause = [];
    foreach ($_POST['data'] as $column => $value) {
        if ($column !== 'id') {
            $setClause[] = "$column = '" . $conn->real_escape_string($value) . "'";
        }
    }
    $id = $_POST['id'];
    $sql = "UPDATE `$table` SET " . implode(", ", $setClause) . " WHERE id = $id";
    
    if ($conn->query($sql) === TRUE) {
        $message = "Запись успешно обновлена.";
    } else {
        $message = "Ошибка обновления записи: " . $conn->error;
    }
}

// Удаление записи
if (isset($_POST['delete'])) {
    $id = $_POST['id'];
    $sql = "DELETE FROM `$table` WHERE id = $id";
    
    if ($conn->query($sql) === TRUE) {
        $message = "Запись успешно удалена.";
    } else {
        $message = "Ошибка удаления записи: " . $conn->error;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRUD операции</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
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

        .success {
            color: green;
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
    <?php endif; ?>

    <?php if (!empty($data)): ?>
        <h2>Данные из таблицы: <?= htmlspecialchars($table) ?></h2>
        <table>
            <thead>
                <tr>
                    <?php foreach ($data[0] as $key => $value): ?>
                        <?php if ($key !== 'id'): ?>
                            <th><?= htmlspecialchars($key) ?></th>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <form method="POST">
                            <?php foreach ($row as $key => $cell): ?>
                                <?php if ($key === 'author_id'): ?>
                                    <td>
                                        <select name="data[<?= htmlspecialchars($key) ?>]">
                                            <?php foreach ($authors as $author): ?>
                                                <option value="<?= htmlspecialchars($author['id']) ?>" <?= $author['id'] == $cell ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($author['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                <?php elseif ($key !== 'id'): ?>
                                    <td><input type="text" name="data[<?= htmlspecialchars($key) ?>]" value="<?= htmlspecialchars($cell) ?>"></td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <td>
                                <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($row['id']) ?>">
                                <button type="submit" name="update">Обновить</button>
                                <button type="submit" name="delete">Удалить</button>
                            </td>
                        </form>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Добавить новую запись</h2>
        <form method="POST">
            <?php foreach (array_keys($data[0]) as $column): ?>
                <?php if ($column !== 'id'): ?>
                    <label><?= htmlspecialchars($column) ?>:</label>
                    <input type="text" name="data[<?= htmlspecialchars($column) ?>]" value=""><br>
                <?php endif; ?>
            <?php endforeach; ?>
            <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
            <button type="submit" name="create">Создать</button>
        </form>
    <?php endif; ?>

    <div><a href="logout.php">Выйти</a></div>

</body>

</html>