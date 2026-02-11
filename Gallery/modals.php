<?php require_once __DIR__ . '/csrf.php'; ?>
<div class="modal fade" id="addModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addImageForm" action="/admin/add_image" method="post" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <div class="modal-header">
                    <h5 class="modal-title">添加图片</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">标题 <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required maxlength="100">
                        <div class="invalid-feedback">请输入标题</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">描述</label>
                        <textarea name="description" class="form-control" maxlength="255"></textarea>
                        <small class="form-text text-muted">最大长度255字符</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">类型 <span class="text-danger">*</span></label>
                        <select class="form-select" id="uploadType" name="type" required>
                            <option value="">请选择类型</option>
                            <option value="remote">远程链接</option>
                            <option value="local">本地上传</option>
                        </select>
                        <div class="invalid-feedback">请选择上传类型</div>
                    </div>
                    <div class="mb-3" id="remoteField">
                        <label class="form-label">远程图片链接 <span class="text-danger">*</span></label>
                        <input type="url" name="remote_url" class="form-control" placeholder="https://example.com/image.jpg">
                        <div class="invalid-feedback">请输入有效的图片URL</div>
                        <small class="form-text text-muted">支持JPG, PNG, GIF,webp格式</small>
                    </div>
                    <div class="mb-3 d-none" id="localField">
                        <label class="form-label">选择图片文件 <span class="text-danger">*</span></label>
                        <div class="file-drop-area d-flex flex-column align-items-center justify-content-center py-4 rounded border border-2 border-dashed">
                            <input type="file" id="imageInput" name="image" class="form-control d-none" accept="image/jpeg, image/png, image/gif, image/webp">
                            <div class="d-flex flex-column align-items-center">
                                <span class="btn btn-primary mb-2">
                                    <i class="bi bi-cloud-upload me-2"></i>选择文件
                                </span>
                                <small class="text-muted">点击或拖放文件到此处</small>
                                <small class="text-muted mt-1">支持JPG, PNG, GIF, webp (最大6MB，自动压缩至800KB)</small>
                                <div id="fileName" class="mt-2 small text-center"></div>
                            </div>
                        </div>
                        <div class="invalid-feedback">请选择有效的图片文件</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">添加</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 编辑图片模态框 -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="editImageForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="id" id="editImageId">
                <div class="modal-header">
                    <h5 class="modal-title">编辑图片</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">标题 <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="editImageTitle" class="form-control" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">描述</label>
                        <textarea name="description" id="editImageDescription" class="form-control" rows="3" maxlength="255"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">更新图片</label>
                        <input type="file" name="new_image" id="editImageFile" class="form-control" accept="image/jpeg, image/png, image/gif, image/webp">
                        <small class="form-text text-muted">留空则不更新图片 (最大6MB，自动压缩至800KB)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary" id="editSaveBtn">保存</button>
                </div>
            </form>
        </div>
    </div>
</div>
