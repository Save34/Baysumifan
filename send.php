<?php
// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Недопустимый метод запроса']);
    exit;
}

// Получаем данные из формы
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$address = isset($_POST['address']) ? trim($_POST['address']) : '';
$region = isset($_POST['region']) ? trim($_POST['region']) : '';
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

// Проверяем обязательные поля
if (empty($name) || empty($phone)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Пожалуйста, заполните обязательные поля']);
    exit;
}

// Формируем сообщение для лога
$order_log_message = "Имя: $name\nТелефон: $phone\nАдрес: $address\nРегион: $region\nКоличество: $quantity\nДата: " . date("d.m.Y H:i:s") . "\n-----------------------------------\n";

// Записываем в лог
$log_filename = 'orders.log';
file_put_contents($log_filename, $order_log_message, FILE_APPEND);

// Функция для отправки email с улучшенными настройками
function send_email_notification($name, $phone, $address, $region, $quantity) {
    // Email получателя
    $to = 'islamkulovamangeldi100@gmail.com';
    
    // Формируем ID заказа
    $order_id = 'SUMIFUN-' . date('YmdHis');
    
    // Тема письма
    $subject = "Новый заказ Sumifun #$order_id от $name";
    
    // Текст письма в формате HTML
    $message = "
    <html>
    <head>
      <title>Новый заказ Sumifun</title>
      <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { width: 100%; max-width: 600px; margin: 0 auto; }
        .header { background-color: #2e7d32; color: white; padding: 15px; text-align: center; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .order-info { margin-bottom: 20px; }
        .order-info p { margin: 5px 0; }
        .order-id { font-weight: bold; color: #2e7d32; }
        .footer { font-size: 12px; text-align: center; margin-top: 20px; color: #777; }
      </style>
    </head>
    <body>
      <div class='container'>
        <div class='header'>
          <h2>Новый заказ Sumifun</h2>
        </div>
        <div class='content'>
          <div class='order-info'>
            <p class='order-id'>Номер заказа: $order_id</p>
            <p><strong>Имя:</strong> $name</p>
            <p><strong>Телефон:</strong> $phone</p>
            <p><strong>Адрес:</strong> $address</p>
            <p><strong>Регион:</strong> $region</p>
            <p><strong>Количество:</strong> $quantity</p>
            <p><strong>Сумма:</strong> " . ($quantity == 3 ? "1800 сом" : "890 сом") . "</p>
            <p><strong>Дата заказа:</strong> " . date("d.m.Y H:i:s") . "</p>
          </div>
        </div>
        <div class='footer'>
          <p>Это автоматическое уведомление с сайта Sumifun.</p>
        </div>
      </div>
    </body>
    </html>";
    
    // Заголовки письма
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "From: Сайт Sumifun <noreply@" . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'sumifun.kg') . ">\r\n";
    $headers .= "Reply-To: $to\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "X-Priority: 1\r\n"; // Высокий приоритет
    
    // Попытка отправки email
    $mail_result = @mail($to, $subject, $message, $headers);
    
    // Запись результата в лог
    file_put_contents('mail_log.txt', date("d.m.Y H:i:s") . " - Попытка отправки на $to: " . ($mail_result ? "УСПЕШНО" : "ОШИБКА") . "\n", FILE_APPEND);
    
    // Если первая попытка неудачна, пробуем еще раз
    if (!$mail_result) {
        sleep(1); // пауза в 1 секунду
        $mail_result = @mail($to, $subject, $message, $headers);
        file_put_contents('mail_log.txt', date("d.m.Y H:i:s") . " - Повторная попытка: " . ($mail_result ? "УСПЕШНО" : "ОШИБКА") . "\n", FILE_APPEND);
    }
    
    return $mail_result;
}

// Пытаемся отправить email
$mail_sent = send_email_notification($name, $phone, $address, $region, $quantity);

// Сохраняем заказ в JSON файл для надежности
try {
    $order_data = [
        'id' => uniqid('SUMIFUN-'),
        'name' => $name,
        'phone' => $phone,
        'address' => $address,
        'region' => $region,
        'quantity' => $quantity,
        'date' => date("d.m.Y H:i:s"),
        'status' => 'new',
        'price' => ($quantity == 3) ? 1800 : 890
    ];
    
    // Создаем директорию для заказов, если ее нет
    $orders_dir = 'orders';
    if (!file_exists($orders_dir)) {
        mkdir($orders_dir, 0755, true);
    }
    
    // Путь к файлу заказов
    $orders_file = $orders_dir . '/orders.json';
    $orders = [];
    
    // Если файл существует, загружаем текущие заказы
    if (file_exists($orders_file)) {
        $orders = json_decode(file_get_contents($orders_file), true) ?: [];
    }
    
    // Добавляем новый заказ
    $orders[] = $order_data;
    
    // Сохраняем обновленный список заказов
    file_put_contents($orders_file, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Сохраняем копию заказа в отдельный файл для надежности
    $single_order_file = $orders_dir . '/order_' . $order_data['id'] . '.json';
    file_put_contents($single_order_file, json_encode($order_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
} catch (Exception $e) {
    // Записываем ошибку в лог
    file_put_contents('error_log.txt', date("d.m.Y H:i:s") . " - Ошибка сохранения заказа: " . $e->getMessage() . "\n", FILE_APPEND);
}

// Всегда возвращаем положительный ответ пользователю
header('Content-Type: application/json');
echo json_encode([
    'success' => true, 
    'message' => 'Заказ успешно оформлен! Мы свяжемся с вами в ближайшее время.',
    'order_id' => $order_data['id'],
    'mail_sent' => $mail_sent
]);
?>