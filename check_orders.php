<?php
// Защита от несанкционированного доступа
session_start();

// Установка базовых учетных данных для админа
// ВНИМАНИЕ: В реальном проекте эти данные должны храниться в защищенном месте!
$admin_username = 'admin';
$admin_password = 'sumifun2023'; // В реальном проекте используйте хешированные пароли

// Проверка авторизации
$is_authenticated = false;

// Обработка входа в систему
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

    if ($username === $admin_username && $password === $admin_password) {
        $_SESSION['auth'] = true;
        $_SESSION['auth_time'] = time();
        header('Location: check_orders.php');
        exit;
    } else {
        $login_error = 'Неверное имя пользователя или пароль';
    }
}

// Обработка выхода из системы
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: check_orders.php');
    exit;
}

// Проверка авторизации пользователя
if (isset($_SESSION['auth']) && $_SESSION['auth'] === true) {
    // Проверка истечения сессии (автоматический выход через 30 минут)
    if (time() - $_SESSION['auth_time'] > 1800) {
        session_unset();
        session_destroy();
        header('Location: check_orders.php');
        exit;
    } else {
        // Обновляем время активности
        $_SESSION['auth_time'] = time();
        $is_authenticated = true;
    }
}

// Обработка изменения статуса заказа
if ($is_authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_SANITIZE_STRING);
    $new_status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

    // Путь к файлу с заказами
    $orders_file = 'orders/orders.json';

    if (file_exists($orders_file)) {
        $orders = json_decode(file_get_contents($orders_file), true);

        foreach ($orders as &$order) {
            if ($order['id'] === $order_id) {
                $order['status'] = $new_status;
                // Добавляем информацию о времени изменения статуса
                $order['status_updated'] = date('Y-m-d H:i:s');
                break;
            }
        }

        // Сохраняем обновленные заказы
        file_put_contents($orders_file, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Запись в лог
        $log_message = "[" . date('Y-m-d H:i:s') . "] Изменен статус заказа #$order_id на '$new_status'\n";
        file_put_contents('orders/status_changes.log', $log_message, FILE_APPEND);

        // Перенаправляем, чтобы избежать повторной отправки формы
        header('Location: check_orders.php');
        exit;
    }
}

// Получение списка заказов для отображения
$orders = [];
if ($is_authenticated) {
    $orders_file = 'orders/orders.json';
    if (file_exists($orders_file)) {
        $json_data = file_get_contents($orders_file);
        if (!empty($json_data)) {
            $orders = json_decode($json_data, true);
            if (!is_array($orders)) {
                $orders = [];
            }

            // Сортировка заказов по дате (новые в начале)
            usort($orders, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });
        }
    }
}

// Фильтрация заказов по статусу
$status_filter = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING);
if ($status_filter && $status_filter !== 'all') {
    $orders = array_filter($orders, function($order) use ($status_filter) {
        return $order['status'] === $status_filter;
    });
}

// Фильтрация заказов по поиску
$search_query = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);
if ($search_query) {
    $orders = array_filter($orders, function($order) use ($search_query) {
        return (
            stripos($order['id'], $search_query) !== false ||
            stripos($order['name'], $search_query) !== false ||
            stripos($order['phone'], $search_query) !== false ||
            stripos($order['address'], $search_query) !== false
        );
    });
}

// Статистика заказов
$total_orders = count($orders);
$total_revenue = array_sum(array_column($orders, 'price'));
$pending_orders = count(array_filter($orders, function($order) {
    return $order['status'] === 'new' || $order['status'] === 'processing';
}));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Проверка заказов Sumifun</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f9fdf9;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background-color: #2e7d32;
            color: #fff;
            padding: 20px 0;
            margin-bottom: 30px;
        }

        .header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
        }

        .login-form {
            max-width: 400px;
            margin: 80px auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .login-form h2 {
            margin-bottom: 20px;
            color: #2e7d32;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Montserrat', sans-serif;
        }

        .error-message {
            color: #c41e1e;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .btn {
            display: inline-block;
            background-color: #43a047;
            color: #fff;
            border: none;
            padding: 12px 20px;
            cursor: pointer;
            border-radius: 4px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 500;
            font-size: 16px;
            transition: all 0.3s;
        }

        .btn:hover {
            background-color: #2e7d32;
        }

        .btn-logout {
            background-color: transparent;
            border: 1px solid #fff;
            padding: 8px 15px;
            font-size: 14px;
        }

        .btn-logout:hover {
            background-color: rgba(255,255,255,0.1);
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }

        .stat-card h3 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #555;
        }

        .stat-card .stat-value {
            font-size: 24px;
            color: #43a047;
            font-weight: 600;
        }

        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-label {
            font-weight: 500;
            font-size: 14px;
        }

        .search-box {
            flex-grow: 1;
            max-width: 400px;
        }

        .search-box .form-control {
            padding: 10px 15px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
        }

        th {
            background-color: #f1f8e9;
            color: #2e7d32;
            font-weight: 600;
        }

        tr {
            border-bottom: 1px solid #eee;
        }

        tr:last-child {
            border-bottom: none;
        }

        .status-new {
            background-color: #e3f2fd;
            color: #1976d2;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
        }

        .status-processing {
            background-color: #fff3e0;
            color: #ff9800;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
        }

        .status-shipped {
            background-color: #e0f2f1;
            color: #009688;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
        }

        .status-delivered {
            background-color: #e8f5e9;
            color: #4caf50;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
        }

        .status-cancelled {
            background-color: #ffebee;
            color: #f44336;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        .btn-small {
            padding: 5px 10px;
            font-size: 12px;
        }

        .btn-delete {
            background-color: #c41e1e;
        }

        .btn-delete:hover {
            background-color: #a51919;
        }

        .empty-message {
            text-align: center;
            padding: 30px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            color: #777;
        }

        @media (max-width: 768px) {
            .header .container {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .filters {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-item {
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <?php if ($is_authenticated): ?>
        <!-- Заголовок для авторизованных пользователей -->
        <header class="header">
            <div class="container">
                <h1>Панель управления заказами Sumifun</h1>
                <a href="check_orders.php?action=logout" class="btn btn-logout">Выйти</a>
            </div>
        </header>

        <div class="container">
            <!-- Статистика -->
            <div class="stats-container">
                <div class="stat-card">
                    <h3>Всего заказов</h3>
                    <div class="stat-value"><?= $total_orders ?></div>
                </div>
                <div class="stat-card">
                    <h3>Общая сумма</h3>
                    <div class="stat-value"><?= $total_revenue ?> сом</div>
                </div>
                <div class="stat-card">
                    <h3>В обработке</h3>
                    <div class="stat-value"><?= $pending_orders ?></div>
                </div>
            </div>

            <!-- Фильтры -->
            <form method="GET" action="check_orders.php">
                <div class="filters">
                    <div class="filter-item">
                        <span class="filter-label">Статус:</span>
                        <select name="status" class="form-control" onchange="this.form.submit()">
                            <option value="all" <?= (!$status_filter || $status_filter === 'all') ? 'selected' : '' ?>>Все</option>
                            <option value="new" <?= $status_filter === 'new' ? 'selected' : '' ?>>Новый</option>
                            <option value="processing" <?= $status_filter === 'processing' ? 'selected' : '' ?>>Обработка</option>
                            <option value="shipped" <?= $status_filter === 'shipped' ? 'selected' : '' ?>>Отправлено</option>
                            <option value="delivered" <?= $status_filter === 'delivered' ? 'selected' : '' ?>>Доставлено</option>
                            <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Отменено</option>
                        </select>
                    </div>
                    <div class="filter-item search-box">
                        <span class="filter-label">Поиск:</span>
                        <input type="text" name="search" class="form-control" placeholder="Поиск по ID, имени, телефону..." value="<?= htmlspecialchars($search_query ?? '') ?>">
                    </div>
                    <div class="filter-item">
                        <button type="submit" class="btn">Применить</button>
                        <a href="check_orders.php" class="btn" style="margin-left: 10px;">Сбросить</a>
                    </div>
                </div>
            </form>

            <!-- Таблица заказов -->
            <?php if (count($orders) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Дата</th>
                                <th>Имя</th>
                                <th>Телефон</th>
                                <th>Адрес</th>
                                <th>Регион</th>
                                <th>Кол-во</th>
                                <th>Сумма</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?= htmlspecialchars($order['id']) ?></td>
                                    <td><?= htmlspecialchars($order['date']) ?></td>
                                    <td><?= htmlspecialchars($order['name']) ?></td>
                                    <td><?= htmlspecialchars($order['phone']) ?></td>
                                    <td><?= htmlspecialchars($order['address']) ?></td>
                                    <td><?= htmlspecialchars($order['region']) ?></td>
                                    <td><?= htmlspecialchars($order['quantity']) ?></td>
                                    <td><?= htmlspecialchars($order['price']) ?> сом</td>
                                    <td>
                                        <span class="status-<?= htmlspecialchars($order['status']) ?>">
                                            <?php
                                            switch ($order['status']) {
                                                case 'new':
                                                    echo 'Новый';
                                                    break;
                                                case 'processing':
                                                    echo 'Обработка';
                                                    break;
                                                case 'shipped':
                                                    echo 'Отправлено';
                                                    break;
                                                case 'delivered':
                                                    echo 'Доставлено';
                                                    break;
                                                case 'cancelled':
                                                    echo 'Отменено';
                                                    break;
                                                default:
                                                    echo htmlspecialchars($order['status']);
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <form method="POST" action="check_orders.php" style="display: inline;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['id']) ?>">
                                                <select name="status" class="form-control" onchange="this.form.submit()" style="padding: 5px; width: auto;">
                                                    <option value="new" <?= $order['status'] === 'new' ? 'selected' : '' ?>>Новый</option>
                                                    <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Обработка</option>
                                                    <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : '' ?>>Отправлено</option>
                                                    <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Доставлено</option>
                                                    <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Отменено</option>
                                                </select>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-message">
                    <p>Нет заказов, соответствующих выбранным критериям.</p>
                </div>
            <?php endif; ?>

            <p>
                <a href="index.html" class="btn" style="background-color: #66bb6a;">На главную страницу</a>
                <a href="orders/orders.json" download class="btn" style="background-color: #7cb342;">Скачать все заказы (JSON)</a>
            </p>
        </div>
    <?php else: ?>
        <!-- Форма авторизации для неавторизованных пользователей -->
        <div class="container">
            <form class="login-form" method="POST" action="check_orders.php">
                <h2>Вход в систему</h2>

                <?php if (isset($login_error)): ?>
                    <div class="error-message"><?= $login_error ?></div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="username">Имя пользователя</label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="password">Пароль</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>

                <input type="hidden" name="action" value="login">
                <button type="submit" class="btn" style="width: 100%;">Войти</button>

                <p style="margin-top: 20px; text-align: center;">
                    <a href="index.html" style="color: #43a047; text-decoration: none;">Вернуться на главную</a>
                </p>
            </form>
        </div>
    <?php endif; ?>
</body>
</html>