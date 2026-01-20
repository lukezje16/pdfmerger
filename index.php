<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Merger Tool</title>

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <!-- jQuery UI CSS for Sortable -->
    <link href="https://cdn.jsdelivr.net/npm/jquery-ui@1.13.2/themes/base/jquery-ui.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Background -->
    <div class="bg-pattern"></div>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-9">

                <!-- Header -->
                <header class="text-center mb-5">
                    <div class="logo-icon mb-4">
                        <i class="bi bi-file-earmark-pdf"></i>
                    </div>
                    <h1>PDF Merger</h1>
                    <p class="lead">Combine multiple PDF files into a single document.<br>Upload your files, arrange them in order, then merge.</p>
                </header>

                <!-- Main Card -->
                <div class="main-card">

                    <!-- Upload Zone -->
                    <div class="upload-zone" id="uploadZone">
                        <input type="file" id="fileInput" multiple accept=".pdf,application/pdf" class="d-none">
                        <div class="upload-content">
                            <i class="bi bi-cloud-arrow-up upload-icon"></i>
                            <h4>Drop your PDF files here</h4>
                            <p>or click anywhere in this box to browse your computer</p>
                            <button type="button" class="btn btn-primary btn-lg" id="browseBtn">
                                <i class="bi bi-folder2-open me-2"></i>Select PDF Files
                            </button>
                        </div>
                        <div class="upload-hint">
                            <i class="bi bi-info-circle me-2"></i>
                            Maximum 50MB per file &bull; Only PDF files accepted
                        </div>
                    </div>

                    <!-- File List -->
                    <div class="file-list-container" id="fileListContainer" style="display: none;">
                        <div class="file-list-header">
                            <h5>
                                <i class="bi bi-list-ol me-2"></i>
                                Your Files
                                <span class="badge bg-primary ms-2" id="fileCount">0</span>
                            </h5>
                            <button type="button" class="btn btn-outline-secondary" id="addMoreBtn">
                                <i class="bi bi-plus-lg me-2"></i>Add More Files
                            </button>
                        </div>

                        <p class="mb-4" style="color: #d1d5db; font-size: 1.1rem;">
                            <i class="bi bi-grip-vertical me-2"></i>
                            <strong>Tip:</strong> Drag and drop files to change the order. Files will be merged from top to bottom.
                        </p>

                        <ul class="file-list" id="fileList">
                            <!-- Files will be added here dynamically -->
                        </ul>

                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <button type="button" class="btn btn-outline-danger" id="clearAllBtn">
                                <i class="bi bi-trash3 me-2"></i>Clear All Files
                            </button>
                            <button type="button" class="btn btn-primary btn-lg" id="mergeBtn" disabled>
                                <i class="bi bi-layers me-2"></i>Merge All PDFs
                                <span class="spinner-border spinner-border-sm ms-2 d-none" id="mergeSpinner"></span>
                            </button>
                        </div>
                    </div>

                </div>

                <!-- Footer -->
                <footer class="text-center mt-5">
                    <p style="color: #9ca3af; font-size: 1rem;">
                        <i class="bi bi-shield-check me-2"></i>
                        Your files are processed securely and automatically deleted after 1 hour
                    </p>
                </footer>

            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>

    <!-- jQuery UI (for Sortable) -->
    <script src="https://cdn.jsdelivr.net/npm/jquery-ui@1.13.2/dist/jquery-ui.min.js"></script>

    <!-- Bootstrap 5.3 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Custom JS -->
    <script src="assets/js/app.js"></script>
</body>

</html>
