<?php
require 'db.php';
session_start();


if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

try {
    // Получаем данные текущего пользователя
    $user_stmt = $pdo->prepare("SELECT is_superuser, allowed_tables FROM user WHERE id = ?");
    $user_stmt->execute([$_SESSION['user_id']]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        throw new Exception("Пользователь не найден.");
    }

    $is_superuser = (bool) $user_data['is_superuser'];
    $allowed_tables = json_decode($user_data['allowed_tables'], true); // Преобразуем JSON в массив
    // Проверка результата
    if (is_array($allowed_tables) || $is_superuser) {

    } else {
        throw new Exception("Ошибка получения данных");
    }
    // Если пользователь не суперпользователь, проверяем доступные таблицы
    if (!$is_superuser) {
        if (empty($allowed_tables)) {
            throw new Exception("У пользователя нет доступа ни к одной таблице.");
        }
    }

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage();
    exit;
}

try {
    // Получаем список таблиц в зависимости от уровня доступа пользователя
    if ($is_superuser) {
        // Если суперпользователь, выбираем все таблицы кроме 'user'
        $tables_stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'marketplace' ");
    } else {
        // Если не суперпользователь, выбираем только те таблицы, которые разрешены пользователю
        $allowed_tables_placeholders = implode(', ', array_fill(0, count($allowed_tables), '?'));
        $tables_stmt = $pdo->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema = 'marketplace' AND table_name != 'user' AND table_name IN ($allowed_tables_placeholders)");
        $tables_stmt->execute($allowed_tables);
    }

    $tables = $tables_stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables)) {
        throw new Exception("Нет доступных таблиц для пользователя.");
    }

    $selected_table = $_GET['table'] ?? $tables[0];  // Дефолтная таблица

    // Получаем данные столбцов выбранной таблицы
    $columns_stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = 'marketplace' AND table_name = '$selected_table'");
    $columns = $columns_stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage();
    exit;
}

// Проверка доступа к выбранной таблице для не-суперпользователя
if (!$is_superuser && !in_array($selected_table, $allowed_tables)) {
    echo "У вас нет доступа к выбранной таблице.";
    exit;
}


// Функция для добавления, редактирования и удаления записи
function manageRecord($pdo, $table, $action, $data = [], $id = null)
{
    global $is_superuser, $allowed_tables;

    try {
        if ($table == 'user' && !$is_superuser) {
            echo "Ошибка!";
            exit;
        }

        // Проверяем доступ для не-суперпользователя
        if (!$is_superuser && !in_array($table, $allowed_tables)) {
            echo "У вас нет прав на выполнение действий с таблицей: $table";
            exit;
        }

        // Далее идет остальная логика для добавления, редактирования и удаления
        if ($action == 'add') {
            $keys = array_keys($data);
            $fields = implode(', ', $keys);
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $sql = "INSERT INTO $table ($fields) VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($data));
        } elseif ($action == 'edit' && $id !== null) {
            $set = implode(', ', array_map(function ($key) {
                return "$key = ?";
            }, array_keys($data)));
            $sql = "UPDATE $table SET $set WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge(array_values($data), [$id]));
        } elseif ($action == 'delete' && $id !== null) {
            $sql = "DELETE FROM $table WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
        }
    } catch (mysqli_sql_exception $ex) {
        echo "Ошибка базы данных при выполнении действия: " . $ex->getMessage();
        exit;
    } catch (Exception $ex) {
        echo "Ошибка " . $ex->getMessage();
        exit;
    }
}


try {
    // Поиск данных
    $search = $_GET['search'] ?? '';
    // Сортировка данных
    $sort_column = $_GET['sort_column'] ?? 'id';
    $sort_order = $_GET['sort_order'] ?? 'ASC';
} catch (Exception $ex) {
    echo '' . $ex->getMessage();
    exit;
}

// Обработка формы добавления записи
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_record'])) {

    try {
        $data = [];
        foreach ($columns as $column) {
            if ($column != 'id' && isset($_POST[$column])) {
                $data[$column] = $_POST[$column];
            }
        }
        manageRecord($pdo, $selected_table, 'add', $data);
    } catch (mysqli_sql_exception $ex) {
        echo "Ошибка базы данных при выполнении действия: " . $ex->getMessage();
        exit;
    } catch (Exception $ex) {
        echo "Ошибка " . $ex->getMessage();
        exit;
    }
}

// Обработка удаления записи
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $id = $_GET['delete'];
        manageRecord($pdo, $selected_table, 'delete', [], $id);
    } catch (mysqli_sql_exception $ex) {
        echo "Ошибка базы данных при выполнении действия: " . $ex->getMessage();
        exit;
    } catch (Exception $ex) {
        echo "Ошибка " . $ex->getMessage();
        exit;
    }
}

// Обработка редактирования записи
if (isset($_POST['edit_record']) && is_numeric($_POST['id'])) {
    try {
        $id = $_POST['id'];
        $data = [];
        foreach ($columns as $column) {
            if ($column != 'id' && isset($_POST[$column])) {
                $data[$column] = $_POST[$column];
            }
        }
        manageRecord($pdo, $selected_table, 'edit', $data, $id);
    } catch (mysqli_sql_exception $ex) {
        echo "Ошибка базы данных при выполнении действия: " . $ex->getMessage();
        exit;
    } catch (Exception $ex) {
        echo "Ошибка " . $ex->getMessage();
        exit;
    }

}

try {
    // Проверяем доступ к таблице
    if (!$is_superuser && !in_array($selected_table, $allowed_tables)) {
        echo "У вас нет доступа к этой таблице.";
        exit;
    }

    $user_id = $_SESSION['user_id'];
    if (!$is_superuser) {
        $sql = "SELECT * FROM $selected_table WHERE user_id = '$user_id'";
    } else {
        $sql = "SELECT * FROM $selected_table";

    }
    if ($search) {
        $search_conditions = implode(' OR ', array_map(function ($col) {
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
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage();
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
            <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>"
                placeholder="Введите запрос">
            <button type="submit" class="btn btn-primary">Найти</button>
        </div>
    </form>

    <h3>Данные таблицы <?= ucfirst($selected_table) ?>:</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <?php if ($is_superuser): ?>
                    <?php foreach ($columns as $column): ?>
                        <th>
                            <a href="?table=<?= $selected_table ?>&search=<?= htmlspecialchars($search) ?>&sort_column=<?= $column ?>&sort_order=<?= $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>"
                                class="text-decoration-none">
                                <?= htmlspecialchars($column) ?>
                            </a>
                        </th>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php foreach ($columns as $column): ?>
                        <?php if ($column != 'user_id'): ?>
                            <th>
                                <a href="?table=<?= $selected_table ?>&search=<?= htmlspecialchars($search) ?>&sort_column=<?= $column ?>&sort_order=<?= $sort_order === 'ASC' ? 'DESC' : 'ASC' ?>"
                                    class="text-decoration-none">
                                    <?= htmlspecialchars($column) ?>
                                </a>
                            </th>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data as $row): ?>
                <tr>
                    <form method="post">
                        <?php if ($is_superuser): ?>
                            <?php foreach ($row as $key => $value): ?>
                                <td>
                                    <?php if ($key == 'id'): ?>
                                        <?= htmlspecialchars($value) ?>
                                        <input type="hidden" name="id" value="<?= $value ?>">
                                    <?php else: ?>
                                        <input type="text" name="<?= $key ?>" class="form-control"
                                            value="<?= htmlspecialchars($value) ?>">
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach ($row as $key => $value): ?>
                                <?php if ($key != 'user_id'): ?>
                                    <td>
                                        <?php if ($key == 'id'): ?>
                                            <?= htmlspecialchars($value) ?>
                                            <input type="hidden" name="id" value="<?= $value ?>">
                                        <?php else: ?>
                                            <input type="text" name="<?= $key ?>" class="form-control"
                                                value="<?= htmlspecialchars($value) ?>">
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <td class="text-center">
                            <button type="submit" name="edit_record" class="btn btn-warning">Изменить</button>
                            <a href="?table=<?= $selected_table ?>&delete=<?= $row['id'] ?>"
                                class="btn btn-danger">Удалить</a>
                        </td>
                    </form>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>


    <h3>Добавить новую запись:</h3>
    <form method="post">
        <div class="mb-3">
            <?php if ($is_superuser): ?>
                <?php foreach ($columns as $column): ?>
                    <?php if ($column != 'id'): ?>
                        <label class="form-label"><?= htmlspecialchars($column) ?>:</label>
                        <input type="text" name="<?= htmlspecialchars($column) ?>" class="form-control mb-3" required>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <?php foreach ($columns as $column): ?>
                    <?php if ($column != 'id' && $column != 'user_id'): ?>
                        <label class="form-label"><?= htmlspecialchars($column) ?>:</label>
                        <input type="text" name="<?= htmlspecialchars($column) ?>" class="form-control mb-3" required>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
        <button type="submit" name="add_record" class="btn btn-success">Добавить запись</button>
    </form>

    <!-- Подключение Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>