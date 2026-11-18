<?php
/**
 * calendarH.php  (가로 화면 : 10일간 달력)
 *
 * - 기준일(base, 기본값 = 오늘) 기준으로 이전 5일 ~ 이후 4일, 총 10일을 표시 (첫 줄 5일, 둘째 줄 5일)
 *     예) 오늘이 11/16 이면 11/11 ~ 11/20  (첫 줄 11/11~11/15, 둘째 줄 11/16(오늘)~11/20)
 * - ◀ : 10일 이전,  ▶ : 10일 이후,  오늘 : 기준일을 오늘로 되돌림
 * - 날짜 한 칸을 가로 3 x 세로 2 = 6구역으로 나누고, 사용 중(use_yn = 'Y')인 밭 중
 *   정렬순서(sort_order) 앞의 최대 6개 밭 정보를 좌상 → 중상 → 우상 → 좌하 → 중하 → 우하 순서로 표시
 * - DB 조회(SQL)와 일자 클릭 팝업은 Calendar_common.php 와 공유
 * - 세로 화면은 calendar.php (menu.php 의 loadPage 가 화면 방향에 따라 자동 전환)
 *
 * 한 줄에 표시할 칸 수는 $colsPerRow 로 조정합니다.
 *   5  : 5칸 x 2줄 (기본)
 *   10 : 10칸 x 1줄
 */

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/Calendar_common.php';

const CAL_H_DAYS_BEFORE = 5;   // 기준일 이전 일수
const CAL_H_DAYS_AFTER  = 4;   // 기준일 이후 일수
const CAL_H_SHIFT_DAYS  = 10;  // ◀ ▶ 이동 일수 (표시 일수와 같게)
$colsPerRow = 5;               // 한 줄에 표시할 칸 수

$calendarTimezone = new DateTimeZone('Asia/Seoul');
$todayDt  = new DateTimeImmutable('today', $calendarTimezone);
$todayYmd = $todayDt->format('Y-m-d');

/* --------------------------------------------------
 * 기준일(base) : 잘못된 값이면 오늘로 초기화
 * -------------------------------------------------- */
$baseDt = $todayDt;
if (isset($_GET['base']) && is_string($_GET['base'])) {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $_GET['base'], $calendarTimezone);
    $errs   = DateTimeImmutable::getLastErrors();
    if ($parsed instanceof DateTimeImmutable
        && ($errs === false || ($errs['warning_count'] === 0 && $errs['error_count'] === 0))) {
        $baseDt = $parsed;
    }
}

$totalDays = CAL_H_DAYS_BEFORE + 1 + CAL_H_DAYS_AFTER;   // 5 + 1 + 4 = 10
$rows      = (int)ceil($totalDays / $colsPerRow);

$startDt = $baseDt->modify('-' . CAL_H_DAYS_BEFORE . ' days');
$endDt   = $startDt->modify('+' . $totalDays . ' days');  // 종료일(포함하지 않음)
$lastDt  = $endDt->modify('-1 day');

/* --------------------------------------------------
 * 공통 SQL 조회 (Calendar_common.php)
 * -------------------------------------------------- */
$calData     = calendarFetchData($pdo, $startDt->format('Y-m-d'), $endDt->format('Y-m-d'), $calendarTimezone);
$eventsData  = $calData['events'];
$tasksData   = $calData['tasks'];
$harvestData = $calData['harvest'];
$taskIcons   = $calData['icons'];

/* --------------------------------------------------
 * 날짜 칸 6구역(좌상, 중상, 우상, 좌하, 중하, 우하)에 표시할 밭
 *  - 사용 중(use_yn = 'Y')인 밭을 정렬순서(sort_order) 순으로 최대 6개 (6개를 넘는 밭은 표시하지 않음)
 * -------------------------------------------------- */
$quadFarms = [];
try {
    $quadStmt = $pdo->prepare("
        SELECT farm_id, farm_name
        FROM farms
        WHERE use_yn = 'Y'
        ORDER BY sort_order ASC, farm_id ASC
        LIMIT 6
    ");
    $quadStmt->execute();
    $quadFarms = $quadStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("DB 오류 (farms): " . $e->getMessage());
}
$quadMarks = ['↖', '↑', '↗', '↙', '↓', '↘'];   // 좌상, 중상, 우상, 좌하, 중하, 우하

/* --------------------------------------------------
 * 제목 / 이동 URL
 * -------------------------------------------------- */
$weekdays = ['일', '월', '화', '수', '목', '금', '토'];

$title = $startDt->format('Y') !== $lastDt->format('Y')
    ? $startDt->format('Y년 n월 j일') . ' ~ ' . $lastDt->format('Y년 n월 j일')
    : $startDt->format('Y년 n월 j일') . ' ~ ' . $lastDt->format('n월 j일');

$prevPageUrl  = 'calendarH.php?base=' . $baseDt->modify('-' . CAL_H_SHIFT_DAYS . ' days')->format('Y-m-d');
$nextPageUrl  = 'calendarH.php?base=' . $baseDt->modify('+' . CAL_H_SHIFT_DAYS . ' days')->format('Y-m-d');
$todayPageUrl = 'calendarH.php';
?>

<style>
    /* 가로 화면에서는 컨텐츠 영역 여백을 줄여 화면을 최대한 사용 (:has 미지원 브라우저는 기본 여백 유지) */
    main#content-area:has(.calendarh-wrap) { padding: 4px 6px; }

    .calendarh-wrap { width: 100%; height: 100%; min-height: 220px; background: #fff; border-radius: 8px; overflow: hidden; display: flex; flex-direction: column; }
    /* 헤더 : [◀ 조회기준 년월일 ▶ 오늘] 은 좌측 정렬, [밭 이름 안내] 는 우측 정렬 */
    .calendarh-header { flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 4px 10px; border-bottom: 1px solid #e5e7eb; }
    .calendarh-left { flex-shrink: 0; display: flex; align-items: center; gap: 6px; }
    .calendarh-title { margin: 0; font-size: 15px; font-weight: 700; color: #222; text-align: center; white-space: nowrap; }
    .calendarh-btn { min-width: 40px; height: 28px; padding: 0 10px; border: 1px solid #d1d5db; border-radius: 6px; background: #fff; color: #333; cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; justify-content: center; transition: 0.15s; }
    .calendarh-btn:hover { background: #f3f4f6; }
    .calendarh-btn.today-btn { min-width: 52px; }

    .calendarh-grid { flex: 1; min-height: 0; display: grid; grid-template-columns: repeat(<?php echo $colsPerRow; ?>, minmax(0, 1fr)); grid-template-rows: repeat(<?php echo $rows; ?>, minmax(84px, 1fr)); }
    .calh-cell { min-height: 0; min-width: 0; border-right: 1px solid #eee; border-bottom: 1px solid #eee; padding: 3px 3px; background: #fff; display: flex; flex-direction: column; overflow: hidden; }
    .calh-cell:nth-child(<?php echo $colsPerRow; ?>n) { border-right: none; }
    .calh-cell.today { background: #fffde7; }
    .calh-link { display: flex; flex-direction: column; width: 100%; height: 100%; min-height: 0; color: inherit; text-decoration: none; cursor: pointer; }
    .calh-head { flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; padding: 0 1px; margin-bottom: 2px; }
    .calh-date { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 5px; border-radius: 11px; font-size: 12px; font-weight: 700; color: #333; white-space: nowrap; }
    .calh-date .dow { font-size: 10px; font-weight: 600; margin-left: 2px; }
    .calh-date.sun { color: #d32f2f; }
    .calh-date.sat { color: #1976d2; }
    .calh-cell.today .calh-date { background: #1976d2; color: #fff; }
    .calh-count { font-size: 10px; color: #1976d2; font-weight: bold; }
    /* 날짜 칸 6구역 (가로 3 x 세로 2) : 좌상(1번 밭) 중상(2번 밭) 우상(3번 밭) / 좌하(4번 밭) 중하(5번 밭) 우하(6번 밭) - 점선 없음 */
    .calh-quads { flex: 1; min-height: 0; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); grid-template-rows: minmax(0, 1fr) minmax(0, 1fr); gap: 1px; }
    .calh-quad { min-width: 0; min-height: 0; overflow-y: auto; overflow-x: hidden; display: flex; flex-wrap: wrap; align-content: flex-start; gap: 0 4px; padding: 1px 2px; font-size: 10px; line-height: 1.35; color: #555; }
    .calh-quad.harvestable { background: #e8f5e9; }   /* 해당 밭이 수확 가능한 날 : 그 밭 구역만 연한 녹색 */
    .calh-quad::-webkit-scrollbar { width: 2px; }
    .calh-quad::-webkit-scrollbar-thumb { background: #ccc; border-radius: 2px; }
    .calh-chip { white-space: nowrap; letter-spacing: -0.5px; }
    .calh-chip.harvest-item, .calh-chip.harvest-item b { font-weight: 900 !important; color: #2e7d32; }

    /* 6구역에 표시되는 밭 안내 (헤더 우측 정렬) */
    .calendarh-legend { flex: 1; min-width: 0; display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 2px 4px; font-size: 11px; color: #555; text-align: right; }
    .calendarh-legend .lg { padding: 1px 6px; border-radius: 4px; white-space: nowrap; }
    .calendarh-legend b { color: #333; }
</style>

<div class="calendarh-wrap">

    <div class="calendarh-header">
        <!-- 좌측 정렬 : ◀ 현재 조회기준 년월일 ▶ 오늘 -->
        <div class="calendarh-left">
            <button type="button" class="calendarh-btn" onclick="loadPage('<?php echo $prevPageUrl; ?>', '달력')" title="10일 이전">◀</button>
            <h1 class="calendarh-title"><?php echo calendarEsc($title); ?></h1>
            <button type="button" class="calendarh-btn" onclick="loadPage('<?php echo $nextPageUrl; ?>', '달력')" title="10일 이후">▶</button>
            <button type="button" class="calendarh-btn today-btn" onclick="loadPage('<?php echo $todayPageUrl; ?>', '달력')">오늘</button>
        </div>

        <!-- 우측 정렬 : 밭 이름 안내 (↖ ↑ ↗ ↙ ↓ ↘ 구역별 밭) -->
        <div class="calendarh-legend">
            <?php if (empty($quadFarms)): ?>
                <span>사용 중인 밭이 없습니다.</span>
            <?php else: ?>
                <?php foreach ($quadFarms as $qi => $qf): ?>
                    <span class="lg"><b><?php echo $quadMarks[$qi]; ?></b> <?php echo calendarEsc($qf['farm_name']); ?></span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="calendarh-grid">
        <?php
        for ($i = 0; $i < $totalDays; $i++) {
            $dt         = $startDt->modify('+' . $i . ' days');
            $dateString = $dt->format('Y-m-d');
            $dayOfWeek  = (int)$dt->format('w');
            $dayNum     = (int)$dt->format('j');

            // 첫 칸과 매월 1일에는 "월/일" 로 표시, 그 외에는 "일" 만 표시
            $dateLabel = ($i === 0 || $dayNum === 1) ? $dt->format('n/j') : (string)$dayNum;

            // 해당 일자의 이벤트 / 수확 가능 구역을 밭(farm_id)별로 모음
            $eventsByFarm  = [];
            foreach (($eventsData[$dateString] ?? []) as $event) {
                $eventsByFarm[(int)$event['farm_id']][] = $event;
            }
            $harvestByFarm = [];
            foreach (($harvestData[$dateString] ?? []) as $hv) {
                $harvestByFarm[(int)$hv['farm_id']] = $hv;
            }

            $cellClass = 'calh-cell';
            if ($dateString === $todayYmd) {
                $cellClass .= ' today';
            }

            $dateClass = 'calh-date';
            if ($dayOfWeek === 0) {
                $dateClass .= ' sun';
            } elseif ($dayOfWeek === 6) {
                $dateClass .= ' sat';
            }

            $dailyTaskCount = $tasksData[$dateString]['total'] ?? 0;
            ?>
            <div class="<?php echo $cellClass; ?>" data-date="<?php echo $dateString; ?>">
                <a href="javascript:void(0);" class="calh-link" onclick="selectCalendarDate('<?php echo $dateString; ?>');">
                    <div class="calh-head">
                        <span class="<?php echo $dateClass; ?>"><?php echo $dateLabel; ?><span class="dow"><?php echo $weekdays[$dayOfWeek]; ?></span></span>
                        <?php if ($dailyTaskCount > 0): ?>
                            <span class="calh-count">(<?php echo $dailyTaskCount; ?>)</span>
                        <?php endif; ?>
                    </div>

                    <div class="calh-quads">
                        <?php for ($q = 0; $q < 6; $q++): ?>
                            <?php
                            $qf = $quadFarms[$q] ?? null;   // 좌상, 중상, 우상, 좌하, 중하, 우하 순서의 밭
                            $quadClass = 'calh-quad';
                            if ($qf !== null && isset($harvestByFarm[(int)$qf['farm_id']])) {
                                $quadClass .= ' harvestable';   // 이 밭이 이 날짜에 수확 가능 → 이 구역만 녹색
                            }
                            ?>
                            <div class="<?php echo $quadClass; ?>">
                                <?php
                                if ($qf !== null) {
                                    $qFarmId   = (int)$qf['farm_id'];
                                    $qFarmName = calendarEsc($qf['farm_name']);

                                    // 수확 가능한 구역 표시 : 예) 🧺A,C
                                    if (isset($harvestByFarm[$qFarmId])) {
                                        $hvText = '🧺' . implode(',', $harvestByFarm[$qFarmId]['zones']);
                                        echo '<span class="calh-chip harvest-item" title="' . $qFarmName . ' 수확 가능 ' . $hvText . '"><b>' . $hvText . '</b></span>';
                                    }

                                    // 작업 이벤트 : 아이콘 + 이랑 개수  예) 💧5
                                    foreach (($eventsByFarm[$qFarmId] ?? []) as $event) {
                                        $taskId   = (int)$event['task_id'];
                                        $yCount   = (int)$event['y_count'];
                                        $icon     = $taskIcons[$taskId] ?? '📌';
                                        $chipText = $icon . $yCount;
                                        echo '<span class="calh-chip" title="' . $qFarmName . ' ' . $chipText . '">' . $chipText . '</span>';
                                    }
                                }
                                ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </a>
            </div>
            <?php
        }
        ?>
    </div>

</div>

<?php
// 일자 클릭 팝업 (HTML/CSS/JS) - calendar.php 와 공유
calendarRenderPopup($calData);