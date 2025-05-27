<?php
require_once 'includes/init.php';

// Verificar la estructura de la tabla stores
$result = $conn->query('DESCRIBE stores');
echo "<h2>Estructura de la tabla stores</h2>";
echo "<pre>";
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
echo "</pre>";
?>
