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

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/Calendar_common.php';

/* --------------------------------------------------
 * 날짜 설정
 * -------------------------------------------------- */
$calendarTimezone = new DateTimeZone('Asia/Seoul');

$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

// 잘못된 날짜가 들어오는 경우 현재 날짜로 초기화
if ($year < 1) {
    $year = (int)date('Y');
}
if ($month < 1 || $month > 12) {
    $month = (int)date('n');
}

// 조회 대상 월의 시작일과 다음 달 시작일
$startDate = sprintf('%04d-%02d-01', $year, $month);
$endDate   = date('Y-m-d', strtotime("$startDate +1 month"));

/* --------------------------------------------------
 * 공통 SQL 조회 (Calendar_common.php)
 * -------------------------------------------------- */
$calData     = calendarFetchData($pdo, $startDate, $endDate, $calendarTimezone);
$eventsData  = $calData['events'];
$tasksData   = $calData['tasks'];
$harvestData = $calData['harvest'];
$taskIcons   = $calData['icons'];
$today       = $calData['today'];

/* --------------------------------------------------
 * 해당 월 정보 계산
 * -------------------------------------------------- */
$firstDate = new DateTime($startDate, $calendarTimezone);
$lastDate  = clone $firstDate;
$lastDate->modify('last day of this month');

$daysInMonth    = (int)$lastDate->format('j');
$firstDayOfWeek = (int)$firstDate->format('w');

/* --------------------------------------------------
 * 이전 달 / 다음 달 / 오늘 URL
 * -------------------------------------------------- */
$prevDate = (clone $firstDate)->modify('-1 month');
$nextDate = (clone $firstDate)->modify('+1 month');

$monthNames = [
    1 => '1월', 2 => '2월', 3 => '3월', 4 => '4월', 5 => '5월', 6 => '6월',
    7 => '7월', 8 => '8월', 9 => '9월', 10 => '10월', 11 => '11월', 12 => '12월',
];
$monthName = $monthNames[$month];

$prevPageUrl  = 'calendar.php?year=' . $prevDate->format('Y') . '&month=' . $prevDate->format('n');
$nextPageUrl  = 'calendar.php?year=' . $nextDate->format('Y') . '&month=' . $nextDate->format('n');
$todayPageUrl = 'calendar.php?year=' . date('Y') . '&month=' . date('n');
?>

<style>
    .calendar-wrap { width: 100%; background: #fff; border-radius: 8px; overflow: hidden; }
    .calendar-header { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 10px; border-bottom: 1px solid #e5e7eb; }
    .calendar-title { margin: 0; font-size: 16px; font-weight: 700; color: #222; text-align: center; flex: 1; }
    .calendar-nav { display: flex; align-items: center; gap: 4px; }
    .calendar-btn { min-width: 36px; height: 28px; padding: 0 8px; border: 1px solid #d1d5db; border-radius: 6px; background: #fff; color: #333; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: 0.15s; }
    .calendar-btn:hover { background: #f3f4f6; }
    .calendar-btn:disabled { opacity: 0.3; cursor: default; background: #fff; }
    .calendar-btn.today-btn { min-width: 52px; }
    .calendar-week { display: grid; grid-template-columns: repeat(7, 1fr); background: #fafafa; border-bottom: 1px solid #e5e7eb; }
    .weekday { padding: 6px 2px; text-align: center; font-weight: 700; font-size: 12px; color: #555; border-right: 1px solid #eee; }
    .weekday:last-child { border-right: none; }
    .weekday.sun { color: #d32f2f; }
    .weekday.sat { color: #1976d2; }
    .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); }
    .calendar-cell { min-height: 110px; border-right: 1px solid #eee; border-bottom: 1px solid #eee; padding: 4px 2px; position: relative; background: #fff; display: flex; flex-direction: column; }
    .calendar-cell:nth-child(7n) { border-right: none; }
    .calendar-cell.empty { background: #fafafa; }
    .date-number { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; font-size: 12px; font-weight: 600; color: #333; }
    .date-number.sun { color: #d32f2f; }
    .date-number.sat { color: #1976d2; }
    .date-header { display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 0 2px; margin-bottom: 2px; }
    .date-count { font-size: 10px; color: #1976d2; font-weight: bold; }
    .calendar-cell.today { background: #fffde7; }
    .calendar-cell.today .date-number { background: #1976d2; color: #fff; }
    .calendar-cell.harvestable { background: #e8f5e9; }   /* 수확 가능한 날짜 : 연한 녹색 (오늘과 겹치면 녹색 우선) */
    .date-item.harvest-item { font-weight: 700; color: #2e7d32; }
    .date-content { flex: 1; font-size: 10px; line-height: 1.4; color: #555; width: 100%; max-height: 84px; overflow-y: auto; overflow-x: hidden; }
    .date-content::-webkit-scrollbar { width: 3px; }
    .date-content::-webkit-scrollbar-track { background: transparent; }
    .date-content::-webkit-scrollbar-thumb { background: #ccc; border-radius: 3px; }
    .date-content::-webkit-scrollbar-thumb:hover { background: #aaa; }
    .date-item { display: block; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; letter-spacing: -0.5px; padding: 1px 2px; }
    .date-link { display: flex; flex-direction: column; width: 100%; height: 100%; color: inherit; text-decoration: none; cursor: pointer; }
</style>

<div class="calendar-wrap">

    <div class="calendar-header">
        <div class="calendar-nav">
            <button type="button" class="calendar-btn" onclick="loadPage('<?php echo $prevPageUrl; ?>', '달력')" title="이전 달">◀</button>
        </div>

        <h1 class="calendar-title">
            <?php echo htmlspecialchars($year . '년 ' . $monthName, ENT_QUOTES, 'UTF-8'); ?>
        </h1>

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
        for ($i = 0; $i < $firstDayOfWeek; $i++) {
            echo '<div class="calendar-cell empty"></div>';
        }

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateString = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $dayOfWeek  = (int)date('w', strtotime($dateString));
            $isToday    = ($dateString === $today);

            $cellClass = 'calendar-cell';
            if ($isToday) {
                $cellClass .= ' today';
            }
            $dailyHarvest = $harvestData[$dateString] ?? [];
            if (!empty($dailyHarvest)) {
                $cellClass .= ' harvestable';   // 수확 가능한 날짜 : 바탕색 연한 녹색
            }

            $numberClass = 'date-number';
            if ($dayOfWeek === 0) {
                $numberClass .= ' sun';
            } elseif ($dayOfWeek === 6) {
                $numberClass .= ' sat';
            }

            $dailyEvents    = $eventsData[$dateString] ?? [];
            $dailyTaskCount = isset($tasksData[$dateString]) ? $tasksData[$dateString]['total'] : 0;
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
                        // 수확 가능한 구역 표시 : 예) 겨울🧺A,C
                        foreach ($dailyHarvest as $hv) {
                            $hvText = htmlspecialchars($hv['farm_name'], ENT_QUOTES, 'UTF-8') . '🧺' . implode(',', $hv['zones']);
                            echo '<div class="date-item harvest-item" title="수확 가능 ' . $hvText . '">' . $hvText . '</div>';
                        }

                        foreach ($dailyEvents as $event) {
                            $farmName = htmlspecialchars($event['farm_name'], ENT_QUOTES, 'UTF-8');
                            $taskId   = (int)$event['task_id'];
                            $yCount   = (int)$event['y_count'];
                            $icon     = $taskIcons[$taskId] ?? '📌';

                            $displayText = $farmName . $icon . $yCount;
                            echo '<div class="date-item" title="' . $displayText . '">' . $displayText . '</div>';
                        }
                        ?>
                    </div>
                </a>
            </div>

            <?php
        }

        $totalCells     = $firstDayOfWeek + $daysInMonth;
        $remainingCells = (7 - ($totalCells % 7)) % 7;

        for ($i = 0; $i < $remainingCells; $i++) {
            echo '<div class="calendar-cell empty"></div>';
        }
        ?>
    </div>

</div>

<?php
// 일자 클릭 팝업 (HTML/CSS/JS) - calendarH.php 와 공유
calendarRenderPopup($calData);
