<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use InvalidArgumentException;
use Rasuvaeff\Yii3AbTesting\AllowListAnalyticsContextPolicy;
use Rasuvaeff\Yii3AbTesting\AssignmentContext;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(AllowListAnalyticsContextPolicy::class)]
final class AllowListAnalyticsContextPolicyTest
{
    public function dropsEverythingByDefault(): void
    {
        $context = AssignmentContext::forEnvironment('prod')->withAttribute('country', 'RU');

        Assert::same((new AllowListAnalyticsContextPolicy())->apply($context), []);
    }

    public function keepsOnlyAllowedAttributes(): void
    {
        $policy = new AllowListAnalyticsContextPolicy(allowedAttributes: ['country']);
        $context = AssignmentContext::empty()
            ->withAttribute('country', 'RU')
            ->withAttribute('email', 'user@example.com');

        Assert::same($policy->apply($context), ['country' => 'RU']);
    }

    public function missingAttributesAreOmittedRatherThanNulled(): void
    {
        $policy = new AllowListAnalyticsContextPolicy(allowedAttributes: ['country', 'plan']);
        $context = AssignmentContext::empty()->withAttribute('country', 'RU');

        Assert::same($policy->apply($context), ['country' => 'RU']);
    }

    public function nullContextYieldsNoDimensions(): void
    {
        $policy = new AllowListAnalyticsContextPolicy(allowedAttributes: ['country']);

        Assert::same($policy->apply(null), []);
    }

    public function renamesAllowedAttributes(): void
    {
        $policy = new AllowListAnalyticsContextPolicy(
            allowedAttributes: ['country'],
            renamedAttributes: ['country' => 'geo'],
        );
        $context = AssignmentContext::empty()->withAttribute('country', 'RU');

        Assert::same($policy->apply($context), ['geo' => 'RU']);
    }

    public function redactsSelectedValuesButKeepsTheColumn(): void
    {
        $policy = new AllowListAnalyticsContextPolicy(
            allowedAttributes: ['country', 'user'],
            redactedAttributes: ['user'],
        );
        $context = AssignmentContext::empty()
            ->withAttribute('country', 'RU')
            ->withAttribute('user', 'alice');

        Assert::same($policy->apply($context), [
            'country' => 'RU',
            'user' => AllowListAnalyticsContextPolicy::REDACTED,
        ]);
    }

    public function preservesNonStringValues(): void
    {
        $policy = new AllowListAnalyticsContextPolicy(allowedAttributes: ['age', 'beta']);
        $context = AssignmentContext::empty()
            ->withAttribute('age', 42)
            ->withAttribute('beta', true);

        Assert::same($policy->apply($context), ['age' => 42, 'beta' => true]);
    }

    public function rejectsTwoAttributesMappedToOneField(): void
    {
        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Duplicate analytics context field "geo"');

        new AllowListAnalyticsContextPolicy(
            allowedAttributes: ['country', 'region'],
            renamedAttributes: ['country' => 'geo', 'region' => 'geo'],
        );
    }

    public function rejectsRenamingAnAttributeThatIsNotAllowed(): void
    {
        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Renamed attribute "country" must be allow-listed');

        new AllowListAnalyticsContextPolicy(renamedAttributes: ['country' => 'geo']);
    }

    public function rejectsRedactingAnAttributeThatIsNotAllowed(): void
    {
        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Redacted attribute "user" must be allow-listed');

        new AllowListAnalyticsContextPolicy(redactedAttributes: ['user']);
    }

    #[DataProvider('invalidIdentifierProvider')]
    public function rejectsAnIdentifierThatIsNotAColumnName(string $identifier): void
    {
        Expect::exception(InvalidArgumentException::class)
            ->withMessage(sprintf('Invalid analytics context field "%s"', $identifier));

        new AllowListAnalyticsContextPolicy(allowedAttributes: [$identifier]);
    }

    public static function invalidIdentifierProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'leading digit' => ['1country'];
        yield 'dash' => ['country-code'];
        yield 'dot' => ['geo.country'];
        yield 'space' => ['country code'];
        yield 'trailing newline' => ["country\n"];
    }

    public function rejectsARenameTargetThatIsNotAColumnName(): void
    {
        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Invalid analytics context field "geo-code"');

        new AllowListAnalyticsContextPolicy(
            allowedAttributes: ['country'],
            renamedAttributes: ['country' => 'geo-code'],
        );
    }
}
