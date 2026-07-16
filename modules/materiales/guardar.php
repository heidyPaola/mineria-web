<?php
// modules/materiales/guardar.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $nombre = trim($_POST['nombre']);
    $codigo = trim($_POST['codigo'] ?? '');
    $categoria = $_POST['categoria'] ?? 'Mineral';
    $unidad_medida = $_POST['unidad_medida'];
    $stock_actual = $_POST['stock_actual'] ?? 0;
    $stock_minimo = $_POST['stock_minimo'] ?? 0;
    $ubicacion = $_POST['ubicacion'] ?? '';
    $proveedor = $_POST['proveedor'] ?? '';
    $precio_unitario = $_POST['precio_unitario'] ?? 0;
    $ultimo_precio_compra = $_POST['ultimo_precio_compra'] ?? null;
    $ultima_compra = $_POST['ultima_compra'] ?? null;
    $codigo_barras = $_POST['codigo_barras'] ?? '';
    $notas = $_POST['notas'] ?? '';
    
    if ($id) {
        $query = "UPDATE materiales SET 
                  nombre=:nombre, codigo=:codigo, categoria=:categoria,
                  unidad_medida=:unidad_medida, stock_actual=:stock_actual,
                  stock_minimo=:stock_minimo, ubicacion=:ubicacion,
                  proveedor=:proveedor, precio_unitario=:precio_unitario,
                  ultimo_precio_compra=:ultimo_precio_compra, ultima_compra=:ultima_compra,
                  codigo_barras=:codigo_barras, notas=:notas
                  WHERE id=:id";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':nombre' => $nombre, ':codigo' => $codigo, ':categoria' => $categoria,
            ':unidad_medida' => $unidad_medida, ':stock_actual' => $stock_actual,
            ':stock_minimo' => $stock_minimo, ':ubicacion' => $ubicacion,
            ':proveedor' => $proveedor, ':precio_unitario' => $precio_unitario,
            ':ultimo_precio_compra' => $ultimo_precio_compra, ':ultima_compra' => $ultima_compra,
            ':codigo_barras' => $codigo_barras, ':notas' => $notas, ':id' => $id
        ]);
        registrarAuditoria($conn, 'ACTUALIZAR', 'materiales', $id);
        header('Location: index.php?msg=actualizado');
    } else {
        $query = "INSERT INTO materiales (nombre, codigo, categoria, unidad_medida, stock_actual,
                  stock_minimo, ubicacion, proveedor, precio_unitario, ultimo_precio_compra,
                  ultima_compra, codigo_barras, notas, estado)
                  VALUES (:nombre, :codigo, :categoria, :unidad_medida, :stock_actual,
                  :stock_minimo, :ubicacion, :proveedor, :precio_unitario, :ultimo_precio_compra,
                  :ultima_compra, :codigo_barras, :notas, 1)";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':nombre' => $nombre, ':codigo' => $codigo, ':categoria' => $categoria,
            ':unidad_medida' => $unidad_medida, ':stock_actual' => $stock_actual,
            ':stock_minimo' => $stock_minimo, ':ubicacion' => $ubicacion,
            ':proveedor' => $proveedor, ':precio_unitario' => $precio_unitario,
            ':ultimo_precio_compra' => $ultimo_precio_compra, ':ultima_compra' => $ultima_compra,
            ':codigo_barras' => $codigo_barras, ':notas' => $notas
        ]);
        registrarAuditoria($conn, 'CREAR', 'materiales', $conn->lastInsertId());
        header('Location: index.php?msg=creado');
    }
    exit();
}
header('Location: index.php');
?>