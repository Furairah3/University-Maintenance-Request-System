<?php
require_once __DIR__ . '/../backend/includes/Auth.php';
require_once __DIR__ . '/../backend/includes/helpers.php';
Auth::requireRole('student');

$db = Database::getInstance();
$pageTitle = 'New Request';
$error = '';

// Get active categories
$categories = $db->fetchAll("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY display_order");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $location = sanitize($_POST['location'] ?? '');
        $roomNumber = sanitize($_POST['room_number'] ?? '');

        if (empty($title) || strlen($title) < 5) {
            $error = 'Title must be at least 5 characters long.';
        } elseif (empty($description) || strlen($description) < 10) {
            $error = 'Please provide a detailed description (at least 10 characters).';
        } elseif ($categoryId <= 0) {
            $error = 'Please select a category.';
        } else {
            // Handle image upload
            $imagePath = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadResult = handleImageUpload($_FILES['image']);
                if (!$uploadResult['success']) {
                    $error = $uploadResult['error'];
                } else {
                    $imagePath = $uploadResult['path'];
                }
            }

            if (empty($error)) {
                try {
                    $requestId = $db->insert(
                        "INSERT INTO requests (title, description, category_id, created_by, image_url, location, room_number)
                         VALUES (?, ?, ?, ?, ?, ?, ?)",
                        [$title, $description, $categoryId, Auth::getUserId(), $imagePath, $location, $roomNumber]
                    );

                    Logger::info('Request submitted', ['request_id' => $requestId]);
                    Logger::activity('submit_request', 'request', $requestId);

                    redirect('dashboard.php', 'Your maintenance request has been submitted successfully!', 'success');
                } catch (Exception $e) {
                    Logger::error('Request submission failed', ['error' => $e->getMessage()]);
                    $error = 'Failed to submit request. Please try again.';
                }
            }
        }
    }
}

include __DIR__ . '/../frontend/includes/student-header.php';
?>

<div class="card" style="max-width:700px;">
    <div class="card-header">
        <h3>Submit Maintenance Request</h3>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <?= csrfField() ?>

            <div class="form-group">
                <label class="form-label" for="title">Title <span class="required">*</span></label>
                <input type="text" id="title" name="title" class="form-control"
                       placeholder="Brief summary of the issue (e.g., Broken light in room 204)"
                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                       required minlength="5" maxlength="200">
            </div>

            <div class="form-group">
                <label class="form-label" for="category_id">Category <span class="required">*</span></label>
                <select id="category_id" name="category_id" class="form-control" required>
                    <option value="">-- Select category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= (($_POST['category_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="location">Building / Block</label>
                    <input type="text" id="location" name="location" class="form-control"
                           placeholder="e.g., Block A"
                           value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="room_number">Room number</label>
                    <input type="text" id="room_number" name="room_number" class="form-control"
                           placeholder="e.g., 204"
                           value="<?= htmlspecialchars($_POST['room_number'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description <span class="required">*</span></label>
                <textarea id="description" name="description" class="form-control" rows="5"
                          placeholder="Describe the issue in detail. What's wrong? When did it start? How urgent is it?"
                          required minlength="10"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Attach photo (optional)</label>
                <div class="file-upload" onclick="document.getElementById('image').click()">
                    <input type="file" id="image" name="image" accept="image/jpeg,image/png"
                           onchange="previewImage(this)">
                    <div class="upload-icon">📷</div>
                    <p>Click to upload a photo of the issue</p>
                    <p class="file-info">JPEG or PNG, max 5 MB</p>
                </div>
                <div id="imagePreview" class="image-preview" style="display:none;">
                    <img id="previewImg" src="" alt="Preview">
                </div>
            </div>

            <div style="display:flex;gap:12px;">
                <button type="submit" class="btn btn-primary">Submit Request</button>
                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    const img = document.getElementById('previewImg');
    if (input.files && input.files[0]) {
        if (input.files[0].size > 5 * 1024 * 1024) {
            alert('File size must be under 5 MB.');
            input.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include __DIR__ . '/../frontend/includes/footer.php'; ?>
