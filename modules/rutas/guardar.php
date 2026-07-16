<?php
// modules/rutas/guardar.php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/conexion.php';

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $origen = trim($_POST['origen']);
    $destino = trim($_POST['destino']);
    $distancia = $_POST['distancia'] ?? 0;
    $tiempo_estimado = $_POST['tiempo_estimado'] ?? 0;
    $tipo = $_POST['tipo'] ?? 'Terrestre';
    $dificultad = $_POST['dificultad'] ?? 'media';
    $peligrosidad = $_POST['peligrosidad'] ?? 'media';
    $estado = $_POST['estado'] ?? 1;
    $puntos_intermedios = $_POST['puntos_intermedios'] ?? '';
    $condiciones = $_POST['condiciones'] ?? '';
    $restriccion_peso = $_POST['restriccion_peso'] ?? null;
    $restriccion_altura = $_POST['restriccion_altura'] ?? null;
    $coordenadas_origen = $_POST['coordenadas_origen'] ?? '';
    $coordenadas_destino = $_POST['coordenadas_destino'] ?? '';
    $ultimo_mantenimiento = $_POST['ultimo_mantenimiento'] ?? null;
    
    if ($id) {
        $query = "UPDATE rutas SET 
                  origen=:origen, destino=:destino, distancia=:distancia,
                  tiempo_estimado=:tiempo_estimado, tipo=:tipo, dificultad=:dificultad,
                  peligrosidad=:peligrosidad, estado=:estado, puntos_intermedios=:puntos_intermedios,
                  condiciones=:condiciones, restriccion_peso=:restriccion_peso,
                  restriccion_altura=:restriccion_altura, coordenadas_origen=:coordenadas_origen,
                  coordenadas_destino=:coordenadas_destino, ultimo_mantenimiento=:ultimo_mantenimiento
                  WHERE id=:id";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':origen' => $origen, ':destino' => $destino, ':distancia' => $distancia,
            ':tiempo_estimado' => $tiempo_estimado, ':tipo' => $tipo, ':dificultad' => $dificultad,
            ':peligrosidad' => $peligrosidad, ':estado' => $estado, ':puntos_intermedios' => $puntos_intermedios,
            ':condiciones' => $condiciones, ':restriccion_peso' => $restriccion_peso,
            ':restriccion_altura' => $restriccion_altura, ':coordenadas_origen' => $coordenadas_origen,
            ':coordenadas_destino' => $coordenadas_destino, ':ultimo_mantenimiento' => $ultimo_mantenimiento,
            ':id' => $id
        ]);
        registrarAuditoria($conn, 'ACTUALIZAR', 'rutas', $id);
        header('Location: index.php?msg=actualizado');
    } else {
        $query = "INSERT INTO rutas (origen, destino, distancia, tiempo_estimado, tipo, dificultad,
                  peligrosidad, estado, puntos_intermedios, condiciones, restriccion_peso,
                  restriccion_altura, coordenadas_origen, coordenadas_destino, ultimo_mantenimiento)
                  VALUES (:origen, :destino, :distancia, :tiempo_estimado, :tipo, :dificultad,
                  :peligrosidad, :estado, :puntos_intermedios, :condiciones, :restriccion_peso,
                  :restriccion_altura, :coordenadas_origen, :coordenadas_destino, :ultimo_mantenimiento)";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':origen' => $origen, ':destino' => $destino, ':distancia' => $distancia,
            ':tiempo_estimado' => $tiempo_estimado, ':tipo' => $tipo, ':dificultad' => $dificultad,
            ':peligrosidad' => $peligrosidad, ':estado' => $estado, ':puntos_intermedios' => $puntos_intermedios,
            ':condiciones' => $condiciones, ':restriccion_peso' => $restriccion_peso,
            ':restriccion_altura' => $restriccion_altura, ':coordenadas_origen' => $coordenadas_origen,
            ':coordenadas_destino' => $coordenadas_destino, ':ultimo_mantenimiento' => $ultimo_mantenimiento
        ]);
        registrarAuditoria($conn, 'CREAR', 'rutas', $conn->lastInsertId());
        header('Location: index.php?msg=creado');
    }
    exit();
}
header('Location: index.php');
?>