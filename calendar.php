<?php
/**
 * calendar.php  (세로 화면 : 월간 달력)
 *
 * - 1일부터 해당 월의 마지막 날짜까지 표시
 * - 이전 달 / 다음 달 / 오늘 이동 (AJAX 연동 : loadPage)
 * - DB 조회(SQL)와 일자 클릭 팝업은 Calendar_common.php 와 공유
 * - 가로 화면은 calendarH.php (menu.php 의 loadPage 가 화면 방향에 따라 자동 전환)
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
 * 공통 SQL 조회 (Calendar_common.php) : [조회 시작일, 다음 달 1일)
 * -------------------------------------------------- */
$calData = calendarFetchData(
    $pdo,
    $firstDate->format('Y-m-d'),
    $firstDate->modify('+1 month')->format('Y-m-d'),
    $calendarTimezone
);
$eventsData  = $calData['events'];
$tasksData   = $calData['tasks'];
$harvestData = $calData['harvest'];
$taskIcons   = $calData['icons'];
$today       = $calData['today'];

/* --------------------------------------------------
 * 이전 달 / 다음 달 / 오늘 URL
 * -------------------------------------------------- */
$monthUrl = function (DateTimeImmutable $d): string {
    return 'calendar.php?year=' . $d->format('Y') . '&month=' . $d->format('n');
};
$prevPageUrl  = $monthUrl($firstDate->modify('-1 month'));
$nextPageUrl  = $monthUrl($firstDate->modify('+1 month'));
$todayPageUrl = $monthUrl($now);
?>

<style>
    .calendar-wrap { width: 100%; height: 100%; display: flex; flex-direction: column; background: #fff; border-radius: 8px; overflow: hidden; }
    .calendar-header { flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 10px; border-bottom: 1px solid #e5e7eb; }
    .calendar-title { margin: 0; font-size: 16px; font-weight: 700; color: #222; text-align: center; flex: 1; }
    .calendar-nav { display: flex; align-items: center; gap: 4px; }
    .calendar-btn { min-width: 36px; height: 28px; padding: 0 8px; border: 1px solid #d1d5db; border-radius: 6px; background: #fff; color: #333; cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; justify-content: center; transition: 0.15s; }
    .calendar-btn:hover { background: #f3f4f6; }
    .calendar-btn.today-btn { min-width: 52px; }

    .calendar-week { flex-shrink: 0; display: grid; grid-template-columns: repeat(7, 1fr); background: #fafafa; border-bottom: 1px solid #e5e7eb; }
    .weekday { padding: 6px 2px; text-align: center; font-weight: 700; font-size: 12px; color: #555; border-right: 1px solid #eee; }
    .weekday:last-child { border-right: none; }
    .sun { color: #d32f2f; }
    .sat { color: #1976d2; }

    /* 남는 높이를 주(週) 수만큼 균등 분배 (최소 72px, 모자라면 content 영역이 스크롤) */
    .calendar-grid { flex: 1 1 auto; display: grid; grid-template-columns: repeat(7, 1fr); grid-auto-rows: minmax(72px, 1fr); }
    .calendar-cell { min-height: 0; overflow: hidden; border-right: 1px solid #eee; border-bottom: 1px solid #eee; padding: 4px 2px; background: #fff; display: flex; flex-direction: column; }
    .calendar-cell:nth-child(7n) { border-right: none; }
    .calendar-cell.empty { background: #fafafa; }
    .calendar-cell.today { background: #fffde7; }
    .calendar-cell.harvestable { background: #e8f5e9; }   /* 수확 가능한 날짜 : 연한 녹색 (오늘과 겹치면 녹색 우선) */
    .calendar-cell.today .date-number { background: #1976d2; color: #fff; }

    .date-link { display: flex; flex-direction: column; width: 100%; height: 100%; min-height: 0; color: inherit; text-decoration: none; cursor: pointer; }
    .date-header { display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 0 2px; margin-bottom: 2px; }
    .date-number { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; font-size: 12px; font-weight: 600; color: #333; }
    .date-number.sun { color: #d32f2f; }
    .date-number.sat { color: #1976d2; }
    .date-count { font-size: 10px; color: #1976d2; font-weight: bold; }

    .date-content { flex: 1; min-height: 0; width: 100%; font-size: 10px; line-height: 1.4; color: #555; overflow-y: auto; overflow-x: hidden; }
    .date-content::-webkit-scrollbar { width: 3px; }
    .date-content::-webkit-scrollbar-thumb { background: #ccc; border-radius: 3px; }
    .date-item { display: block; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; letter-spacing: -0.5px; padding: 1px 2px; }
    .date-item.harvest-item, .date-item.harvest-item b { font-weight: 900 !important; color: #2e7d32; }
</style>

<div class="calendar-wrap">

    <div class="calendar-header">
        <div class="calendar-nav">
            <button type="button" class="calendar-btn" onclick="loadPage('<?php echo $prevPageUrl; ?>', '달력')" title="이전 달">◀</button>
        </div>

        <h1 class="calendar-title"><?php echo $year; ?>년 <?php echo $month; ?>월</h1>

        <div class="calendar-nav">
            <button type="button" class="calendar-btn" onclick="loadPage('<?php echo $nextPageUrl; ?>', '달력')" title="다음 달">▶</button>
            <button type="button" class="calendar-btn today-btn" onclick="loadPage('<?php echo $todayPageUrl; ?>', '달력')">오늘</button>
        </div>
    </div>

    <div class="calendar-week">
        <div class="weekday sun">일</div>
        <div class="weekday">월</div>
        <div class="weekday">화</div>
        <div class="weekday">수</div>
        <div class="weekday">목</div>
        <div class="weekday">금</div>
        <div class="weekday sat">토</div>
    </div>

    <div class="calendar-grid">
        <?php
        // 1일 앞의 빈 칸
        echo str_repeat('<div class="calendar-cell empty"></div>', $firstDayOfWeek);

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateString = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $dayOfWeek  = ($firstDayOfWeek + $day - 1) % 7;

            $dailyHarvest   = $harvestData[$dateString] ?? [];
            $dailyEvents    = $eventsData[$dateString] ?? [];
            $dailyTaskCount = $tasksData[$dateString]['total'] ?? 0;

            $cellClass = 'calendar-cell';
            if ($dateString === $today) {
                $cellClass .= ' today';
            }
            if ($dailyHarvest) {
                $cellClass .= ' harvestable';
            }

            $numberClass = 'date-number';
            if ($dayOfWeek === 0) {
                $numberClass .= ' sun';
            } elseif ($dayOfWeek === 6) {
                $numberClass .= ' sat';
            }
            ?>
            <div class="<?php echo $cellClass; ?>" data-date="<?php echo $dateString; ?>">
                <a href="javascript:void(0);" class="date-link" onclick="selectCalendarDate('<?php echo $dateString; ?>');">
                    <div class="date-header">
                        <span class="<?php echo $numberClass; ?>"><?php echo $day; ?></span>
                        <?php if ($dailyTaskCount > 0): ?>
                            <span class="date-count">(<?php echo $dailyTaskCount; ?>)</span>
                        <?php endif; ?>
                    </div>

                    <div class="date-content">
                        <?php
                        // 수확 가능한 구역 : 예) 겨울🧺A,C
                        foreach ($dailyHarvest as $hv) {
                            $text = calendarEsc($hv['farm_name']) . '🧺' . implode(',', $hv['zones']);
                            echo '<div class="date-item harvest-item" title="수확 가능 ' . $text . '"><b>' . $text . '</b></div>';
                        }

                        // 밭 작업 : 예) 겨울💧5
                        foreach ($dailyEvents as $event) {
                            $icon = $taskIcons[(int)$event['task_id']] ?? '📌';
                            $text = calendarEsc($event['farm_name']) . $icon . (int)$event['y_count'];
                            echo '<div class="date-item" title="' . $text . '">' . $text . '</div>';
                        }
                        ?>
                    </div>
                </a>
            </div>
            <?php
        }

        // 마지막 주의 빈 칸
        echo str_repeat('<div class="calendar-cell empty"></div>', (7 - ($firstDayOfWeek + $daysInMonth) % 7) % 7);
        ?>
    </div>

</div>

<?php
// 일자 클릭 팝업 (HTML/CSS/JS) - calendarH.php 와 공유
calendarRenderPopup($calData);
