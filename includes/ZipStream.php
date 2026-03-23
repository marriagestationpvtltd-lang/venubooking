<?php
/**
 * ZipStream - Streaming ZIP file generator with ZIP64 support
 *
 * This class creates ZIP files on-the-fly and streams them directly to the browser
 * without creating temporary files on disk. This provides instant download feedback
 * like Google Drive, eliminating the "Preparing download..." delay.
 *
 * Features:
 * - No temporary files needed (streams directly to output)
 * - ZIP64 support for files and archives larger than 4 GB
 * - Single-pass streaming via data descriptors (no double-read of large files)
 * - Instant download start (no preparation delay)
 * - Memory efficient (constant memory usage regardless of file size)
 * - Compatible with standard ZIP readers (uses STORE method - no compression)
 *
 * Based on ZIP file format specification (APPNOTE.TXT)
 */

class ZipStream {
    private $files = [];
    private $offset = 0;
    private $fileCount = 0;
    private $zipFilename;

    // 4 GB - 1: the maximum value that fits in a 32-bit ZIP field.
    // Any size or offset at or above this threshold requires ZIP64 extensions.
    private const ZIP64_LIMIT = 0xFFFFFFFF;

    // ZIP version 4.5 is required when ZIP64 extensions are used.
    private const ZIP64_VERSION = 45;

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

        // Disable PHP's zlib output compression so it cannot buffer the stream.
        // ob_end_clean() does not remove the zlib layer; it must be turned off
        // explicitly before any output buffering cleanup.
        @ini_set('zlib.output_compression', '0');

        // Disable output buffering for immediate streaming
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Ask Apache/Nginx not to apply mod_deflate / gzip on this response.
        // A binary ZIP compressed again would be corrupt and grows in size.
        @apache_setenv('no-gzip', '1');
        @apache_setenv('dont-vary', '1');

        // Send headers for ZIP download
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $this->zipFilename . '"');
        header('Content-Transfer-Encoding: binary');
        // Explicitly declare no content encoding so proxies/servers do not
        // attempt to re-compress the already-binary ZIP stream.
        header('Content-Encoding: identity');
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
     * The file is streamed directly to output in a single pass.
     * CRC-32 and sizes are written in a data descriptor after the file data,
     * so the file is never read twice (important for large files).
     *
     * @param string $filePath Full path to the file on disk
     * @param string $zipPath  Path/filename to use inside the ZIP
     * @return bool True if file was added successfully
     */
    public function addFile(string $filePath, string $zipPath): bool {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }

        $fileSize  = filesize($filePath);
        $fileMTime = filemtime($filePath);

        $dosTime = $this->toDosTime($fileMTime);
        $dosDate = $this->toDosDate($fileMTime);

        // Determine whether ZIP64 extensions are needed for this entry.
        // Use ZIP64 when the file itself is >= 4 GB, or when the current
        // stream offset (= local header position) is >= 4 GB.
        $useZip64 = ($fileSize >= self::ZIP64_LIMIT) || ($this->offset >= self::ZIP64_LIMIT);

        // Build the local file header.
        // We use GP bit 3 (data descriptor flag) so that CRC-32 and sizes
        // can be written AFTER the file data.  This avoids reading the file
        // twice just to pre-compute the CRC for the header.
        $zipPathBytes = $zipPath;

        if ($useZip64) {
            // ZIP64 extra field: uncompressed size + compressed size (each 8 bytes)
            $zip64Extra = pack('vv', 0x0001, 16)          // header-ID, data-size
                . $this->packUint64($fileSize)             // uncompressed size
                . $this->packUint64($fileSize);            // compressed size

            $localHeader = pack('V', 0x04034b50)           // local file header signature
                . pack('v', self::ZIP64_VERSION)           // version needed (4.5)
                . pack('v', 0x0008)                        // GP flag: bit 3 = data descriptor
                . pack('v', 0)                             // compression method (STORE)
                . pack('v', $dosTime)                      // last mod time
                . pack('v', $dosDate)                      // last mod date
                . pack('V', 0)                             // CRC-32 (deferred to data descriptor)
                . pack('V', self::ZIP64_LIMIT)             // compressed size   (ZIP64 sentinel)
                . pack('V', self::ZIP64_LIMIT)             // uncompressed size (ZIP64 sentinel)
                . pack('v', strlen($zipPathBytes))         // file name length
                . pack('v', strlen($zip64Extra))           // extra field length
                . $zipPathBytes
                . $zip64Extra;
        } else {
            $localHeader = pack('V', 0x04034b50)           // local file header signature
                . pack('v', 20)                            // version needed (2.0)
                . pack('v', 0x0008)                        // GP flag: bit 3 = data descriptor
                . pack('v', 0)                             // compression method (STORE)
                . pack('v', $dosTime)                      // last mod time
                . pack('v', $dosDate)                      // last mod date
                . pack('V', 0)                             // CRC-32 (deferred to data descriptor)
                . pack('V', 0)                             // compressed size   (deferred)
                . pack('V', 0)                             // uncompressed size (deferred)
                . pack('v', strlen($zipPathBytes))         // file name length
                . pack('v', 0)                             // extra field length
                . $zipPathBytes;
        }

        echo $localHeader;
        flush();

        $localHeaderSize = strlen($localHeader);

        // Open file and stream its content, computing CRC-32 on the fly
        $fileHandle = fopen($filePath, 'rb');
        if ($fileHandle === false) {
            return false;
        }

        $crcContext    = hash_init('crc32b');
        $chunkSize     = 64 * 1024; // 64 KB chunks
        $bytesStreamed = 0;

        while (!feof($fileHandle)) {
            $chunk = fread($fileHandle, $chunkSize);
            if ($chunk !== false && $chunk !== '') {
                hash_update($crcContext, $chunk);
                echo $chunk;
                $bytesStreamed += strlen($chunk);
                flush();
            }

            // Check if client disconnected
            if (connection_aborted()) {
                fclose($fileHandle);
                return false;
            }
        }

        fclose($fileHandle);

        // Convert hex CRC string to unsigned 32-bit integer
        $crc32 = hexdec(hash_final($crcContext));

        // Write data descriptor immediately after file data.
        // The optional signature 0x08074b50 is widely recognised.
        if ($useZip64) {
            // ZIP64 data descriptor: CRC-32 (4 bytes) + sizes (8 bytes each)
            $dataDescriptor = pack('V', 0x08074b50)        // data descriptor signature
                . pack('V', $crc32)                        // CRC-32
                . $this->packUint64($bytesStreamed)        // compressed size   (64-bit)
                . $this->packUint64($bytesStreamed);       // uncompressed size (64-bit)
        } else {
            $dataDescriptor = pack('V', 0x08074b50)        // data descriptor signature
                . pack('V', $crc32)                        // CRC-32
                . pack('V', $bytesStreamed)                 // compressed size   (32-bit)
                . pack('V', $bytesStreamed);               // uncompressed size (32-bit)
        }

        echo $dataDescriptor;
        flush();

        $dataDescriptorSize = strlen($dataDescriptor);

        // Record entry metadata for the central directory
        $this->files[] = [
            'zipPath'          => $zipPath,
            'crc32'            => $crc32,
            'compressedSize'   => $bytesStreamed,
            'uncompressedSize' => $bytesStreamed,
            'dosTime'          => $dosTime,
            'dosDate'          => $dosDate,
            'localHeaderOffset' => $this->offset,
            'useZip64'         => $useZip64,
        ];

        // Advance stream offset: header + file data + data descriptor
        $this->offset += $localHeaderSize + $bytesStreamed + $dataDescriptorSize;
        $this->fileCount++;

        return true;
    }

    /**
     * Add a file from string content (for small inline data)
     *
     * @param string $content The file content
     * @param string $zipPath Path/filename to use inside the ZIP
     * @return bool True if file was added successfully
     */
    public function addFromString(string $content, string $zipPath): bool {
        $fileSize = strlen($content);
        $crc32    = hexdec(hash('crc32b', $content));

        $now     = time();
        $dosTime = $this->toDosTime($now);
        $dosDate = $this->toDosDate($now);

        $zipPathBytes = $zipPath;
        $useZip64     = ($fileSize >= self::ZIP64_LIMIT) || ($this->offset >= self::ZIP64_LIMIT);

        if ($useZip64) {
            $zip64Extra = pack('vv', 0x0001, 16)
                . $this->packUint64($fileSize)
                . $this->packUint64($fileSize);

            $localHeader = pack('V', 0x04034b50)
                . pack('v', self::ZIP64_VERSION)
                . pack('v', 0)                             // GP flag (CRC known, no data descriptor)
                . pack('v', 0)                             // STORE
                . pack('v', $dosTime)
                . pack('v', $dosDate)
                . pack('V', $crc32)
                . pack('V', self::ZIP64_LIMIT)             // compressed size   sentinel
                . pack('V', self::ZIP64_LIMIT)             // uncompressed size sentinel
                . pack('v', strlen($zipPathBytes))
                . pack('v', strlen($zip64Extra))
                . $zipPathBytes
                . $zip64Extra;
        } else {
            $localHeader = pack('V', 0x04034b50)
                . pack('v', 20)
                . pack('v', 0)
                . pack('v', 0)                             // STORE
                . pack('v', $dosTime)
                . pack('v', $dosDate)
                . pack('V', $crc32)
                . pack('V', $fileSize)
                . pack('V', $fileSize)
                . pack('v', strlen($zipPathBytes))
                . pack('v', 0)
                . $zipPathBytes;
        }

        echo $localHeader;
        flush();

        $localHeaderSize = strlen($localHeader);

        $this->files[] = [
            'zipPath'          => $zipPath,
            'crc32'            => $crc32,
            'compressedSize'   => $fileSize,
            'uncompressedSize' => $fileSize,
            'dosTime'          => $dosTime,
            'dosDate'          => $dosDate,
            'localHeaderOffset' => $this->offset,
            'useZip64'         => $useZip64,
        ];

        echo $content;
        flush();

        $this->offset += $localHeaderSize + $fileSize;
        $this->fileCount++;

        return true;
    }

    /**
     * Finish the ZIP file by writing the central directory and end records.
     * Writes ZIP64 end records whenever the archive exceeds 32-bit limits.
     * Must be called after all files have been added.
     */
    public function finish(): void {
        $centralDirectoryOffset = $this->offset;
        $centralDirectorySize   = 0;

        // --- Central directory entries ---
        foreach ($this->files as $file) {
            $zipPathBytes = $file['zipPath'];

            $needsZip64Size   = ($file['compressedSize']    >= self::ZIP64_LIMIT)
                             || ($file['uncompressedSize']  >= self::ZIP64_LIMIT);
            $needsZip64Offset = ($file['localHeaderOffset'] >= self::ZIP64_LIMIT);
            $useZip64         = $needsZip64Size || $needsZip64Offset;

            if ($useZip64) {
                // Build ZIP64 extra field containing only the fields that overflow
                $zip64ExtraData = '';
                if ($needsZip64Size) {
                    $zip64ExtraData .= $this->packUint64($file['uncompressedSize']);
                    $zip64ExtraData .= $this->packUint64($file['compressedSize']);
                }
                if ($needsZip64Offset) {
                    $zip64ExtraData .= $this->packUint64($file['localHeaderOffset']);
                }
                $zip64Extra = pack('vv', 0x0001, strlen($zip64ExtraData)) . $zip64ExtraData;

                $centralEntry = pack('V', 0x02014b50)      // central dir signature
                    . pack('v', self::ZIP64_VERSION)       // version made by
                    . pack('v', self::ZIP64_VERSION)       // version needed
                    . pack('v', 0x0008)                    // GP flag: bit 3 (data descriptor was used)
                    . pack('v', 0)                         // STORE
                    . pack('v', $file['dosTime'])
                    . pack('v', $file['dosDate'])
                    . pack('V', $file['crc32'])
                    . pack('V', $needsZip64Size   ? self::ZIP64_LIMIT : $file['compressedSize'])
                    . pack('V', $needsZip64Size   ? self::ZIP64_LIMIT : $file['uncompressedSize'])
                    . pack('v', strlen($zipPathBytes))
                    . pack('v', strlen($zip64Extra))
                    . pack('v', 0)                         // file comment length
                    . pack('v', 0)                         // disk number start
                    . pack('v', 0)                         // internal file attributes
                    . pack('V', 32)                        // external file attributes
                    . pack('V', $needsZip64Offset ? self::ZIP64_LIMIT : $file['localHeaderOffset'])
                    . $zipPathBytes
                    . $zip64Extra;
            } else {
                $centralEntry = pack('V', 0x02014b50)
                    . pack('v', 20)                        // version made by
                    . pack('v', 20)                        // version needed (2.0)
                    . pack('v', 0x0008)                    // GP flag: bit 3
                    . pack('v', 0)                         // STORE
                    . pack('v', $file['dosTime'])
                    . pack('v', $file['dosDate'])
                    . pack('V', $file['crc32'])
                    . pack('V', $file['compressedSize'])
                    . pack('V', $file['uncompressedSize'])
                    . pack('v', strlen($zipPathBytes))
                    . pack('v', 0)                         // extra field length
                    . pack('v', 0)                         // file comment length
                    . pack('v', 0)                         // disk number start
                    . pack('v', 0)                         // internal file attributes
                    . pack('V', 32)                        // external file attributes
                    . pack('V', $file['localHeaderOffset'])
                    . $zipPathBytes;
            }

            echo $centralEntry;
            $centralDirectorySize += strlen($centralEntry);
        }

        // --- ZIP64 end records (required when any field exceeds 32-bit limits) ---
        $needsZip64Eocd = ($this->fileCount        >= 0xFFFF)
                       || ($centralDirectorySize   >= self::ZIP64_LIMIT)
                       || ($centralDirectoryOffset >= self::ZIP64_LIMIT);

        if ($needsZip64Eocd) {
            $zip64EocdOffset = $centralDirectoryOffset + $centralDirectorySize;

            // ZIP64 End of Central Directory Record
            $zip64Eocd = pack('V', 0x06064b50)             // ZIP64 EOCD signature
                . $this->packUint64(44)                    // size of remaining ZIP64 EOCD (fixed 44 bytes)
                . pack('v', self::ZIP64_VERSION)           // version made by
                . pack('v', self::ZIP64_VERSION)           // version needed
                . pack('V', 0)                             // disk number
                . pack('V', 0)                             // disk with central dir
                . $this->packUint64($this->fileCount)      // entries on this disk
                . $this->packUint64($this->fileCount)      // total entries
                . $this->packUint64($centralDirectorySize) // central dir size
                . $this->packUint64($centralDirectoryOffset); // central dir offset
            echo $zip64Eocd;

            // ZIP64 End of Central Directory Locator
            $zip64Locator = pack('V', 0x07064b50)          // locator signature
                . pack('V', 0)                             // disk with ZIP64 EOCD
                . $this->packUint64($zip64EocdOffset)      // offset of ZIP64 EOCD
                . pack('V', 1);                            // total disks
            echo $zip64Locator;
        }

        // --- Standard End of Central Directory Record ---
        // Use sentinel values (0xFFFF / 0xFFFFFFFF) for fields that overflow
        $eocdFileCount  = min($this->fileCount,        0xFFFF);
        $eocdCdSize     = min($centralDirectorySize,   self::ZIP64_LIMIT);
        $eocdCdOffset   = min($centralDirectoryOffset, self::ZIP64_LIMIT);

        $eocd = pack('V', 0x06054b50)                      // EOCD signature
            . pack('v', 0)                                 // disk number
            . pack('v', 0)                                 // disk with central dir
            . pack('v', $eocdFileCount)                    // entries on this disk
            . pack('v', $eocdFileCount)                    // total entries
            . pack('V', $eocdCdSize)                       // central dir size
            . pack('V', $eocdCdOffset)                     // central dir offset
            . pack('v', 0);                                // comment length

        echo $eocd;
        flush();
    }

    /**
     * Pack a 64-bit unsigned integer as two 32-bit little-endian words.
     *
     * Requires 64-bit PHP (PHP_INT_SIZE === 8).  ZIP64 is only triggered for
     * files or archives >= 4 GB, which are physically impossible to read or
     * write on 32-bit PHP anyway (PHP_INT_MAX ≈ 2 GB), so the guard below
     * exists purely as a safety net.
     *
     * @param int $value Non-negative 64-bit integer
     * @return string 8 bytes, little-endian
     */
    private function packUint64(int $value): string {
        if (PHP_INT_SIZE < 8) {
            // 32-bit PHP cannot represent values this large; fall back to zero.
            // This path is unreachable in practice because filesize() already
            // caps at PHP_INT_MAX (~2 GB) on 32-bit builds.
            return pack('VV', 0, 0);
        }
        $low  = $value & 0xFFFFFFFF;
        $high = ($value >> 32) & 0xFFFFFFFF;
        return pack('VV', $low, $high);
    }

    /**
     * Convert Unix timestamp to DOS time format
     *
     * @param int $timestamp Unix timestamp
     * @return int DOS time
     */
    private function toDosTime(int $timestamp): int {
        // DOS time format only supports years 1980-2107
        $minTimestamp = mktime(0, 0, 0, 1, 1, 1980);
        $maxTimestamp = mktime(23, 59, 59, 12, 31, 2107);
        $timestamp    = max($minTimestamp, min($maxTimestamp, $timestamp));

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
        $minTimestamp = mktime(0, 0, 0, 1, 1, 1980);
        $maxTimestamp = mktime(23, 59, 59, 12, 31, 2107);
        $timestamp    = max($minTimestamp, min($maxTimestamp, $timestamp));

        $time = getdate($timestamp);
        return ($time['mday'] | ($time['mon'] << 5) | (($time['year'] - 1980) << 9));
    }
}
