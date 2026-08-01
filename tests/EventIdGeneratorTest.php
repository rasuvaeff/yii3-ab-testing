<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\Yii3AbTesting\EventIdGenerator;
use Rasuvaeff\Yii3AbTesting\RamseyUuidEventIdGenerator;
use Rasuvaeff\Yii3AbTesting\SymfonyUidEventIdGenerator;
use Rasuvaeff\Yii3AbTesting\Tests\Support\FixedClock;
use Rasuvaeff\Yii3AbTesting\Uuid7EventIdGenerator;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(Uuid7EventIdGenerator::class)]
#[Covers(SymfonyUidEventIdGenerator::class)]
#[Covers(RamseyUuidEventIdGenerator::class)]
final class EventIdGeneratorTest
{
    private const string UUID7_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/';

    #[DataProvider('generatorProvider')]
    public function producesAWellFormedUuid7(EventIdGenerator $generator): void
    {
        Assert::true(self::isUuid7($generator->generate()));
    }

    #[DataProvider('generatorProvider')]
    public function producesDistinctIdentifiers(EventIdGenerator $generator): void
    {
        $ids = [];

        for ($i = 0; $i < 500; ++$i) {
            $ids[$generator->generate()] = true;
        }

        Assert::same(\count($ids), 500);
    }

    public static function generatorProvider(): iterable
    {
        yield 'built-in' => [new Uuid7EventIdGenerator()];
        yield 'symfony/uid' => [new SymfonyUidEventIdGenerator()];
        yield 'ramsey/uuid' => [new RamseyUuidEventIdGenerator()];
    }

    public function encodesTheClockTimestampInThePrefix(): void
    {
        $clock = new FixedClock('2026-08-01 10:00:00.123');

        $id = (new Uuid7EventIdGenerator(clock: $clock))->generate();

        $milliseconds = (int) hexdec(str_replace('-', '', substr($id, 0, 13)));
        Assert::same($milliseconds, (int) $clock->now()->format('Uv'));
    }

    public function laterTimestampsSortAfterEarlierOnes(): void
    {
        $clock = new FixedClock('2026-08-01 10:00:00.000');
        $generator = new Uuid7EventIdGenerator(clock: $clock);

        $first = $generator->generate();
        $clock->advance(1);
        $second = $generator->generate();

        Assert::true($first < $second);
    }

    /**
     * Ordering is guaranteed between milliseconds, which is what the analytics
     * sort prefix relies on; RFC 9562's intra-millisecond counter is
     * deliberately not implemented.
     */
    #[Property(runs: 200)]
    public function idsSortInTimestampOrder(int $secondsApart): void
    {
        $clock = new FixedClock('2026-08-01 10:00:00.000');
        $generator = new Uuid7EventIdGenerator(clock: $clock);

        $earlier = $generator->generate();
        $clock->advance($secondsApart);
        $later = $generator->generate();

        Assert::true($earlier < $later);
    }

    /** @return array<string, ArbitraryInterface> */
    public static function idsSortInTimestampOrderGenerators(): array
    {
        return ['secondsApart' => Gen::intBetween(1, 100_000)];
    }

    #[Property(runs: 200)]
    public function everyGeneratedIdCarriesTheVersionAndVariantBits(int $offsetSeconds): void
    {
        $clock = new FixedClock('2026-08-01 10:00:00.000');
        $clock->advance($offsetSeconds);

        $id = (new Uuid7EventIdGenerator(clock: $clock))->generate();

        Assert::true(self::isUuid7($id));
    }

    /** @return array<string, ArbitraryInterface> */
    public static function everyGeneratedIdCarriesTheVersionAndVariantBitsGenerators(): array
    {
        return ['offsetSeconds' => Gen::intBetween(0, 10_000_000)];
    }

    private static function isUuid7(string $id): bool
    {
        return preg_match(self::UUID7_PATTERN, $id) === 1;
    }
}
