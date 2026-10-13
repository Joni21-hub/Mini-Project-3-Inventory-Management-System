<?php
$pageTitle = 'Tambah Kategori';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_role('admin');

$errors = [];
$name = '';
$description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = 'Sesi form telah kedaluwarsa. Silakan refresh halaman dan coba lagi.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        validate_string_required($name, 'Nama Kategori', 100, $errors);

        if (empty($errors)) {
            $check = $pdo->prepare("SELECT id FROM categories WHERE name = :name");
            $check->execute(['name' => $name]);
            if ($check->fetch()) {
                $errors[] = "Kategori dengan nama '{$name}' sudah ada.";
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (:name, :description)");
            $stmt->execute([
                'name'        => $name,
                'description' => $description ?: null,
            ]);

            set_flash('success', "Kategori '{$name}' berhasil ditambahkan.");
            header("Location: index.php");
            exit;
        }
    }
}

require_once __DIR__ . '/../../views/header.php';
?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="index.php" class="btn btn-outline-secondary btn-sm">
        &larr; Kembali
    </a>
    <div>
        <h3 class="fw-bold text-dark mb-0" style="letter-spacing: -0.02em;">Tambah Kategori Baru</h3>
        <p class="text-muted small mb-0">Klasifikasi kelompok produk dan pengaturan katalog</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4" role="alert">
        <div class="fw-bold mb-1">Terjadi kesalahan:</div>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <span class="fw-bold text-dark">Formulir Kategori</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="create.php">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= e($name) ?>" placeholder="Contoh: Perangkat Audio & Suara" required autofocus>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Deskripsi (Opsional)</label>
                        <textarea name="description" rows="3" class="form-control" placeholder="Keterangan singkat mengenai kelompok kategori ini"><?= e($description) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary px-4">
                        Simpan Kategori
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
