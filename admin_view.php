<?php
$ref = $_GET['ref'] ?? '';
$ordersFile = __DIR__ . '/orders.json';
$orders = [];
if(file_exists($ordersFile)) $orders = json_decode(file_get_contents($ordersFile), true) ?? [];
$o = $orders[$ref] ?? null;
?>
<!doctype html><html><head><meta charset="utf-8"><title>Pedido <?php echo htmlspecialchars($ref); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="p-4">
<h1>Pedido: <?php echo htmlspecialchars($ref); ?></h1>
<pre><?php echo json_encode($o, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE); ?></pre>
</body></html>
