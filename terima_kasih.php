<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Terima Kasih</title>

<!-- POPPINS FONT -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: 'Poppins', sans-serif;
}

body {
    background: #f5f7fb;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 20px;
}

.container {
    background: #ffffff;
    padding: 40px 32px;
    border-radius: 18px;
    box-shadow: 0 12px 28px rgba(0,0,0,0.08);
    text-align: center;
    max-width: 460px;
    width: 100%;
    transition: all 0.3s ease;
}

.container:hover {
    transform: translateY(-6px);
    box-shadow: 0 16px 34px rgba(0,0,0,0.12);
}

.icon {
    font-size: 48px;
    margin-bottom: 10px;
}

h2 {
    font-size: 1.8rem;
    color: #333;
    margin-bottom: 14px;
    font-weight: 700;
}

p {
    font-size: 0.98rem;
    color: #666;
    margin-bottom: 28px;
    line-height: 1.6;
    font-weight: 400;
}

.btn {
    display: inline-block;
    background: linear-gradient(90deg, #4f46e5, #6366f1);
    color: #fff;
    padding: 12px 28px;
    border-radius: 999px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.25s ease;
    box-shadow: 0 6px 14px rgba(79,70,229,0.35);
}

.btn:hover {
    transform: scale(1.05);
    box-shadow: 0 10px 22px rgba(79,70,229,0.45);
}

/* RESPONSIVE */
@media(max-width: 480px){
    .container {
        padding: 30px 22px;
    }
    h2 {
        font-size: 1.5rem;
    }
    p {
        font-size: 0.92rem;
    }
}
</style>
</head>

<body>
<div class="container">
    <div class="icon">🙏</div>
    <h2>Terima Kasih</h2>
    <p>
        Terima kasih telah meluangkan waktu untuk mengisi kuesioner ini.  
        Jawaban Anda sangat berarti dalam proses evaluasi dan pengembangan layanan aplikasi POSPAY.
    </p>
    <a href="index.php" class="btn">Kembali ke Beranda</a>
</div>
</body>
</html>
