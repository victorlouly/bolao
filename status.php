<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
$externalRef = $_GET['externalRef'] ?? '';
$ordersFile = __DIR__ . '/orders.json';
if(!file_exists($ordersFile)){ echo json_encode(['error'=>'no_orders']); exit; }
$orders = json_decode(file_get_contents($ordersFile), true) ?? [];
if($externalRef && isset($orders[$externalRef])){
    echo json_encode(['status'=>$orders[$externalRef]['status'],'order'=>$orders[$externalRef]]);
    exit;
}
foreach($orders as $k=>$v){
    if(isset($v['id']) && $v['id']==$externalRef){
        echo json_encode(['status'=>$v['status'],'order'=>$v]);
        exit;
    }
}
echo json_encode(['status'=>'unknown']);
?>
