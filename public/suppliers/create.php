<?php
$pageTitle = 'Tambah Mitra Supplier';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_role('admin');

$errors = [];
$name        = '';
$contactName = '';
$phone       = '';
$email       = '';
$address     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = 'Sesi form telah kedaluwarsa. Silakan refresh halaman dan coba lagi.';
    } else {
        $name        = trim($_POST['name'] ?? '');
        $contactName = trim($_POST['contact_name'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $address     = trim($_POST['address'] ?? '');

        validate_string_required($name, 'Nama Supplier', 150, $errors);

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format alamat email tidak valid.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("
                INSERT INTO suppliers (name, contact_name, phone, email, address)
                VALUES (:name, :contact_name, :phone, :email, :address)
            ");
            $stmt->execute([
                'name'         => $name,
                'contact_name' => $contactName ?: null,
                'phone'        => $phone ?: null,
                'email'        => $email ?: null,
                'address'      => $address ?: null,
            ]);

            set_flash('success', "Supplier '{$name}' berhasil didaftarkan.");
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
        <h3 class="fw-bold text-dark mb-0" style="letter-spacing: -0.02em;">Tambah Mitra Pemasok (Supplier)</h3>
        <p class="text-muted small mb-0">Daftarkan vendor atau penyedia produk barang inventaris</p>
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
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <span class="fw-bold text-dark">Formulir Data Vendor</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="create.php">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Nama Perusahaan / Supplier <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= e($name) ?>" placeholder="Contoh: PT Sumber Rezeki Sentosa" required autofocus>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama PIC / Kontak Person</label>
                            <input type="text" name="contact_name" class="form-control" value="<?= e($contactName) ?>" placeholder="Contoh: Budi Gunawan">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nomor Telepon</label>
                            <input type="text" name="phone" class="form-control" value="<?= e($phone) ?>" placeholder="Contoh: 021-1234567">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email Resmi</label>
                        <input type="email" name="email" class="form-control" value="<?= e($email) ?>" placeholder="Contoh: sales@sumberrezeki.co.id">
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Alamat Kantor / Gudang</label>
                        <textarea name="address" rows="3" class="form-control" placeholder="Alamat lengkap supplier"><?= e($address) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary px-4">
                        Simpan Supplier
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
