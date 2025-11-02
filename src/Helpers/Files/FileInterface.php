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
 * File interface for all file implementations
 */
interface FileInterface {

	// ========================================================================
	// Identity & Validation
	// ========================================================================

	/**
	 * Get file path
	 * @throws FileException if file has been invalidated
	 */
	public function getPath(): string;

	/**
	 * Check if this file instance is still valid
	 */
	public function isValid(): bool;

	// ========================================================================
	// Read Operations
	// ========================================================================

	/**
	 * Get file name (basename)
	 */
	public function getName(): string;

	/**
	 * Get file size in bytes
	 * @throws FileException if file is invalid or inaccessible
	 */
	public function getSize(): int;

	/**
	 * Get MIME type
	 * @throws FileException if detection fails
	 */
	public function getMimeType(): string;

	/**
	 * Read entire file contents
	 * @throws FileException if read fails
	 */
	public function getContents(): string;

	/**
	 * Write contents to file (overwrites existing)
	 * @param string $contents Content to write
	 * @throws FileException if write fails
	 */
	public function write(string $contents): void;

	/**
	 * Append contents to file
	 * @param string $contents Content to append
	 * @throws FileException if append fails
	 */
	public function append(string $contents): void;

	// ========================================================================
	// File Metadata
	// ========================================================================

	/**
	 * Get file modification time
	 * @return int Unix timestamp
	 * @throws FileException if operation fails
	 */
	public function getMTime(): int;

	/**
	 * Get file access time
	 * @return int Unix timestamp
	 * @throws FileException if operation fails
	 */
	public function getATime(): int;

	/**
	 * Get file creation/change time
	 * @return int Unix timestamp
	 * @throws FileException if operation fails
	 */
	public function getCTime(): int;

	/**
	 * Update file access and modification time
	 * @param int|null $mtime Modification time (null = current time)
	 * @param int|null $atime Access time (null = current time)
	 * @throws FileException if operation fails
	 */
	public function touch(?int $mtime = null, ?int $atime = null): void;

	// ========================================================================
	// File Operations
	// ========================================================================

	/**
	 * Copy file to new location
	 * @param string $destination Destination path
	 * @return FileInterface New file instance at destination
	 * @throws FileException if copy fails
	 */
	public function copy(string $destination): FileInterface;

	/**
	 * Move file to new location
	 * @param string $destination Destination path
	 * @throws FileException if move fails
	 */
	public function move(string $destination): void;

	/**
	 * Delete file
	 * @throws FileException if delete fails
	 */
	public function delete(): void;

	// ========================================================================
	// Streaming
	// ========================================================================

	/**
	 * Open file for streaming
	 * @param string $mode fopen() mode (r, w, a, etc.)
	 * @return resource File handle
	 * @throws FileException on failure
	 */
	public function open(string $mode);

	/**
	 * Read file in chunks (for large files)
	 * @param callable $callback function(string $chunk): void
	 * @param int $chunkSize Bytes per chunk
	 * @throws FileException on read failure
	 */
	public function readChunked(callable $callback, int $chunkSize = 8192): void;

	/**
	 * Copy to stream (memory efficient)
	 * @param resource $destination Destination stream
	 * @throws FileException on failure
	 */
	public function copyToStream($destination): void;

	// ========================================================================
	// Permissions & Ownership
	// ========================================================================

	/**
	 * Set file permissions (octal mode, preset, or string format)
	 *
	 * @param int|string $mode Permission mode
	 *   - int: Octal mode (e.g., 0644, 0755)
	 *   - string: Permission string (e.g., "rwxr-xr-x", "rw-r--r--")
	 *   - Or use presets: File::PRIVATE, File::SHARED, File::EXECUTABLE, File::PUBLIC
	 * @throws FileException if operation fails
	 *
	 * @example $file->setPermissions(0644);              // Octal
	 * @example $file->setPermissions('rw-r--r--');       // String
	 * @example $file->setPermissions(File::SHARED);      // Preset
	 */
	public function setPermissions(int|string $mode): void;

	/**
	 * Get file permissions as octal integer
	 * @return int Permission mode (e.g., 0644)
	 */
	public function getPermissions(): int;

	/**
	 * Get formatted permissions string (e.g., "rw-r--r--")
	 */
	public function getPermissionsString(): string;

	/**
	 * Check if file is readable by current user
	 */
	public function isReadable(): bool;

	/**
	 * Check if file is writable by current user
	 */
	public function isWritable(): bool;

	/**
	 * Check if file is executable by current user
	 */
	public function isExecutable(): bool;

	/**
	 * Set file owner
	 * @param string $user Username
	 * @throws FileException if operation fails
	 */
	public function setOwner(string $user): void;

	/**
	 * Get file owner username
	 */
	public function getOwner(): string;

	/**
	 * Set file group
	 * @param string $group Group name
	 * @throws FileException if operation fails
	 */
	public function setGroup(string $group): void;

	/**
	 * Get file group name
	 */
	public function getGroup(): string;
}
