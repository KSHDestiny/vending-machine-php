<?php
/**
 * Revenue per day as an SVG bar chart (no JavaScript), with the same figures in a
 * visually hidden table for screen readers. Each bar has a tooltip; today is darker.
 *
 * @var callable(string|int|float|null): string $e
 * @var callable(string): string $money
 * @var list<array{date: string, count: int, revenue: string}> $days
 */
$width = 720;
$height = 240;
[$left, $right, $top, $bottom] = [56, 8, 12, 28];
$plotWidth = $width - $left - $right;
$plotHeight = $height - $top - $bottom;

// A round axis maximum (1, 2, 2.5 or 5 × a power of ten) split into 4 gridlines.
$max = max(array_map(static fn (array $day): float => (float) $day['revenue'], $days) ?: [0.0]);
$step = 2.5;
if ($max > 0) {
    $magnitude = 10 ** floor(log10($max / 4));
    foreach ([1, 2, 2.5, 5, 10] as $factor) {
        if ($factor * $magnitude * 4 >= $max) {
            $step = $factor * $magnitude;
            break;
        }
    }
}
$axisMax = $step * 4;
$axisLabel = static fn (float $value): string => '$' . ($value >= 1000 ? rtrim(rtrim(number_format($value / 1000, 1), '0'), '.') . 'K' : rtrim(rtrim(number_format($value, 2), '0'), '.'));

$slot = $plotWidth / max(1, count($days));
$barWidth = max(2.0, min(28.0, $slot * 0.6));
$labelEvery = (int) ceil(count($days) / 7);
$lastIndex = count($days) - 1;
$shortDate = static fn (string $date): string => (new DateTimeImmutable($date))->format('M j');
?>
<svg viewBox="0 0 <?= $width ?> <?= $height ?>" class="h-auto w-full" role="img" aria-labelledby="sales-chart-title">
    <title id="sales-chart-title">Revenue per day, <?= $e($shortDate($days[0]['date'] ?? 'today')) ?> to <?= $e($shortDate($days[$lastIndex]['date'] ?? 'today')) ?></title>
    <?php for ($i = 0; $i <= 4; $i++): ?>
        <?php $y = $top + $plotHeight - $plotHeight * $i / 4; ?>
        <line x1="<?= $left ?>" x2="<?= $width - $right ?>" y1="<?= $y ?>" y2="<?= $y ?>" class="stroke-slate-200" stroke-dasharray="<?= $i === 0 ? '0' : '4 4' ?>"/>
        <text x="<?= $left - 8 ?>" y="<?= $y + 4 ?>" text-anchor="end" font-size="11" class="fill-slate-400"><?= $e($axisLabel($step * $i)) ?></text>
    <?php endfor ?>
    <?php foreach ($days as $index => $day): ?>
        <?php
        $barHeight = $axisMax > 0 ? $plotHeight * (float) $day['revenue'] / $axisMax : 0;
        $x = $left + $slot * $index + ($slot - $barWidth) / 2;
        $tooltip = $shortDate($day['date']) . ': $' . $money($day['revenue']) . ' from ' . $day['count'] . ($day['count'] === 1 ? ' purchase' : ' purchases');
        ?>
        <g>
            <title><?= $e($tooltip) ?></title>
            <rect x="<?= $left + $slot * $index ?>" y="<?= $top ?>" width="<?= $slot ?>" height="<?= $plotHeight ?>" fill="transparent"/>
            <rect x="<?= round($x, 2) ?>" y="<?= round($top + $plotHeight - max($barHeight, 1.5), 2) ?>" width="<?= round($barWidth, 2) ?>" height="<?= round(max($barHeight, 1.5), 2) ?>" rx="3"
                  class="<?= $index === $lastIndex ? 'fill-indigo-600' : ($barHeight > 0 ? 'fill-indigo-300' : 'fill-slate-200') ?>"/>
        </g>
        <?php if ($index % $labelEvery === 0 || $index === $lastIndex): ?>
            <text x="<?= round($left + $slot * $index + $slot / 2, 2) ?>" y="<?= $height - 8 ?>" text-anchor="middle" font-size="11" class="fill-slate-400"><?= $e($index === $lastIndex ? 'Today' : $shortDate($day['date'])) ?></text>
        <?php endif ?>
    <?php endforeach ?>
</svg>
<table class="sr-only">
    <caption>Revenue per day</caption>
    <thead><tr><th scope="col">Date</th><th scope="col">Purchases</th><th scope="col">Revenue</th></tr></thead>
    <tbody>
    <?php foreach ($days as $day): ?>
        <tr><td><?= $e($day['date']) ?></td><td><?= $e($day['count']) ?></td><td>$<?= $e($money($day['revenue'])) ?></td></tr>
    <?php endforeach ?>
    </tbody>
</table>
