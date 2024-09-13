<?php
require 'db.php';
session_start();

function isLoggedIn()
{
    if ($_SESSION['role'] == 'buyer') {
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

function fetchProducts($pdo)
{
    try {
        $stmt = $pdo->query("SELECT id, name, description, price, quantity FROM products");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return "Ошибка при получении данных о товарах: " . $e->getMessage();
    } catch (Exception $e) {
        return "" . $e->getMessage();
    }
}

function addProduct($name, $description, $price, $quantity, $sellerId, $pdo)
{
    try {
        $stmt = $pdo->prepare("INSERT INTO products (name, description, price, quantity, seller_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $description, $price, $quantity, $sellerId]);
        return "Товар успешно добавлен!";
    } catch (PDOException $e) {
        return "Ошибка при добавлении товара: " . $e->getMessage();
    } catch (Exception $e) {
        return "" . $e->getMessage();
    }
}


function updateProduct($id, $name, $description, $price, $quantity, $pdo)
{
    try {
        $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, quantity = ? WHERE id = ?");
        $stmt->execute([$name, $description, $price, $quantity, $id]);
        return "Товар успешно обновлен!";
    } catch (PDOException $e) {
        return "Ошибка при обновлении товара: " . $e->getMessage();
    } catch (Exception $e) {
        return "" . $e->getMessage();
    }
}

function deleteProduct($id, $pdo)
{
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        return "Товар успешно удален!";
    } catch (PDOException $e) {
        return "Ошибка при удалении товара: " . $e->getMessage();
    } catch (Exception $e) {
        return "" . $e->getMessage();
    }
}

if (!isLoggedIn()) {
    redirectToLogin();
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_product'])) {
        // Add new product
        $message = addProduct($_POST['name'], $_POST['description'], $_POST['price'], $_POST['quantity'], $_POST['seller_id'], $pdo);
    } elseif (isset($_POST['update_product'])) {
        // Update product
        $message = updateProduct($_POST['id'], $_POST['name'], $_POST['description'], $_POST['price'], $_POST['quantity'], $pdo);
    } elseif (isset($_POST['delete_product'])) {
        // Delete product
        $message = deleteProduct($_POST['id'], $pdo);
    }
}


$products = fetchProducts($pdo);
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление товарами</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <header class="bg-light py-3">
        <div class="container d-flex justify-content-between align-items-center">
            <h1>Управление товарами</h1>
            <div>
                <a href="order_seller.php" class="btn btn-primary">Мои заказы</a>
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
                                <p class="card-text"><strong>Количество:</strong> <?= htmlspecialchars($product['quantity']) ?>
                                </p>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="id" value="<?= $product['id'] ?>">
                                    <button type="submit" name="delete_product" class="btn btn-danger">Удалить</button>
                                </form>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="id" value="<?= $product['id'] ?>">
                                    <button type="button" class="btn btn-warning" data-toggle="modal"
                                        data-target="#editProductModal<?= $product['id'] ?>">Изменить</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Product Modal -->
                    <div class="modal fade" id="editProductModal<?= $product['id'] ?>" tabindex="-1" role="dialog"
                        aria-labelledby="editProductModalLabel" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editProductModalLabel">Изменить товар</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <form method="post">
                                        <input type="hidden" name="id" value="<?= $product['id'] ?>">
                                        <div class="form-group">
                                            <label for="name">Название</label>
                                            <input type="text" class="form-control" id="name" name="name"
                                                value="<?= htmlspecialchars($product['name']) ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="description">Описание</label>
                                            <textarea class="form-control" id="description" name="description"
                                                required><?= htmlspecialchars($product['description']) ?></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="price">Цена</label>
                                            <input type="number" class="form-control" id="price" name="price"
                                                value="<?= htmlspecialchars($product['price']) ?>" step="0.01" required,
                                                min="0.01">
                                        </div>
                                        <div class="form-group">
                                            <label for="quantity">Количество</label>
                                            <input type="number" class="form-control" id="quantity" name="quantity"
                                                value="<?= htmlspecialchars($product['quantity']) ?>" min="0" required>
                                        </div>
                                        <button type="submit" name="update_product" class="btn btn-primary">Обновить</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Add Product Form -->
        <h2 class="text-center mt-5">Добавить новый товар</h2>
        <form method="post">
            <div class="form-group">
                <label for="name">Название</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="description">Описание</label>
                <textarea class="form-control" id="description" name="description" required></textarea>
            </div>
            <div class="form-group">
                <label for="price">Цена</label>
                <input type="number" class="form-control" id="price" name="price" step="0.01" required>
            </div>
            <div class="form-group">
                <label for="quantity">Количество</label>
                <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
            </div>
            <button type="submit" name="add_product" class="btn btn-success">Добавить</button>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>

</html>