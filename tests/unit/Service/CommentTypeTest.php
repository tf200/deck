<?php

declare(strict_types=1);

namespace OCA\Deck\Tests\Unit\Service;

use OCA\Deck\Service\CommentType;
use PHPUnit\Framework\TestCase;

final class CommentTypeTest extends TestCase {
	public function testSupportedTypesAreAccepted(): void {
		$this->assertTrue(CommentType::isValid('decision'));
		$this->assertTrue(CommentType::isValid('risk_blocker'));
	}

	public function testUnknownTypesFallBackToGeneral(): void {
		$this->assertSame(CommentType::DEFAULT, CommentType::normalize('unknown'));
	}
}
