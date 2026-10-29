<?php

require_once __DIR__ . '/../identity.php';
require_once __DIR__ . '/lib/db.php'; 

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 2. Tangkap dan sanitasi input form
    $nama  = htmlspecialchars($_POST['nama']);
    $email = htmlspecialchars($_POST['email']);
    $topik = htmlspecialchars($_POST['topik']);
    $isi   = htmlspecialchars($_POST['isi_pertanyaan']);
    
    
    $route_id = MEMBER_ROUTE;

  
    $sql = "INSERT INTO pertanyaan (route_id, nama, isi) 
            VALUES (:route_id, :nama, :isi)";
    
    try {
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':route_id'  => $route_id,
            ':nama'      => $nama,
            ':isi'       => $isi
        ]);
        
      
        header("Location: tanya.php?status=sukses");
        exit;
    } catch (PDOException $e) {
        die("Error saat menyimpan data: " . $e->getMessage());
    }
} else {
    die("Akses ditolak.");
}
?>