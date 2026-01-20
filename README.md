# PDF Merger Tool

A simple, modern web application for merging multiple PDF files into one. Built with PHP 7.4+, Bootstrap 5, jQuery, and SweetAlert2.

## Features

- **Drag & Drop Upload**: Easily upload PDF files by dragging them onto the page
- **Reorder Files**: Drag and drop to arrange PDFs in your desired order
- **Batch Processing**: Merge up to 50 PDF files at once
- **Modern UI**: Clean, dark-themed interface with smooth animations
- **Secure**: Session-isolated uploads with automatic cleanup
- **Responsive**: Works on desktop and tablet devices

## Requirements

- PHP 7.4 or higher
- Composer (for dependency management)
- Apache with `mod_rewrite` enabled (or equivalent for other servers)
- PHP Extensions:
  - `fileinfo` (for MIME type validation)
  - `gd` (optional, for some PDF operations)

## Installation

### 1. Clone or Download

Place the files in your web server's document root or a subdirectory.

### 2. Install Dependencies

```bash
composer install
```

### 3. Set Directory Permissions

Ensure the `uploads` and `merged` directories are writable by the web server:

```bash
chmod 755 uploads merged
# Or on Windows, ensure the directories are writable
```

### 4. Configure PHP (php.ini)

For handling large files and multiple uploads, adjust these settings:

```ini
; Maximum size of uploaded file
upload_max_filesize = 100M

; Maximum size of POST data
post_max_size = 500M

; Maximum number of files that can be uploaded at once
max_file_uploads = 50

; Memory limit for processing large PDFs
memory_limit = 512M

; Execution time limit for merging operations
max_execution_time = 300
```

### 5. Restart Web Server

After changing PHP settings, restart Apache/Nginx:

```bash
# Apache
sudo systemctl restart apache2

# Nginx + PHP-FPM
sudo systemctl restart php-fpm
sudo systemctl restart nginx
```

## Usage

1. Open the application in your browser
2. Drag and drop PDF files onto the upload zone, or click to browse
3. Reorder files by dragging the grip handles
4. Click "Merge PDFs" to combine all files
5. The merged PDF will automatically download

## File Structure

```
├── index.php              # Main page
├── upload.php             # File upload handler
├── merge.php              # PDF merge handler
├── download.php           # Secure download handler
├── composer.json          # PHP dependencies
├── .htaccess              # Apache configuration
├── assets/
│   ├── css/
│   │   └── style.css      # Custom styles
│   └── js/
│       └── app.js         # Main JavaScript
├── uploads/               # Temporary upload storage
│   └── .htaccess          # Deny direct access
└── merged/                # Merged PDF storage
    └── .htaccess          # Deny direct access
```

## Security Features

- **MIME Type Validation**: Server-side verification that uploads are valid PDFs
- **PDF Header Check**: Additional validation by checking file headers
- **Session Isolation**: Each user's files are stored in separate directories
- **Access Control**: `.htaccess` files prevent direct access to uploaded files
- **Automatic Cleanup**: Files older than 1 hour are automatically deleted
- **Input Sanitization**: All filenames and user inputs are sanitized

## Configuration Options

Edit the constants at the top of each PHP file to customize:

### upload.php
```php
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB per file
```

### merge.php
```php
define('FILE_EXPIRY', 3600); // 1 hour cleanup interval
```

### assets/js/app.js
```javascript
const Config = {
    maxFileSize: 50 * 1024 * 1024, // 50MB
    maxFiles: 50
};
```

## Troubleshooting

### "File exceeds server upload limit"
Increase `upload_max_filesize` and `post_max_size` in php.ini

### "Failed to create upload directory"
Ensure the `uploads` directory exists and is writable

### "Memory exhausted" during merge
Increase `memory_limit` in php.ini

### Merge takes too long
Increase `max_execution_time` in php.ini

### Files not appearing after upload
Check browser console for JavaScript errors and PHP error logs

## Browser Support

- Chrome 80+
- Firefox 75+
- Safari 13+
- Edge 80+

## Dependencies

- [FPDF](http://www.fpdf.org/) - PDF generation library
- [FPDI](https://www.setasign.com/products/fpdi/about/) - PDF import library
- [Bootstrap 5.3](https://getbootstrap.com/) - CSS framework
- [jQuery 3.7](https://jquery.com/) - JavaScript library
- [jQuery UI](https://jqueryui.com/) - Sortable functionality
- [SweetAlert2](https://sweetalert2.github.io/) - Beautiful alerts

## License

This project is provided as-is for internal use.
