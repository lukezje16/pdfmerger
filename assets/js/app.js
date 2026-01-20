/**
 * PDF Merger Tool - Main Application JavaScript
 */

(function ($) {
  "use strict";

  // =========================================
  // Configuration
  // =========================================
  const Config = {
    maxFileSize: 50 * 1024 * 1024, // 50MB
    maxFiles: 50,
    allowedTypes: ["application/pdf"],
    uploadEndpoint: "upload.php",
    mergeEndpoint: "merge.php",
    downloadEndpoint: "download.php",
  };

  // =========================================
  // State Management
  // =========================================
  const State = {
    files: [],
    isUploading: false,
    isMerging: false,
  };

  // =========================================
  // DOM Elements
  // =========================================
  const Elements = {
    uploadZone: $("#uploadZone"),
    fileInput: $("#fileInput"),
    browseBtn: $("#browseBtn"),
    addMoreBtn: $("#addMoreBtn"),
    fileListContainer: $("#fileListContainer"),
    fileList: $("#fileList"),
    fileCount: $("#fileCount"),
    mergeBtn: $("#mergeBtn"),
    mergeSpinner: $("#mergeSpinner"),
    clearAllBtn: $("#clearAllBtn"),
    loadingOverlay: $("#loadingOverlay"),
    loadingText: $("#loadingText"),
    loadingSubtext: $("#loadingSubtext"),
  };

  // =========================================
  // Loading Overlay Functions
  // =========================================
  function showLoading(text = "Processing your files...", subtext = "This may take a moment") {
    Elements.loadingText.text(text);
    Elements.loadingSubtext.text(subtext);
    Elements.loadingOverlay.addClass("active");
  }

  function hideLoading() {
    Elements.loadingOverlay.removeClass("active");
  }

  function updateLoadingText(text, subtext) {
    if (text) Elements.loadingText.text(text);
    if (subtext) Elements.loadingSubtext.text(subtext);
  }

  // =========================================
  // Toast Notifications
  // =========================================
  const Toast = Swal.mixin({
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    background: "#1a1f26",
    color: "#e8eaed",
    didOpen: (toast) => {
      toast.addEventListener("mouseenter", Swal.stopTimer);
      toast.addEventListener("mouseleave", Swal.resumeTimer);
    },
  });

  // =========================================
  // Utility Functions
  // =========================================

  /**
   * Format file size to human readable string
   */
  function formatFileSize(bytes) {
    const units = ["B", "KB", "MB", "GB"];
    let i = 0;
    while (bytes >= 1024 && i < units.length - 1) {
      bytes /= 1024;
      i++;
    }
    return bytes.toFixed(2) + " " + units[i];
  }

  /**
   * Validate a file before upload
   */
  function validateFile(file) {
    // Check file type
    if (!Config.allowedTypes.includes(file.type)) {
      return { valid: false, error: `"${file.name}" is not a PDF file` };
    }

    // Check file size
    if (file.size > Config.maxFileSize) {
      return { valid: false, error: `"${file.name}" exceeds 50MB limit` };
    }

    // Check max files
    if (State.files.length >= Config.maxFiles) {
      return {
        valid: false,
        error: `Maximum ${Config.maxFiles} files allowed`,
      };
    }

    return { valid: true };
  }

  /**
   * Generate unique ID
   */
  function generateId() {
    return "file_" + Date.now() + "_" + Math.random().toString(36).substr(2, 9);
  }

  // =========================================
  // UI Functions
  // =========================================

  /**
   * Update the file count badge
   */
  function updateFileCount() {
    Elements.fileCount.text(State.files.length);
  }

  /**
   * Update merge button state
   */
  function updateMergeButton() {
    const canMerge =
      State.files.length >= 2 && !State.isUploading && !State.isMerging;
    Elements.mergeBtn.prop("disabled", !canMerge);
  }

  /**
   * Update file order numbers after sorting
   */
  function updateFileOrder() {
    Elements.fileList.find(".file-item").each(function (index) {
      $(this)
        .find(".file-order")
        .text(index + 1);
    });
  }

  /**
   * Show/hide file list container
   */
  function toggleFileListVisibility() {
    if (State.files.length > 0) {
      Elements.uploadZone.hide();
      Elements.fileListContainer.show();
    } else {
      Elements.uploadZone.show();
      Elements.fileListContainer.hide();
    }
    updateFileCount();
    updateMergeButton();
  }

  /**
   * Create file item HTML
   */
  function createFileItemHTML(file, order) {
    return `
            <li class="file-item" data-id="${file.id}" data-server-id="${
      file.serverId || ""
    }">
                <span class="file-drag-handle">
                    <i class="bi bi-grip-vertical"></i>
                </span>
                <span class="file-order">${order}</span>
                <span class="file-icon">
                    <i class="bi bi-file-earmark-pdf-fill"></i>
                </span>
                <div class="file-info">
                    <div class="file-name" title="${file.name}">${
      file.name
    }</div>
                    <div class="file-size">${file.sizeFormatted}</div>
                </div>
                <button type="button" class="file-remove" title="Remove file">
                    <i class="bi bi-x-lg"></i>
                </button>
            </li>
        `;
  }

  /**
   * Add file to the list
   */
  function addFileToList(file) {
    const order = State.files.length;
    const html = createFileItemHTML(file, order);
    Elements.fileList.append(html);
  }

  /**
   * Remove file from list
   */
  function removeFile(fileId) {
    State.files = State.files.filter((f) => f.id !== fileId);
    Elements.fileList.find(`[data-id="${fileId}"]`).fadeOut(200, function () {
      $(this).remove();
      updateFileOrder();
      toggleFileListVisibility();
    });
  }

  /**
   * Clear all files
   */
  function clearAllFiles() {
    State.files = [];
    Elements.fileList.empty();
    toggleFileListVisibility();
  }

  // =========================================
  // Upload Functions
  // =========================================

  /**
   * Upload a single file
   */
  function uploadFile(file) {
    return new Promise((resolve, reject) => {
      const formData = new FormData();
      formData.append("pdf", file);

      $.ajax({
        url: Config.uploadEndpoint,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
          if (response.success) {
            resolve(response.file);
          } else {
            reject(new Error(response.message));
          }
        },
        error: function (xhr, status, error) {
          reject(new Error("Upload failed: " + error));
        },
      });
    });
  }

  /**
   * Handle file selection/drop
   */
  async function handleFiles(fileList) {
    if (State.isUploading) return;

    const files = Array.from(fileList);
    const validFiles = [];
    const errors = [];

    // Validate all files first
    for (const file of files) {
      const validation = validateFile(file);
      if (validation.valid) {
        validFiles.push(file);
      } else {
        errors.push(validation.error);
      }
    }

    // Show validation errors
    if (errors.length > 0) {
      Toast.fire({
        icon: "error",
        title: errors.join("<br>"),
      });
    }

    if (validFiles.length === 0) return;

    // Start uploading
    State.isUploading = true;
    updateMergeButton();

    let uploadedCount = 0;
    let failedCount = 0;

    for (const file of validFiles) {
      // Create temporary file entry
      const tempId = generateId();
      const tempFile = {
        id: tempId,
        name: file.name,
        size: file.size,
        sizeFormatted: formatFileSize(file.size),
        serverId: null,
        uploading: true,
      };

      State.files.push(tempFile);
      addFileToList(tempFile);
      toggleFileListVisibility();

      // Mark as uploading
      const $item = Elements.fileList.find(`[data-id="${tempId}"]`);
      $item.addClass("uploading");
      $item
        .find(".file-icon i")
        .removeClass("bi-file-earmark-pdf-fill")
        .addClass("bi-arrow-repeat");

      try {
        const serverFile = await uploadFile(file);

        // Update file with server info
        tempFile.serverId = serverFile.id;
        tempFile.uploading = false;
        $item.attr("data-server-id", serverFile.id);
        $item.removeClass("uploading");
        $item
          .find(".file-icon i")
          .removeClass("bi-arrow-repeat")
          .addClass("bi-file-earmark-pdf-fill");

        uploadedCount++;
      } catch (error) {
        // Remove failed file
        removeFile(tempId);
        failedCount++;

        Toast.fire({
          icon: "error",
          title: error.message,
        });
      }
    }

    State.isUploading = false;
    updateMergeButton();

    // Show success message
    if (uploadedCount > 0) {
      Toast.fire({
        icon: "success",
        title: `${uploadedCount} file${uploadedCount > 1 ? "s" : ""} uploaded`,
      });
    }
  }

  // =========================================
  // Merge Functions
  // =========================================

  /**
   * Get files in current order
   */
  function getFilesInOrder() {
    const orderedFiles = [];
    Elements.fileList.find(".file-item").each(function () {
      const serverId = $(this).attr("data-server-id");
      if (serverId) {
        orderedFiles.push(serverId);
      }
    });
    return orderedFiles;
  }

  /**
   * Merge PDFs
   */
  async function mergePDFs() {
    if (State.isMerging) return;

    const fileIds = getFilesInOrder();

    if (fileIds.length < 2) {
      Toast.fire({
        icon: "warning",
        title: "Please add at least 2 PDF files",
      });
      return;
    }

    // Confirmation for large merges
    if (fileIds.length > 10) {
      const result = await Swal.fire({
        title: "Merge " + fileIds.length + " files?",
        text: "This may take a moment for large files.",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Yes, merge them",
        cancelButtonText: "Cancel",
        background: "#252a33",
        color: "#ffffff",
      });

      if (!result.isConfirmed) return;
    }

    // Start merging - show loading overlay
    State.isMerging = true;
    Elements.mergeBtn.prop("disabled", true);
    Elements.mergeSpinner.removeClass("d-none");
    Elements.fileListContainer.addClass("merge-loading");
    
    showLoading(
      "Merging " + fileIds.length + " PDF files...",
      "Please wait while we combine your documents"
    );

    try {
      const response = await $.ajax({
        url: Config.mergeEndpoint,
        type: "POST",
        contentType: "application/json",
        data: JSON.stringify({ fileIds: fileIds }),
      });

      hideLoading();

      if (response.success) {
        // Success - trigger download
        Toast.fire({
          icon: "success",
          title: "PDFs merged successfully!",
        });

        // Auto-download
        const downloadUrl =
          Config.downloadEndpoint + "?id=" + response.downloadId;
        window.location.href = downloadUrl;

        // Clear the list after successful merge
        setTimeout(() => {
          clearAllFiles();
        }, 1000);
      } else {
        throw new Error(response.message);
      }
    } catch (error) {
      hideLoading();
      Swal.fire({
        title: "Merge Failed",
        text: error.message || "An error occurred while merging PDFs",
        icon: "error",
        background: "#252a33",
        color: "#ffffff",
      });
    } finally {
      hideLoading();
      State.isMerging = false;
      Elements.mergeBtn.prop("disabled", false);
      Elements.mergeSpinner.addClass("d-none");
      Elements.fileListContainer.removeClass("merge-loading");
      updateMergeButton();
    }
  }

  // =========================================
  // Event Handlers
  // =========================================

  /**
   * Initialize drag and drop
   */
  function initDragAndDrop() {
    const zone = Elements.uploadZone[0];

    // Prevent default drag behaviors
    ["dragenter", "dragover", "dragleave", "drop"].forEach((eventName) => {
      zone.addEventListener(eventName, preventDefaults, false);
      document.body.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
      e.preventDefault();
      e.stopPropagation();
    }

    // Highlight drop zone
    ["dragenter", "dragover"].forEach((eventName) => {
      zone.addEventListener(
        eventName,
        () => {
          Elements.uploadZone.addClass("drag-over");
        },
        false
      );
    });

    ["dragleave", "drop"].forEach((eventName) => {
      zone.addEventListener(
        eventName,
        () => {
          Elements.uploadZone.removeClass("drag-over");
        },
        false
      );
    });

    // Handle drop
    zone.addEventListener(
      "drop",
      (e) => {
        const files = e.dataTransfer.files;
        handleFiles(files);
      },
      false
    );
  }

  /**
   * Initialize sortable list
   */
  function initSortable() {
    Elements.fileList.sortable({
      handle: ".file-drag-handle",
      placeholder: "file-item ui-sortable-placeholder",
      animation: 150,
      update: function () {
        updateFileOrder();

        // Update State.files order to match DOM
        const newOrder = [];
        Elements.fileList.find(".file-item").each(function () {
          const id = $(this).attr("data-id");
          const file = State.files.find((f) => f.id === id);
          if (file) newOrder.push(file);
        });
        State.files = newOrder;
      },
    });
  }

  /**
   * Bind event listeners
   */
  function bindEvents() {
    // Prevent file input clicks from bubbling
    Elements.fileInput.on("click", (e) => {
      e.stopPropagation();
    });

    // Browse button click
    Elements.browseBtn.on("click", (e) => {
      e.stopPropagation();
      Elements.fileInput.trigger("click");
    });

    // Upload zone click
    Elements.uploadZone.on("click", (e) => {
      // Don't trigger if clicking on child buttons or inputs
      if (
        e.target === Elements.uploadZone[0] ||
        $(e.target).closest(".upload-content").length
      ) {
        Elements.fileInput.trigger("click");
      }
    });

    // Add more button
    Elements.addMoreBtn.on("click", (e) => {
      e.stopPropagation();
      Elements.fileInput.trigger("click");
    });

    // File input change
    Elements.fileInput.on("change", function () {
      if (this.files.length > 0) {
        handleFiles(this.files);
        this.value = ""; // Reset input
      }
    });

    // Remove file button
    Elements.fileList.on("click", ".file-remove", function () {
      const fileId = $(this).closest(".file-item").attr("data-id");
      removeFile(fileId);
    });

    // Clear all button
    Elements.clearAllBtn.on("click", async () => {
      if (State.files.length === 0) return;

      const result = await Swal.fire({
        title: "Clear all files?",
        text: "This will remove all uploaded files.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, clear all",
        cancelButtonText: "Cancel",
        background: "#1a1f26",
        color: "#e8eaed",
      });

      if (result.isConfirmed) {
        clearAllFiles();
        Toast.fire({
          icon: "info",
          title: "All files cleared",
        });
      }
    });

    // Merge button
    Elements.mergeBtn.on("click", () => {
      mergePDFs();
    });
  }

  // =========================================
  // Initialization
  // =========================================

  $(document).ready(function () {
    initDragAndDrop();
    initSortable();
    bindEvents();

    console.log("PDF Merger Tool initialized");
  });
})(jQuery);
