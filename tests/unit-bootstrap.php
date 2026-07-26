<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace {
	$coreBase = '/var/www/html/lib/base.php';
	if (is_file($coreBase)) {
		require_once $coreBase;
	}

	require_once __DIR__ . '/../vendor/autoload.php';

	spl_autoload_register(static function (string $class): void {
		$prefix = 'OCA\\Deck\\';
		if (!str_starts_with($class, $prefix)) {
			return;
		}

		$relativePath = str_replace('\\', '/', substr($class, strlen($prefix)));
		$file = __DIR__ . '/../lib/' . $relativePath . '.php';
		if (is_file($file)) {
			require_once $file;
		}
	});
}

namespace Test {
	class TestCase extends \PHPUnit\Framework\TestCase {
	}
}
