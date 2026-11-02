<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_resep";

$koneksi = mysqli_connect($host, $user, $pass, $db);

if (!$koneksi) {
    echo json_encode(["status" => "error", "message" => "Koneksi database gagal"]);
    exit;
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// ==========================================================================
// 1. REGISTRASI USER
// ==========================================================================
if ($action == 'register') {
    $nama_lengkap = isset($_POST['nama_lengkap']) ? mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']) : '';
    $username     = isset($_POST['username']) ? mysqli_real_escape_string($koneksi, $_POST['username']) : '';
    $password     = isset($_POST['password']) ? $_POST['password'] : '';
    $email        = isset($_POST['email']) ? mysqli_real_escape_string($koneksi, $_POST['email']) : '';

    if($nama_lengkap == '' || $username == '' || $password == '' || $email == '') {
        echo json_encode(["status" => "error", "message" => "Semua data wajib diisi!"]);
        exit;
    }

    $cek_username = mysqli_query($koneksi, "SELECT * FROM user WHERE username = '$username'");
    if (mysqli_num_rows($cek_username) > 0) {
        echo json_encode(["status" => "error", "message" => "Username sudah terdaftar! Gunakan nama lain."]);
        exit;
    }

    $query = "INSERT INTO user (nama_lengkap, username, password, email, bio, foto_profile) 
              VALUES ('$nama_lengkap', '$username', '$password', '$email', '', 'default.png')";
    
    if (mysqli_query($koneksi, $query)) {
        echo json_encode(["status" => "success", "message" => "Registrasi Berhasil"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal menyimpan ke database: " . mysqli_error($koneksi)]);
    }
    exit;
}

// ==========================================================================
// 2. LOGIN SATU PINTU
// ==========================================================================
if ($action == 'login_satu_pintu') {
    $username = isset($_POST['username']) ? mysqli_real_escape_string($koneksi, $_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    $query_admin = "SELECT * FROM admin WHERE username = '$username'";
    $res_admin = mysqli_query($koneksi, $query_admin);
    $admin = mysqli_fetch_assoc($res_admin);

    if ($admin) {
        if ($password == $admin['password']) {
            echo json_encode([
                "status" => "success", 
                "role" => "admin", 
                "id_user" => intval($admin['id_admin']), 
                "message" => "Login Berhasil Sebagai Admin"
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Password Admin salah"]);
        }
        exit;
    }

    $query_user = "SELECT * FROM user WHERE username = '$username'";
    $res_user = mysqli_query($koneksi, $query_user);
    $user = mysqli_fetch_assoc($res_user);

    if ($user) {
        if ($password == $user['password']) {
            echo json_encode([
                "status" => "success", 
                "role" => "user", 
                "id_user" => intval($user['id_user']), 
                "message" => "Login Berhasil Sebagai User"
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Password User salah"]);
        }
        exit;
    }

    echo json_encode(["status" => "error", "message" => "Username tidak terdaftar"]);
    exit;
}

// ==========================================================================
// 3. AMBIL DAFTAR MENU
// ==========================================================================
if ($action == 'get_resep') {
    $id_user = isset($_GET['id_user']) ? intval($_GET['id_user']) : 0;
    $jenis = isset($_GET['jenis']) ? $_GET['jenis'] : 'semua';

    if ($jenis === 'pribadi') {
        $query = "SELECT resep.*, COUNT(favorit.id_favorit) AS total_likes 
                  FROM resep 
                  LEFT JOIN favorit ON resep.id_resep = favorit.id_resep 
                  WHERE resep.id_user = $id_user 
                  GROUP BY resep.id_resep 
                  ORDER BY resep.id_resep DESC";
    } else {
        $query = "SELECT resep.*, COUNT(favorit.id_favorit) AS total_likes 
                  FROM resep 
                  LEFT JOIN favorit ON resep.id_resep = favorit.id_resep 
                  GROUP BY resep.id_resep 
                  ORDER BY resep.id_resep DESC";
    }

    $result = mysqli_query($koneksi, $query);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['total_likes'] = intval($row['total_likes']);
        $data[] = $row;
    }
    echo json_encode($data);
    exit;
}

// ==========================================================================
// 5. AMBIL DAFTAR DRAFT
// ==========================================================================
if ($action == 'get_draft') {
    $id_user = isset($_GET['id_user']) ? intval($_GET['id_user']) : 0;
    $query = "SELECT * FROM draft WHERE id_user = $id_user ORDER BY id_draft DESC";
    $result = mysqli_query($koneksi, $query);
    
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    echo json_encode($data);
    exit;
}

// ==========================================================================
// 5B. AMBIL DETAIL SATU DRAFT (FIX BARU: untuk isi form edit draft)
// ==========================================================================
if ($action == 'get_draft_detail') {
    $id_draft = isset($_GET['id_draft']) ? intval($_GET['id_draft']) : 0;

    if ($id_draft === 0) {
        echo json_encode(["status" => "error", "message" => "ID draft tidak valid"]);
        exit;
    }

    $query = "SELECT * FROM draft WHERE id_draft = $id_draft";
    $result = mysqli_query($koneksi, $query);
    $draft = mysqli_fetch_assoc($result);

    if ($draft) {
        echo json_encode(["status" => "success", "data" => $draft]);
    } else {
        echo json_encode(["status" => "error", "message" => "Draft tidak ditemukan"]);
    }
    exit;
}

// ==========================================================================
// 6. PROFILE ADMIN
// ==========================================================================
if ($action == 'get_profile_admin') {
    $id_admin = isset($_GET['id_user']) ? intval($_GET['id_user']) : 0;
    $query = "SELECT * FROM admin WHERE id_admin = $id_admin";
    $result = mysqli_query($koneksi, $query);
    $admin = mysqli_fetch_assoc($result);
    
    if ($admin) {
        echo json_encode(["status" => "success", "data" => $admin]);
    } else {
        echo json_encode(["status" => "error", "message" => "Data Admin tidak ditemukan"]);
    }
    exit;
}

// ==========================================================================
// 7. PROFILE USER
// ==========================================================================
if ($action == 'get_profile_user') {
    $id_user = isset($_GET['id_user']) ? intval($_GET['id_user']) : 0;
    
    $query = "SELECT * FROM user WHERE id_user = $id_user";
    $result = mysqli_query($koneksi, $query);
    $user = mysqli_fetch_assoc($result);
    
    if ($user) {
        echo json_encode(["status" => "success", "data" => $user]);
    } else {
        echo json_encode([
            "status" => "success", 
            "data" => [
                "nama_lengkap" => "Pengguna Baru",
                "username" => "user",
                "bio" => "Belum ada bio.",
                "foto_profile" => "default.png"
            ]
        ]);
    }
    exit;
}

// ==========================================================================
// 8. UPDATE PROFILE USER / ADMIN
// ==========================================================================
if ($action == 'update_profile_user' || $action == 'update_profile_admin') {
    $tabel = ($action == 'update_profile_user') ? 'user' : 'admin';
    $pk_kolom = ($action == 'update_profile_user') ? 'id_user' : 'id_admin';
    
    $id_pemakai = isset($_REQUEST['id_user']) ? intval($_REQUEST['id_user']) : 0;
    $nama_lengkap = isset($_REQUEST['nama_lengkap']) ? mysqli_real_escape_string($koneksi, $_REQUEST['nama_lengkap']) : '';
    $username = isset($_REQUEST['username']) ? mysqli_real_escape_string($koneksi, $_REQUEST['username']) : '';
    $bio = isset($_REQUEST['bio']) ? mysqli_real_escape_string($koneksi, $_REQUEST['bio']) : '';

    if (isset($_FILES['foto_profile']) && $_FILES['foto_profile']['error'] === 0) {
        $file_tmp = $_FILES['foto_profile']['tmp_name'];
        $ekstensi = pathinfo($_FILES['foto_profile']['name'], PATHINFO_EXTENSION);
        $nama_file_gambar = "avatar_" . time() . "_" . uniqid() . '.' . $ekstensi;
        
        if (!is_dir("uploads/")) {
            @mkdir("uploads/", 0777, true);
        }
        
        move_uploaded_file($file_tmp, "uploads/" . $nama_file_gambar);
        $query = "UPDATE $tabel SET nama_lengkap='$nama_lengkap', username='$username', bio='$bio', foto_profile='$nama_file_gambar' WHERE $pk_kolom=$id_pemakai";
    } else {
        $query = "UPDATE $tabel SET nama_lengkap='$nama_lengkap', username='$username', bio='$bio' WHERE $pk_kolom=$id_pemakai";
    }

    if (mysqli_query($koneksi, $query)) {
        echo json_encode(["status" => "success", "message" => "Profil berhasil diperbarui"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal update database: " . mysqli_error($koneksi)]);
    }
    exit;
}

// ==========================================================================
// 9. DELETE RESEP (FIX: Terbuka untuk request POST Admin & Member)
// ==========================================================================
if ($action == 'delete_resep') {
    $id_resep = isset($_POST['id_resep']) ? intval($_POST['id_resep']) : 0;
    
    // Amankan: Hapus juga data likes resep ini di tabel favorit agar tidak memicu Foreign Key Constraint Error
    mysqli_query($koneksi, "DELETE FROM favorit WHERE id_resep = $id_resep");
    
    $query = "DELETE FROM resep WHERE id_resep = $id_resep";
    if (mysqli_query($koneksi, $query)) {
        echo json_encode(["status" => "success", "message" => "Resep berhasil dihapus secara permanen"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal menghapus resep: " . mysqli_error($koneksi)]);
    }
    exit;
}

// ==========================================================================
// 10. DELETE DRAFT RESEP (Penulis berhak menghapus file draftnya sendiri)
// ==========================================================================
if ($action == 'delete_draft') {
    $id_draft = isset($_POST['id_draft']) ? intval($_POST['id_draft']) : 0;
    
    $query = "DELETE FROM draft WHERE id_draft = $id_draft"; 
    
    if (mysqli_query($koneksi, $query)) {
        echo json_encode(["status" => "success", "message" => "Draft resep berhasil dihapus permanen"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal menghapus draft: " . mysqli_error($koneksi)]);
    }
    exit;
}

// ==========================================================================
// 11. AMBIL RESEP FAVORIT
// ==========================================================================
if ($action == 'get_favorit') {
    $id_user = isset($_GET['id_user']) ? intval($_GET['id_user']) : 0;
    $query = "SELECT resep.* FROM favorit 
              JOIN resep ON favorit.id_resep = resep.id_resep 
              WHERE favorit.id_user = $id_user 
              ORDER BY favorit.id_favorit DESC";
              
    $result = mysqli_query($koneksi, $query);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    echo json_encode($data);
    exit;
}

// ==========================================================================
// 12. TOGGLE FAVORIT (SUPPORT USER & ADMIN)
// ==========================================================================
if ($action == 'toggle_favorit') {
    $id_resep = isset($_POST['id_resep']) ? intval($_POST['id_resep']) : 0;
    $id_user  = isset($_POST['id_user'])  ? intval($_POST['id_user'])  : 0;

    if ($id_resep == 0 || $id_user == 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID tidak valid']);
        exit;
    }

    $cek = mysqli_query($koneksi, "SELECT id_favorit FROM favorit WHERE id_resep=$id_resep AND id_user=$id_user");
    
    mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS = 0;");

    if (mysqli_num_rows($cek) > 0) {
        mysqli_query($koneksi, "DELETE FROM favorit WHERE id_resep=$id_resep AND id_user=$id_user");
        $pesan = 'Dihapus dari favorit';
    } else {
        mysqli_query($koneksi, "INSERT INTO favorit (id_user, id_resep, created_at) VALUES ($id_user, $id_resep, NOW())");
        $pesan = 'Resep ditambahkan ke favorit! ❤️';
    }

    mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS = 1;");

    $totalRow = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM favorit WHERE id_resep=$id_resep");
    $total = mysqli_fetch_assoc($totalRow)['total'];

    echo json_encode([
        'status'      => 'success',
        'message'     => $pesan,
        'total_likes' => (int)$total
    ]);
    exit;
}

// ==========================================================================
// 13. AMBIL DETAIL RESEP (FIX SINKRONISASI KOLOM BAHAN & LANGKAH)
// ==========================================================================
if ($action == 'get_resep_detail') {
    $id_resep = isset($_GET['id_resep']) ? intval($_GET['id_resep']) : 0;
    $id_user_aktif = isset($_GET['id_user']) ? intval($_GET['id_user']) : 0;

    $query = "SELECT resep.*, 
              IFNULL(user.nama_lengkap, admin.nama_lengkap) AS nama_chef 
              FROM resep 
              LEFT JOIN user ON resep.id_user = user.id_user 
              LEFT JOIN admin ON resep.id_user = admin.id_admin 
              WHERE resep.id_resep = $id_resep";
              
    $result = mysqli_query($koneksi, $query);
    $resep = mysqli_fetch_assoc($result);

    if ($resep) {
        $resep['bahan'] = $resep['bahan_bahan'];
        $resep['langkah'] = $resep['langkah_memasak'];

        $query_total = "SELECT COUNT(*) AS total_likes FROM favorit WHERE id_resep = $id_resep";
        $res_total = mysqli_query($koneksi, $query_total);
        $row_total = mysqli_fetch_assoc($res_total);
        $resep['total_likes'] = intval($row_total['total_likes']);

        $query_status = "SELECT * FROM favorit WHERE id_resep = $id_resep AND id_user = $id_user_aktif";
        $res_status = mysqli_query($koneksi, $query_status);
        $resep['sudah_like'] = (mysqli_num_rows($res_status) > 0) ? true : false;

        echo json_encode(["status" => "success", "data" => $resep]);
    } else {
        echo json_encode(["status" => "error", "message" => "Resep tidak ditemukan"]);
    }
    exit;
}

// ==========================================================================
// 16. REQUEST OTP LUPA PASSWORD
// ==========================================================================
if ($action == 'request_otp') {
    $username = isset($_REQUEST['username']) ? mysqli_real_escape_string($koneksi, $_REQUEST['username']) : '';

    if ($username == '') {
        echo json_encode(["status" => "error", "message" => "Username tidak boleh kosong!"]);
        exit;
    }

    $cek_user = mysqli_query($koneksi, "SELECT * FROM user WHERE username = '$username'");
    $cek_admin = mysqli_query($koneksi, "SELECT * FROM admin WHERE username = '$username'");

    if (mysqli_num_rows($cek_user) > 0 || mysqli_num_rows($cek_admin) > 0) {
        $kode_otp_palsu = rand(1000, 9999);
        echo json_encode([
            "status" => "success", 
            "otp" => $kode_otp_palsu, 
            "message" => "Simulasi OTP Berhasil"
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Username tidak ditemukan di sistem!"]);
    }
    exit;
}

// ==========================================================================
// 17. UPDATE PASSWORD BARU SETELAH OTP
// ==========================================================================
if ($action == 'reset_password_akhir') {
    $username = isset($_REQUEST['username']) ? mysqli_real_escape_string($koneksi, $_REQUEST['username']) : '';
    $password_baru = isset($_REQUEST['password_baru']) ? $_REQUEST['password_baru'] : '';

    if ($username == '' || $password_baru == '') {
        echo json_encode(["status" => "error", "message" => "Data tidak lengkap!"]);
        exit;
    }

    $update_user = mysqli_query($koneksi, "UPDATE user SET password = '$password_baru' WHERE username = '$username'");
    $update_admin = mysqli_query($koneksi, "UPDATE admin SET password = '$password_baru' WHERE username = '$username'");

    if ($update_user || $update_admin) {
        echo json_encode(["status" => "success", "message" => "Password berhasil diperbarui!"]);
    } else {
        echo json_encode(["status" => "error", "message" => mysqli_error($koneksi)]);
    }
    exit;
}

// ==========================================================================
// 18. TAMBAH RESEP BARU (PUBLIK) - INTEGRATED BASE64 UPLOAD
// ==========================================================================
if ($action == 'tambah_resep_publik') {
    $id_user = (isset($_REQUEST['id_user']) && $_REQUEST['id_user'] !== '') ? intval($_REQUEST['id_user']) : 0;

    if ($id_user === 0) {
        echo json_encode(["status" => "error", "message" => "Gagal upload: Sesi ID tidak sah!"]);
        exit;
    }
    
    $nama_resep      = isset($_REQUEST['nama_resep']) ? mysqli_real_escape_string($koneksi, $_REQUEST['nama_resep']) : '';
    $deskripsi       = isset($_REQUEST['deskripsi']) ? mysqli_real_escape_string($koneksi, $_REQUEST['deskripsi']) : '';
    $waktu_masak     = isset($_REQUEST['waktu_masak']) ? mysqli_real_escape_string($koneksi, $_REQUEST['waktu_masak']) : '';
    $porsi           = isset($_REQUEST['porsi']) ? mysqli_real_escape_string($koneksi, $_REQUEST['porsi']) : '';
    $bahan_bahan     = isset($_REQUEST['bahan_bahan']) ? mysqli_real_escape_string($koneksi, $_REQUEST['bahan_bahan']) : '';
    $langkah_memasak = isset($_REQUEST['langkah_memasak']) ? mysqli_real_escape_string($koneksi, $_REQUEST['langkah_memasak']) : '';

    $nama_file_gambar = 'Default_menu.jpeg'; 

    if (isset($_POST['foto_resep_base64']) && $_POST['foto_resep_base64'] !== "") {
        $gambar_biner = base64_decode($_POST['foto_resep_base64']);
        $nama_acak = "resep_" . time() . "_" . uniqid() . '.jpg';
        
        if (!is_dir("uploads/")) {
            @mkdir("uploads/", 0777, true);
        }
        
        if (@file_put_contents("uploads/" . $nama_acak, $gambar_biner)) {
            $nama_file_gambar = $nama_acak;
        }
    }

    mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS = 0;");

    $query = "INSERT INTO resep (id_user, nama_resep, deskripsi, foto_resep, waktu_masak, porsi, bahan_bahan, langkah_memasak) 
              VALUES ($id_user, '$nama_resep', '$deskripsi', '$nama_file_gambar', '$waktu_masak', '$porsi', '$bahan_bahan', '$langkah_memasak')";

    $eksekusi = mysqli_query($koneksi, $query);
    
    mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS = 1;");

    if ($eksekusi) {
        echo json_encode(["status" => "success", "message" => "Resep berhasil dipublikasikan!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "MySQL Error: " . mysqli_error($koneksi)]);
    }
    exit;
}

// ==========================================================================
// 19. SIMPAN KE TABEL DRAFT
// ==========================================================================
if ($action == 'tambah_resep_draft') {
    $id_user = (isset($_REQUEST['id_user']) && $_REQUEST['id_user'] !== '') ? intval($_REQUEST['id_user']) : 0;

    if ($id_user === 0) {
        echo json_encode(["status" => "error", "message" => "Gagal menyimpan draft: Sesi ID tidak sah!"]);
        exit;
    }
    
    $nama_resep      = isset($_REQUEST['nama_resep']) ? mysqli_real_escape_string($koneksi, $_REQUEST['nama_resep']) : '';
    $deskripsi       = isset($_REQUEST['deskripsi']) ? mysqli_real_escape_string($koneksi, $_REQUEST['deskripsi']) : '';
    $waktu_masak     = isset($_REQUEST['waktu_masak']) ? mysqli_real_escape_string($koneksi, $_REQUEST['waktu_masak']) : '';
    $porsi           = isset($_REQUEST['porsi']) ? mysqli_real_escape_string($koneksi, $_REQUEST['porsi']) : '';
    $bahan_bahan     = isset($_REQUEST['bahan_bahan']) ? mysqli_real_escape_string($koneksi, $_REQUEST['bahan_bahan']) : '';
    $langkah_memasak = isset($_REQUEST['langkah_memasak']) ? mysqli_real_escape_string($koneksi, $_REQUEST['langkah_memasak']) : '';

    $nama_file_gambar = 'Default_menu.jpeg';

    if (isset($_POST['foto_resep_base64']) && $_POST['foto_resep_base64'] !== "") {
        $gambar_biner = base64_decode($_POST['foto_resep_base64']);
        $nama_acak = "draft_" . time() . "_" . uniqid() . '.jpg';
        if (!is_dir("uploads/")) {
            @mkdir("uploads/", 0777, true);
        }
        if (@file_put_contents("uploads/" . $nama_acak, $gambar_biner)) {
            $nama_file_gambar = $nama_acak;
        }
    }

    mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS = 0;");

    $query = "INSERT INTO draft (id_user, nama_resep, deskripsi, foto_resep, waktu_masak, porsi, bahan_bahan, langkah_memasak) 
              VALUES ($id_user, '$nama_resep', '$deskripsi', '$nama_file_gambar', '$waktu_masak', '$porsi', '$bahan_bahan', '$langkah_memasak')";

    $eksekusi = mysqli_query($koneksi, $query);
    mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS = 1;");

    if ($eksekusi) {
        // FIX: Kembalikan id_draft agar upload_resep.html bisa simpan cadangan lokal dengan key yang benar
        $id_draft_baru = mysqli_insert_id($koneksi);
        echo json_encode([
            "status"   => "success",
            "message"  => "Resep berhasil disimpan sebagai Draft",
            "id_draft" => $id_draft_baru
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "MySQL Error: " . mysqli_error($koneksi)]);
    }
    exit;
}

// ==========================================================================
// 20. UPDATE DRAFT YANG SUDAH ADA
// ==========================================================================
if ($action == 'update_resep_draft') {
    $id_draft = isset($_REQUEST['id_draft']) ? intval($_REQUEST['id_draft']) : 0;
    $id_user  = (isset($_REQUEST['id_user']) && $_REQUEST['id_user'] !== '') ? intval($_REQUEST['id_user']) : 0;

    if ($id_draft === 0 || $id_user === 0) {
        echo json_encode(["status" => "error", "message" => "ID draft atau ID user tidak valid!"]);
        exit;
    }

    $nama_resep      = isset($_REQUEST['nama_resep']) ? mysqli_real_escape_string($koneksi, $_REQUEST['nama_resep']) : '';
    $deskripsi       = isset($_REQUEST['deskripsi']) ? mysqli_real_escape_string($koneksi, $_REQUEST['deskripsi']) : '';
    $waktu_masak     = isset($_REQUEST['waktu_masak']) ? mysqli_real_escape_string($koneksi, $_REQUEST['waktu_masak']) : '';
    $porsi           = isset($_REQUEST['porsi']) ? mysqli_real_escape_string($koneksi, $_REQUEST['porsi']) : '';
    $bahan_bahan     = isset($_REQUEST['bahan_bahan']) ? mysqli_real_escape_string($koneksi, $_REQUEST['bahan_bahan']) : '';
    $langkah_memasak = isset($_REQUEST['langkah_memasak']) ? mysqli_real_escape_string($koneksi, $_REQUEST['langkah_memasak']) : '';

    // Ambil nama foto lama sebagai fallback jika tidak ada foto baru
    $res_lama = mysqli_query($koneksi, "SELECT foto_resep FROM draft WHERE id_draft = $id_draft");
    $row_lama = mysqli_fetch_assoc($res_lama);
    $nama_file_gambar = $row_lama ? $row_lama['foto_resep'] : 'Default_menu.jpeg';

    if (isset($_POST['foto_resep_base64']) && $_POST['foto_resep_base64'] !== "") {
        $gambar_biner = base64_decode($_POST['foto_resep_base64']);
        $nama_acak = "draft_" . time() . "_" . uniqid() . '.jpg';
        if (!is_dir("uploads/")) {
            @mkdir("uploads/", 0777, true);
        }
        if (@file_put_contents("uploads/" . $nama_acak, $gambar_biner)) {
            $nama_file_gambar = $nama_acak;
        }
    }

    $query = "UPDATE draft SET 
                nama_resep='$nama_resep', 
                deskripsi='$deskripsi', 
                foto_resep='$nama_file_gambar', 
                waktu_masak='$waktu_masak', 
                porsi='$porsi', 
                bahan_bahan='$bahan_bahan', 
                langkah_memasak='$langkah_memasak' 
              WHERE id_draft=$id_draft AND id_user=$id_user";

    $eksekusi = mysqli_query($koneksi, $query);

    if ($eksekusi) {
        echo json_encode([
            "status"   => "success",
            "message"  => "Draft berhasil diperbarui",
            "id_draft" => $id_draft
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "MySQL Error: " . mysqli_error($koneksi)]);
    }
    exit;
}

// ==========================================================================
// 21. UPDATE RESEP PUBLIK YANG SUDAH ADA
// ==========================================================================
if ($action == 'update_resep_publik') {
    $id_resep = isset($_REQUEST['id_resep']) ? intval($_REQUEST['id_resep']) : 0;
    $id_user  = (isset($_REQUEST['id_user']) && $_REQUEST['id_user'] !== '') ? intval($_REQUEST['id_user']) : 0;

    if ($id_resep === 0 && $id_user === 0) {
        echo json_encode(["status" => "error", "message" => "ID resep atau ID user tidak valid!"]);
        exit;
    }

    $nama_resep      = isset($_REQUEST['nama_resep']) ? mysqli_real_escape_string($koneksi, $_REQUEST['nama_resep']) : '';
    $deskripsi       = isset($_REQUEST['deskripsi']) ? mysqli_real_escape_string($koneksi, $_REQUEST['deskripsi']) : '';
    $waktu_masak     = isset($_REQUEST['waktu_masak']) ? mysqli_real_escape_string($koneksi, $_REQUEST['waktu_masak']) : '';
    $porsi           = isset($_REQUEST['porsi']) ? mysqli_real_escape_string($koneksi, $_REQUEST['porsi']) : '';
    $bahan_bahan     = isset($_REQUEST['bahan_bahan']) ? mysqli_real_escape_string($koneksi, $_REQUEST['bahan_bahan']) : '';
    $langkah_memasak = isset($_REQUEST['langkah_memasak']) ? mysqli_real_escape_string($koneksi, $_REQUEST['langkah_memasak']) : '';

    // Jika ada id_draft di request, artinya draft dipublikasikan — hapus draft lama setelah sukses insert
    $id_draft_asal = isset($_REQUEST['id_draft']) ? intval($_REQUEST['id_draft']) : 0;

    // Ambil nama foto lama sebagai fallback
    $nama_file_gambar = 'Default_menu.jpeg';
    if ($id_resep > 0) {
        $res_lama = mysqli_query($koneksi, "SELECT foto_resep FROM resep WHERE id_resep = $id_resep");
        $row_lama = mysqli_fetch_assoc($res_lama);
        if ($row_lama) $nama_file_gambar = $row_lama['foto_resep'];
    }

    if (isset($_POST['foto_resep_base64']) && $_POST['foto_resep_base64'] !== "") {
        $gambar_biner = base64_decode($_POST['foto_resep_base64']);
        $nama_acak = "resep_" . time() . "_" . uniqid() . '.jpg';
        if (!is_dir("uploads/")) {
            @mkdir("uploads/", 0777, true);
        }
        if (@file_put_contents("uploads/" . $nama_acak, $gambar_biner)) {
            $nama_file_gambar = $nama_acak;
        }
    }

    mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS = 0;");

    if ($id_resep > 0) {
        // Update resep publik yang sudah ada
        $query = "UPDATE resep SET 
                    nama_resep='$nama_resep', 
                    deskripsi='$deskripsi', 
                    foto_resep='$nama_file_gambar', 
                    waktu_masak='$waktu_masak', 
                    porsi='$porsi', 
                    bahan_bahan='$bahan_bahan', 
                    langkah_memasak='$langkah_memasak' 
                  WHERE id_resep=$id_resep";
        mysqli_query($koneksi, $query);
    } else {
        // Insert resep baru dari draft yang dipublikasikan
        $query = "INSERT INTO resep (id_user, nama_resep, deskripsi, foto_resep, waktu_masak, porsi, bahan_bahan, langkah_memasak) 
                  VALUES ($id_user, '$nama_resep', '$deskripsi', '$nama_file_gambar', '$waktu_masak', '$porsi', '$bahan_bahan', '$langkah_memasak')";
        mysqli_query($koneksi, $query);

        // Hapus draft asal setelah berhasil dipublikasikan
        if ($id_draft_asal > 0) {
            mysqli_query($koneksi, "DELETE FROM draft WHERE id_draft = $id_draft_asal");
        }
    }

    mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS = 1;");

    echo json_encode(["status" => "success", "message" => "Resep berhasil diperbarui dan dipublikasikan!"]);
    exit;
}
?>