<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3AbTesting\AssignmentContext;

#[CoversClass(AssignmentContext::class)]
final class AssignmentContextTest extends TestCase
{
    #[Test]
    public function emptyHasNoEnvironmentAndNoAttributes(): void
    {
        $context = AssignmentContext::empty();

        $this->assertNull($context->getEnvironment());
        $this->assertSame([], $context->getAttributes());
    }

    #[Test]
    public function forEnvironmentSetsEnvironment(): void
    {
        $context = AssignmentContext::forEnvironment('production');

        $this->assertSame('production', $context->getEnvironment());
    }

    #[Test]
    public function constructorStoresEnvironmentAndAttributes(): void
    {
        $context = new AssignmentContext(environment: 'staging', attributes: ['country' => 'DE']);

        $this->assertSame('staging', $context->getEnvironment());
        $this->assertSame(['country' => 'DE'], $context->getAttributes());
    }

    #[Test]
    public function getAttributeReturnsValueWhenPresent(): void
    {
        $context = new AssignmentContext(attributes: ['plan' => 'pro']);

        $this->assertSame('pro', $context->getAttribute('plan'));
    }

    #[Test]
    public function getAttributeReturnsNullWhenAbsent(): void
    {
        $context = AssignmentContext::empty();

        $this->assertNull($context->getAttribute('missing'));
    }

    #[Test]
    public function withEnvironmentReturnsCopyWithEnvironmentAndKeepsAttributes(): void
    {
        $context = (new AssignmentContext(attributes: ['country' => 'FR']))->withEnvironment('production');

        $this->assertSame('production', $context->getEnvironment());
        $this->assertSame(['country' => 'FR'], $context->getAttributes());
    }

    #[Test]
    public function withAttributeAddsAttributeAndKeepsEnvironment(): void
    {
        $context = AssignmentContext::forEnvironment('production')->withAttribute('country', 'IT');

        $this->assertSame('production', $context->getEnvironment());
        $this->assertSame('IT', $context->getAttribute('country'));
    }

    #[Test]
    public function withMethodsDoNotMutateOriginal(): void
    {
        $original = AssignmentContext::empty();
        $original->withEnvironment('production')->withAttribute('country', 'ES');

        $this->assertNull($original->getEnvironment());
        $this->assertSame([], $original->getAttributes());
    }
}
