<?php

/**
 * This file is part of the Peku Framework.
 *
 * @author    Patricio Rossi <meduzapat@netscape.net>
 * @copyright Copyright (c) 2025 Patricio Rossi
 * @license   MIT License - see LICENSE file for details
 * @link      https://github.com/meduzapat/peku
 */

declare(strict_types=1);

namespace Peku\Helpers\Files;

/**
 * General-purpose file representation with self-managed registry that wraps over PHP FS tools.
 *
 * Thread Safety: NOT thread-safe across processes
 * Multiple PHP processes can interfere with file operations.
 * Use file locking (flock) or semaphores for multi-process coordination.
 *
 * Platform Notes:
 * - Ownership methods (setOwner/setGroup) require appropriate privileges
 * - POSIX functions may not work correctly on Windows
 * - Designed primarily for Unix-like systems
 */
class File implements FileInterface {

	/**********************
	 * Permission Presets *
	 **********************/

	/**
	 * Private file - owner read/write only (rw-------)
	 * Use for: logs, secrets, sensitive data
	 */
	public const PRIVATE = 0600;

	/**
	 * Shared file - owner read/write, others read (rw-r--r--)
	 * Use for: configs, data files, documents
	 */
	public const SHARED = 0644;

	/**
	 * Executable file - owner read/write/execute, others read/execute (rwxr-xr-x)
	 * Use for: scripts, binaries
	 */
	public const EXECUTABLE = 0755;

	/**
	 * Public file - everyone read/write (rw-rw-rw-)
	 * Use for: temp files, shared writable files
	 */
	public const PUBLIC = 0666;

	/**************
	 * Properties *
	 **************/

	/**
	 * Registry of managed files: path → File instance
	 * @var array<string, File>
	 */
	private static array $instances = [];

	/**
	 * Physical location of this file
	 */
	private string $path;

	/**
	 * Private constructor - use manage() or Files utility methods
	 */
	private function __construct(string $path) {
		$this->path = $path;
		self::$instances[$path] = $this;
	}

	/*****************************
	 * Static Factory & Registry *
	 *****************************/

	/**
	 * Get managed file reference (returns existing or creates new)
	 *
	 * @param string $path File path
	 * @return FileInterface File instance
	 * @throws FileException if file doesn't exist or not a file
	 */
	public static function manage(string $path): FileInterface {
		// Normalize path
		$canonicalPath = realpath($path);
		if ($canonicalPath === false) {
			throw new FileException($path, 'Cannot resolve path');
		}

		// Return existing instance
		if (isset(self::$instances[$canonicalPath])) {
			return self::$instances[$canonicalPath];
		}

		if (!is_file($canonicalPath)) {
			throw new FileException($path, 'Path is not a file');
		}

		// Create specialized file type based on MIME
		return self::createFileByMime($canonicalPath);
	}

	/**
	 * Check if path is currently managed
	 */
	public static function isManaged(string $path): bool {
		$canonicalPath = realpath($path);
		return $canonicalPath !== false && isset(self::$instances[$canonicalPath]);
	}

	/**
	 * Create appropriate File subclass based on MIME type
	 *
	 * @param string $path Canonical file path
	 * @return FileInterface Specialized file instance
	 */
	private static function createFileByMime(string $path): FileInterface {
		// TODO: Detect MIME and return specialized types (ImageFile, etc.)
		// For now, return base File
		return new static($path);
	}

	/*******************
	 * Factory Methods *
	 *******************/

	/**
	 * Create new file with contents
	 *
	 * @param string $path Destination path
	 * @param string $contents Initial contents
	 * @return FileInterface File instance
	 * @throws FileException if file exists or creation fails
	 */
	public static function create(string $path, string $contents = ''): FileInterface {
		if (file_exists($path)) {
			throw new FileException($path, 'File already exists');
		}

		if (@file_put_contents($path, $contents, LOCK_EX) === false) {
			throw new FileException($path, 'Failed to create file');
		}

		return self::manage($path);
	}

	/**
	 * Create temporary file
	 *
	 * @param string $prefix Filename prefix
	 * @return FileInterface Temp file instance
	 */
	public static function createTemp(string $prefix = 'peku_'): FileInterface {
		// tempnam failure indicates catastrophic FS issue (no system tmp directory)
		$path = tempnam(sys_get_temp_dir(), $prefix);
		return self::manage($path);
	}

	/*************************
	 * Identity & Validation *
	 *************************/

	/**
	 * @see FileInterface::isValid()
	 */
	public function isValid(): bool {
		return $this->path !== '' && file_exists($this->path);
	}

	/**
	 * @see FileInterface::getPath()
	 */
	public function getPath(): string {
		if ($this->path === '') {
			throw new FileException('', 'File has been invalidated');
		}
		return $this->path;
	}

	/*******************
	 * Read Operations *
	 *******************/

	/**
	 * @see FileInterface::getName()
	 */
	public function getName(): string {
		return basename($this->getPath());
	}

	/**
	 * @see FileInterface::getSize()
	 */
	public function getSize(): int {
		$size = @filesize($this->getPath());
		if ($size === false) {
			$this->invalidateIfGone();
			throw new FileException($this->path, 'Failed to get file size');
		}
		return $size;
	}

	/**
	 * @see FileInterface::getMimeType()
	 */
	public function getMimeType(): string {
		$mime = @mime_content_type($this->getPath());
		if ($mime === false) {
			$this->invalidateIfGone();
			throw new FileException($this->path, 'Failed to detect MIME type');
		}
		return $mime;
	}

	/**
	 * @see FileInterface::getContents()
	 */
	public function getContents(): string {
		$contents = @file_get_contents($this->getPath());
		if ($contents === false) {
			$this->invalidateIfGone();
			throw new FileException($this->path, 'Failed to read file');
		}
		return $contents;
	}

	/**
	 * Write contents to file (overwrites existing)
	 *
	 * @param string $contents Content to write
	 * @throws FileException if write fails
	 */
	public function write(string $contents): void {
		if (@file_put_contents($this->getPath(), $contents, LOCK_EX) === false) {
			$this->invalidateIfGone();
			throw new FileException($this->path, 'Failed to write file');
		}
	}

	/**
	 * Append contents to file
	 *
	 * @param string $contents Content to append
	 * @throws FileException if append fails
	 */
	public function append(string $contents): void {
		if (@file_put_contents($this->getPath(), $contents, FILE_APPEND | LOCK_EX) === false) {
			$this->invalidateIfGone();
			throw new FileException($this->path, 'Failed to append to file');
		}
	}

	/**
	 * Get file modification time
	 *
	 * @return int Unix timestamp
	 * @throws FileException if operation fails
	 */
	public function getMTime(): int {
		$mtime = @filemtime($this->getPath());
		if ($mtime === false) {
			$this->invalidateIfGone();
			throw new FileException($this->path, 'Failed to get modification time');
		}
		return $mtime;
	}

	/**
	 * Get file access time
	 *
	 * @return int Unix timestamp
	 * @throws FileException if operation fails
	 */
	public function getATime(): int {
		$atime = @fileatime($this->getPath());
		if ($atime === false) {
			$this->invalidateIfGone();
			throw new FileException($this->path, 'Failed to get access time');
		}
		return $atime;
	}

	/**
	 * Get file creation/change time
	 *
	 * @return int Unix timestamp
	 * @throws FileException if operation fails
	 */
	public function getCTime(): int {
		$ctime = @filectime($this->getPath());
		if ($ctime === false) {
			$this->invalidateIfGone();
			throw new FileException($this->path, 'Failed to get change time');
		}
		return $ctime;
	}

	/**
	 * Update file access and modification time
	 *
	 * @param int|null $mtime Modification time (null = current time)
	 * @param int|null $atime Access time (null = current time)
	 * @throws FileException if operation fails
	 */
	public function touch(?int $mtime = null, ?int $atime = null): void {
		$path = $this->getPath();

		$args = [$path];
		if ($mtime !== null) {
			$args[] = $mtime;
			if ($atime !== null) {
				$args[] = $atime;
			}
		}

		if (!@touch(...$args)) {
			$this->invalidateIfGone();
			throw new FileException($path, 'Failed to touch file');
		}

		clearstatcache(true, $path);
	}

	/*******************
	 * File Operations *
	 *******************/

	/**
	 * @see FileInterface::copy()
	 */
	public function copy(string $destination): FileInterface {
		if (!@copy($this->getPath(), $destination)) {
			$this->invalidateIfGone();
			throw new FileException($this->path, "Failed to copy file to $destination");
		}
		return self::manage($destination);
	}

	/**
	 * @see FileInterface::move()
	 */
	public function move(string $destination): void {
		$oldPath = $this->getPath();

		// Canonicalize directory before move
		$destDir      = dirname($destination);
		$destName     = basename($destination);
		$canonicalDir = realpath($destDir);

		if ($canonicalDir === false) {
			throw new FileException($destination, 'Destination directory does not exist');
		}

		$fullDest = $canonicalDir . DIRECTORY_SEPARATOR . $destName;

		if (file_exists($fullDest)) {
			throw new FileException($fullDest, 'Destination file already exists');
		}

		if (!@rename($oldPath, $fullDest)) {
			$this->invalidateIfGone();
			throw new FileException($oldPath, "Failed to move file to $fullDest");
		}

		// Update registry
		unset(self::$instances[$oldPath]);
		$this->path = $fullDest;
		self::$instances[$fullDest] = $this;
	}

	/**
	 * @see FileInterface::delete()
	 */
	public function delete(): void {
		$path = $this->getPath();

		if (!@unlink($path)) {
			$this->invalidateIfGone();
			throw new FileException($path, 'Failed to delete file');
		}

		// Successful delete - invalidate
		unset(self::$instances[$path]);
		$this->path = '';
	}

	/*************
	 * Streaming *
	 *************/

	/**
	 * @see FileInterface::open()
	 */
	public function open(string $mode) {
		$handle = @fopen($this->getPath(), $mode);
		if ($handle === false) {
			$this->invalidateIfGone();
			throw new FileException($this->path, "Failed to open file in mode: $mode");
		}
		return $handle;
	}

	/**
	 * @see FileInterface::readChunked()
	 */
	public function readChunked(callable $callback, int $chunkSize = 8192): void {

		$handle = $this->open('rb');
		try {
			while (!feof($handle)) {
				$chunk = fread($handle, $chunkSize);
				$callback($chunk);
			}
		}
		finally {
			fclose($handle);
		}
	}

	/**
	 * @see FileInterface::copyToStream()
	 */
	public function copyToStream($destination): void {
		$path = $this->getPath();

		if (!is_resource($destination) || get_resource_type($destination) !== 'stream') {
			throw new FileException($path, 'Destination must be a valid stream resource');
		}

		$source = $this->open('rb');

		try {
			$bytes = stream_copy_to_stream($source, $destination);
			if ($bytes === false) {
				throw new FileException($path, 'Failed to copy stream');
			}
		}
		finally {
			fclose($source);
		}
	}

	/***************
	 * Permissions *
	 ***************/

	/**
	 * @see FileInterface::setPermissions()
	 */
	public function setPermissions(int|string $mode): void {

		$path = $this->getPath();

		// Convert string to octal if needed
		if (is_string($mode)) {
			$mode = $this->parsePermissionString($mode);
		}

		if (!@chmod($path, $mode)) {
			throw new FileException($path, sprintf('Failed to set permissions to: %o', $mode));
		}

		clearstatcache(true, $path);
	}

	/**
	 * @see FileInterface::getPermissions()
	 */
	public function getPermissions(): int {

		$path = $this->getPath();

		$perms = @fileperms($path);
		if ($perms === false) {
			$this->invalidateIfGone();
			throw new FileException($path, 'Failed to get file permissions');
		}

		// Return only the permission bits (last 9 bits)
		return $perms & 0777;
	}

	/**
	 * @see FileInterface::getPermissionsString()
	 */
	public function getPermissionsString(): string {

		$perms = $this->getPermissions();

		$str = '';
		// Owner
		$str .= ($perms & 0400) ? 'r' : '-';
		$str .= ($perms & 0200) ? 'w' : '-';
		$str .= ($perms & 0100) ? 'x' : '-';
		// Group
		$str .= ($perms & 0040) ? 'r' : '-';
		$str .= ($perms & 0020) ? 'w' : '-';
		$str .= ($perms & 0010) ? 'x' : '-';
		// Other
		$str .= ($perms & 0004) ? 'r' : '-';
		$str .= ($perms & 0002) ? 'w' : '-';
		$str .= ($perms & 0001) ? 'x' : '-';

		return $str;
	}

	/**
	 * @see FileInterface::isReadable()
	 */
	public function isReadable(): bool {
		return @is_readable($this->getPath());
	}

	/**
	 * @see FileInterface::isWritable()
	 */
	public function isWritable(): bool {
		return @is_writable($this->getPath());
	}

	/**
	 * @see FileInterface::isExecutable()
	 */
	public function isExecutable(): bool {
		return @is_executable($this->getPath());
	}

	/*************
	 * Ownership *
	 *************/

	/**
	 * @see FileInterface::setOwner()
	 */
	public function setOwner(string $user): void {
		$path = $this->getPath();
		if (!@chown($path, $user)) {
			throw new FileException($path, "Failed to set owner to: $user");
		}
		clearstatcache(true, $path);
	}

	/**
	 * @see FileInterface::getOwner()
	 */
	public function getOwner(): string {
		$path = $this->getPath();
		$owner = @fileowner($path);
		if ($owner === false) {
			$this->invalidateIfGone();
			throw new FileException($path, 'Failed to get file owner');
		}

		$userInfo = @posix_getpwuid($owner);
		return $userInfo['name'] ?? (string)$owner;
	}

	/**
	 * @see FileInterface::setGroup()
	 */
	public function setGroup(string $group): void {
		$path = $this->getPath();
		if (!@chgrp($path, $group)) {
			throw new FileException($path, "Failed to set group to: $group");
		}

		clearstatcache(true, $path);
	}

	/**
	 * @see FileInterface::getGroup()
	 */
	public function getGroup(): string {
		$path = $this->getPath();

		$group = @filegroup($path);
		if ($group === false) {
			$this->invalidateIfGone();
			throw new FileException($path, 'Failed to get file group');
		}

		$groupInfo = @posix_getgrgid($group);
		return $groupInfo['name'] ?? (string)$group;
	}

	/********************
	 * Internal Helpers *
	 ********************/

	/**
	 * Parse permission string to octal mode
	 *
	 * @param string $perms Permission string (e.g., "rwxr-xr-x")
	 * @return int Octal permission mode
	 * @throws FileException if string format is invalid
	 */
	private function parsePermissionString(string $perms): int {
		if (strlen($perms) !== 9) {
			throw new FileException('', 'Permission string must be 9 characters (e.g., "rwxr-xr-x")');
		}

		$mode = 0;

		// Owner (positions 0-2)
		if ($perms[0] === 'r') $mode |= 0400;
		if ($perms[1] === 'w') $mode |= 0200;
		if ($perms[2] === 'x') $mode |= 0100;

		// Group (positions 3-5)
		if ($perms[3] === 'r') $mode |= 0040;
		if ($perms[4] === 'w') $mode |= 0020;
		if ($perms[5] === 'x') $mode |= 0010;

		// Other (positions 6-8)
		if ($perms[6] === 'r') $mode |= 0004;
		if ($perms[7] === 'w') $mode |= 0002;
		if ($perms[8] === 'x') $mode |= 0001;

		return $mode;
	}

	/**
	 * Invalidate this instance if file no longer exists
	 * Called before throwing exceptions on operation failures
	 */
	private function invalidateIfGone(): void {
		if ($this->path !== '' && !file_exists($this->path)) {
			unset(self::$instances[$this->path]);
			$this->path = '';
		}
	}
}