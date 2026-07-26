<?php

declare(strict_types=1);

namespace OCA\Deck\Service;

use OCA\Deck\Db\Card;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CardAccessPolicyIntegrationTest extends TestCase {
	public function testCapabilitiesDefaultToAllowedWithoutProvider(): void {
		$integration = new CardAccessPolicyIntegration($this->createMock(LoggerInterface::class));
		$this->setProvider($integration, null);

		$this->assertSame([
			'canMove' => true,
			'canSign' => true,
			'canVerify' => true,
		], $integration->getCapabilities(new Card(), 'alice'));
	}

	public function testCapabilitiesAreDelegatedToProvider(): void {
		$integration = new CardAccessPolicyIntegration($this->createMock(LoggerInterface::class));
		$provider = new class {
			public function getCapabilities(Card $card, ?string $userId): array {
				return [
					'canMove' => false,
					'canSign' => $userId === 'alice',
					'canVerify' => false,
				];
			}
		};
		$this->setProvider($integration, $provider);

		$this->assertSame([
			'canMove' => false,
			'canSign' => true,
			'canVerify' => false,
		], $integration->getCapabilities(new Card(), 'alice'));
	}

	public function testCapabilitiesDefaultToAllowedWhenProviderFails(): void {
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('debug');
		$integration = new CardAccessPolicyIntegration($logger);
		$this->setProvider($integration, new class {
			public function getCapabilities(Card $card, ?string $userId): array {
				throw new \RuntimeException('Policy tables are unavailable');
			}
		});

		$this->assertSame([
			'canMove' => true,
			'canSign' => true,
			'canVerify' => true,
		], $integration->getCapabilities(new Card(), 'alice'));
	}

	private function setProvider(CardAccessPolicyIntegration $integration, ?object $provider): void {
		$resolved = new \ReflectionProperty($integration, 'resolved');
		$resolved->setValue($integration, true);
		$providerProperty = new \ReflectionProperty($integration, 'provider');
		$providerProperty->setValue($integration, $provider);
	}
}
