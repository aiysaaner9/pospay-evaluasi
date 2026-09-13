<?php
session_start();
include "../config/koneksi.php"; // ini bikin $koneksi ada

// Guard koneksi
if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
  die('<div class="alert alert-danger m-3">Koneksi database tidak tersedia. Pastikan <b>../config/koneksi.php</b> benar dan variabelnya <b>$koneksi</b>.</div>');
}

// Kalau sudah login, langsung masuk dashboard
if (isset($_SESSION['admin']) && $_SESSION['admin'] !== '') {
  header("Location: dashboard.php");
  exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $username = trim((string)($_POST['username'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  if ($username === '' || $password === '') {
    $error = "Username dan password wajib diisi.";
  } else {

    $stmt = $koneksi->prepare("SELECT * FROM admin WHERE username = ? LIMIT 1");
    if (!$stmt) {
      $error = "Query error: " . $koneksi->error;
    } else {
      $stmt->bind_param("s", $username);
      $stmt->execute();

      $result = $stmt->get_result();
      $admin  = $result ? $result->fetch_assoc() : null;
      $stmt->close();

      // sesuai punyamu: kolom password_hash
      if ($admin && password_verify($password, (string)($admin['password_hash'] ?? ''))) {
        session_regenerate_id(true);

        // tetap seperti kamu: simpan username
        $_SESSION['admin'] = (string)$admin['username'];

        header("Location: dashboard.php");
        exit;
      } else {
        $error = "Login gagal! Username atau password salah.";
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login Admin</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <style>
    body{
      min-height:100vh;
      background: radial-gradient(1100px 600px at 20% 0%, rgba(90,84,232,.12), transparent),
                  radial-gradient(1100px 600px at 100% 100%, rgba(25,135,84,.10), transparent),
                  #f5f7fb;
    }
    .card{
      border: 1px solid rgba(17,24,39,.08);
      border-radius: 18px;
      box-shadow: 0 18px 50px rgba(0,0,0,.08);
      overflow: hidden;
    }
    .card-header{
      background: rgba(90,84,232,.06);
      border-bottom: 1px solid rgba(17,24,39,.08);
      padding: 16px 18px;
    }
    .brand{
      display:flex; gap:10px; align-items:center;
      font-weight:900;
      letter-spacing:.2px;
    }
    .logo{
      width:40px;height:40px;
      border-radius:14px;
      display:flex;align-items:center;justify-content:center;
      background: rgba(90,84,232,.16);
      border: 1px solid rgba(90,84,232,.22);
      color: rgba(90,84,232,.95);
    }
    .form-control{
      border-radius: 14px;
    }
    .btn{
      border-radius: 14px;
      font-weight: 800;
    }
  </style>
</head>

<body class="d-flex justify-content-center align-items-center p-3">

  <div class="card" style="width:min(390px, 100%);">
    <div class="card-header">
      <div class="brand">
        <div class="logo"><i class="bi bi-shield-lock-fill"></i></div>
        <div>
          Login Admin
          <div class="text-muted" style="font-weight:600; font-size:12px;">Masuk untuk mengelola sistem</div>
        </div>
      </div>
    </div>

    <div class="card-body p-4">
      <?php if ($error !== ''): ?>
        <div class="alert alert-danger" style="border-radius:14px;">
          <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <label class="form-label fw-semibold">Username</label>
        <div class="input-group mb-3">
          <span class="input-group-text" style="border-radius:14px 0 0 14px;">
            <i class="bi bi-person"></i>
          </span>
          <input type="text" name="username" placeholder="Username" class="form-control" required>
        </div>

        <label class="form-label fw-semibold">Password</label>
        <div class="input-group mb-3">
          <span class="input-group-text" style="border-radius:14px 0 0 14px;">
            <i class="bi bi-key"></i>
          </span>
          <input id="pw" type="password" name="password" placeholder="Password" class="form-control" required>
          <button class="btn btn-outline-secondary" type="button" id="togglePw" style="border-radius:0 14px 14px 0;">
            <i class="bi bi-eye"></i>
          </button>
        </div>

        <button type="submit" name="login" class="btn btn-primary w-100">
          <i class="bi bi-box-arrow-in-right me-1"></i>Login
        </button>
      </form>

      <div class="text-center text-muted mt-3" style="font-size:12px;">
        © <?= date('Y'); ?> Admin Panel
      </div>
    </div>
  </div>

<script>
  const pw = document.getElementById('pw');
  const btn = document.getElementById('togglePw');
  btn.addEventListener('click', () => {
    const isPwd = pw.type === 'password';
    pw.type = isPwd ? 'text' : 'password';
    btn.innerHTML = isPwd ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
  });
</script>

</body>
</html>
