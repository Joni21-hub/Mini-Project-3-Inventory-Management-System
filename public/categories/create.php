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

        // Cek duplikasi nama
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

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex align-items-center">
            <a href="index.php" class="btn btn-outline-secondary btn-sm me-3">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
            <div>
                <h3 class="fw-bold text-dark mb-0">Tambah Kategori Baru</h3>
                <small class="text-muted">Master kategori untuk mengelompokkan produk</small>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger border-0 shadow-sm" role="alert">
        <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-circle me-1"></i> Terjadi kesalahan:</h6>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="create.php">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= e($name) ?>" placeholder="Contoh: Perangkat Audio" required autofocus>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold text-secondary small">Deskripsi (Opsional)</label>
                        <textarea name="description" rows="3" class="form-control" placeholder="Keterangan singkat mengenai kategori ini"><?= e($description) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-save me-1"></i> Simpan Kategori
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
