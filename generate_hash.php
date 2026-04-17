<?php
/**
 * Script para generar el hash correcto y crear/actualizar el usuario admin.
 * Ejecutar desde el navegador: http://localhost/api-integrar/generate_hash.php
 */

$password = 'admin123';
$hash = password_hash($password, PASSWORD_BCRYPT);

echo "Contraseña: $password\n";
echo "Hash generado: $hash\n\n";

// Intentar conectar a la BD
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=integrar_salud;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Verificar si existe el usuario
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = "admin@integrarsalud.com"');
    $stmt->execute();
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE email = "admin@integrarsalud.com"');
        $stmt->execute([$hash]);
        echo "✅ Usuario admin ACTUALIZADO con nueva contraseña.\n";
    } else {
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $stmt->execute(['Administrador', 'admin@integrarsalud.com', $hash, 'admin']);
        echo "✅ Usuario admin CREADO con nueva contraseña.\n";
    }

    // Verificar que el hash es correcto
    $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = "admin@integrarsalud.com"');
    $stmt->execute();
    $user = $stmt->fetch();

    if (password_verify($password, $user['password_hash'])) {
        echo "✅ Verificación OK: password_verify funciona correctamente.\n";
    } else {
        echo "❌ ERROR: password_verify NO funciona. Revisar la BD.\n";
    }

    echo "\n📋 Datos del usuario:\n";
    print_r($user);

} catch (PDOException $e) {
    echo "❌ Error de base de datos: " . $e->getMessage() . "\n";
    echo "Asegurate de que:\n";
    echo "1. MySQL esté corriendo en XAMPP\n";
    echo "2. La base de datos 'integrar_salud' exista\n";
    echo "3. database.sql haya sido importado correctamente\n";
}
