<?php
require 'db.php';
session_start();

function isLoggedIn()
{
    if ($_SESSION['role'] == 'seller') {
        echo '<div style="display: flex; justify-content: center; align-items: center; height: 100vh;">
                <h1>У вас нету доступа к данной странице</h1>
              </div>';
        exit;
    }
    return isset($_SESSION['user_id']);
}

function redirectToLogin()
{
    header(header: "Location: index.php");
    exit;
}

function fetchProducts($pdo)
{
    try {
        $stmt = $pdo->query("SELECT id, name, description, price, quantity, seller_id FROM products WHERE quantity > 0");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return "Ошибка при получении данных о товарах: " . $e->getMessage();
    } catch (Exception $e) {
        return "" . $e->getMessage();
    }
}

function addToCart($userId, $productId, $quantity, $pdo)
{
    try {
        $stmt = $pdo->prepare("INSERT INTO basket (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $productId, $quantity]);
        return "Товар успешно добавлен в корзину!";
    } catch (PDOException $e) {
        return "Ошибка при добавлении в корзину: " . $e->getMessage();
    } catch (Exception $e) {
        return "" . $e->getMessage();
    }
}

if (!isLoggedIn()) {
    redirectToLogin();
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $message = addToCart($_SESSION['user_id'], $_POST['product_id'], $_POST['quantity'], $pdo);
}

$products = fetchProducts($pdo);
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список товаров</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>
    <header class="bg-light py-3">
        <div class="container d-flex justify-content-between align-items-center">
            <h1>Магазин</h1>
            <div>
                <a href="basket.php" class="btn btn-secondary mr-2">Корзина</a>
                <a href="order.php" class="btn btn-primary mr-2">Заказы</a>
                <a href="logout.php" class="btn btn-danger">Выйти</a>
            </div>
        </div>
    </header>

    <div class="container mt-5">
        <h2 class="text-center">Список товаров</h2>
        <?php if (!empty($message)): ?>
            <div class="alert alert-success" role="alert">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        <?php if (is_string($products)): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($products) ?>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($products as $product): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($product['name']) ?></h5>
                                <p class="card-text"><?= htmlspecialchars($product['description']) ?></p>
                                <p class="card-text"><strong>Цена:</strong> <?= htmlspecialchars($product['price']) ?> руб.</p>
                                <form method="post">
                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                    <input required class='form-control' type="number" name="quantity" value="1" min="1"
                                        max="100">
                                    <br>
                                    <button type="submit" class="btn btn-primary">Добавить в корзину</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>


</body>

</html>