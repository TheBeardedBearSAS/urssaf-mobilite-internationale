<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Command;

use Symfony\Component\Console\Exception\InvalidOptionException;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\BinaryStream;

/**
 * Writes a certificate to a local file (ADR 0005): the file is created exclusively with the mode 0600 (the PDF names a
 * posted worker), the stream is copied without being buffered whole, closed in every case, and a partial file is
 * removed when the copy fails. An existing file is replaced only on request, and then removed first so that the new one
 * is created with the mode 0600 too.
 */
final class CertificateFile
{
    /** Bits of the permissions that the process creating the file must never grant: group and others. */
    private const UMASK_OWNER_ONLY = 0o177;

    /**
     * @return int the number of bytes written
     *
     * @throws InvalidOptionException when the target exists and is not to be replaced, or cannot be created
     */
    public static function write(BinaryStream $stream, string $path, bool $replace): int
    {
        try {
            $target = self::create($path, $replace);
            $complete = false;
            try {
                $written = $stream->copyTo($target);
                $complete = true;

                return $written;
            } finally {
                fclose($target);
                if (!$complete) {
                    unlink($path);
                }
            }
        } finally {
            $stream->close();
        }
    }

    /**
     * @return resource
     *
     * @throws InvalidOptionException
     *
     * @mago-expect lint:no-boolean-flag-parameter The flag is the --force option of the command.
     */
    private static function create(string $path, bool $replace): mixed
    {
        if (file_exists($path) || is_link($path)) {
            if (!$replace) {
                throw new InvalidOptionException('The output file already exists: choose another path or use --force to replace it.');
            }
            if (self::quietly(static fn(): bool => unlink($path)) !== true) {
                throw new InvalidOptionException('The existing output file cannot be replaced.');
            }
        }
        $previous = umask(self::UMASK_OWNER_ONLY);
        try {
            // "x+b", not "xb": PSR-7 implementations (nyholm/psr7) only see a stream as writable for the modes they list.
            $target = self::quietly(static fn(): mixed => fopen($path, 'x+b'));
        } finally {
            umask($previous);
        }

        return \is_resource($target) ? $target : throw new InvalidOptionException('The output file cannot be created: check that its directory exists and is writable.');
    }

    /**
     * Runs a filesystem call whose failure is reported by its return value: the PHP warning is not wanted, the caller
     * checks the result.
     *
     * @template T
     *
     * @param callable(): T $operation
     * @param-immediately-invoked-callable $operation
     *
     * @return T
     */
    private static function quietly(callable $operation): mixed
    {
        set_error_handler(static fn(): bool => true);
        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }
}
