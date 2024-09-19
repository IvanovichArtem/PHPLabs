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

function getStatusDetails($status)
{
    switch ($status) {
        case 'new':
            return ['class' => 'badge badge-primary', 'icon' => 'fas fa-hourglass-start', 'text' => 'Новый'];
        case 'shipped':
            return ['class' => 'badge badge-info', 'icon' => 'fas fa-truck', 'text' => 'Отправлен'];
        case 'delivered':
            return ['class' => 'badge badge-success', 'icon' => 'fas fa-box', 'text' => 'Доставлен'];
        case 'returned':
            return ['class' => 'badge badge-warning', 'icon' => 'fas fa-undo', 'text' => 'Возвращен'];
        case 'completed':
            return ['class' => 'badge badge-dark', 'icon' => 'fas fa-check-circle', 'text' => 'Завершен'];
        case 'canceled':
            return ['class' => 'badge badge-danger', 'icon' => 'fas fa-times-circle', 'text' => 'Отменен'];
        default:
            return ['class' => 'badge badge-secondary', 'icon' => 'fas fa-question-circle', 'text' => 'Неизвестно'];
    }
}


function fetchUserOrders($userId, $pdo)
{
    try {
        $stmt = $pdo->prepare("
<<<<<<< HEAD
            SELECT orders.id, products.name, products.description, products.price*orders.quantity as total_price, orders.quantity, orders.address, orders.status FROM orders
=======
            SELECT orders.id, products.name, products.description, products.price*orders.quantity as total_price, orders.quantity, orders.address, orders.status
            FROM orders
>>>>>>> bb3e64e8051365956def824d197a1ad91e06449d
            INNER JOIN products ON orders.product_id = products.id
            WHERE orders.user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return "Ошибка при получении данных о заказах: " . $e->getMessage();
    } catch (Exception $e) {
        return "" . $e->getMessage();
    }
}



if (!isLoggedIn()) {
    redirectToLogin();
}

$userId = $_SESSION['user_id'];
$message = '';

$orders = fetchUserOrders($userId, $pdo);
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои заказы</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

</head>

<body>
    <header class="bg-light py-3">
        <div class="container d-flex justify-content-between align-items-center">
            <h1>Мои заказы</h1>
            <div>
                <a href="basket.php" class="btn btn-secondary mr-2">Корзина</a>
                <a href="buyer.php" class="btn btn-primary mr-2">Товары</a>
                <a href="logout.php" class="btn btn-danger">Выйти</a>
            </div>
        </div>
    </header>

    <div class="container mt-5">
        <h2 class="text-center">Ваши заказы</h2>
        <?php if (!empty($message)): ?>
            <div class="alert alert-success" role="alert">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        <?php if (is_string($orders)): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($orders) ?>
            </div>
        <?php else: ?>
            <?php if (empty($orders)): ?>
                <div class="alert alert-warning" role="alert">
                    У вас нет заказов.
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($orders as $order): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($order['name']) ?></h5>
                                    <p class="card-text"><?= htmlspecialchars($order['description']) ?></p>
<<<<<<< HEAD
                                    <p class="card-text"><strong>Цена:</strong> <?= htmlspecialchars($order['total_price']) ?> руб.
                                    </p>
=======
                                    <p class="card-text"><strong>Цена:</strong> <?= htmlspecialchars($order['total_price']) ?> руб.</p>
>>>>>>> bb3e64e8051365956def824d197a1ad91e06449d
                                    <p class="card-text"><strong>Количество:</strong> <?= htmlspecialchars($order['quantity']) ?>
                                    </p>
                                    <p class="card-text"><strong>Адрес:</strong> <?= htmlspecialchars($order['address']) ?></p>
                                    <p class="card-text">
                                        <strong>Статус:</strong>
                                        <?php
                                        $statusDetails = getStatusDetails($order['status']);
                                        ?>
                                        <span class="<?= $statusDetails['class'] ?>">
                                            <?= htmlspecialchars($statusDetails['text']) ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>

</html>