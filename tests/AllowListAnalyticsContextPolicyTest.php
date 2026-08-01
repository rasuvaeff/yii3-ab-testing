<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use InvalidArgumentException;
use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
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

    /**
     * The missing attribute is listed *first*, so a `continue` that became a
     * `break` would drop the attribute that follows it.
     */
    public function aMissingAttributeDoesNotStopTheOnesAfterIt(): void
    {
        $policy = new AllowListAnalyticsContextPolicy(allowedAttributes: ['plan', 'country']);
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

    /**
     * The source name is invalid while the rename target is valid, so only the
     * check on the source name can reject it.
     */
    public function rejectsAnInvalidSourceNameEvenWhenItIsRenamedToAValidOne(): void
    {
        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Invalid analytics context field "country-code"');

        new AllowListAnalyticsContextPolicy(
            allowedAttributes: ['country-code'],
            renamedAttributes: ['country-code' => 'country'],
        );
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

    /**
     * The security invariant, and the reason this class exists: whatever the
     * context contains, nothing outside the allow-list may reach storage. A
     * handful of examples cannot cover that — an attribute name is arbitrary
     * application data.
     *
     * @param list<string> $allowed
     * @param array<string, scalar> $attributes
     */
    #[Property(runs: 300)]
    public function nothingOutsideTheAllowListEverReachesStorage(array $allowed, array $attributes): void
    {
        $allowed = array_values(array_unique($allowed));
        $policy = new AllowListAnalyticsContextPolicy(allowedAttributes: $allowed);

        $context = AssignmentContext::empty();

        foreach ($attributes as $name => $value) {
            $context = $context->withAttribute($name, $value);
        }

        $result = $policy->apply($context);

        foreach (array_keys($result) as $name) {
            Assert::true(\in_array($name, $allowed, true), sprintf('Leaked attribute "%s"', $name));
            Assert::true(\array_key_exists($name, $attributes), sprintf('Invented attribute "%s"', $name));
            Assert::same($result[$name], $attributes[$name]);
        }
    }

    /** @return array<string, ArbitraryInterface> */
    public static function nothingOutsideTheAllowListEverReachesStorageGenerators(): array
    {
        $name = Gen::stringFrom('abcdef_', 1, 6);

        return [
            'allowed' => Gen::arrayOf($name, 0, 6),
            'attributes' => Gen::dictOf(
                $name,
                Gen::frequency([
                    [3, Gen::stringFrom('abcdefghij0123456789', 0, 12)],
                    [1, Gen::int()],
                    [1, Gen::bool()],
                ]),
                0,
                8,
            ),
        ];
    }
}
