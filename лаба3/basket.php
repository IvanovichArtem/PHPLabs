<?php
require 'db.php';
session_start();

function isLoggedIn()
{
    if ($_SESSION['role'] == 'seller') {
        echo "Нельзя зайти";
        exit;
    }
    return isset($_SESSION['user_id']);
}

function redirectToLogin()
{
    header("Location: index.php");
    exit;
}

function fetchBasket($userId, $pdo)
{
    try {
        // SQL-запрос для получения товаров в корзине текущего пользователя
        $stmt = $pdo->prepare("
            SELECT products.id, products.name, products.description, products.price, products.quantity AS available_quantity, basket.quantity
            FROM basket
            INNER JOIN products ON basket.product_id = products.id
            WHERE basket.user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return "Ошибка при получении данных о корзине: " . $e->getMessage();
    } catch (Exception $e) {
        return "" . $e->getMessage();
    }
}

function clearBasket($userId, $pdo)
{
    try {
        $stmt = $pdo->prepare("DELETE FROM basket WHERE user_id = ?");
        $stmt->execute([$userId]);
    } catch (PDOException $e) {
        return "Ошибка при очистке корзины: " . $e->getMessage();
    }
}

function createOrder($userId, $basket, $address, $pdo)
{
    try {
        $pdo->beginTransaction();

        foreach ($basket as $item) {
            // Проверка, достаточно ли товара на складе
            if ($item['quantity'] > $item['available_quantity']) {
                throw new Exception("Недостаточное количество товара: {$item['name']}. Доступно: {$item['available_quantity']}, требуется: {$item['quantity']}");
            }
            $price = $item['quantity'] * $item['price'];
            // Создание заказа
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, product_id, quantity, address, status, total_price) VALUES (?, ?, ?, ?, 'new', $price)");
            $stmt->execute([$userId, $item['id'], $item['quantity'], $address]);

            // Уменьшение количества товара на складе
            $stmt = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
            $stmt->execute([$item['quantity'], $item['id']]);
        }

        // Очищаем корзину
        clearBasket($userId, $pdo);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return $e->getMessage();
    }
}

function updateItemQuantity($userId, $productId, $quantity, $pdo)
{
    try {
        $stmt = $pdo->prepare("UPDATE basket SET quantity = ? WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$quantity, $userId, $productId]);
    } catch (PDOException $e) {
        return "Ошибка при обновлении количества товара: " . $e->getMessage();
    } catch (Exception $e) {
        return "" . $e->getMessage();
    }
}

function deleteItem($userId, $productId, $pdo)
{
    try {
        $stmt = $pdo->prepare("DELETE FROM basket WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$userId, $productId]);
    } catch (PDOException $e) {
        return "Ошибка при удалении товара: " . $e->getMessage();
    } catch (Exception $e) {
        return "" . $e->getMessage();
    }
}


if (!isLoggedIn()) {
    redirectToLogin();
}

// Получаем товары в корзине текущего пользователя
$basket = fetchBasket($_SESSION['user_id'], $pdo);

// Переменная для хранения общей стоимости всех товаров в корзине
$fullPrice = 0;

// Подсчет общей стоимости
if (is_array($basket)) {
    foreach ($basket as $item) {
        $fullPrice += $item['price'] * $item['quantity'];
    }
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $productId = $_POST['product_id'];
        $userId = $_SESSION['user_id'];

        if ($_POST['action'] === 'update_quantity') {
            $quantity = $_POST['quantity'];
            $result = updateItemQuantity($userId, $productId, $quantity, $pdo);
            if ($result !== null) {
                $message = 'Ошибка: ' . $result;
            } else {
                header(header: "Location: basket.php");
            }
        } elseif ($_POST['action'] === 'delete_item') {
            $result = deleteItem($userId, $productId, $pdo);
            if ($result !== null) {
                $message = 'Ошибка: ' . $result;
            } else {
                header(header: "Location: basket.php");
            }
        }
    } elseif (isset($_POST['order'])) {
        $address = $_POST['pickup_address'] ?? '';
        $result = createOrder($_SESSION['user_id'], $basket, $address, $pdo);
        if ($result === true) {
            $message = 'Ваш заказ был успешно создан!';
            header(header: "Location: basket.php");
        } else {
            $message = 'Ошибка: ' . $result;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Корзина</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <header class="bg-light py-3">
        <div class="container d-flex justify-content-between align-items-center">
            <h1>Моя корзина</h1>
            <div>
                <a href="order.php" class="btn btn-secondary mr-2">Заказы</a>
                <a href="buyer.php" class="btn btn-primary mr-2">Товары</a>
                <a href="logout.php" class="btn btn-danger">Выйти</a>
            </div>
        </div>
    </header>

    <div class="container mt-5">
        <h2 class="text-center">Ваши товары в корзине</h2>
        <?php if (!empty($message)): ?>
            <div class="alert alert-success" role="alert">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (is_string($basket)): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($basket) ?>
            </div>
        <?php elseif (empty($basket)): ?>
            <div class="alert alert-warning" role="alert">
                Ваша корзина пуста.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($basket as $item): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($item['name']) ?></h5>
                                <p class="card-text"><?= htmlspecialchars($item['description']) ?></p>
                                <p class="card-text"><strong>Цена:</strong> <?= htmlspecialchars($item['price']) ?> руб.</p>
                                <p class="card-text"><strong>Количество:</strong> <?= htmlspecialchars($item['quantity']) ?></p>
                                <p class="card-text"><strong>Итого за товар:</strong>
                                    <?= htmlspecialchars($item['price'] * $item['quantity']) ?> руб.</p>

                                <!-- Форма для обновления количества -->
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="update_quantity" min="1" max="100">
                                    <input type="hidden" name="product_id" value="<?= htmlspecialchars($item['id']) ?>">
                                    <div class="form-group">
                                        <label for="quantity_<?= htmlspecialchars($item['id']) ?>">Количество:</label>
                                        <input type="number" id="quantity_<?= htmlspecialchars($item['id']) ?>" name="quantity"
                                            value="<?= htmlspecialchars($item['quantity']) ?>" min="1" max='100'
                                            class="form-control" style="width: auto; display: inline-block;">
                                    </div>
                                    <button type="submit" class="btn btn-warning">Изменить количество</button>
                                </form>

                                <!-- Форма для удаления товара -->
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_item">
                                    <input type="hidden" name="product_id" value="<?= htmlspecialchars($item['id']) ?>">
                                    <button type="submit" class="btn btn-danger">Удалить</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
            <div class="alert alert-info mt-4">
                <h4>Общая стоимость: <?= htmlspecialchars($fullPrice) ?> руб.</h4>
            </div>

            <!-- Форма для оформления заказа -->
            <h3>Оформить заказ</h3>
            <form method="post">
                <!-- Блок для выбора адреса самовывоза -->
                <div id="pickup_address_block" class="form-group">
                    <input type="hidden" name="order" value="">
                    <label for="pickup_address">Выберите пункт самовывоза:</label>
                    <select name="pickup_address" id="address" class="form-control">
                        <option value="ул. Ленина, 1">ул. Ленина, 1</option>
                        <option value="ул. Пушкина, 25">ул. Пушкина, 25</option>
                        <option value="пр. Мира, 123">пр. Мира, 123</option>
                        <option value="ул. Гагарина, 50">ул. Гагарина, 50</option>
                    </select>
                </div>

                <!-- Добавьте name к кнопке -->
                <button type="submit" name="order" class="btn btn-primary">Оформить заказ</button>
            </form>


        <?php endif; ?>
    </div>
    <br><br>
    <script>
        // Проверка на недостаток товара и вывод сообщения
        <?php if (!empty($message) && strpos($message, 'Ошибка') !== false): ?>
            alert("<?= htmlspecialchars($message) ?>");
        <?php endif; ?>
    </script>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>

</html>