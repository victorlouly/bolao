<?php
$ordersFile = __DIR__ . '/orders.json';
$orders = [];
if(file_exists($ordersFile)) $orders = json_decode(file_get_contents($ordersFile), true) ?? [];
?>
<!doctype html><html><head><meta charset="utf-8"><title>Admin - Orders</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="p-4">
<h1>Pedidos (orders.json)</h1>
<p><small>Atualize a página para verificar novos postbacks</small></p>
<table class="table table-striped">
<thead><tr><th>Ref</th><th>ID</th><th>Status</th><th>Criado</th><th>Ações</th></tr></thead><tbody>
<?php foreach($orders as $k=>$o): ?>
<tr>
  <td><?php echo htmlspecialchars($o['externalRef'] ?? $k); ?></td>
  <td><?php echo htmlspecialchars($o['id'] ?? ''); ?></td>
  <td><?php echo htmlspecialchars($o['status'] ?? ''); ?></td>
  <td><?php echo htmlspecialchars($o['createdAt'] ?? ''); ?></td>
  <td><a class="btn btn-sm btn-primary" href="admin_view.php?ref=<?php echo urlencode($k); ?>" target="_blank">Ver</a></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</body></html>
