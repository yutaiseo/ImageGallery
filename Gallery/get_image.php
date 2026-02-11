<?php
include 'install_guard.php';
require_once __DIR__ . '/../ctrol/config/config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(403);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit;
}

$stmt = $pdo->prepare('SELECT id, title, description, file_path, is_remote FROM images WHERE id = ? AND is_deleted = 0');
$stmt->execute([$id]);
$image = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$image) {
    http_response_code(404);
    exit;
}

$imgUrl = build_image_url($image['file_path'], (int)$image['is_remote']);
?>
<div class="modal-dialog">
    <div class="modal-content">
        <form action="update_image.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo (int)$image['id']; ?>">
            <div class="modal-header">
                <h5 class="modal-title">编辑图片</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">标题 <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" required value="<?php echo htmlspecialchars($image['title']); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">描述</label>
                    <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($image['description']); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">当前图片</label>
                    <img src="<?php echo htmlspecialchars($imgUrl); ?>" class="img-fluid mb-2 edit-image-preview" alt="<?php echo htmlspecialchars($image['title']); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">更新图片</label>
                    <input type="file" name="new_image" class="form-control" accept="image/jpeg, image/png, image/gif, image/webp">
                    <small class="form-text text-muted">留空则不更新图片 (最大2MB)</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>
