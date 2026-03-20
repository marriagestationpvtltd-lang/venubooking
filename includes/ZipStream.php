<?php
/**
 * ZipStream - Streaming ZIP file generator for instant downloads
 * 
 * This class creates ZIP files on-the-fly and streams them directly to the browser
 * without creating temporary files on disk. This provides instant download feedback
 * like Google Drive, eliminating the "Preparing download..." delay.
 * 
 * Features:
 * - No temporary files needed (streams directly to output)
 * - Supports large files and many files
 * - Instant download start (no preparation delay)
 * - Memory efficient (constant memory usage regardless of file size)
 * - Compatible with standard ZIP readers (uses STORE method - no compression)
 * 
 * Based on ZIP file format specification (APPNOTE.TXT)
 */

class ZipStream {
    private $files = [];
    private $centralDirectory = '';
    private $offset = 0;
    private $fileCount = 0;
    private $zipFilename;
    
    /**
     * Create a new ZipStream instance
     * 
     * @param string $filename The filename to suggest for the download
     */
    public function __construct(string $filename = 'download.zip') {
        $this->zipFilename = $filename;
    }
    
    /**
     * Start the streaming ZIP download
     * Sends appropriate headers and prepares for file streaming
     */
    public function begin(): void {
        // Disable time limit for large file transfers
        @set_time_limit(0);
        
        // Disable output buffering for immediate streaming
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Send headers for ZIP download
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $this->zipFilename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: 0');
        // Note: We cannot send Content-Length because we're streaming
        // The browser will show the download progress based on data received
        
        // Flush headers immediately
        flush();
    }
    
    /**
     * Add a file to the ZIP stream
     * The file is compressed and sent immediately to the output
     * 
     * @param string $filePath Full path to the file on disk
     * @param string $zipPath Path/filename to use inside the ZIP
     * @return bool True if file was added successfully
     */
    public function addFile(string $filePath, string $zipPath): bool {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }
        
        // Get file info
        $fileSize = filesize($filePath);
        $fileMTime = filemtime($filePath);
        
        // Convert modification time to DOS format
        $dosTime = $this->toDosTime($fileMTime);
        $dosDate = $this->toDosDate($fileMTime);
        
        // Read and compress file content
        // For streaming, we use STORE method (no compression) for reliability and speed
        // This is faster and works better for already-compressed files (images, videos)
        $fileHandle = fopen($filePath, 'rb');
        if ($fileHandle === false) {
            return false;
        }
        
        // Calculate CRC32 by reading the file
        $crc32 = $this->calculateCRC32($filePath);
        
        // Reset file pointer
        rewind($fileHandle);
        
        // Prepare local file header
        $zipPathBytes = $zipPath;
        $localHeader = pack('V', 0x04034b50); // Local file header signature
        $localHeader .= pack('v', 10);         // Version needed to extract (1.0)
        $localHeader .= pack('v', 0);          // General purpose bit flag
        $localHeader .= pack('v', 0);          // Compression method (0 = STORE)
        $localHeader .= pack('v', $dosTime);   // Last mod file time
        $localHeader .= pack('v', $dosDate);   // Last mod file date
        $localHeader .= pack('V', $crc32);     // CRC-32
        $localHeader .= pack('V', $fileSize);  // Compressed size (same as uncompressed for STORE)
        $localHeader .= pack('V', $fileSize);  // Uncompressed size
        $localHeader .= pack('v', strlen($zipPathBytes)); // File name length
        $localHeader .= pack('v', 0);          // Extra field length
        $localHeader .= $zipPathBytes;         // File name
        
        // Output local file header
        echo $localHeader;
        flush();
        
        $localHeaderSize = strlen($localHeader);
        
        // Store info for central directory
        $this->files[] = [
            'zipPath' => $zipPath,
            'crc32' => $crc32,
            'compressedSize' => $fileSize,
            'uncompressedSize' => $fileSize,
            'dosTime' => $dosTime,
            'dosDate' => $dosDate,
            'localHeaderOffset' => $this->offset
        ];
        
        // Stream file content in chunks
        $chunkSize = 64 * 1024; // 64KB chunks for efficient streaming
        while (!feof($fileHandle)) {
            $chunk = fread($fileHandle, $chunkSize);
            if ($chunk !== false) {
                echo $chunk;
                flush();
            }
            
            // Check if connection is still alive
            if (connection_aborted()) {
                fclose($fileHandle);
                return false;
            }
        }
        
        fclose($fileHandle);
        
        // Update offset
        $this->offset += $localHeaderSize + $fileSize;
        $this->fileCount++;
        
        return true;
    }
    
    /**
     * Add a file from string content (for small data)
     * 
     * @param string $content The file content
     * @param string $zipPath Path/filename to use inside the ZIP
     * @return bool True if file was added successfully
     */
    public function addFromString(string $content, string $zipPath): bool {
        $fileSize = strlen($content);
        $crc32 = crc32($content);
        
        // Use current time for modification time
        $now = time();
        $dosTime = $this->toDosTime($now);
        $dosDate = $this->toDosDate($now);
        
        // Prepare local file header
        $zipPathBytes = $zipPath;
        $localHeader = pack('V', 0x04034b50); // Local file header signature
        $localHeader .= pack('v', 10);         // Version needed to extract (1.0)
        $localHeader .= pack('v', 0);          // General purpose bit flag
        $localHeader .= pack('v', 0);          // Compression method (0 = STORE)
        $localHeader .= pack('v', $dosTime);   // Last mod file time
        $localHeader .= pack('v', $dosDate);   // Last mod file date
        $localHeader .= pack('V', $crc32);     // CRC-32
        $localHeader .= pack('V', $fileSize);  // Compressed size
        $localHeader .= pack('V', $fileSize);  // Uncompressed size
        $localHeader .= pack('v', strlen($zipPathBytes)); // File name length
        $localHeader .= pack('v', 0);          // Extra field length
        $localHeader .= $zipPathBytes;         // File name
        
        // Output local file header
        echo $localHeader;
        flush();
        
        $localHeaderSize = strlen($localHeader);
        
        // Store info for central directory
        $this->files[] = [
            'zipPath' => $zipPath,
            'crc32' => $crc32,
            'compressedSize' => $fileSize,
            'uncompressedSize' => $fileSize,
            'dosTime' => $dosTime,
            'dosDate' => $dosDate,
            'localHeaderOffset' => $this->offset
        ];
        
        // Output file content
        echo $content;
        flush();
        
        // Update offset
        $this->offset += $localHeaderSize + $fileSize;
        $this->fileCount++;
        
        return true;
    }
    
    /**
     * Finish the ZIP file by writing the central directory
     * Must be called after all files have been added
     */
    public function finish(): void {
        $centralDirectoryOffset = $this->offset;
        $centralDirectorySize = 0;
        
        // Write central directory entries
        foreach ($this->files as $file) {
            $zipPathBytes = $file['zipPath'];
            
            $centralEntry = pack('V', 0x02014b50); // Central directory file header signature
            $centralEntry .= pack('v', 20);         // Version made by
            $centralEntry .= pack('v', 10);         // Version needed to extract
            $centralEntry .= pack('v', 0);          // General purpose bit flag
            $centralEntry .= pack('v', 0);          // Compression method (STORE)
            $centralEntry .= pack('v', $file['dosTime']);  // Last mod file time
            $centralEntry .= pack('v', $file['dosDate']);  // Last mod file date
            $centralEntry .= pack('V', $file['crc32']);    // CRC-32
            $centralEntry .= pack('V', $file['compressedSize']);   // Compressed size
            $centralEntry .= pack('V', $file['uncompressedSize']); // Uncompressed size
            $centralEntry .= pack('v', strlen($zipPathBytes));     // File name length
            $centralEntry .= pack('v', 0);          // Extra field length
            $centralEntry .= pack('v', 0);          // File comment length
            $centralEntry .= pack('v', 0);          // Disk number start
            $centralEntry .= pack('v', 0);          // Internal file attributes
            $centralEntry .= pack('V', 32);         // External file attributes (archive)
            $centralEntry .= pack('V', $file['localHeaderOffset']); // Relative offset of local header
            $centralEntry .= $zipPathBytes;         // File name
            
            echo $centralEntry;
            $centralDirectorySize += strlen($centralEntry);
        }
        
        // Write end of central directory record
        $eocd = pack('V', 0x06054b50);            // End of central directory signature
        $eocd .= pack('v', 0);                     // Number of this disk
        $eocd .= pack('v', 0);                     // Disk where central directory starts
        $eocd .= pack('v', $this->fileCount);      // Number of central directory records on this disk
        $eocd .= pack('v', $this->fileCount);      // Total number of central directory records
        $eocd .= pack('V', $centralDirectorySize); // Size of central directory
        $eocd .= pack('V', $centralDirectoryOffset); // Offset of central directory
        $eocd .= pack('v', 0);                     // ZIP file comment length
        
        echo $eocd;
        flush();
    }
    
    /**
     * Calculate CRC32 checksum for a file
     * Reads file in chunks to handle large files efficiently
     * 
     * @param string $filePath Path to the file
     * @return int CRC32 checksum
     */
    private function calculateCRC32(string $filePath): int {
        $context = hash_init('crc32b');
        $handle = fopen($filePath, 'rb');
        
        if ($handle === false) {
            return 0;
        }
        
        while (!feof($handle)) {
            $chunk = fread($handle, 64 * 1024);
            if ($chunk !== false) {
                hash_update($context, $chunk);
            }
        }
        
        fclose($handle);
        
        // PHP's crc32b returns hex string, convert to unsigned int
        $hash = hash_final($context);
        return hexdec($hash);
    }
    
    /**
     * Convert Unix timestamp to DOS time format
     * 
     * @param int $timestamp Unix timestamp
     * @return int DOS time
     */
    private function toDosTime(int $timestamp): int {
        // DOS time format only supports years 1980-2107
        // Clamp timestamp to valid range
        $minTimestamp = mktime(0, 0, 0, 1, 1, 1980);
        $maxTimestamp = mktime(23, 59, 59, 12, 31, 2107);
        $timestamp = max($minTimestamp, min($maxTimestamp, $timestamp));
        
        $time = getdate($timestamp);
        return (($time['seconds'] >> 1) | ($time['minutes'] << 5) | ($time['hours'] << 11));
    }
    
    /**
     * Convert Unix timestamp to DOS date format
     * 
     * @param int $timestamp Unix timestamp
     * @return int DOS date
     */
    private function toDosDate(int $timestamp): int {
        // DOS date format only supports years 1980-2107
        // Clamp timestamp to valid range
        $minTimestamp = mktime(0, 0, 0, 1, 1, 1980);
        $maxTimestamp = mktime(23, 59, 59, 12, 31, 2107);
        $timestamp = max($minTimestamp, min($maxTimestamp, $timestamp));
        
        $time = getdate($timestamp);
        return ($time['mday'] | ($time['mon'] << 5) | (($time['year'] - 1980) << 9));
    }
}
