<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\Yii3AbTesting\AssignmentContext;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(AssignmentContext::class)]
final class AssignmentContextTest
{
    public function emptyHasNoEnvironmentAndNoAttributes(): void
    {
        $context = AssignmentContext::empty();

        Assert::null($context->getEnvironment());
        Assert::same($context->getAttributes(), []);
    }

    public function forEnvironmentSetsEnvironment(): void
    {
        $context = AssignmentContext::forEnvironment('production');

        Assert::same($context->getEnvironment(), 'production');
    }

    public function constructorStoresEnvironmentAndAttributes(): void
    {
        $context = new AssignmentContext(environment: 'staging', attributes: ['country' => 'DE']);

        Assert::same($context->getEnvironment(), 'staging');
        Assert::same($context->getAttributes(), ['country' => 'DE']);
    }

    public function getAttributeReturnsValueWhenPresent(): void
    {
        $context = new AssignmentContext(attributes: ['plan' => 'pro']);

        Assert::same($context->getAttribute('plan'), 'pro');
    }

    public function getAttributeReturnsNullWhenAbsent(): void
    {
        $context = AssignmentContext::empty();

        Assert::null($context->getAttribute('missing'));
    }

    public function withEnvironmentReturnsCopyWithEnvironmentAndKeepsAttributes(): void
    {
        $context = (new AssignmentContext(attributes: ['country' => 'FR']))->withEnvironment('production');

        Assert::same($context->getEnvironment(), 'production');
        Assert::same($context->getAttributes(), ['country' => 'FR']);
    }

    public function withAttributeAddsAttributeAndKeepsEnvironment(): void
    {
        $context = AssignmentContext::forEnvironment('production')->withAttribute('country', 'IT');

        Assert::same($context->getEnvironment(), 'production');
        Assert::same($context->getAttribute('country'), 'IT');
    }

    public function withMethodsDoNotMutateOriginal(): void
    {
        $original = AssignmentContext::empty();
        $original->withEnvironment('production')->withAttribute('country', 'ES');

        Assert::null($original->getEnvironment());
        Assert::same($original->getAttributes(), []);
    }
}
