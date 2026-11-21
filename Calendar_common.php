<?php
/**
 * Calendar_common.php
 *
 * calendar.php(세로 화면, 월간)와 calendarH.php(가로 화면, 10일간)가 함께 쓰는 공통 파일
 *  - calendarTaskIcons()    : 작업(task_id)별 아이콘 매핑
 *  - calendarFetchData()    : 지정한 기간의 달력 데이터 조회 (SQL 전부 여기서 처리)
 *  - calendarEsc()          : HTML 이스케이프
 *  - calendarQuadFarms() / calendarRenderLegend() / calendarRenderQuads() / calendarRenderQuadStyle()
 *                           : 날짜 칸 6구역(밭별 표시)과 밭 안내 - calendarH.php, calendarWide.php 공용
 *  - calendarRenderPopup()  : 일자 클릭 시 뜨는 상세 팝업(HTML + CSS + JS)
 *
 * 이 파일은 단독으로 호출해도 아무것도 출력하지 않습니다. (함수 정의만 있음)
 */

declare(strict_types=1);

/* --------------------------------------------------
 * Task ID별 아이콘 매핑
 * -------------------------------------------------- */
function calendarTaskIcons(): array
{
    return [
        10 => '💧',
        20 => '🌾',
        30 => '✂️',
        40 => '☠️', // 농약(40~46)
        70 => '💊', // 영양제(70~72 : 1군/2군/3군)
    ];
}

/** HTML 이스케이프 (calendar.php / calendarH.php 공용) */
function calendarEsc($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * 지정한 기간([$startYmd, $endYmd) : 종료일은 포함하지 않음)의 달력 데이터를 조회한다.
 *
 * @return array{
 *   events: array,   // [Y-m-d => 밭 작업 목록]
 *   tasks: array,    // [Y-m-d => 오늘의 할일 건수 정보]
 *   harvest: array,  // [Y-m-d => 수확 가능 밭/구역 목록]
 *   icons: array,    // task_id => 아이콘
 *   today: string    // 서울 기준 오늘 (Y-m-d)
 * }
 */
function calendarFetchData(PDO $pdo, string $startYmd, string $endYmd, DateTimeZone $tz): array
{
    $now         = new DateTime('now', $tz);
    $todayYmd    = $now->format('Y-m-d');
    // 내일 날짜 (오늘 이후 작업은 체크 여부와 상관없이 조회하기 위한 기준일)
    $tomorrowYmd = (clone $now)->modify('+1 day')->format('Y-m-d');

    /* ==================================================
     * 1) 밭 작업 기록 이벤트
     * ================================================== */
    $eventsData = [];

    $sql = "
        SELECT
            r.farm_id,
            f.farm_name,
            CASE
                WHEN r.task_id BETWEEN '40' AND '46' THEN '40'
                WHEN r.task_id BETWEEN '70' AND '72' THEN '70'
                ELSE r.task_id
            END AS task_id,
            DATE_FORMAT(r.work_date, '%Y-%m-%d') AS work_date,
            COUNT(*) AS y_count,
            GROUP_CONCAT(DISTINCT r.col_num ORDER BY r.col_num SEPARATOR ',') AS col_nums
        FROM ridge_records r
        INNER JOIN farms f
            ON r.farm_id = f.farm_id
        WHERE r.work_date >= :start_date
          AND r.work_date < :end_date
          AND (r.check_status = 'Y' OR r.work_date >= :tomorrow)
          AND f.use_yn = 'Y'
        GROUP BY
            r.farm_id,
            f.farm_name,
            CASE
                WHEN r.task_id BETWEEN '40' AND '46' THEN '40'
                WHEN r.task_id BETWEEN '70' AND '72' THEN '70'
                ELSE r.task_id
            END,
            DATE_FORMAT(r.work_date, '%Y-%m-%d')
        ORDER BY
            work_date,
            r.farm_id,
            task_id
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'start_date' => $startYmd,
            'end_date'   => $endYmd,
            'tomorrow'   => $tomorrowYmd,
        ]);

        foreach ($stmt->fetchAll() as $row) {
            // col_num(1~) → 열 문자(A,B,C,D ...) 로 변환 (예: '1,2,4' → 'A,B,D')
            $colLetters = [];
            foreach (explode(',', (string)($row['col_nums'] ?? '')) as $cn) {
                $cn = (int)trim($cn);
                if ($cn >= 1 && $cn <= 26) {
                    $colLetters[] = chr(64 + $cn);
                }
            }
            $row['col_letters'] = implode(',', $colLetters);
            $eventsData[$row['work_date']][] = $row;
        }
    } catch (PDOException $e) {
        error_log("DB 오류 (events): " . $e->getMessage());
    }

    /* ==================================================
     * 2) 오늘의 할 일 ($sql2, $sql3)
     *  - 오늘            : 미완료 + 진행중 + 오늘 완료
     *  - 오늘이 아닌 날짜 : 해당 일자에 완료된 건수만
     * ================================================== */
    $tasksData = [];

    // 기간 내 일자별 완료 건수
    $sql2 = "
        SELECT
            DATE(task_date) AS task_day,
            COUNT(*) AS done_cnt
        FROM dailytasksT
        WHERE completion_status = '완료'
          AND task_date >= :start_date
          AND task_date < :end_date
        GROUP BY DATE(task_date)
    ";

    // 오늘 칸에만 쓰는 미완료 / 진행중 건수 (끝나지 않은 할일은 날짜가 지나도 오늘로 넘어옴)
    $sql3 = "
        SELECT
            SUM(CASE WHEN completion_status IS NULL OR completion_status = '' THEN 1 ELSE 0 END) AS open_cnt,
            SUM(CASE WHEN completion_status = '진행중' THEN 1 ELSE 0 END) AS progress_cnt
        FROM dailytasksT
        WHERE (task_date IS NULL OR task_date <= :today)
          AND (completion_status IS NULL OR completion_status = '' OR completion_status = '진행중')
    ";

    $doneCounts = []; // [Y-m-d => 완료 건수]
    try {
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute([
            'start_date' => $startYmd,
            'end_date'   => $endYmd,
        ]);
        foreach ($stmt2->fetchAll() as $row) {
            $doneCounts[$row['task_day']] = (int)$row['done_cnt'];
        }
    } catch (PDOException $e) {
        error_log("DB 오류 (tasks): " . $e->getMessage());
    }

    // 오늘이 조회 기간에 포함될 때만 미완료 / 진행중 건수를 조회
    $openCnt     = 0;
    $progressCnt = 0;
    $todayInView = ($todayYmd >= $startYmd && $todayYmd < $endYmd);
    if ($todayInView) {
        try {
            $stmt3 = $pdo->prepare($sql3);
            $stmt3->execute(['today' => $todayYmd]);
            $row3        = $stmt3->fetch();
            $openCnt     = (int)($row3['open_cnt'] ?? 0);
            $progressCnt = (int)($row3['progress_cnt'] ?? 0);
        } catch (PDOException $e) {
            error_log("DB 오류 (tasks today): " . $e->getMessage());
        }
    }

    $taskDates = array_keys($doneCounts);
    if ($todayInView) {
        $taskDates[] = $todayYmd;
    }
    foreach (array_unique($taskDates) as $d) {
        $done = $doneCounts[$d] ?? 0;

        if ($d === $todayYmd) {
            $total = $openCnt + $progressCnt + $done;
            $entry = [
                'total'       => $total,
                'completed'   => $done,
                'incomplete'  => $openCnt,
                'in_progress' => $progressCnt,
                'mode'        => 'today',
            ];
        } else {
            $total = $done;
            $entry = [
                'total'     => $total,
                'completed' => $done,
                'mode'      => 'other',
            ];
        }

        if ($total > 0) {
            $tasksData[$d] = $entry;
        }
    }

    /* ==================================================
     * 3) 수확 가능일 계산
     *  - 수확(20)    : 마지막 수확일 + N일 (common_code '수확기간' / '5' 의 extra_value1)
     *  - 농약(40~46) : 살포일 + 대기일
     *  - 구역(열) 수확 가능일 = 그 열의 이랑 중 가장 늦은 날짜
     * ================================================== */
    $harvestWaitDays = 5;
    try {
        $stmtWait = $pdo->prepare("
            SELECT extra_value1
            FROM common_code
            WHERE code_group = '수확기간'
              AND code = '5'
              AND use_yn = 'Y'
            LIMIT 1
        ");
        $stmtWait->execute();
        $waitValue = $stmtWait->fetchColumn();
        if ($waitValue !== false && $waitValue !== null
            && is_numeric(trim((string)$waitValue)) && (float)$waitValue >= 0) {
            $harvestWaitDays = (int)round((float)$waitValue);
        }
    } catch (PDOException $e) {
        error_log("DB 오류 (common_code 수확기간): " . $e->getMessage());
    }

    $pesticideWaitDays = ['40' => 1, '41' => 3, '42' => 3, '43' => 5, '44' => 5, '45' => 7, '46' => 7];
    $harvestData       = [];

    try {
        // 이랑별 마지막 수확일
        $sqlH = "
            SELECT r.farm_id, f.farm_name, r.row_num, r.col_num,
                   DATE_FORMAT(MAX(r.work_date), '%Y-%m-%d') AS last_harvest
            FROM ridge_records r
            INNER JOIN farms f ON f.farm_id = r.farm_id
            WHERE r.task_id = '20'
              AND r.check_status = 'Y'
              AND f.use_yn = 'Y'
            GROUP BY r.farm_id, f.farm_name, r.row_num, r.col_num
        ";

        // 이랑별 마지막 수확 이후(수확 이력이 없으면 전체)의 농약 이벤트
        $sqlP = "
            SELECT p.farm_id, f.farm_name, p.row_num, p.col_num, p.task_id,
                   DATE_FORMAT(p.work_date, '%Y-%m-%d') AS work_date
            FROM ridge_records p
            INNER JOIN farms f ON f.farm_id = p.farm_id
            LEFT JOIN (
                SELECT farm_id, row_num, col_num, MAX(work_date) AS last_h
                FROM ridge_records
                WHERE task_id = '20' AND check_status = 'Y'
                GROUP BY farm_id, row_num, col_num
            ) h ON h.farm_id = p.farm_id AND h.row_num = p.row_num AND h.col_num = p.col_num
            WHERE p.check_status = 'Y'
              AND p.task_id IN ('40','41','42','43','44','45','46')
              AND f.use_yn = 'Y'
              AND (h.last_h IS NULL OR p.work_date >= h.last_h)
        ";

        $addDays = function (string $ymd, int $days): string {
            return (new DateTimeImmutable($ymd))->modify('+' . $days . ' days')->format('Y-m-d');
        };

        $ridgeAvail = []; // "밭|열|행" => 이랑의 수확 가능일 (가장 늦은 날짜)
        $farmNames  = []; // 밭ID => 밭 이름
        $bump = function (string $key, string $date) use (&$ridgeAvail): void {
            if (!isset($ridgeAvail[$key]) || $date > $ridgeAvail[$key]) {
                $ridgeAvail[$key] = $date;
            }
        };

        foreach ($pdo->query($sqlH)->fetchAll() as $row) {
            $farmNames[$row['farm_id']] = $row['farm_name'];
            $bump($row['farm_id'] . '|' . $row['col_num'] . '|' . $row['row_num'],
                  $addDays($row['last_harvest'], $harvestWaitDays));
        }
        foreach ($pdo->query($sqlP)->fetchAll() as $row) {
            $farmNames[$row['farm_id']] = $row['farm_name'];
            $wait = $pesticideWaitDays[(string)$row['task_id']] ?? 0;
            $bump($row['farm_id'] . '|' . $row['col_num'] . '|' . $row['row_num'],
                  $addDays($row['work_date'], $wait));
        }

        // 구역(열) 수확 가능일 = 그 열 이랑들 중 가장 늦은 날짜
        $zoneAvail = []; // "밭|열" => 날짜
        foreach ($ridgeAvail as $key => $date) {
            [$fId, $cNum] = explode('|', $key);
            $zKey = $fId . '|' . $cNum;
            if (!isset($zoneAvail[$zKey]) || $date > $zoneAvail[$zKey]) {
                $zoneAvail[$zKey] = $date;
            }
        }

        // 조회 기간에 속하는 수확 가능일만 날짜별 / 밭별로 모으기
        $byDateFarm = [];
        foreach ($zoneAvail as $zKey => $date) {
            if ($date < $startYmd || $date >= $endYmd) continue;
            [$fId, $cNum] = explode('|', $zKey);
            $cNum = (int)$cNum;
            if ($cNum < 1 || $cNum > 26) continue;
            $byDateFarm[$date][$fId][] = $cNum;
        }
        foreach ($byDateFarm as $date => $farms) {
            foreach ($farms as $fId => $cols) {
                sort($cols);
                $harvestData[$date][] = [
                    'farm_id'   => (int)$fId,
                    'farm_name' => $farmNames[$fId] ?? '',
                    'zones'     => array_map(function ($c) { return chr(64 + $c); }, $cols), // 1 → A, 2 → B ...
                ];
            }
            usort($harvestData[$date], function ($a, $b) { return $a['farm_id'] <=> $b['farm_id']; });
        }
    } catch (PDOException $e) {
        error_log("DB 오류 (harvest): " . $e->getMessage());
    }

    return [
        'events'  => $eventsData,
        'tasks'   => $tasksData,
        'harvest' => $harvestData,
        'icons'   => calendarTaskIcons(),
        'today'   => $todayYmd,
    ];
}

/* --------------------------------------------------
 * 날짜 칸 6구역 (가로 3 x 세로 2) : 좌상, 중상, 우상, 좌하, 중하, 우하
 *  - 사용 중(use_yn = 'Y')인 밭을 정렬순서(sort_order) 순으로 최대 6개 (넘는 밭은 표시하지 않음)
 * -------------------------------------------------- */
function calendarQuadFarms(PDO $pdo): array
{
    try {
        $stmt = $pdo->prepare("
            SELECT farm_id, farm_name
            FROM farms
            WHERE use_yn = 'Y'
            ORDER BY sort_order ASC, farm_id ASC
            LIMIT 6
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("DB 오류 (farms): " . $e->getMessage());
        return [];
    }
}

/** 6구역에 표시되는 밭 안내 (↖ ↑ ↗ ↙ ↓ ↘ 구역별 밭 이름) */
function calendarRenderLegend(array $quadFarms): void
{
    if (!$quadFarms) {
        echo '<span>사용 중인 밭이 없습니다.</span>';
        return;
    }
    $marks = ['↖', '↑', '↗', '↙', '↓', '↘'];
    foreach ($quadFarms as $i => $qf) {
        echo '<span class="lg"><b>' . $marks[$i] . '</b> ' . calendarEsc($qf['farm_name']) . '</span>';
    }
}

/** 하루 칸 안의 6구역(밭별 수확 가능 구역 + 작업 이벤트)을 출력 */
function calendarRenderQuads(array $quadFarms, string $date, array $calData): void
{
    $eventsByFarm = [];
    foreach (($calData['events'][$date] ?? []) as $event) {
        $eventsByFarm[(int)$event['farm_id']][] = $event;
    }
    $harvestByFarm = [];
    foreach (($calData['harvest'][$date] ?? []) as $hv) {
        $harvestByFarm[(int)$hv['farm_id']] = $hv;
    }
    $icons = $calData['icons'];

    echo '<div class="calh-quads">';
    for ($q = 0; $q < 6; $q++) {
        $qf     = $quadFarms[$q] ?? null;
        $farmId = $qf !== null ? (int)$qf['farm_id'] : null;
        $canHarvest = $farmId !== null && isset($harvestByFarm[$farmId]);

        echo '<div class="calh-quad' . ($canHarvest ? ' harvestable' : '') . '">';
        if ($qf !== null) {
            $farmName = calendarEsc($qf['farm_name']);

            // 수확 가능한 구역 : 예) 🧺A,C
            if ($canHarvest) {
                $hvText = '🧺' . implode(',', $harvestByFarm[$farmId]['zones']);
                echo '<span class="calh-chip harvest-item" title="' . $farmName . ' 수확 가능 ' . $hvText . '"><b>' . $hvText . '</b></span>';
            }

            // 작업 이벤트 : 아이콘 + 이랑 개수  예) 💧5
            foreach (($eventsByFarm[$farmId] ?? []) as $event) {
                $chipText = ($icons[(int)$event['task_id']] ?? '📌') . (int)$event['y_count'];
                echo '<span class="calh-chip" title="' . $farmName . ' ' . $chipText . '">' . $chipText . '</span>';
            }
        }
        echo '</div>';
    }
    echo '</div>';
}

/** 6구역 날짜 칸 / 밭 안내의 공통 스타일 */
function calendarRenderQuadStyle(): void
{
    ?>
<style>
    .calh-cell { min-height: 0; min-width: 0; border-right: 1px solid #eee; border-bottom: 1px solid #eee; padding: 3px; background: #fff; display: flex; flex-direction: column; overflow: hidden; }
    .calh-cell.today { background: #fffde7; }
    .calh-link { display: flex; flex-direction: column; width: 100%; height: 100%; min-height: 0; color: inherit; text-decoration: none; cursor: pointer; }
    .calh-head { flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; padding: 0 1px; margin-bottom: 2px; }
    .calh-date { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 5px; border-radius: 11px; font-size: 12px; font-weight: 700; color: #333; white-space: nowrap; }
    .calh-date .dow { font-size: 10px; font-weight: 600; margin-left: 2px; }
    .calh-date.sun { color: #d32f2f; }
    .calh-date.sat { color: #1976d2; }
    .calh-cell.today .calh-date { background: #1976d2; color: #fff; }
    .calh-count { font-size: 10px; color: #1976d2; font-weight: bold; }

    /* 날짜 칸 6구역 (가로 3 x 세로 2) : 1~3번 밭 / 4~6번 밭 */
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
    <?php
}

/**
 * 일자 클릭 시 표시되는 상세 팝업(HTML + CSS + JS)을 출력한다.
 * calendar.php / calendarH.php 가 같은 팝업을 사용한다.
 * 호출하는 쪽에서는 date-link 의 onclick 에 selectCalendarDate('YYYY-MM-DD') 만 연결하면 된다.
 */
function calendarRenderPopup(array $data): void
{
    ?>
<style>
    .calendar-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 2000; display: none; justify-content: center; align-items: center; }
    .calendar-modal-content { background: #fff; border-radius: 8px; width: 90%; max-width: 400px; max-height: 80vh; max-height: 80dvh; display: flex; flex-direction: column; box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
    .calendar-modal-header { padding: 12px 16px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .calendar-modal-header h3 { margin: 0; font-size: 1.1rem; color: #333; }
    .calendar-modal-close { cursor: pointer; font-size: 1.2rem; color: #666; }
    .calendar-modal-body { overflow-y: auto; flex: 1; }

    /* 오늘의 할 일 요약 */
    .task-summary-box { margin: 6px 10px 2px 10px; padding: 6px 8px; background-color: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 6px; cursor: pointer; text-align: center; transition: all 0.2s ease; }
    .task-summary-box:hover { background-color: #e3f2fd; border-color: #90caf9; }
    .task-summary-text { font-size: 16px; color: #333; white-space: nowrap; letter-spacing: -0.5px; overflow: hidden; text-overflow: ellipsis; }

    .event-list { list-style: none; padding: 0; margin: 0; border-top: 1px solid #f0f0f0; }
    .event-list-item { padding: 14px 16px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: background 0.2s; font-size: 0.95rem; color: #444; }
    .event-list-item:hover { background: #f1f8e9; }
    .event-list-item:last-child { border-bottom: none; }
    .event-list-item.harvest { background: #e8f5e9; }
    .event-list-empty { padding: 20px; text-align: center; color: #888; font-size: 0.95rem; }

    /* 가로 화면(낮은 화면)에서는 팝업을 더 넓고 낮게 */
    @media (orientation: landscape) and (max-height: 500px) {
        .calendar-modal-content { max-width: 520px; max-height: 92vh; max-height: 92dvh; }
        .event-list-item { padding: 10px 16px; }
    }
</style>

<div id="calendarEventModal" class="calendar-modal-overlay">
    <div class="calendar-modal-content">
        <div class="calendar-modal-header">
            <h3 id="calendarModalTitle">상세 내역</h3>
            <span class="calendar-modal-close" onclick="closeCalendarModal()">✖</span>
        </div>
        <div class="calendar-modal-body">
            <div id="calendarTaskSummary"></div>
            <ul id="calendarEventList" class="event-list"></ul>
        </div>
    </div>
</div>

<script>
window.calendarEventsData  = <?php echo json_encode($data['events'],  JSON_UNESCAPED_UNICODE); ?>;
window.calendarTasksData   = <?php echo json_encode($data['tasks'],   JSON_UNESCAPED_UNICODE); ?>;
window.calendarHarvestData = <?php echo json_encode($data['harvest'], JSON_UNESCAPED_UNICODE); ?>;
window.calendarTaskIcons   = <?php echo json_encode($data['icons'],   JSON_UNESCAPED_UNICODE); ?>;

function closeCalendarModal() {
    document.getElementById('calendarEventModal').style.display = 'none';
}

// 팝업을 닫고 다른 화면으로 이동
function calendarOpen(url, title) {
    closeCalendarModal();
    if (typeof closeWideCalendar === 'function') closeWideCalendar(true);   // 가로 달력 팝업(menu.php)이 열려 있으면 함께 닫음
    loadPage(url, title);
}

function selectCalendarDate(date) {
    const events   = window.calendarEventsData[date]  || [];
    const harvests = window.calendarHarvestData[date] || [];
    const tasks    = window.calendarTasksData[date]   || null;

    const summary = document.getElementById('calendarTaskSummary');
    const list    = document.getElementById('calendarEventList');

    document.getElementById('calendarModalTitle').textContent = date + ' 상세 내역';

    // 오늘의 할 일 요약 (오늘: 미완료 / 진행중 / 완료, 그 외 날짜: 완료만)
    if (tasks && tasks.total > 0) {
        const parts = [];
        if (tasks.mode === 'today') {
            parts.push(`미완료 <strong style="color:#e67e22;">${tasks.incomplete}</strong>개`);
            parts.push(`진행중 <strong style="color:#3498db;">${tasks.in_progress}</strong>개`);
        }
        parts.push(`완료 <strong style="color:#2ecc71;">${tasks.completed}</strong>개`);
        summary.innerHTML = `
            <div class="task-summary-box" onclick="calendarOpen('dailytasks.php?d=${date}', '오늘의 할일')">
                <div class="task-summary-text">오늘의 할 일 : ${parts.join(' / ')}</div>
            </div>`;
        summary.style.display = 'block';
    } else {
        summary.innerHTML = '';
        summary.style.display = 'none';
    }

    list.innerHTML = '';

    // 목록 한 줄 추가 : 터치하면 해당 밭 상세로 이동
    const addItem = function (text, farmId, extraClass) {
        const li = document.createElement('li');
        li.className = 'event-list-item' + (extraClass ? ' ' + extraClass : '');
        li.textContent = text;
        li.onclick = function () { calendarOpen('farmmngdtl.php?farm_id=' + farmId, '밭 상세정보'); };
        list.appendChild(li);
    };

    if (events.length === 0 && harvests.length === 0) {
        list.innerHTML = '<li class="event-list-empty">해당 일자에 등록된 밭 작업 기록이 없습니다.</li>';
    } else {
        // 수확 가능한 구역
        harvests.forEach(function (hv) {
            addItem('🧺 ' + hv.farm_name + '밭 ' + hv.zones.join(',') + ' 구역 수확 가능', hv.farm_id, 'harvest');
        });

        // 밭 작업 : "이랑" 뒤에 붙는 문구 (매핑에 없는 작업은 기본 문구)
        const taskText = { 10: '에 물을 줌', 20: '에서 수확', 30: '에서 옆순 제거', 40: '에 농약 살포', 70: '에 영양제 투여' };
        events.forEach(function (ev) {
            const taskId    = parseInt(ev.task_id, 10);
            // 열(A,B,D)은 괄호 밖, 이랑 개수는 괄호 안 : 예) A,B,D(3개 줄)
            const countText = ev.col_letters ? `${ev.col_letters}(${ev.y_count}개 줄)` : `${ev.y_count}개`;
            const suffix    = taskText[taskId] || `에 작업(${ev.task_id}) 적용`;
            const icon      = window.calendarTaskIcons[taskId] || '📌';
            addItem(`${icon} ${ev.farm_name}밭 ${countText} 이랑${suffix}`, ev.farm_id);
        });
    }

    document.getElementById('calendarEventModal').style.display = 'flex';
}
</script>
    <?php
}