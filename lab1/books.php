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
        if ($table == 'users'){
            echo "НЕЛЬЗЯ";
            exit;
        }
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
    } catch (mysqli_sql_exception $ex){
        echo "Ошибка базы данных при выполнении действия: " . $ex->getMessage();
        exit;
    }catch (Exception $ex){
        echo "Ошибка выполнения " . $ex->getMessage(); 
    }
    
}

// Поиск данных
$search = $_GET['search'] ?? '';

// Сортировка данных
$sort_column = $_GET['sort_column'] ?? 'id';
$sort_order = $_GET['sort_order'] ?? 'ASC'; 

// Обработка формы добавления записи
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_record'])) {

    try{

        $data = [];
        foreach ($columns as $column) {
            if ($column != 'id' && isset($_POST[$column])) {
                $data[$column] = $_POST[$column];
            }
        }
        manageRecord($pdo, $selected_table, 'add', $data);

    }catch (mysqli_sql_exception $ex){
        echo "Ошибка базы данных при выполнении действия: " . $ex->getMessage();
    exit;
    } catch(Exception $ex){
        echo "Ошибка выполнения " . $ex->getMessage(); 
        exit;
    }
}

// Обработка удаления записи

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {

    try{

        $id = $_GET['delete'];
        manageRecord($pdo, $selected_table, 'delete', [], $id);
    }catch(mysqli_sql_exception $ex){
        echo "Ошибка базы данных при выполнении действия: " . $ex->getMessage();
    exit;
    }catch (Exception $ex){
        echo "Ошибка выполнения " . $ex->getMessage(); 
        exit;
    }
}

// Обработка редактирования записи
if (isset($_POST['edit_record']) && is_numeric($_POST['id'])) {
    try{

        $id = $_POST['id'];
        $data = [];
        foreach ($columns as $column) {
            if ($column != 'id' && isset($_POST[$column])) {
                $data[$column] = $_POST[$column];
            }
        }
        manageRecord($pdo, $selected_table, 'edit', $data, $id);
}catch (mysqli_sql_exception $ex){
    echo "Ошибка базы данных при выполнении действия: " . $ex->getMessage();
    exit;
}catch (Exception $ex){
    echo "Ошибка выполнения " . $ex->getMessage(); 
        exit;
}

}

// Получаем данные из выбранной таблицы с учетом поиска и сортировки
try {
    if ($selected_table == 'users'){
            echo "НЕЛЬЗЯ";
            exit;
        }
    $sql = "SELECT * FROM $selected_table";
    if ($search) {
        $search_conditions = implode(' OR ', array_map(function($col) {
            return "$col LIKE ?";
        }, $columns));
        $sql .= " WHERE $search_conditions";
    }
    $sql .= " ORDER BY $sort_column $sort_order";
        echo $sql;
    $data_stmt = $pdo->prepare($sql);
    if ($search) {
        $search_values = array_fill(0, count($columns), "%$search%");
        $data_stmt->execute($search_values);
    } else {
        $data_stmt->execute();
    }

    $data = $data_stmt->fetchAll(PDO::FETCH_ASSOC);

// Проверяем наличие внешних ключей в выбранной таблице
$related_data = [];
if (!empty($foreign_keys)) {
    foreach ($foreign_keys as $foreign_key) {
        // Получаем связанные данные из внешних таблиц
        $referenced_table = $foreign_key['REFERENCED_TABLE_NAME'];
        $referenced_column = $foreign_key['REFERENCED_COLUMN_NAME'];

        // Получаем все данные из внешней таблицы (например, authors)
        $stmt = $pdo->query("SELECT $referenced_column, name FROM $referenced_table");
        $related_data[$foreign_key['COLUMN_NAME']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}


} catch(PDOException $e) {
    echo "Ошибка базы данных при выполнении действия: " . $e->getMessage();
    exit;
} catch(mysqli_sql_exception $e){
    echo "Ошибка базы данных при выполнении действия: " . $e->getMessage();
    exit;
} catch(Exception $e){
echo "Ошибка выполнения " . $e->getMessage(); 
        exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Управление таблицами</title>
    <!-- Подключение Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">
<h2 class="text-center mb-4">Добро пожаловать!</h2>
<div class="d-flex justify-content-end mb-4">
    <a href="logout.php" class="btn btn-danger">Выйти</a>
</div>

<h3>Выберите таблицу:</h3>
<form method="get" class="mb-4">
    <select name="table" class="form-select w-50" onchange="this.form.submit()">
        <?php foreach ($tables as $table): ?>
            <option value="<?= $table ?>" <?= $selected_table == $table ? 'selected' : '' ?>>
                <?= ucfirst($table) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<h3>Поиск по таблице <?= ucfirst($selected_table) ?>:</h3>
<form method="get" class="mb-4">
    <input type="hidden" name="table" value="<?= $selected_table ?>">
    <div class="input-group w-50">
        <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Введите запрос">
        <button type="submit" class="btn btn-primary">Найти</button>
    </div>
</form>

<h3>Данные таблицы <?= ucfirst($selected_table) ?>:</h3>
<table class="table table-bordered">
    <thead>
        <tr>
            <?php foreach ($columns as $column): ?>
                <th>
                    <a href="?table=<?= $selected_table ?>&search=<?= htmlspecialchars($search) ?>&sort_column=<?= $column ?>&sort_order=<?= $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>" class="text-decoration-none">
                        <?= $column ?>
                    </a>
                </th>
            <?php endforeach; ?>
            <th>Действия</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($data as $row): ?>
            <tr>
                <form method="post">
                    <?php foreach ($row as $key => $value): ?>
                        <td>
                            <?php if ($key == 'id'): ?>
                                <?= htmlspecialchars($value) ?>
                                <input type="hidden" name="id" value="<?= $value ?>">
                            <?php else: ?>
                                <input type="text" name="<?= $key ?>" class="form-control" value="<?= htmlspecialchars($value) ?>">
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                    <td class="text-center">
                        <button type="submit" name="edit_record" class="btn btn-warning">Изменить</button>
                        <a href="?table=<?= $selected_table ?>&delete=<?= $row['id'] ?>" class="btn btn-danger">Удалить</a>
                    </td>
                </form>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<h3>Добавить новую запись:</h3>
<form method="post">
    <div class="mb-3">
        <?php foreach ($columns as $column): ?>
            <?php if ($column != 'id'): ?>
                <label class="form-label"><?= $column ?>:</label>
                <?php if (in_array($column, $foreign_columns)): ?>
                    <select name="<?= $column ?>" class="form-select mb-3" required>
                        <?php foreach ($foreign_data[$column] as $option): ?>
                            <option value="<?= $option[$foreign_keys[array_search($column, $foreign_columns)]['REFERENCED_COLUMN_NAME']] ?>">
                                <?= $option['name'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="text" name="<?= $column ?>" class="form-control mb-3" required>
                <?php endif; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <button type="submit" name="add_record" class="btn btn-success">Добавить запись</button>
</form>

<!-- Подключение Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
