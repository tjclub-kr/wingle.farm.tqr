<?php
/**
 * calendarWide.php  (PC 등 넓은 화면용 : 월간 가로 달력 - 햄버거 메뉴 "달력 (가로 보기)" 팝업에 표시)
 *
 * - 1일부터 말일까지 한 달 전체를 7열(일~토) 달력으로 표시
 * - 상단 : ◀ 년월 ▶ 오늘 (한 달 단위 이동) / 우측 : 6개 밭 안내 / 닫기
 * - 날짜 한 칸은 calendarH.php 와 같이 가로 3 x 세로 2 = 6구역으로 나누어 밭별 정보를 표시
 * - ◀ ▶ 오늘 은 팝업 안에서만 갱신 (menu.php 의 loadWideCalendar)
 * - DB 조회(SQL)·6구역 표시·일자 클릭 팝업은 Calendar_common.php 와 공유
 */

declare(strict_types=1);
require_once __DIR__ . '/auth_check.php';   // ← 추가 (맨 위)

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/Calendar_common.php';

/* --------------------------------------------------
 * 날짜 설정 (잘못된 값은 서울 기준 현재 년/월로 초기화)
 * -------------------------------------------------- */
$calendarTimezone = new DateTimeZone('Asia/Seoul');
$now = new DateTimeImmutable('now', $calendarTimezone);

$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)$now->format('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)$now->format('n');
if ($year < 1 || $year > 9999) {
    $year = (int)$now->format('Y');
}
if ($month < 1 || $month > 12) {
    $month = (int)$now->format('n');
}

$firstDate      = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $calendarTimezone);
$daysInMonth    = (int)$firstDate->format('t');
$firstDayOfWeek = (int)$firstDate->format('w');

/* --------------------------------------------------
 * 공통 조회 (Calendar_common.php) : [1일, 다음 달 1일)
 * -------------------------------------------------- */
$calData = calendarFetchData(
    $pdo,
    $firstDate->format('Y-m-d'),
    $firstDate->modify('+1 month')->format('Y-m-d'),
    $calendarTimezone
);
$tasksData = $calData['tasks'];
$today     = $calData['today'];
$quadFarms = calendarQuadFarms($pdo);

/* --------------------------------------------------
 * 이전 달 / 다음 달 / 오늘 URL
 * -------------------------------------------------- */
$monthUrl = function (DateTimeImmutable $d): string {
    return 'calendarWide.php?year=' . $d->format('Y') . '&month=' . $d->format('n');
};
$prevPageUrl  = $monthUrl($firstDate->modify('-1 month'));
$nextPageUrl  = $monthUrl($firstDate->modify('+1 month'));
$todayPageUrl = $monthUrl($now);

calendarRenderQuadStyle();
?>

<style>
    /* 좁은 화면(폰)에서는 팝업 안에서 좌우로 스크롤 */
    .calw-wrap { width: 100%; height: 100%; min-width: 1000px; min-height: 480px; background: #fff; display: flex; flex-direction: column; }
    .calw-header { flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 6px 12px; border-bottom: 1px solid #e5e7eb; }
    .calw-left { flex-shrink: 0; display: flex; align-items: center; gap: 6px; }
    .calw-title { margin: 0 6px; font-size: 18px; font-weight: 700; color: #222; white-space: nowrap; }
    .calw-btn { min-width: 40px; height: 30px; padding: 0 12px; border: 1px solid #d1d5db; border-radius: 6px; background: #fff; color: #333; cursor: pointer; font-size: 14px; display: inline-flex; align-items: center; justify-content: center; transition: 0.15s; }
    .calw-btn:hover { background: #f3f4f6; }
    .calw-btn.today-btn { min-width: 56px; }
    .calw-wrap .calendarh-legend { font-size: 13px; }

    .calw-week { flex-shrink: 0; display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); background: #fafafa; border-bottom: 1px solid #e5e7eb; }
    .calw-weekday { padding: 5px 2px; text-align: center; font-weight: 700; font-size: 13px; color: #555; border-right: 1px solid #eee; }
    .calw-weekday:last-child { border-right: none; }
    .calw-weekday.sun { color: #d32f2f; }
    .calw-weekday.sat { color: #1976d2; }

    /* 남는 높이를 주(週) 수만큼 균등 분배 (최소 96px) */
    .calw-grid { flex: 1; min-height: 0; display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); grid-auto-rows: minmax(96px, 1fr); }
    .calw-grid .calh-cell:nth-child(7n) { border-right: none; }
    .calw-grid .calh-cell.empty { background: #fafafa; }
    .calw-wrap .calh-date { font-size: 14px; height: 24px; min-width: 24px; border-radius: 12px; }
    .calw-wrap .calh-count { font-size: 12px; }
    .calw-wrap .calh-quad { font-size: 12px; }
</style>

<div class="calw-wrap">

    <div class="calw-header">
        <!-- 좌측 : ◀ 년월 ▶ 오늘 (한 달 단위 이동) -->
        <div class="calw-left">
            <button type="button" class="calw-btn" onclick="loadWideCalendar('<?php echo $prevPageUrl; ?>')" title="이전 달">◀</button>
            <h1 class="calw-title"><?php echo $year; ?>년 <?php echo $month; ?>월</h1>
            <button type="button" class="calw-btn" onclick="loadWideCalendar('<?php echo $nextPageUrl; ?>')" title="다음 달">▶</button>
            <button type="button" class="calw-btn today-btn" onclick="loadWideCalendar('<?php echo $todayPageUrl; ?>')">오늘</button>
        </div>

        <!-- 우측 : 밭 이름 안내 (↖ ↑ ↗ ↙ ↓ ↘ 구역별 밭) / 닫기 -->
        <div class="calendarh-legend"><?php calendarRenderLegend($quadFarms); ?></div>
        <button type="button" class="calw-btn" onclick="closeWideCalendar()" title="닫기">✖</button>
    </div>

    <div class="calw-week">
        <div class="calw-weekday sun">일</div>
        <div class="calw-weekday">월</div>
        <div class="calw-weekday">화</div>
        <div class="calw-weekday">수</div>
        <div class="calw-weekday">목</div>
        <div class="calw-weekday">금</div>
        <div class="calw-weekday sat">토</div>
    </div>

    <div class="calw-grid">
        <?php
        // 1일 앞의 빈 칸
        echo str_repeat('<div class="calh-cell empty"></div>', $firstDayOfWeek);

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateString = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $dayOfWeek  = ($firstDayOfWeek + $day - 1) % 7;

            $cellClass = 'calh-cell' . ($dateString === $today ? ' today' : '');
            $dateClass = 'calh-date' . ($dayOfWeek === 0 ? ' sun' : ($dayOfWeek === 6 ? ' sat' : ''));
            $dailyTaskCount = $tasksData[$dateString]['total'] ?? 0;
            ?>
            <div class="<?php echo $cellClass; ?>" data-date="<?php echo $dateString; ?>">
                <a href="javascript:void(0);" class="calh-link" onclick="selectCalendarDate('<?php echo $dateString; ?>');">
                    <div class="calh-head">
                        <span class="<?php echo $dateClass; ?>"><?php echo $day; ?></span>
                        <?php if ($dailyTaskCount > 0): ?>
                            <span class="calh-count">(<?php echo $dailyTaskCount; ?>)</span>
                        <?php endif; ?>
                    </div>
                    <?php calendarRenderQuads($quadFarms, $dateString, $calData); ?>
                </a>
            </div>
            <?php
        }

        // 마지막 주의 빈 칸
        echo str_repeat('<div class="calh-cell empty"></div>', (7 - ($firstDayOfWeek + $daysInMonth) % 7) % 7);
        ?>
    </div>

</div>

<?php
// 일자 클릭 팝업 (HTML/CSS/JS) - calendar.php / calendarH.php 와 공유
calendarRenderPopup($calData);
