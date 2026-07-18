<?php

declare(strict_types=1);

namespace ModernBx\Cli\App\Console\Command\Bx\Backup\Internal\Restore;

use ModernBx\Cli\App\Console\Command\Bx\Backup\Exception\ArchiveReadException;
use ModernBx\Cli\App\Console\Command\Bx\Backup\Exception\DestinationNotEmptyException;
use ModernBx\Cli\App\Console\Command\Bx\Backup\Exception\ExtractionException;
use ModernBx\Cli\App\Console\Command\Bx\Backup\Exception\UnsafePathException;
use ModernBx\Cli\App\Console\Command\Bx\Backup\Internal\Crypto\BitrixCipher;

final class RestoreTarExtractor
{
    private const BLOCK_BYTES = 512;
    private const BUFFER_BYTES = 51_200;
    private const BITRIX_ENCRYPTED_SIGNATURE = 'Bitrix Encrypted File';

    /** @var resource|null */
    private mixed $stream = null;
    private string $file = '';
    private string $buffer = '';
    private bool $gzip = false;
    private int $block = 0;
    private ?string $passwordDigest = null;
    private ?string $encryptionMethod = null;
    private int $files = 0;
    private int $directories = 0;

    public function __construct(private readonly ?string $password = null)
    {
    }

    /** @return array{files:int,directories:int} */
    public function extract(string $archive, string $destination): array
    {
        $root = $this->prepareDestination($destination);
        $this->openRead(self::firstName(realpath($archive) ?: $archive));

        try {
            while (($header = $this->readHeader()) !== null) {
                if ($header['filename'] === '.' || $header['filename'] === './') {
                    continue;
                }

                $name = $this->normalizePath($header['filename']);
                $target = $root . '/' . $name;
                $this->assertInsideRoot($root, $target);

                if ($header['type'] === '5') {
                    $this->mkdir($target);
                    $this->directories++;
                    continue;
                }

                $this->mkdir(dirname($target));
                $this->writeFile($target, $header['size']);
                $this->files++;
            }
        } finally {
            $this->close();
        }

        return ['files' => $this->files, 'directories' => $this->directories];
    }

    private function prepareDestination(string $destination): string
    {
        if (!file_exists($destination) && !mkdir($destination, 0700, true) && !is_dir($destination)) {
            throw new ExtractionException("Cannot create destination: {$destination}");
        }
        if (!is_dir($destination)) {
            throw new ExtractionException('Destination is not a directory.');
        }
        $root = realpath($destination);
        if ($root === false) {
            throw new ExtractionException('Cannot resolve destination.');
        }
        $it = new \FilesystemIterator($root, \FilesystemIterator::SKIP_DOTS);
        if ($it->valid()) {
            throw new DestinationNotEmptyException("Destination is not empty: {$root}");
        }

        return rtrim($root, '/');
    }

    private function openRead(string $file): void
    {
        $this->gzip = str_ends_with($file, '.gz') || str_ends_with($file, '.tgz');
        $this->open($file);
        $this->inspectEncryptionHeader();
    }

    private function open(string $file): void
    {
        if (!is_file($file)) {
            throw new ArchiveReadException("Archive volume does not exist: {$file}");
        }
        $this->file = $file;
        $stream = $this->gzip ? @gzopen($file, 'rb') : @fopen($file, 'rb');
        if (!is_resource($stream)) {
            throw new ArchiveReadException("Cannot open archive volume: {$file}");
        }
        $this->stream = $stream;
    }

    private function close(): void
    {
        if (!is_resource($this->stream)) {
            return;
        }
        $this->gzip ? gzclose($this->stream) : fclose($this->stream);
        $this->stream = null;
    }

    private function openNext(bool $ignoreMissing): bool
    {
        $next = self::nextName($this->file);
        if (is_file($next)) {
            $this->close();
            $this->open($next);
            return true;
        }
        if (!$ignoreMissing) {
            throw new ArchiveReadException("File doesn't exist: {$next}");
        }

        return false;
    }

    private function readBlock(bool $ignoreOpenNextError = false): string
    {
        if ($this->buffer === '') {
            $chunk = $this->readFromStream(self::BUFFER_BYTES);
            if ($chunk === '' && $this->openNext($ignoreOpenNextError)) {
                $chunk = $this->readFromStream(self::BUFFER_BYTES);
            }
            if ($chunk !== '' && $this->passwordDigest !== null) {
                $decoded = BitrixCipher::decrypt(
                    $chunk,
                    $this->passwordDigest,
                    $this->encryptionMethod ?? 'aes-256-ecb',
                );
                if (!is_string($decoded)) {
                    throw new ArchiveReadException('Cannot decrypt archive volume.');
                }
                $chunk = $decoded;
            }
            $this->buffer = $chunk;
        }

        if ($this->buffer === '') {
            return '';
        }

        $block = substr($this->buffer, 0, self::BLOCK_BYTES);
        $this->buffer = substr($this->buffer, self::BLOCK_BYTES);
        $this->block++;

        return $block;
    }

    /** @return array{filename:string,type:string,size:int}|null */
    private function readHeader(bool $long = false): ?array
    {
        $block = '';
        while (trim($block) === '') {
            $block = $this->readBlock(true);
            if (strlen($block) === 0) {
                return null;
            }
        }

        if (strlen($block) !== self::BLOCK_BYTES) {
            throw new ArchiveReadException('Wrong block size: ' . strlen($block) . ' (block ' . $this->block . ')');
        }

        $data = unpack(
            'a100filename/a8mode/a8uid/a8gid/a12size/a12mtime/a8checksum/a1type/a100link'
            . '/a6magic/a2version/a32uname/a32gname/a8devmajor/a8devminor/a155prefix',
            $block,
        );
        if (!is_array($data)) {
            throw new ArchiveReadException('Cannot read tar header.');
        }

        $deviceMarker = trim((string) $data['devmajor']) . trim((string) $data['devminor']);
        if (!is_numeric(trim((string) $data['checksum'])) || ($deviceMarker !== '' && (int) $deviceMarker !== 0)) {
            throw new ArchiveReadException('Archive is corrupted, wrong block: ' . ($this->block - 1));
        }

        $header = [
            'filename' => trim(
                rtrim((string) $data['prefix'], "\0") . '/' . rtrim((string) $data['filename'], "\0"),
                '/',
            ),
            'type' => trim((string) $data['type'], "\0"),
            'size' => (int) octdec((string) $data['size']),
        ];

        if (str_starts_with($header['filename'], './')) {
            $header['filename'] = substr($header['filename'], 2);
        }

        if ($header['type'] === 'L') {
            $filename = '';
            $blocks = (int) ceil($header['size'] / self::BLOCK_BYTES);
            for ($index = 0; $index < $blocks; $index++) {
                $filename .= $this->readBlock();
            }
            $header = $this->readHeader(true);
            if ($header === null) {
                throw new ArchiveReadException('Wrong long header, block: ' . $this->block);
            }
            $nullOffset = strpos($filename, "\0");
            $header['filename'] = substr($filename, 0, $nullOffset === false ? strlen($filename) : $nullOffset);
        }

        if (str_ends_with($header['filename'], '/')) {
            $header['type'] = '5';
        }
        if ($header['type'] === '5') {
            $header['size'] = 0;
        }
        if ($header['filename'] === '') {
            throw new ArchiveReadException('Filename is empty, wrong block: ' . ($this->block - 1));
        }
        if (!$this->checkChecksum($block, (string) $data['checksum'])) {
            throw new ArchiveReadException('Checksum error on file: ' . $header['filename']);
        }

        return $header;
    }

    private function writeFile(string $target, int $size): void
    {
        $stream = @fopen($target, 'wb');
        if ($stream === false) {
            throw new ExtractionException("Cannot create file: {$target}");
        }

        $blocks = (int) ceil($size / self::BLOCK_BYTES);
        try {
            for ($index = 1; $index <= $blocks; $index++) {
                $contents = $this->readBlock();
                if ($contents === '') {
                    throw new ArchiveReadException("Unexpected end of archive while reading {$target}");
                }
                if ($index === $blocks && ($chunk = $size % self::BLOCK_BYTES)) {
                    $contents = substr($contents, 0, $chunk);
                }
                $written = fwrite($stream, $contents);
                if ($written === false || $written === 0 || $written !== strlen($contents)) {
                    throw new ExtractionException("Cannot write file: {$target}");
                }
            }
        } finally {
            fclose($stream);
        }
    }

    private function mkdir(string $directory): void
    {
        if (!file_exists($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new ExtractionException("Cannot create directory: {$directory}");
        }
        if (!is_dir($directory)) {
            throw new ExtractionException("Path is not a directory: {$directory}");
        }
    }

    private function normalizePath(string $name): string
    {
        $name = str_replace('\\', '/', $name);
        $name = preg_replace('#^\./+#', '', $name) ?? $name;
        if ($name === '' || str_starts_with($name, '/') || preg_match('#^[A-Za-z]:/#', $name)) {
            throw new UnsafePathException("Unsafe archive path: {$name}");
        }
        foreach (explode('/', $name) as $part) {
            if ($part === '' || $part === '.' || $part === '..' || str_contains($part, "\0")) {
                throw new UnsafePathException("Unsafe archive path: {$name}");
            }
        }

        return $name;
    }

    private function assertInsideRoot(string $root, string $target): void
    {
        $parent = dirname($target);
        $probe = $parent;
        while (!file_exists($probe) && $probe !== dirname($probe)) {
            $probe = dirname($probe);
        }
        $resolved = realpath($probe);
        if ($resolved === false || ($resolved !== $root && !str_starts_with($resolved, $root . '/'))) {
            throw new UnsafePathException("Target leaves destination: {$target}");
        }
    }

    private function checkChecksum(string $block, string $storedChecksum): bool
    {
        $checksum = $this->checksum($block);

        return octdec($storedChecksum) === $checksum || ($storedChecksum === "\0" && $checksum === 256);
    }

    private function checksum(string $block): int
    {
        $bytes = unpack('C*', substr($block, 0, 148) . '        ' . substr($block, 156));
        if ($bytes === false) {
            throw new ArchiveReadException('Cannot calculate tar checksum.');
        }

        return array_sum($bytes);
    }

    private function inspectEncryptionHeader(): void
    {
        $sample = $this->readFromStream(self::BLOCK_BYTES);
        if ($sample === '') {
            return;
        }
        $data = unpack('a100empty/a90signature/a10version/a56tail/a256enc', $sample);
        if (!is_array($data) || trim((string) $data['signature']) !== self::BITRIX_ENCRYPTED_SIGNATURE) {
            $this->seekToStart();
            return;
        }
        if ($this->password === null || $this->password === '') {
            throw new ArchiveReadException('The archive is encrypted and requires a password.');
        }
        $version = trim((string) $data['version']);
        if (version_compare($version, '1.2', '>')) {
            throw new ArchiveReadException('Unsupported encrypted archive version: ' . $version);
        }
        $digest = BitrixCipher::passwordDigest($this->password);
        foreach (BitrixCipher::supportedMethods() as $method) {
            $plain = BitrixCipher::decrypt((string) $data['enc'], $digest, $method);
            if (is_string($plain) && substr($sample, 0, 256) === $plain) {
                $this->passwordDigest = $digest;
                $this->encryptionMethod = $method;
                $this->block = 1;
                return;
            }
        }
        throw new ArchiveReadException('The archive password is invalid.');
    }

    private function readFromStream(int $length): string
    {
        if (!is_resource($this->stream)) {
            throw new ArchiveReadException("Archive volume {$this->file} is not open.");
        }
        $length = max(1, $length);
        $chunk = $this->gzip ? gzread($this->stream, $length) : fread($this->stream, $length);
        if ($chunk === false) {
            throw new ArchiveReadException("Cannot read archive volume {$this->file}.");
        }

        return $chunk;
    }

    private function seekToStart(): void
    {
        if (!is_resource($this->stream)) {
            throw new ArchiveReadException("Archive volume {$this->file} is not open.");
        }
        $status = $this->gzip ? gzseek($this->stream, 0) : fseek($this->stream, 0);
        if ($status !== 0) {
            throw new ArchiveReadException("Cannot rewind archive volume {$this->file}.");
        }
    }

    private static function firstName(string $file): string
    {
        return preg_replace('/\.[0-9]+$/D', '', $file) ?? $file;
    }

    private static function nextName(string $file): string
    {
        if (preg_match('/^(.*\.)([0-9]+)$/D', $file, $match) === 1) {
            return $match[1] . ((int) $match[2] + 1);
        }

        return $file . '.1';
    }
}
