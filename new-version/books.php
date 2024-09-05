<?php
require 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

try {
    // Получаем список таблиц кроме таблицы users
    $tables_stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'bookstore' AND table_name != 'users'");
    $tables = $tables_stmt->fetchAll(PDO::FETCH_COLUMN);
    $selected_table = $_GET['table'] ?? $tables[0];  // Дефолтная таблица
    
    // Получаем данные столбцов выбранной таблицы
    $columns_stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = 'bookstore' AND table_name = '$selected_table'");
    $columns = $columns_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Получаем информацию о внешних ключах
    $foreign_keys_stmt = $pdo->query("
        SELECT 
            k.COLUMN_NAME, 
            k.REFERENCED_TABLE_NAME, 
            k.REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE k
        WHERE k.TABLE_SCHEMA = 'bookstore' 
          AND k.TABLE_NAME = '$selected_table' 
          AND k.REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $foreign_keys = $foreign_keys_stmt->fetchAll(PDO::FETCH_ASSOC);
    $foreign_columns = array_column($foreign_keys, 'COLUMN_NAME');
    
    // Для каждого внешнего ключа получаем данные из связанной таблицы
    $foreign_data = [];
    foreach ($foreign_keys as $foreign_key) {
        $referenced_table = $foreign_key['REFERENCED_TABLE_NAME'];
        $referenced_column = $foreign_key['REFERENCED_COLUMN_NAME'];
        
        $stmt = $pdo->query("SELECT $referenced_column, name FROM $referenced_table");
        $foreign_data[$foreign_key['COLUMN_NAME']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch(PDOException $e) {
    echo "Ошибка БД: " . $e->getMessage();
}

// Функция для добавления, редактирования и удаления записи
function manageRecord($pdo, $table, $action, $data = [], $id = null) {
    try {
        if ($action == 'add') {
            $keys = array_keys($data);
            $fields = implode(', ', $keys);
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $sql = "INSERT INTO $table ($fields) VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($data));
        } elseif ($action == 'edit' && $id !== null) {
            $set = implode(', ', array_map(function($key) { return "$key = ?"; }, array_keys($data)));
            $sql = "UPDATE $table SET $set WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge(array_values($data), [$id]));
        } elseif ($action == 'delete' && $id !== null) {
            $sql = "DELETE FROM $table WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
        }
    } catch (PDOException $e) {
        echo "Ошибка базы данных при выполнении действия: " . $e->getMessage();
        exit;
    }
}

// Поиск данных
$search = $_GET['search'] ?? '';

// Сортировка данных
$sort_column = $_GET['sort_column'] ?? 'id';
$sort_order = $_GET['sort_order'] ?? 'ASC'; // Значения могут быть ASC или DESC

// Обработка формы добавления записи
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_record'])) {
    $data = [];
    foreach ($columns as $column) {
        if ($column != 'id' && isset($_POST[$column])) {
            $data[$column] = $_POST[$column];
        }
    }
    manageRecord($pdo, $selected_table, 'add', $data);
}

// Обработка удаления записи
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    manageRecord($pdo, $selected_table, 'delete', [], $id);
}

// Обработка редактирования записи
if (isset($_POST['edit_record']) && is_numeric($_POST['id'])) {
    $id = $_POST['id'];
    $data = [];
    foreach ($columns as $column) {
        if ($column != 'id' && isset($_POST[$column])) {
            $data[$column] = $_POST[$column];
        }
    }
    manageRecord($pdo, $selected_table, 'edit', $data, $id);
}

// Получаем данные из выбранной таблицы с учетом поиска и сортировки
try {
    $sql = "SELECT * FROM $selected_table";
    if ($search) {
        $search_conditions = implode(' OR ', array_map(function($col) {
            return "$col LIKE ?";
        }, $columns));
        $sql .= " WHERE $search_conditions";
    }
    $sql .= " ORDER BY $sort_column $sort_order";

    $data_stmt = $pdo->prepare($sql);
    if ($search) {
        $search_values = array_fill(0, count($columns), "%$search%");
        $data_stmt->execute($search_values);
    } else {
        $data_stmt->execute();
    }

    $data = $data_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Получаем список авторов для формы добавления книги, если выбрана таблица books
    if ($selected_table == 'books') {
        $authors_stmt = $pdo->query("SELECT * FROM authors");
        $authors = $authors_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch(PDOException $e) {
    echo "Ошибка базы данных при выполнении действия: " . $e->getMessage();
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление таблицами</title>
</head>
<body>
<h2>Добро пожаловать!</h2>
<a href="logout.php">Выйти</a>

<h3>Выберите таблицу:</h3>
<form method="get">
    <select name="table" onchange="this.form.submit()">
        <?php foreach ($tables as $table): ?>
            <option value="<?= $table ?>" <?= $selected_table == $table ? 'selected' : '' ?>><?= ucfirst($table) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<h3>Поиск по таблице <?= ucfirst($selected_table) ?>:</h3>
<form method="get">
    <input type="hidden" name="table" value="<?= $selected_table ?>">
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Введите запрос">
    <button type="submit">Найти</button>
</form>

<h3>Данные таблицы <?= ucfirst($selected_table) ?>:</h3>
<table border="1">
    <tr>
        <?php foreach ($columns as $column): ?>
            <th>
                <a href="?table=<?= $selected_table ?>&search=<?= htmlspecialchars($search) ?>&sort_column=<?= $column ?>&sort_order=<?= $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>">
                    <?= $column ?>
                </a>
            </th>
        <?php endforeach; ?>
        <th>Действия</th>
    </tr>
    <?php foreach ($data as $row): ?>
        <tr>
            <form method="post">
                <?php foreach ($row as $key => $value): ?>
                    <td>
                        <?php if ($key == 'id'): ?>
                            <?= htmlspecialchars($value) ?>
                            <input type="hidden" name="id" value="<?= $value ?>">
                        <?php else: ?>
                            <input type="text" name="<?= $key ?>" value="<?= htmlspecialchars($value) ?>">
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
                <td>
                    <button type="submit" name="edit_record">Изменить</button>
                    <a href="?table=<?= $selected_table ?>&delete=<?= $row['id'] ?>">Удалить</a>
                </td>
            </form>
        </tr>
    <?php endforeach; ?>
</table>

<h3>Добавить новую запись:</h3>
<form method="post">
    <?php foreach ($columns as $column): ?>
        <?php if ($column != 'id'): ?>
            <label><?= $column ?>:</label><br>
            <?php if (in_array($column, $foreign_columns)): ?>
                <!-- Если поле является внешним ключом, генерируем выпадающий список -->
                <select name="<?= $column ?>" required>
                    <?php foreach ($foreign_data[$column] as $option): ?>
                        <option value="<?= $option[$foreign_keys[array_search($column, $foreign_columns)]['REFERENCED_COLUMN_NAME']] ?>">
                            <?= $option['name'] ?>
                        </option>
                    <?php endforeach; ?>
                </select><br><br>
            <?php else: ?>
                <!-- Обычное текстовое поле для других данных -->
                <input type="text" name="<?= $column ?>" required><br><br>
            <?php endif; ?>
        <?php endif; ?>
    <?php endforeach; ?>
    <button type="submit" name="add_record">Добавить запись</button>
</form>

</body>
</html>
