/**
 * Enhanced Image Upload Handler
 * - Client-side image compression with minimal visible quality loss
 * - Multiple file upload with progress indication
 * - Preview before upload
 * - Drag & drop support
 */

class ImageUploadHandler {
    // Video MIME types supported for upload
    static VIDEO_TYPES = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm', 'video/x-matroska', 'video/mpeg', 'video/3gpp'];

    constructor(options = {}) {
        // Set defaults first, then override with provided options
        const defaults = {
            fileInput: '#images',
            dropZone: '#dropZone',
            previewContainer: '#imagePreviewContainer',
            uploadButton: '#uploadButton',
            form: '#uploadForm',
            maxWidth: 1920,
            maxHeight: 1920,
            quality: 0.85,
            maxFileSize: 10 * 1024 * 1024, // 10MB before compression (photos only)
            maxVideoSize: 8 * 1024 * 1024 * 1024, // 8GB for videos
            allowedTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            allowVideos: true, // Allow video uploads alongside images
            uploadUrl: 'ajax-upload.php',
            onUploadStart: () => {},
            onUploadProgress: () => {},
            onUploadComplete: () => {},
            onUploadError: () => {}
        };
        
        this.options = { ...defaults, ...options };

        this.files = [];
        this.processedFiles = [];
        this.init();
    }

    isVideoFile(file) {
        return ImageUploadHandler.VIDEO_TYPES.includes(file.type);
    }

    init() {
        this.fileInput = document.querySelector(this.options.fileInput);
        this.dropZone = document.querySelector(this.options.dropZone);
        this.previewContainer = document.querySelector(this.options.previewContainer);
        this.uploadButton = document.querySelector(this.options.uploadButton);
        this.form = document.querySelector(this.options.form);

        if (this.fileInput) {
            this.fileInput.addEventListener('change', (e) => this.handleFileSelect(e));
        }

        if (this.dropZone) {
            this.setupDragAndDrop();
        }

        if (this.form) {
            this.form.addEventListener('submit', (e) => this.handleFormSubmit(e));
        }
    }

    setupDragAndDrop() {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            this.dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            this.dropZone.addEventListener(eventName, () => {
                this.dropZone.classList.add('drag-over');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            this.dropZone.addEventListener(eventName, () => {
                this.dropZone.classList.remove('drag-over');
            });
        });

        this.dropZone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            this.handleFiles(Array.from(files));
        });

        // Make drop zone clickable
        this.dropZone.addEventListener('click', () => {
            this.fileInput.click();
        });
    }

    handleFileSelect(e) {
        const files = Array.from(e.target.files);
        this.handleFiles(files);
    }

    async handleFiles(files) {
        const validFiles = files.filter(file => {
            const isImage = this.options.allowedTypes.includes(file.type);
            const isVideo = this.options.allowVideos && this.isVideoFile(file);

            if (!isImage && !isVideo) {
                this.showError(`${file.name}: Invalid file type. Allowed: JPG, PNG, GIF, WebP (photos) or MP4, MOV, AVI, WebM, MKV (videos).`);
                return false;
            }
            if (isVideo && file.size > this.options.maxVideoSize) {
                this.showError(`${file.name}: Video too large (${this.formatFileSize(file.size)}). Maximum is ${this.formatFileSize(this.options.maxVideoSize)}.`);
                return false;
            }
            if (isImage && file.size > this.options.maxFileSize) {
                this.showError(`${file.name}: File too large (${this.formatFileSize(file.size)}). Maximum is ${this.formatFileSize(this.options.maxFileSize)}.`);
                return false;
            }
            return true;
        });

        for (const file of validFiles) {
            const existingIndex = this.files.findIndex(f => f.name === file.name && f.size === file.size);
            if (existingIndex === -1) {
                this.files.push(file);
                await this.addPreview(file, this.files.length - 1);
            }
        }

        this.updateUploadButton();
    }

    async addPreview(file, index) {
        const preview = document.createElement('div');
        preview.className = 'image-preview-item';
        preview.dataset.index = index;
        preview.innerHTML = `
            <div class="preview-image-container">
                <div class="preview-loading">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="preview-info">
                <div class="preview-name" title="${this.escapeHtml(file.name)}">${this.truncateFilename(file.name)}</div>
                <div class="preview-size original-size">${this.formatFileSize(file.size)}</div>
                <div class="preview-status">
                    <span class="badge bg-secondary">Pending</span>
                </div>
            </div>
            <button type="button" class="preview-remove" data-index="${index}" title="Remove">
                <i class="fas fa-times"></i>
            </button>
            <div class="preview-progress" style="display: none;">
                <div class="progress-bar" role="progressbar" style="width: 0%"></div>
            </div>
        `;

        this.previewContainer.appendChild(preview);

        // Add remove button handler
        preview.querySelector('.preview-remove').addEventListener('click', (e) => {
            e.stopPropagation();
            this.removeFile(index);
        });

        // Generate thumbnail
        const container = preview.querySelector('.preview-image-container');
        if (this.isVideoFile(file)) {
            // Show video icon placeholder for videos
            container.innerHTML = `<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#666;">
                <i class="fas fa-video" style="font-size:2rem;color:#dc3545;"></i>
                <small style="margin-top:4px;font-size:0.65rem;color:#888;">VIDEO</small>
            </div>`;
        } else {
            try {
                const thumbnail = await this.generateThumbnail(file);
                container.innerHTML = `<img src="${thumbnail}" alt="${this.escapeHtml(file.name)}" />`;
            } catch (error) {
                console.error('Error generating thumbnail:', error);
                container.innerHTML = '<i class="fas fa-image text-muted"></i>';
            }
        }
    }

    async generateThumbnail(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    
                    // Thumbnail size
                    const maxSize = 100;
                    let width = img.width;
                    let height = img.height;
                    
                    if (width > height) {
                        if (width > maxSize) {
                            height = (height * maxSize) / width;
                            width = maxSize;
                        }
                    } else {
                        if (height > maxSize) {
                            width = (width * maxSize) / height;
                            height = maxSize;
                        }
                    }
                    
                    canvas.width = width;
                    canvas.height = height;
                    ctx.drawImage(img, 0, 0, width, height);
                    
                    resolve(canvas.toDataURL('image/jpeg', 0.7));
                };
                img.onerror = reject;
                img.src = e.target.result;
            };
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }

    async compressImage(file) {
        // GIF files should not be compressed (animated)
        if (file.type === 'image/gif') {
            return file;
        }

        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    
                    let width = img.width;
                    let height = img.height;
                    const maxWidth = this.options.maxWidth;
                    const maxHeight = this.options.maxHeight;
                    
                    // Only resize if larger than max dimensions
                    if (width > maxWidth || height > maxHeight) {
                        const ratio = Math.min(maxWidth / width, maxHeight / height);
                        width = Math.round(width * ratio);
                        height = Math.round(height * ratio);
                    }
                    
                    canvas.width = width;
                    canvas.height = height;
                    
                    // Use better image smoothing
                    ctx.imageSmoothingEnabled = true;
                    ctx.imageSmoothingQuality = 'high';
                    
                    ctx.drawImage(img, 0, 0, width, height);
                    
                    // Determine output type
                    let outputType = file.type;
                    let quality = this.options.quality;
                    
                    // For PNG with transparency, keep as PNG
                    if (file.type === 'image/png') {
                        // Check if image has transparency
                        const hasTransparency = this.checkTransparency(ctx, width, height);
                        if (!hasTransparency) {
                            // No transparency, convert to JPEG for better compression
                            outputType = 'image/jpeg';
                        } else {
                            quality = undefined; // PNG doesn't use quality parameter
                        }
                    }
                    
                    canvas.toBlob((blob) => {
                        if (blob) {
                            // Map output type to file extension
                            const typeToExt = {
                                'image/jpeg': 'jpg',
                                'image/png': 'png',
                                'image/gif': 'gif',
                                'image/webp': 'webp'
                            };
                            const extension = typeToExt[outputType] || 'jpg';
                            const newName = file.name.replace(/\.[^/.]+$/, '.' + extension);
                            const compressedFile = new File([blob], newName, {
                                type: outputType,
                                lastModified: Date.now()
                            });
                            resolve(compressedFile);
                        } else {
                            resolve(file); // Fallback to original
                        }
                    }, outputType, quality);
                };
                img.onerror = () => resolve(file);
                img.src = e.target.result;
            };
            reader.onerror = () => resolve(file);
            reader.readAsDataURL(file);
        });
    }

    checkTransparency(ctx, width, height) {
        try {
            const imageData = ctx.getImageData(0, 0, width, height);
            const data = imageData.data;
            for (let i = 3; i < data.length; i += 4) {
                if (data[i] < 255) {
                    return true;
                }
            }
        } catch (e) {
            // Security error if canvas is tainted
            return false;
        }
        return false;
    }

    removeFile(index) {
        const preview = this.previewContainer.querySelector(`[data-index="${index}"]`);
        if (preview) {
            preview.remove();
        }
        this.files[index] = null;
        this.processedFiles[index] = null;
        this.updateUploadButton();
    }

    updateUploadButton() {
        const activeFiles = this.files.filter(f => f !== null).length;
        if (this.uploadButton) {
            this.uploadButton.disabled = activeFiles === 0;
            const text = activeFiles > 0 ? 
                `<i class="fas fa-upload"></i> Upload ${activeFiles} File${activeFiles > 1 ? 's' : ''}` : 
                '<i class="fas fa-upload"></i> Upload File(s)';
            this.uploadButton.innerHTML = text;
        }
    }

    async handleFormSubmit(e) {
        e.preventDefault();

        const activeFiles = this.files.filter(f => f !== null);
        if (activeFiles.length === 0) {
            this.showError('Please select at least one file to upload.');
            return;
        }

        // Disable upload button
        if (this.uploadButton) {
            this.uploadButton.disabled = true;
            this.uploadButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        }

        // Get form data
        const formData = new FormData(this.form);
        
        // Remove existing files from FormData
        formData.delete('images[]');

        this.options.onUploadStart();

        // Show global loader
        this.showGlobalLoader();

        let uploadedCount = 0;
        let errorCount = 0;
        const errors = [];

        // Process and compress each file, then upload
        for (let i = 0; i < this.files.length; i++) {
            const file = this.files[i];
            if (!file) continue;

            const preview = this.previewContainer.querySelector(`[data-index="${i}"]`);
            const isVideo = this.isVideoFile(file);

            if (preview) {
                preview.querySelector('.preview-status .badge').className = 'badge bg-info';
                preview.querySelector('.preview-status .badge').textContent = isVideo ? 'Uploading...' : 'Compressing...';
            }

            try {
                let fileToUpload = file;

                if (!isVideo) {
                    // Compress image files only
                    fileToUpload = await this.compressImage(file);
                    
                    // Update preview with compressed size
                    if (preview) {
                        const sizeInfo = preview.querySelector('.preview-size');
                        if (fileToUpload.size < file.size) {
                            const savings = ((1 - fileToUpload.size / file.size) * 100).toFixed(0);
                            sizeInfo.innerHTML = `${this.formatFileSize(fileToUpload.size)} <span class="text-success">(−${savings}%)</span>`;
                        }
                    }
                }

                if (preview) {
                    preview.querySelector('.preview-status .badge').textContent = 'Uploading...';
                    preview.querySelector('.preview-progress').style.display = 'block';
                }

                // Upload file
                const uploadFormData = new FormData(this.form);
                uploadFormData.delete('images[]');
                uploadFormData.append('images[]', fileToUpload);
                uploadFormData.append('ajax_upload', '1');

                const result = await this.uploadFile(uploadFormData, preview);
                
                if (result.success) {
                    uploadedCount++;
                    if (preview) {
                        preview.querySelector('.preview-status .badge').className = 'badge bg-success';
                        preview.querySelector('.preview-status .badge').textContent = 'Uploaded';
                        preview.classList.add('upload-success');
                    }
                } else {
                    errorCount++;
                    errors.push(result.message || `Error uploading ${file.name}`);
                    if (preview) {
                        preview.querySelector('.preview-status .badge').className = 'badge bg-danger';
                        preview.querySelector('.preview-status .badge').textContent = 'Failed';
                        preview.classList.add('upload-error');
                    }
                }
            } catch (error) {
                console.error('Upload error:', error);
                errorCount++;
                errors.push(`Error uploading ${file.name}: ${error.message}`);
                if (preview) {
                    preview.querySelector('.preview-status .badge').className = 'badge bg-danger';
                    preview.querySelector('.preview-status .badge').textContent = 'Error';
                    preview.classList.add('upload-error');
                }
            }
        }

        // Hide global loader
        this.hideGlobalLoader();

        // Re-enable upload button
        if (this.uploadButton) {
            this.uploadButton.disabled = false;
            this.uploadButton.innerHTML = '<i class="fas fa-upload"></i> Upload Image(s)';
        }

        // Show results
        this.options.onUploadComplete({ uploadedCount, errorCount, errors });

        if (uploadedCount > 0) {
            this.showSuccess(`${uploadedCount} file${uploadedCount > 1 ? 's' : ''} uploaded successfully!`);
        }
        
        if (errors.length > 0) {
            this.showError(errors.join('<br>'));
        }

        // Clear successfully uploaded files
        if (uploadedCount > 0 && errorCount === 0) {
            setTimeout(() => {
                this.clearFiles();
            }, 2000);
        }
    }

    uploadFile(formData, preview) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    if (preview) {
                        const progressBar = preview.querySelector('.progress-bar');
                        progressBar.style.width = percent + '%';
                        progressBar.setAttribute('aria-valuenow', percent);
                    }
                    this.options.onUploadProgress(percent);
                }
            });

            xhr.addEventListener('load', () => {
                try {
                    const response = JSON.parse(xhr.responseText);
                    resolve(response);
                } catch (e) {
                    reject(new Error('Invalid server response'));
                }
            });

            xhr.addEventListener('error', () => {
                reject(new Error('Network error'));
            });

            xhr.open('POST', this.options.uploadUrl);
            xhr.send(formData);
        });
    }

    clearFiles() {
        this.files = [];
        this.processedFiles = [];
        if (this.previewContainer) {
            this.previewContainer.innerHTML = '';
        }
        if (this.fileInput) {
            this.fileInput.value = '';
        }
        this.updateUploadButton();
    }

    showGlobalLoader() {
        let loader = document.getElementById('uploadLoader');
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'uploadLoader';
            loader.className = 'upload-loader';
            loader.innerHTML = `
                <div class="upload-loader-content">
                    <div class="spinner-border text-success mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mb-0">Compressing and uploading images...</p>
                    <p class="text-muted small">Please wait, do not close this page</p>
                </div>
            `;
            document.body.appendChild(loader);
        }
        loader.style.display = 'flex';
    }

    hideGlobalLoader() {
        const loader = document.getElementById('uploadLoader');
        if (loader) {
            loader.style.display = 'none';
        }
    }

    showSuccess(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                html: message,
                confirmButtonColor: '#4CAF50'
            });
        } else {
            alert(message);
        }
    }

    showError(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                html: message,
                confirmButtonColor: '#4CAF50'
            });
        } else {
            alert('Error: ' + message);
        }
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    truncateFilename(name, maxLength = 20) {
        if (name.length <= maxLength) return name;
        const ext = name.split('.').pop();
        const baseName = name.substring(0, name.length - ext.length - 1);
        const truncatedBase = baseName.substring(0, maxLength - ext.length - 4) + '...';
        return truncatedBase + '.' + ext;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Auto-initialize if data attributes present
document.addEventListener('DOMContentLoaded', () => {
    const uploadForms = document.querySelectorAll('[data-image-upload="true"]');
    uploadForms.forEach(form => {
        new ImageUploadHandler({
            form: `#${form.id}`,
            uploadUrl: form.dataset.uploadUrl || 'ajax-upload.php'
        });
    });
});
