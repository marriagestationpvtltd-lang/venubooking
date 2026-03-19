/**
 * Enhanced Image Upload Handler
 * - Client-side image compression with minimal visible quality loss
 * - Multiple file upload with progress indication
 * - Preview before upload
 * - Drag & drop support
 * - Chunked upload for videos and large files (up to 50 GB) using 5 MB slices
 * - Non-intrusive floating progress bar (does NOT block the page)
 * - Supports any file type: photos, videos, documents, archives, etc.
 */

class ImageUploadHandler {
    // Video MIME types supported for upload
    static VIDEO_TYPES = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm', 'video/x-matroska', 'video/mpeg', 'video/3gpp'];

    // Chunk size for large-file uploads: 5 MB
    static CHUNK_SIZE = 5 * 1024 * 1024;

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
            maxFileSize: 50 * 1024 * 1024,           // 50 MB for photos/general files
            maxVideoSize: 50 * 1024 * 1024 * 1024,   // 50 GB for videos
            maxOtherFileSize: 50 * 1024 * 1024 * 1024, // 50 GB for any other file
            allowedTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            allowVideos: true, // Allow video uploads alongside images
            allowAllFiles: false, // When true, accept any file type (not just images/videos)
            skipCompression: false, // When true, upload images without any compression (preserves original quality)
            uploadUrl: 'ajax-upload.php',
            chunkUploadUrl: 'ajax-chunk-upload.php', // Chunked upload endpoint
            disableChunkedUpload: false, // When true, all uploads use direct POST (max = maxFileSize/maxOtherFileSize)
            onUploadStart: () => {},
            onUploadProgress: () => {},
            onUploadComplete: () => {},
            onUploadError: () => {}
        };
        
        this.options = { ...defaults, ...options };

        this.files = [];
        this.processedFiles = [];
        this._duplicateDecision = null; // Tracks "replace all" / "skip all" choice for the current batch
        this.init();
    }

    isVideoFile(file) {
        return ImageUploadHandler.VIDEO_TYPES.includes(file.type);
    }

    isImageFile(file) {
        return this.options.allowedTypes.includes(file.type);
    }

    /**
     * Returns a Font Awesome icon class and label for a generic file based on extension.
     */
    getFileIcon(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        const icons = {
            // Archives
            zip: { icon: 'fas fa-file-archive', color: '#e67e22', label: 'ZIP' },
            rar: { icon: 'fas fa-file-archive', color: '#e67e22', label: 'RAR' },
            '7z':  { icon: 'fas fa-file-archive', color: '#e67e22', label: '7Z' },
            tar: { icon: 'fas fa-file-archive', color: '#e67e22', label: 'TAR' },
            gz:  { icon: 'fas fa-file-archive', color: '#e67e22', label: 'GZ' },
            // Documents
            pdf: { icon: 'fas fa-file-pdf', color: '#e74c3c', label: 'PDF' },
            doc: { icon: 'fas fa-file-word', color: '#2980b9', label: 'DOC' },
            docx: { icon: 'fas fa-file-word', color: '#2980b9', label: 'DOCX' },
            xls: { icon: 'fas fa-file-excel', color: '#27ae60', label: 'XLS' },
            xlsx: { icon: 'fas fa-file-excel', color: '#27ae60', label: 'XLSX' },
            ppt: { icon: 'fas fa-file-powerpoint', color: '#e67e22', label: 'PPT' },
            pptx: { icon: 'fas fa-file-powerpoint', color: '#e67e22', label: 'PPTX' },
            // Text
            txt: { icon: 'fas fa-file-alt', color: '#7f8c8d', label: 'TXT' },
            csv: { icon: 'fas fa-file-csv', color: '#27ae60', label: 'CSV' },
            // Audio
            mp3: { icon: 'fas fa-file-audio', color: '#8e44ad', label: 'MP3' },
            wav: { icon: 'fas fa-file-audio', color: '#8e44ad', label: 'WAV' },
            flac: { icon: 'fas fa-file-audio', color: '#8e44ad', label: 'FLAC' },
            // Code
            html: { icon: 'fas fa-file-code', color: '#e74c3c', label: 'HTML' },
            css: { icon: 'fas fa-file-code', color: '#2980b9', label: 'CSS' },
            js: { icon: 'fas fa-file-code', color: '#f1c40f', label: 'JS' },
        };
        return icons[ext] || { icon: 'fas fa-file', color: '#95a5a6', label: ext.toUpperCase() || 'FILE' };
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
            const isAllowed = this.options.allowAllFiles || isImage || isVideo;

            if (!isAllowed) {
                this.showError(`${file.name}: Invalid file type. Allowed: JPG, PNG, GIF, WebP (photos) or MP4, MOV, AVI, WebM, MKV (videos).`);
                return false;
            }
            if (isVideo && file.size > this.options.maxVideoSize) {
                this.showError(`${file.name}: Video too large (${this.formatFileSize(file.size)}). Maximum is ${this.formatFileSize(this.options.maxVideoSize)}.`);
                return false;
            }
            if (isImage && file.size > this.options.maxFileSize) {
                this.showError(`${file.name}: Image too large (${this.formatFileSize(file.size)}). Maximum is ${this.formatFileSize(this.options.maxFileSize)}.`);
                return false;
            }
            // For other file types when allowAllFiles is true, apply maxOtherFileSize
            if (this.options.allowAllFiles && !isImage && !isVideo && file.size > this.options.maxOtherFileSize) {
                this.showError(`${file.name}: File too large (${this.formatFileSize(file.size)}). Maximum is ${this.formatFileSize(this.options.maxOtherFileSize)}.`);
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
        const isImage = this.options.allowedTypes.includes(file.type);
        if (this.isVideoFile(file)) {
            // Show video icon placeholder for videos
            container.innerHTML = `<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#666;">
                <i class="fas fa-video" style="font-size:2rem;color:#dc3545;"></i>
                <small style="margin-top:4px;font-size:0.65rem;color:#888;">VIDEO</small>
            </div>`;
        } else if (!this.isImageFile(file)) {
            // Show generic file icon for non-image, non-video files
            const fileInfo = this.getFileIcon(file.name);
            container.innerHTML = `<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#666;">
                <i class="${fileInfo.icon}" style="font-size:2rem;color:${fileInfo.color};"></i>
                <small style="margin-top:4px;font-size:0.65rem;color:#888;">${fileInfo.label}</small>
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

        // Record upload start time for ETA calculation
        this._uploadStartTime = Date.now();

        // Show non-intrusive floating progress bar
        this.showFloatingProgress(0, '');

        let uploadedCount = 0;
        let errorCount = 0;
        let skipCount = 0;
        const errors = [];

        // Reset per-batch duplicate decision
        this._duplicateDecision = null;

        // Process and upload each file
        for (let i = 0; i < this.files.length; i++) {
            const file = this.files[i];
            if (!file) continue;

            const preview = this.previewContainer.querySelector(`[data-index="${i}"]`);
            const isVideo = this.isVideoFile(file);
            const isImage = this.isImageFile(file);
            // Use chunked upload for videos and large non-image files (> 5 MB chunk size),
            // unless disableChunkedUpload is set (e.g. standalone sharing pages without a chunk endpoint)
            const useChunked = !this.options.disableChunkedUpload &&
                (isVideo || (!isImage && file.size > ImageUploadHandler.CHUNK_SIZE));

            if (preview) {
                preview.querySelector('.preview-status .badge').className = 'badge bg-info';
                preview.querySelector('.preview-status .badge').textContent = useChunked ? 'Uploading...' : 'Compressing...';
            }

            // Update floating progress label
            const overallPct = Math.round((uploadedCount / activeFiles.length) * 100);
            this.updateFloatingProgress(overallPct, this.truncateFilename(file.name, 30));

            try {
                let fileToUpload = file;

                // Pre-check for duplicate before doing any compression or upload work
                const dupFormData = new FormData(this.form);
                const dupCheck = await this.checkDuplicate(file.name, dupFormData);
                let replaceExistingId = 0;

                if (dupCheck.exists) {
                    let decision = this._duplicateDecision;

                    if (decision !== 'replace_all' && decision !== 'skip_all') {
                        // No global decision yet — ask the user
                        decision = await this.showDuplicateDialog(file.name);
                        if (decision === 'replace_all') this._duplicateDecision = 'replace_all';
                        else if (decision === 'skip_all') this._duplicateDecision = 'skip_all';
                    }

                    if (decision === 'skip' || decision === 'skip_all') {
                        skipCount++;
                        if (preview) {
                            preview.querySelector('.preview-status .badge').className = 'badge bg-secondary';
                            preview.querySelector('.preview-status .badge').textContent = 'Skipped';
                        }
                        continue; // Move to next file
                    }

                    // Replace: remember the ID of the file to overwrite
                    replaceExistingId = dupCheck.existing_id;
                }

                if (!useChunked && !this.options.skipCompression && isImage) {
                    // Compress image files only (skipped when skipCompression is true)
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

                let result;

                if (useChunked) {
                    // Always use chunked upload for videos and large non-image files
                    result = await this.uploadFileChunked(file, formData, preview, (pct) => {
                        const base = Math.round((uploadedCount / activeFiles.length) * 100);
                        const slice = Math.round(pct / activeFiles.length);
                        this.updateFloatingProgress(base + slice, this.truncateFilename(file.name, 30));
                    }, replaceExistingId);
                } else {
                    // Direct upload for photos and small files
                    const uploadFormData = new FormData(this.form);
                    uploadFormData.delete('images[]');
                    uploadFormData.append('images[]', fileToUpload);
                    uploadFormData.append('ajax_upload', '1');
                    if (replaceExistingId) {
                        uploadFormData.append('replace_existing', '1');
                        uploadFormData.append('existing_id', replaceExistingId);
                    }

                    result = await this.uploadFile(uploadFormData, preview, (pct) => {
                        const base = Math.round((uploadedCount / activeFiles.length) * 100);
                        const slice = Math.round(pct / activeFiles.length);
                        this.updateFloatingProgress(base + slice, this.truncateFilename(file.name, 30));
                    });
                }
                
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

        // Complete and hide floating progress
        this.updateFloatingProgress(100, 'Done!');
        setTimeout(() => this.hideFloatingProgress(), 2000);

        // Re-enable upload button
        if (this.uploadButton) {
            this.uploadButton.disabled = false;
            this.uploadButton.innerHTML = '<i class="fas fa-upload"></i> Upload Image(s)';
        }

        // Show results
        this.options.onUploadComplete({ uploadedCount, errorCount, skipCount, errors });

        if (uploadedCount > 0) {
            let successMsg = `${uploadedCount} file${uploadedCount > 1 ? 's' : ''} uploaded successfully!`;
            if (skipCount > 0) {
                successMsg += ` (${skipCount} skipped)`;
            }
            this.showSuccess(successMsg);
        } else if (skipCount > 0 && errorCount === 0) {
            this.showSuccess(`${skipCount} file${skipCount > 1 ? 's' : ''} skipped.`);
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

    uploadFile(formData, preview, onProgress) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    if (preview) {
                        const progressBar = preview.querySelector('.progress-bar');
                        if (progressBar) {
                            progressBar.style.width = percent + '%';
                            progressBar.setAttribute('aria-valuenow', percent);
                        }
                    }
                    if (typeof onProgress === 'function') onProgress(percent);
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

    /**
     * Upload a file in 5 MB chunks to ajax-chunk-upload.php.
     * Reports per-chunk progress via onProgress(0-100).
     */
    async uploadFileChunked(file, baseFormData, preview, onProgress, replaceExistingId = 0) {
        const chunkSize   = ImageUploadHandler.CHUNK_SIZE;
        const totalChunks = Math.ceil(file.size / chunkSize);
        const uploadId    = this._generateUploadId();
        const csrfToken   = baseFormData.get('csrf_token') || document.getElementById('csrf_token')?.value || '';
        const folderId    = baseFormData.get('folder_id')  || document.getElementById('folder_id')?.value  || '';
        const subfolderName = baseFormData.get('subfolder_name') || document.getElementById('subfolderNameInput')?.value || '';

        for (let i = 0; i < totalChunks; i++) {
            const start = i * chunkSize;
            const end   = Math.min(start + chunkSize, file.size);
            const chunk = file.slice(start, end);

            const fd = new FormData();
            fd.append('csrf_token',   csrfToken);
            fd.append('folder_id',    folderId);
            fd.append('upload_id',    uploadId);
            fd.append('chunk_index',  i);
            fd.append('total_chunks', totalChunks);
            fd.append('original_name', file.name);
            fd.append('chunk',        chunk, file.name);
            if (subfolderName) {
                fd.append('subfolder_name', subfolderName);
            }
            if (replaceExistingId) {
                fd.append('replace_existing', '1');
                fd.append('existing_id', replaceExistingId);
            }

            const result = await this._uploadChunk(fd, preview, i, totalChunks, onProgress);

            if (!result.success) {
                return result; // Propagate error
            }

            if (result.complete) {
                return result; // Final response
            }
        }

        // Should not reach here; last chunk triggers assembly
        return { success: false, message: 'Unexpected upload state.' };
    }

    _uploadChunk(formData, preview, chunkIndex, totalChunks, onProgress) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    // Per-file percent: completed chunks + this chunk's progress
                    const chunkFraction = e.loaded / e.total;
                    const percent = Math.round(((chunkIndex + chunkFraction) / totalChunks) * 100);

                    if (preview) {
                        const progressBar = preview.querySelector('.progress-bar');
                        if (progressBar) {
                            progressBar.style.width = percent + '%';
                            progressBar.setAttribute('aria-valuenow', percent);
                        }
                    }
                    if (typeof onProgress === 'function') onProgress(percent);
                }
            });

            xhr.addEventListener('load', () => {
                try {
                    resolve(JSON.parse(xhr.responseText));
                } catch (e) {
                    reject(new Error('Invalid server response'));
                }
            });

            xhr.addEventListener('error', () => reject(new Error('Network error during chunk upload')));

            xhr.open('POST', this.options.chunkUploadUrl);
            xhr.send(formData);
        });
    }

    /** Generate a hex-encoded random upload ID (32 hex chars, URL-safe) */
    _generateUploadId() {
        const arr = new Uint8Array(16);
        crypto.getRandomValues(arr);
        return Array.from(arr).map(b => b.toString(16).padStart(2, '0')).join('');
    }

    /**
     * Ask the server whether a file with the same title already exists in this folder.
     * Returns { exists, existing_id, existing_title } or { exists: false } on error.
     */
    checkDuplicate(filename, formData) {
        return new Promise((resolve) => {
            const fd = new FormData();
            fd.append('ajax_check_duplicate', '1');
            fd.append('folder_id', formData.get('folder_id') || document.getElementById('folder_id')?.value || '');
            fd.append('filename', filename);
            fd.append('csrf_token', formData.get('csrf_token') || document.getElementById('csrf_token')?.value || '');

            const xhr = new XMLHttpRequest();
            xhr.addEventListener('load', () => {
                try { resolve(JSON.parse(xhr.responseText)); }
                catch (e) { resolve({ exists: false }); }
            });
            xhr.addEventListener('error', () => resolve({ exists: false }));
            xhr.open('POST', this.options.uploadUrl);
            xhr.send(fd);
        });
    }

    /**
     * Show a SweetAlert2 dialog asking the user what to do with a duplicate file.
     * Returns one of: 'replace', 'skip', 'replace_all', 'skip_all'.
     */
    async showDuplicateDialog(filename) {
        if (typeof Swal === 'undefined') {
            const replace = confirm(
                `A file named "${filename}" already exists in this folder.\n\nClick OK to Replace it, or Cancel to Skip it.`
            );
            return replace ? 'replace' : 'skip';
        }

        const { value: action } = await Swal.fire({
            title: 'Duplicate File Found',
            html: `<p>The file <strong>${this.escapeHtml(filename)}</strong> already exists in this folder.</p>
                   <p class="text-muted small mb-0">Choose what to do with this file:</p>`,
            icon: 'warning',
            input: 'radio',
            inputOptions: {
                replace:     'Replace this file',
                skip:        'Skip this file',
                replace_all: 'Replace all duplicates',
                skip_all:    'Skip all duplicates',
            },
            inputValue: 'replace',
            confirmButtonText: 'Continue',
            confirmButtonColor: '#4CAF50',
            allowOutsideClick: false,
            inputValidator: (value) => {
                if (!value) return 'Please select an option.';
            },
        });

        return action || 'skip';
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

    /**
     * Show a non-intrusive floating progress bar at the bottom of the screen.
     * Does NOT block or dim the page.
     */
    showFloatingProgress(percent, filename) {
        let bar = document.getElementById('uploadFloatingProgress');
        if (!bar) {
            bar = document.createElement('div');
            bar.id = 'uploadFloatingProgress';
            bar.className = 'upload-floating-progress';
            bar.innerHTML = `
                <div class="ufp-header">
                    <span class="ufp-icon"><i class="fas fa-cloud-upload-alt"></i></span>
                    <span class="ufp-title">Uploading…</span>
                    <span class="ufp-percent">0%</span>
                </div>
                <div class="ufp-filename"></div>
                <div class="ufp-eta"></div>
                <div class="ufp-bar-track">
                    <div class="ufp-bar-fill"></div>
                </div>
            `;
            document.body.appendChild(bar);
        }
        bar.style.display = 'block';
        this.updateFloatingProgress(percent, filename);
    }

    updateFloatingProgress(percent, filename) {
        const bar = document.getElementById('uploadFloatingProgress');
        if (!bar) return;
        bar.querySelector('.ufp-percent').textContent = percent + '%';
        bar.querySelector('.ufp-bar-fill').style.width = percent + '%';
        if (filename) {
            bar.querySelector('.ufp-filename').textContent = filename;
        }

        // Calculate and display ETA
        const etaEl = bar.querySelector('.ufp-eta');
        if (etaEl) {
            if (this._uploadStartTime && percent > 1 && percent < 100) {
                const elapsed = (Date.now() - this._uploadStartTime) / 1000;
                if (elapsed > 0.5) {
                    const totalEst = elapsed * (100 / percent);
                    const remaining = Math.max(0, Math.ceil(totalEst - elapsed));
                    etaEl.textContent = this._formatEta(remaining);
                } else {
                    etaEl.textContent = 'Estimating…';
                }
            } else if (percent >= 100) {
                etaEl.textContent = 'Complete!';
            } else {
                etaEl.textContent = '';
            }
        }
    }

    /** Format seconds into a human-readable ETA string */
    _formatEta(seconds) {
        if (seconds < 5)  return 'Almost done…';
        if (seconds < 60) return '~' + seconds + ' sec remaining';
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        if (mins < 60) return '~' + mins + ' min' + (secs > 0 ? ' ' + secs + ' sec' : '') + ' remaining';
        const hours = Math.floor(mins / 60);
        const remMins = mins % 60;
        if (hours < 24) return '~' + hours + ' hr' + (remMins > 0 ? ' ' + remMins + ' min' : '') + ' remaining';
        return 'Estimating…';
    }

    hideFloatingProgress() {
        const bar = document.getElementById('uploadFloatingProgress');
        if (bar) {
            bar.classList.add('ufp-hide');
            setTimeout(() => {
                bar.style.display = 'none';
                bar.classList.remove('ufp-hide');
            }, 400);
        }
    }

    // Keep legacy aliases so any external callers don't break
    showGlobalLoader() { this.showFloatingProgress(0, ''); }
    hideGlobalLoader() { this.hideFloatingProgress(); }

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
