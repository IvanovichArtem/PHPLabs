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

// Обработка выбора таблицы и CRUD операций
$data = [];
$message = "";
$table = "";
$primaryKey = "";


if (isset($_POST['table'])) {
    try {

        $table = $_POST['table'];
        if ($table === "") {
            $message = "Пожалуйста, выберите таблицу.";
        } else {
            $dataResult = $conn->query("SELECT * FROM `$table`");
            if ($dataResult) {
                $fields = $dataResult->fetch_fields();
                $primaryKey = $fields[0]->name;
                while ($row = $dataResult->fetch_assoc()) {
                    $data[] = $row;
                }
            } else {
                $message = "Ошибка запроса данных: " . $conn->error;
            }
        }
    } catch (mysqli_sql_exception $e) {
        $message = "" . $e->getMessage();
    } catch (Exception $e) {
        $message = "" . $e->getMessage();
    }
}

// Добавление новой записи
if (isset($_POST['create'])) {
    try {
        $columns = array_keys($_POST['data']);
        $values = array_map([$conn, 'real_escape_string'], array_values($_POST['data']));

        // Удаление id из списка колонок и значений, если он есть
        if (($key = array_search('id', $columns)) !== false) {
            unset($columns[$key]);
            unset($values[$key]);
        }

        $sql = "INSERT INTO `$table` (" . implode(", ", $columns) . ") VALUES ('" . implode("', '", $values) . "')";

        if ($conn->query($sql) === TRUE) {
            // Запрос для обновления данных после добавления записи
            $dataResult = $conn->query("SELECT * FROM `$table`");
            if ($dataResult) {
                $data = [];
                while ($row = $dataResult->fetch_assoc()) {
                    $data[] = $row;
                }
            } else {
                $message = "Ошибка обновления данных: " . $conn->error;
            }

            $message = "Новая запись успешно добавлена.";
        } else {
            $message = "Ошибка добавления записи: " . $conn->error;
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

// Обновление записи
if (isset($_POST['update'])) {
    $message = $table;
    try {
        $setClause = [];
        foreach ($_POST['data'] as $column => $value) {
            if ($column !== 'id') {
                $setClause[] = "$column = '" . $conn->real_escape_string($value) . "'";
            }
        }
        $id = $_POST['id'];
        $sql = "UPDATE `$table` SET " . implode(", ", $setClause) . " WHERE `$primaryKey` = $id";

        if ($conn->query($sql) === TRUE) {
            $message = "Запись успешно обновлена.";
            // Запрос для обновления данных после добавления записи
            $dataResult = $conn->query("SELECT * FROM `$table`");
            if ($dataResult) {
                $data = [];
                while ($row = $dataResult->fetch_assoc()) {
                    $data[] = $row;
                }
            } else {
                $message = "Ошибка обновления данных: " . $conn->error;
            }
        } else {
            $message = "Ошибка обновления записи: " . $conn->error;
        }
    } catch (mysqli_sql_exception $e) {
        $message = $e->getMessage() . ". Maybe it's renamed or deleted!";
    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

// Удаление записи
if (isset($_POST['delete'])) {
    try {
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            $id = intval($_POST['id']); // Приведение к целочисленному типу
            $sql = "DELETE FROM `$table` WHERE `$primaryKey` = $id";
            if ($conn->query($sql) === TRUE) {
                $message = "Запись успешно удалена.";
                // Запрос для обновления данных после удаления записи
                $dataResult = $conn->query("SELECT * FROM `$table`");
                if ($dataResult) {
                    $data = [];
                    while ($row = $dataResult->fetch_assoc()) {
                        $data[] = $row;
                    }
                } else {
                    $message = "Ошибка обновления данных: " . $conn->error;
                }
            } else {
                $message = "Ошибка удаления записи: " . $conn->error;
            }
        } else {
            $message = "Ошибка: идентификатор записи отсутствует.";
        }
    } catch (mysqli_sql_exception $e) {
        $message = "" . $e->getMessage();
    } catch (Exception $e) {
        $message = "" . $e->getMessage();
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
            <?php foreach ($tables as $mtable): ?>
                <option value="<?= htmlspecialchars($mtable) ?>"><?= htmlspecialchars($mtable) ?></option>
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
                                <?php if (strpos($key, '_id') !== false): ?>
                                    <td><input type="text" disabled name="data[<?= htmlspecialchars($key) ?>]"
                                            value="<?= htmlspecialchars($cell) ?>"></td>
                                <?php else: ?>
                                    <!-- Обычные текстовые поля для других данных -->
                                    <td><input type="text" name="data[<?= htmlspecialchars($key) ?>]"
                                            value="<?= htmlspecialchars($cell) ?>"></td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <td>
                                <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($row[$primaryKey]) ?>">
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
            <?php
            $columns = array_keys($data[0]);
            $columns = array_slice($columns, 1); // Удаляем первый элемент из массива ключей
            ?>

            <?php foreach ($columns as $column): ?>
                <label><?= htmlspecialchars($column) ?>:</label>
                <input type="text" name="data[<?= htmlspecialchars($column) ?>]" value=""><br>
            <?php endforeach; ?>

            <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
            <button type="submit" name="create">Создать</button>
        </form>

    <?php endif; ?>

    <div><a href="logout.php">Выйти</a></div>

</body>

</html>