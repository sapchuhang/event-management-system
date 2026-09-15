<?php
/**
 * includes/nepali_date.php
 * ────────────────────────────────────────────────────────────────────────────
 * Nepali (Bikram Sambat) date helpers for PHP.
 *
 * The nepaliDatePicker JS library stores dates in these formats:
 *   "Bhadra 04, 2081"         (Month DD, YYYY)
 *   "Sun, Bhadra 04, 2081"    (Day, Month DD, YYYY)
 *   "2081-05-04"              (YYYY-MM-DD in BS)
 *
 * Public functions:
 *   bsFormat($bsString, $format)  – format a stored BS date string for display
 *   bsToday($format)              – today's date in BS
 *   bsYear()                      – today's BS year (int)
 *   bsTimeFromTimestamp($ts)      – 12-hour time from a DB DATETIME string
 *   adToBsStr($adDateStr, $fmt)   – convert AD "YYYY-MM-DD" → BS formatted string
 * ────────────────────────────────────────────────────────────────────────────
 */

if (!defined('NEPALI_DATE_LOADED')) {
    define('NEPALI_DATE_LOADED', true);

    define('NP_MIN_BS_YEAR',  1970);
    define('NP_MAX_BS_YEAR',  2100);
    define('NP_MIN_AD_YEAR',  1913);
    define('NP_MIN_AD_MONTH', 4);
    define('NP_MIN_AD_DATE',  13);

    $GLOBALS['_np_bsMonthUpperDays'] = [
        [30,31],[31,32],[31,32],[31,32],[31,32],
        [30,31],[29,30],[29,30],[29,30],[29,30],[29,30],[30,31]
    ];

    $GLOBALS['_np_extractedBsMonthData'] = [
        [0,1,1,22,1,3,1,1,1,3,1,22,1,3,1,3,1,22,1,3,1,19,1,3,1,1,3,1,2,2,1,3,1],
        [1,2,2,2,2,2,2,1,3,1,3,1,2,2,2,3,2,2,2,1,3,1,3,1,2,2,2,2,2,2,2,2,2,2,2,1,3,1,2,2,2,2,2,2,2,2,2,2,2,1,3,1,2,2,2,2,2,1,1,1,2,2,2,2,2,1,3,1,1,2],
        [0,1,2,1,3,1,3,1,2,2,2,2,2,2,2,2,3,2,2,2,2,2,2,2,2,1,3,1,3,1,2,2,2,2,2,2,2,2,2,1,3,1,3,1,2,2,2,2,2,2,2,2,2,1,3,1,3,1,1,1,1,2,2,2,2,2,1,3,1,1,2],
        [1,2,1,3,1,3,1,3,1,3,1,3,1,3,1,3,1,3,1,3,1,3,1,3,1,3,1,3,1,2,2,2,1,3,1,3,1,3,1,3,1,3,1,2,2,2,1,3,1,3,1,3,1,3,1,3,1,3,2,2,1,3,1,2,2,2,1,2],
        [59,1,26,1,28,1,2,1,12],
        [0,1,2,2,2,2,2,2,2,2,2,2,2,1,3,1,3,1,3,1,2,2,2,2,2,2,2,2,2,2,2,1,3,1,2,2,2,2,2,2,2,2,2,2,2,1,3,1,2,2,2,2,2,2,2,2,2,2,5,1,1,2,2,1,3,1,2,1,2],
        [0,12,1,3,1,3,1,5,1,11,1,3,1,3,1,18,1,3,1,3,1,18,1,3,1,3,1,27,1,2],
        [1,2,2,2,2,1,2,2,2,2,2,2,2,3,1,3,2,2,2,2,2,2,2,2,2,1,2,2,2,2,2,2,2,2,2,2,2,2,2,1,2,2,2,2,2,2,2,2,2,2,2,2,2,1,2,2,2,15,2,4],
        [0,1,2,2,2,2,1,3,1,3,1,3,1,2,2,2,3,2,2,2,1,3,1,3,1,3,1,2,2,2,2,2,2,2,1,3,1,3,1,3,1,2,2,2,2,2,2,2,2,2,1,3,1,3,1,2,2,2,15,2,4],
        [1,1,3,1,3,1,14,1,3,1,1,1,3,1,14,1,3,1,3,1,3,1,18,1,3,1,3,1,3,1,14,1,3,15,1,2,1,1],
        [0,1,1,3,1,3,1,10,1,3,1,3,1,1,1,3,1,3,1,10,1,3,1,3,1,3,1,3,1,14,1,3,1,3,1,3,1,3,1,10,1,20,1,1,1],
        [1,2,2,1,3,1,3,1,3,1,2,2,2,2,2,3,2,2,2,2,2,1,3,1,3,1,3,1,2,2,2,2,2,2,2,1,3,1,3,1,3,1,3,1,2,2,2,2,2,2,2,1,3,1,3,1,20,3],
    ];

    $GLOBALS['_np_bsMonths'] = [
        'Baisakh','Jestha','Ashadh','Shrawan','Bhadra','Ashwin',
        'Kartik','Mangsir','Poush','Magh','Falgun','Chaitra'
    ];

    function _np_getMonthDays(int $bsMonth, int $yearDiff): int {
        $upper = $GLOBALS['_np_bsMonthUpperDays'];
        $data  = $GLOBALS['_np_extractedBsMonthData'][$bsMonth - 1];
        $yearCount = 0; $days = 0;
        if ($yearDiff === 0) return 0;
        foreach ($data as $i => $val) {
            if ($val !== 0) {
                $ui = $i % 2;
                if ($yearDiff <= $yearCount + $val) {
                    $days += $upper[$bsMonth-1][$ui] * ($yearDiff - $yearCount);
                    break;
                }
                $yearCount += $val;
                $days += $upper[$bsMonth-1][$ui] * $val;
            }
        }
        return $days;
    }

    function _np_getTotalDays(int $by, int $bm, int $bd): int {
        $days = 0; $diff = $by - NP_MIN_BS_YEAR;
        for ($m = 1; $m <= 12; $m++) {
            $days += ($m < $bm) ? _np_getMonthDays($m, $diff + 1) : _np_getMonthDays($m, $diff);
        }
        if     ($by > 2085 && $by < 2088)    $days += $bd - 2;
        elseif ($by === 2085 && $bm > 5)      $days += $bd - 2;
        elseif ($by > 2088)                   $days += $bd - 4;
        elseif ($by === 2088 && $bm > 5)      $days += $bd - 4;
        else                                  $days += $bd;
        return $days;
    }

    function _np_getBsMonthDaysCount(int $by, int $bm): int {
        $upper = $GLOBALS['_np_bsMonthUpperDays'];
        $data  = $GLOBALS['_np_extractedBsMonthData'][$bm - 1];
        $yc = 0; $total = $by + 1 - NP_MIN_BS_YEAR;
        foreach ($data as $i => $val) {
            if ($val !== 0) {
                $ui = $i % 2; $yc += $val;
                if ($total <= $yc) {
                    if (($by === 2085 && $bm === 5) || ($by === 2088 && $bm === 5))
                        return $upper[$bm-1][$ui] - 2;
                    return $upper[$bm-1][$ui];
                }
            }
        }
        return 30;
    }

    function _np_bsToAdDate(int $by, int $bm, int $bd): DateTime {
        $totalDays = _np_getTotalDays($by, $bm, $bd);
        $minAd = new DateTime(
            NP_MIN_AD_YEAR.'-'.str_pad(NP_MIN_AD_MONTH,2,'0',STR_PAD_LEFT).'-'.str_pad(NP_MIN_AD_DATE,2,'0',STR_PAD_LEFT),
            new DateTimeZone('Asia/Kathmandu')
        );
        $minAd->modify('-1 day');
        $minAd->modify("+{$totalDays} days");
        return $minAd;
    }

    function _np_adToBsArr(int $ay, int $am, int $ad): array {
        $by = $ay + 57;
        $bm = ($am + 9) % 12;
        $bd = 1;
        if ($am < 4) { $by -= 1; }
        elseif ($am === 4) {
            $firstAd = _np_bsToAdDate($by, 1, 1);
            if ($ad < (int)$firstAd->format('j')) $by -= 1;
        }
        $firstAd = _np_bsToAdDate($by, $bm, 1);
        $fj = (int)$firstAd->format('j');
        if ($ad >= 1 && $ad < $fj) {
            $bm = ($bm !== 1) ? $bm - 1 : 12;
            $mDays = _np_getBsMonthDaysCount($by, $bm);
            $bd = $mDays - ($fj - $ad) + 1;
        } else {
            $bd = $ad - $fj + 1;
        }
        return ['year' => $by, 'month' => $bm, 'day' => $bd];
    }

    function _np_renderBs(int $by, int $bm, int $bd, string $fmt): string {
        $months = $GLOBALS['_np_bsMonths'];
        $out = $fmt;
        $out = str_replace('%d', str_pad((string)$bd, 2, '0', STR_PAD_LEFT), $out);
        $out = str_replace('%m', str_pad((string)$bm, 2, '0', STR_PAD_LEFT), $out);
        $out = str_replace('%M', $months[$bm - 1],                            $out);
        $out = str_replace('%y', (string)$by,                                  $out);
        return $out;
    }

    function _np_parseBsString(string $s): ?array {
        $s = trim($s);
        if (empty($s) || $s === '—') return null;

        // Format: YYYY-MM-DD (BS ISO)
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m)) {
            // Could be BS or AD — if year > 2000 it's almost certainly BS
            if ((int)$m[1] > 2000) {
                return ['year' => (int)$m[1], 'month' => (int)$m[2], 'day' => (int)$m[3]];
            }
        }

        $months = $GLOBALS['_np_bsMonths'];
        // Strip leading weekday "Sun, " etc.
        $s = preg_replace('/^[A-Za-z]{2,3},\s*/', '', $s);

        // Format: "Bhadra 04, 2081"
        foreach ($months as $idx => $name) {
            if (stripos($s, $name) === 0) {
                if (preg_match('/^'.preg_quote($name,'/').'[\s\-]+(\d{1,2}),?\s+(\d{4})/i', $s, $m)) {
                    return ['year' => (int)$m[2], 'month' => $idx + 1, 'day' => (int)$m[1]];
                }
            }
        }

        return null;
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Format a stored BS date string for display.
     * Default output: "Bhadra 04, 2081"
     */
    function bsFormat(string $bsString, string $format = '%M %d, %y'): string {
        if (empty($bsString)) return '—';
        $p = _np_parseBsString($bsString);
        if (!$p) return htmlspecialchars($bsString);
        return _np_renderBs($p['year'], $p['month'], $p['day'], $format);
    }

    /** Today's date in BS. Default: "Bhadra 04, 2081" */
    function bsToday(string $format = '%M %d, %y'): string {
        $now = new DateTime('now', new DateTimeZone('Asia/Kathmandu'));
        $bs  = _np_adToBsArr((int)$now->format('Y'), (int)$now->format('n'), (int)$now->format('j'));
        return _np_renderBs($bs['year'], $bs['month'], $bs['day'], $format);
    }

    /** Today's BS year as integer. */
    function bsYear(): int {
        $now = new DateTime('now', new DateTimeZone('Asia/Kathmandu'));
        $bs  = _np_adToBsArr((int)$now->format('Y'), (int)$now->format('n'), (int)$now->format('j'));
        return $bs['year'];
    }

    /**
     * Format time portion of a DB DATETIME/TIMESTAMP string.
     * e.g. "2026-09-15 14:32:00" → "02:32 PM"
     */
    function bsTimeFromTimestamp(string $ts): string {
        if (empty($ts) || $ts === '0000-00-00 00:00:00') return '—';
        try {
            $dt = new DateTime($ts, new DateTimeZone('Asia/Kathmandu'));
            return $dt->format('h:i A');
        } catch (Exception $e) { return '—'; }
    }

    /**
     * Convert an AD date string "YYYY-MM-DD" → BS formatted string.
     */
    function adToBsStr(string $adDateStr, string $fmt = '%M %d, %y'): string {
        if (empty($adDateStr)) return '—';
        try {
            $dt = new DateTime($adDateStr, new DateTimeZone('Asia/Kathmandu'));
            $bs = _np_adToBsArr((int)$dt->format('Y'), (int)$dt->format('n'), (int)$dt->format('j'));
            return _np_renderBs($bs['year'], $bs['month'], $bs['day'], $fmt);
        } catch (Exception $e) { return htmlspecialchars($adDateStr); }
    }
}
