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

    /**
     * The bit layout of RFC 9562 asserted directly, because a hand-rolled
     * generator fails subtly: a shifted offset or a mask that eats one bit too
     * many still produces a UUID-shaped string.
     */
    public function layoutFollowsRfc9562(): void
    {
        $clock = new FixedClock('2026-08-01 10:00:00.123');
        $samples = self::sample(new Uuid7EventIdGenerator(clock: $clock), 200);
        $expectedMilliseconds = (int) $clock->now()->format('Uv');

        foreach ($samples as $bytes) {
            Assert::same(\strlen($bytes), 16);
            Assert::same(self::bits($bytes, 0, 48), $expectedMilliseconds);
            Assert::same(self::bits($bytes, 48, 4), 7);
            Assert::same(self::bits($bytes, 64, 2), 0b10);
        }
    }

    /**
     * Every bit the spec calls random must actually vary: `rand_a` (12 bits
     * after the version) and `rand_b` (62 bits after the variant). A mask that
     * pins one of them keeps the format valid but silently drains entropy.
     */
    public function randomBitsVaryAndTimestampBitsDoNot(): void
    {
        $generator = new Uuid7EventIdGenerator(clock: new FixedClock('2026-08-01 10:00:00.123'));
        $samples = self::sample($generator, 200);

        foreach (range(52, 63) as $bit) {
            Assert::same(self::distinctValuesOfBit($samples, $bit), 2, sprintf('rand_a bit %d must vary', $bit));
        }

        foreach (range(66, 127) as $bit) {
            Assert::same(self::distinctValuesOfBit($samples, $bit), 2, sprintf('rand_b bit %d must vary', $bit));
        }

        foreach (range(0, 47) as $bit) {
            Assert::same(
                self::distinctValuesOfBit($samples, $bit),
                1,
                sprintf('timestamp bit %d must not vary under a fixed clock', $bit),
            );
        }
    }

    /**
     * The version and variant bytes must be built from *their own* byte. Taking
     * a neighbouring byte instead keeps the format valid and the entropy intact
     * — the only visible trace is that the two byte's random bits become
     * perfectly correlated, which is what this checks.
     */
    public function versionAndVariantBytesAreBuiltFromTheirOwnRandomByte(): void
    {
        $samples = self::sample(new Uuid7EventIdGenerator(clock: new FixedClock()), 200);

        // rand_a's low nibble (byte 6) against the byte that follows it.
        Assert::false(self::alwaysEqual($samples, 52, 60, 4), 'byte 6 must not mirror byte 7');
        // rand_b's first six bits (byte 8) against its neighbours.
        Assert::false(self::alwaysEqual($samples, 66, 74, 6), 'byte 8 must not mirror byte 9');
        Assert::false(self::alwaysEqual($samples, 66, 58, 6), 'byte 8 must not mirror byte 7');
    }

    /**
     * @param list<string> $samples
     */
    private static function alwaysEqual(array $samples, int $leftOffset, int $rightOffset, int $length): bool
    {
        foreach ($samples as $bytes) {
            if (self::bits($bytes, $leftOffset, $length) !== self::bits($bytes, $rightOffset, $length)) {
                return false;
            }
        }

        return true;
    }

    public function groupsAreSeparatedInTheCanonicalEightFourFourFourTwelveShape(): void
    {
        $id = (new Uuid7EventIdGenerator())->generate();

        Assert::same(\strlen($id), 36);
        Assert::same(array_keys(array_filter(str_split($id), static fn(string $c): bool => $c === '-')), [8, 13, 18, 23]);
    }

    /**
     * @return list<string> raw 16-byte forms
     */
    private static function sample(Uuid7EventIdGenerator $generator, int $count): array
    {
        $samples = [];

        for ($i = 0; $i < $count; ++$i) {
            $binary = hex2bin(str_replace('-', '', $generator->generate()));
            Assert::true($binary !== false);
            $samples[] = (string) $binary;
        }

        return $samples;
    }

    /**
     * @param list<string> $samples
     */
    private static function distinctValuesOfBit(array $samples, int $bit): int
    {
        $seen = [];

        foreach ($samples as $bytes) {
            $seen[self::bits($bytes, $bit, 1)] = true;
        }

        return \count($seen);
    }

    private static function bits(string $bytes, int $offset, int $length): int
    {
        $value = 0;

        for ($i = $offset; $i < $offset + $length; ++$i) {
            $bit = (\ord($bytes[intdiv($i, 8)]) >> (7 - $i % 8)) & 1;
            $value = ($value << 1) | $bit;
        }

        return $value;
    }

    private static function isUuid7(string $id): bool
    {
        return preg_match(self::UUID7_PATTERN, $id) === 1;
    }
}
