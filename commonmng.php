<?php
// DB 접속 정보 설정
require_once __DIR__ . '/auth_check.php';   // ← 추가 (맨 위)
require_once __DIR__ . '/db_connect.php';

// --------------------------------------------------
// DB 연결 (memo.php와 동일한 방식. db_connect.php에서 $pdo를 이미 만들었다면 재사용)
// --------------------------------------------------
if (!isset($pdo) || !($pdo instanceof PDO)) {
    try {
        $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    } catch (PDOException $e) {
        echo "<div style='color:red; text-align:center;'>데이터베이스 연결 실패: " . htmlspecialchars($e->getMessage()) . "</div>";
        exit;
    }
}

// 등록자/수정자 ID (세션에 user_id가 있을 때만 기록, 없으면 NULL)
$currentUserId = (isset($_SESSION) && isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) ? (int)$_SESSION['user_id'] : null;

// --------------------------------------------------
// JSON 응답 헬퍼
// --------------------------------------------------
function ccJson($success, $message = '', $extra = [], $httpCode = 200) {
    while (ob_get_level()) { ob_end_clean(); }
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

// index.php의 window.GLOBAL_COMMON_CODES 와 동일한 구조(사용 'Y'만)를 만들어 반환
function ccBuildGlobalCodes(PDO $pdo) {
    $stmt = $pdo->prepare("SELECT code_group, code, code_nm, description, sort_order, extra_value1, extra_value2, extra_value3, extra_value4, extra_value5 FROM common_code WHERE use_yn = 'Y' ORDER BY code_group ASC, sort_order ASC, code ASC");
    $stmt->execute();
    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        $result[$row['code_group']][$row['code']] = $row;
    }
    return $result;
}

// 입력값 검증 (저장 시 공통)
function ccValidate(array $src) {
    $data = [
        'code_group'   => trim($src['code_group'] ?? ''),
        'code'         => trim($src['code'] ?? ''),
        'code_nm'      => trim($src['code_nm'] ?? ''),
        'description'  => trim($src['description'] ?? ''),
        'use_yn'       => ($src['use_yn'] ?? 'Y') === 'N' ? 'N' : 'Y',
        'sort_order'   => (isset($src['sort_order']) && $src['sort_order'] !== '') ? (int)$src['sort_order'] : 0,
    ];
    for ($i = 1; $i <= 5; $i++) {
        $v = trim($src['extra_value' . $i] ?? '');
        $data['extra_value' . $i] = ($v === '') ? null : $v;
    }
    if ($data['description'] === '') $data['description'] = null;

    if ($data['code_group'] === '') return [null, '코드 그룹을 입력해 주세요.'];
    if ($data['code'] === '')       return [null, '코드를 입력해 주세요.'];
    if ($data['code_nm'] === '')    return [null, '코드명을 입력해 주세요.'];
    if (mb_strlen($data['code_group']) > 500) return [null, '코드 그룹은 500자 이내로 입력해 주세요.'];
    if (mb_strlen($data['code']) > 50)        return [null, '코드는 50자 이내로 입력해 주세요.'];
    if (mb_strlen($data['code_nm']) > 100)    return [null, '코드명은 100자 이내로 입력해 주세요.'];
    if ($data['description'] !== null && mb_strlen($data['description']) > 500) return [null, '설명은 500자 이내로 입력해 주세요.'];
    for ($i = 1; $i <= 5; $i++) {
        if ($data['extra_value' . $i] !== null && mb_strlen($data['extra_value' . $i]) > 255) {
            return [null, "확장값{$i}은 255자 이내로 입력해 주세요."];
        }
    }
    return [$data, ''];
}

// --------------------------------------------------
// 등록 / 수정 / 삭제 처리 (AJAX POST)
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // 등록
        if ($action === 'create') {
            list($data, $err) = ccValidate($_POST);
            if ($err !== '') ccJson(false, $err, [], 400);

            $sql = "INSERT INTO common_code
                        (code_group, code, code_nm, description, use_yn, sort_order,
                         extra_value1, extra_value2, extra_value3, extra_value4, extra_value5,
                         created_by, updated_by)
                    VALUES
                        (:code_group, :code, :code_nm, :description, :use_yn, :sort_order,
                         :extra_value1, :extra_value2, :extra_value3, :extra_value4, :extra_value5,
                         :created_by, :updated_by)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':code_group'   => $data['code_group'],
                ':code'         => $data['code'],
                ':code_nm'      => $data['code_nm'],
                ':description'  => $data['description'],
                ':use_yn'       => $data['use_yn'],
                ':sort_order'   => $data['sort_order'],
                ':extra_value1' => $data['extra_value1'],
                ':extra_value2' => $data['extra_value2'],
                ':extra_value3' => $data['extra_value3'],
                ':extra_value4' => $data['extra_value4'],
                ':extra_value5' => $data['extra_value5'],
                ':created_by'   => $currentUserId,
                ':updated_by'   => $currentUserId,
            ]);
            ccJson(true, '등록되었습니다.', ['codes' => ccBuildGlobalCodes($pdo)]);
        }

        // 수정 (PK인 code_group, code 는 변경하지 않음)
        if ($action === 'update') {
            list($data, $err) = ccValidate($_POST);
            if ($err !== '') ccJson(false, $err, [], 400);

            $sql = "UPDATE common_code
                       SET code_nm = :code_nm,
                           description = :description,
                           use_yn = :use_yn,
                           sort_order = :sort_order,
                           extra_value1 = :extra_value1,
                           extra_value2 = :extra_value2,
                           extra_value3 = :extra_value3,
                           extra_value4 = :extra_value4,
                           extra_value5 = :extra_value5,
                           updated_by = :updated_by
                     WHERE code_group = :code_group AND code = :code";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':code_nm'      => $data['code_nm'],
                ':description'  => $data['description'],
                ':use_yn'       => $data['use_yn'],
                ':sort_order'   => $data['sort_order'],
                ':extra_value1' => $data['extra_value1'],
                ':extra_value2' => $data['extra_value2'],
                ':extra_value3' => $data['extra_value3'],
                ':extra_value4' => $data['extra_value4'],
                ':extra_value5' => $data['extra_value5'],
                ':updated_by'   => $currentUserId,
                ':code_group'   => $data['code_group'],
                ':code'         => $data['code'],
            ]);
            // 값이 동일해 rowCount 가 0 이어도 정상 처리로 간주
            ccJson(true, '수정되었습니다.', ['codes' => ccBuildGlobalCodes($pdo)]);
        }

        // 삭제
        if ($action === 'delete') {
            $codeGroup = trim($_POST['code_group'] ?? '');
            $code      = trim($_POST['code'] ?? '');
            if ($codeGroup === '' || $code === '') ccJson(false, '삭제 대상이 올바르지 않습니다.', [], 400);

            $stmt = $pdo->prepare("DELETE FROM common_code WHERE code_group = :code_group AND code = :code");
            $stmt->execute([':code_group' => $codeGroup, ':code' => $code]);
            if ($stmt->rowCount() < 1) ccJson(false, '이미 삭제되었거나 존재하지 않는 코드입니다.', [], 404);

            ccJson(true, '삭제되었습니다.', ['codes' => ccBuildGlobalCodes($pdo)]);
        }

        ccJson(false, '잘못된 요청입니다.', [], 400);

    } catch (PDOException $e) {
        // 23000: 중복 키(PK) 오류
        if ($e->getCode() === '23000') {
            ccJson(false, '이미 존재하는 코드 그룹/코드 입니다.', [], 409);
        }
        ccJson(false, '처리 중 오류가 발생했습니다.', [], 500);
    }
}

// --------------------------------------------------
// 목록 조회 (검색 + 그룹 필터 + 페이징)
// --------------------------------------------------
$page        = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit       = 10;
$searchGroup = isset($_GET['group']) ? trim($_GET['group']) : '';
$searchWord  = isset($_GET['q']) ? trim($_GET['q']) : '';

$rows = [];
$groupList = [];
$totalCount = 0;
$totalPages = 1;

try {
    // 그룹 선택 목록
    $groupList = $pdo->query("SELECT DISTINCT code_group FROM common_code ORDER BY code_group ASC")->fetchAll(PDO::FETCH_COLUMN);

    // 검색 조건 구성
    $where = [];
    $params = [];
    if ($searchGroup !== '') {
        $where[] = "code_group = :grp";
        $params[':grp'] = $searchGroup;
    }
    if ($searchWord !== '') {
        $where[] = "(code LIKE :kw1 OR code_nm LIKE :kw2 OR description LIKE :kw3)";
        $like = '%' . $searchWord . '%';
        $params[':kw1'] = $like;
        $params[':kw2'] = $like;
        $params[':kw3'] = $like;
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM common_code {$whereSql}");
    $countStmt->execute($params);
    $totalCount = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalCount / $limit));

    if ($page > $totalPages) $page = $totalPages;
    $offset = ($page - 1) * $limit;

    $sql = "SELECT code_group, code, code_nm, description, use_yn, sort_order,
                   extra_value1, extra_value2, extra_value3, extra_value4, extra_value5
              FROM common_code
              {$whereSql}
             ORDER BY code_group ASC, sort_order ASC, code ASC
             LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    // 쿼리 실패 처리
}

$blockSize   = 5;
$currentBlock = (int)ceil($page / $blockSize);
$startPage   = ($currentBlock - 1) * $blockSize + 1;
$endPage     = min($startPage + $blockSize - 1, $totalPages);
?>

<style>
    /* 다른 화면/상위 index.php 스타일과 충돌하지 않도록 모든 클래스에 cc- 접두어 사용 */
    .cc-container { display: flex; flex-direction: column; width: 100%; gap: 12px; box-sizing: border-box; padding-bottom: 20px; }

    .cc-search-section { background-color: #ffffff; padding: 12px; border-radius: 8px; border: 1px solid #e0e0e0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; flex-direction: column; gap: 8px; }
    .cc-search-section select,
    .cc-search-section input[type="text"] { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.9rem; box-sizing: border-box; background-color: #fff; }
    .cc-search-row { display: flex; gap: 6px; }
    .cc-search-row input { flex: 1; min-width: 0; }

    .cc-btn { padding: 8px 14px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 0.88rem; white-space: nowrap; }
    .cc-btn:disabled { background-color: #9e9e9e !important; cursor: not-allowed; }
    .cc-btn-primary { background-color: #4CAF50; color: #fff; }
    .cc-btn-search  { background-color: #2c3e50; color: #fff; }
    .cc-btn-cancel  { background-color: #e0e0e0; color: #333; }
    .cc-btn-danger  { background-color: #e53935; color: #fff; }

    .cc-list-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
    .cc-list-header h3 { font-size: 1rem; color: #333; margin: 0; }
    .cc-list-header .cc-count { font-size: 0.82rem; color: #888; font-weight: normal; margin-left: 4px; }

    .cc-pagination { display: flex; gap: 4px; align-items: center; justify-content: center; flex-wrap: wrap; margin-top: 10px; }
    .cc-pagination button { padding: 4px 8px; font-size: 0.85rem; border: 1px solid #ddd; background-color: #fff; color: #333; border-radius: 4px; cursor: pointer; }
    .cc-pagination button:hover:not(:disabled) { background-color: #f1f8e9; }
    .cc-pagination button.active { background-color: #4CAF50; color: white; border-color: #4CAF50; font-weight: bold; }
    .cc-pagination button:disabled { color: #ccc; border-color: #eee; cursor: default; }

    /* 그리드(표) 영역: 좌우 스크롤 */
    .cc-grid-wrapper { border-top: 1px solid #e0e0e0; border-bottom: 1px solid #e0e0e0; background-color: #fff; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .cc-grid { border-collapse: collapse; width: 100%; min-width: 980px; font-size: 0.82rem; table-layout: fixed; }
    .cc-grid th { background-color: #f1f3f5; color: #444; font-weight: bold; text-align: center; line-height: 1.25; padding: 7px 4px; border: 1px solid #e0e0e0; white-space: normal; }
    .cc-grid td { padding: 7px 5px; border: 1px solid #eee; color: #333; text-align: left; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cc-grid td.cc-center { text-align: center; }
    .cc-grid td.cc-right { text-align: right; }
    .cc-grid tbody tr { cursor: pointer; }
    .cc-grid tbody tr:hover { background-color: #f8f9fa; }
    .cc-grid tbody tr.cc-unused { opacity: 0.5; }
    .cc-grid .cc-col-group { width: 100px; }
    .cc-grid .cc-col-code  { width: 90px; }
    .cc-grid .cc-col-name  { width: 130px; }
    .cc-grid .cc-col-desc  { width: 160px; }
    .cc-grid .cc-col-use   { width: 50px; }
    .cc-grid .cc-col-sort  { width: 50px; }
    .cc-grid .cc-col-extra { width: 90px; }
    .cc-badge { font-size: 0.72rem; padding: 2px 6px; border-radius: 10px; font-weight: bold; display: inline-block; }
    .cc-badge-y { background-color: #e8f5e9; color: #2e7d32; }
    .cc-badge-n { background-color: #eeeeee; color: #777; }
    .cc-grid-hint { font-size: 0.75rem; color: #999; margin: 4px 2px 0; text-align: right; }

    /* 등록/수정 팝업 */
    .cc-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 60; justify-content: center; align-items: center; }
    .cc-modal-overlay.cc-open { display: flex; }
    .cc-modal { background-color: #fff; padding: 18px; border-radius: 12px; width: 92%; max-width: 380px; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.3); box-sizing: border-box; }
    .cc-modal h3 { margin: 0 0 14px 0; font-size: 1.05rem; color: #2c3e50; text-align: center; }
    .cc-field { margin-bottom: 12px; }
    .cc-field label { display: block; font-size: 0.85rem; margin-bottom: 4px; color: #555; font-weight: bold; }
    .cc-field .cc-req { color: #e53935; }
    .cc-field input[type="text"],
    .cc-field input[type="number"],
    .cc-field select,
    .cc-field textarea { width: 100%; padding: 9px; border: 1px solid #ddd; border-radius: 6px; font-size: 0.92rem; font-family: inherit; box-sizing: border-box; background-color: #fff; }
    .cc-field input[readonly] { background-color: #f1f1f1; color: #777; }
    .cc-field textarea { resize: none; height: 64px; }
    .cc-row2 { display: flex; gap: 8px; }
    .cc-row2 .cc-field { flex: 1; min-width: 0; }
    .cc-extra { margin-bottom: 8px; }
    .cc-extra summary { cursor: pointer; font-size: 0.85rem; font-weight: bold; color: #555; padding: 6px 0; }
    .cc-modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px; }
    .cc-modal-actions .cc-spacer { flex: 1; }
</style>

<div class="cc-container">
    <!-- 검색 영역 -->
    <div class="cc-search-section">
        <select id="ccSearchGroup">
            <option value="">전체 그룹</option>
            <?php foreach ($groupList as $g): ?>
                <option value="<?php echo htmlspecialchars($g, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($g === $searchGroup) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($g); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="cc-search-row">
            <input type="text" id="ccSearchWord" placeholder="코드 / 코드명 / 설명 검색" value="<?php echo htmlspecialchars($searchWord, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="button" class="cc-btn cc-btn-search" id="ccBtnSearch">조회</button>
        </div>
    </div>

    <!-- 목록 영역 -->
    <div>
        <div class="cc-list-header">
            <h3>공통코드 <span class="cc-count">(<?php echo number_format($totalCount); ?>건)</span></h3>
            <button type="button" class="cc-btn cc-btn-primary" id="ccBtnNew">+ 신규</button>
        </div>

        <div class="cc-grid-wrapper">
            <table class="cc-grid">
                <colgroup>
                    <col class="cc-col-group"><col class="cc-col-code"><col class="cc-col-name"><col class="cc-col-desc">
                    <col class="cc-col-use"><col class="cc-col-sort">
                    <col class="cc-col-extra"><col class="cc-col-extra"><col class="cc-col-extra"><col class="cc-col-extra"><col class="cc-col-extra">
                </colgroup>
                <thead>
                    <tr>
                        <th>코드<br>그룹</th>
                        <th>코드</th>
                        <th>코드<br>명</th>
                        <th>설명</th>
                        <th>사용<br>여부</th>
                        <th>정렬<br>순서</th>
                        <th>확장값<br>1</th>
                        <th>확장값<br>2</th>
                        <th>확장값<br>3</th>
                        <th>확장값<br>4</th>
                        <th>확장값<br>5</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="11" class="cc-center" style="color:#888; padding:15px; cursor:default;">등록된 공통코드가 없습니다.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr class="cc-item <?php echo $row['use_yn'] === 'Y' ? '' : 'cc-unused'; ?>"
                                data-item="<?php echo htmlspecialchars(json_encode($row, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">
                                <td title="<?php echo htmlspecialchars($row['code_group']); ?>"><?php echo htmlspecialchars($row['code_group']); ?></td>
                                <td title="<?php echo htmlspecialchars($row['code']); ?>"><?php echo htmlspecialchars($row['code']); ?></td>
                                <td title="<?php echo htmlspecialchars($row['code_nm']); ?>"><?php echo htmlspecialchars($row['code_nm']); ?></td>
                                <td title="<?php echo htmlspecialchars((string)$row['description']); ?>"><?php echo htmlspecialchars((string)$row['description']); ?></td>
                                <td class="cc-center">
                                    <span class="cc-badge <?php echo $row['use_yn'] === 'Y' ? 'cc-badge-y' : 'cc-badge-n'; ?>"><?php echo $row['use_yn'] === 'Y' ? '사용' : '미사용'; ?></span>
                                </td>
                                <td class="cc-right"><?php echo (int)$row['sort_order']; ?></td>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <td title="<?php echo htmlspecialchars((string)$row['extra_value' . $i]); ?>"><?php echo htmlspecialchars((string)$row['extra_value' . $i]); ?></td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="cc-grid-hint">← 좌우로 밀어서 확장값 확인 · 행을 누르면 수정/삭제</div>

        <div class="cc-pagination">
            <button type="button" data-page="1" <?php echo ($page <= 1) ? 'disabled' : ''; ?>> &lt;&lt; </button>
            <button type="button" data-page="<?php echo max(1, $page - 1); ?>" <?php echo ($page <= 1) ? 'disabled' : ''; ?>> &lt; </button>
            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                <button type="button" data-page="<?php echo $i; ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></button>
            <?php endfor; ?>
            <button type="button" data-page="<?php echo min($totalPages, $page + 1); ?>" <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>> &gt; </button>
            <button type="button" data-page="<?php echo $totalPages; ?>" <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>> &gt;&gt; </button>
        </div>
    </div>
</div>

<!-- 등록/수정 팝업 -->
<div class="cc-modal-overlay" id="ccModal">
    <div class="cc-modal">
        <h3 id="ccModalTitle">공통코드 등록</h3>

        <div class="cc-field">
            <label>코드 그룹 <span class="cc-req">*</span></label>
            <input type="text" id="ccCodeGroup" maxlength="500" list="ccGroupDatalist" autocomplete="off">
            <datalist id="ccGroupDatalist">
                <?php foreach ($groupList as $g): ?>
                    <option value="<?php echo htmlspecialchars($g, ENT_QUOTES, 'UTF-8'); ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </div>
        <div class="cc-field">
            <label>코드 <span class="cc-req">*</span></label>
            <input type="text" id="ccCode" maxlength="50" autocomplete="off">
        </div>
        <div class="cc-field">
            <label>코드명 <span class="cc-req">*</span></label>
            <input type="text" id="ccCodeNm" maxlength="100" autocomplete="off">
        </div>
        <div class="cc-field">
            <label>설명</label>
            <textarea id="ccDescription" maxlength="500"></textarea>
        </div>
        <div class="cc-row2">
            <div class="cc-field">
                <label>정렬 순서</label>
                <input type="number" id="ccSortOrder" value="0">
            </div>
            <div class="cc-field">
                <label>사용 여부</label>
                <select id="ccUseYn">
                    <option value="Y">사용</option>
                    <option value="N">미사용</option>
                </select>
            </div>
        </div>

        <details class="cc-extra" id="ccExtraBox">
            <summary>확장값 (1~5)</summary>
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <div class="cc-field">
                    <label>확장값<?php echo $i; ?></label>
                    <input type="text" id="ccExtra<?php echo $i; ?>" maxlength="255" autocomplete="off">
                </div>
            <?php endfor; ?>
        </details>

        <div class="cc-modal-actions">
            <button type="button" class="cc-btn cc-btn-danger" id="ccBtnModalDelete" style="display:none;">삭제</button>
            <span class="cc-spacer"></span>
            <button type="button" class="cc-btn cc-btn-cancel" id="ccBtnModalCancel">취소</button>
            <button type="button" class="cc-btn cc-btn-primary" id="ccBtnModalSave">저장</button>
        </div>
    </div>
</div>

<script>
(function() {
    const currentFileUrl = 'commonmng.php';
    const currentPage  = <?php echo (int)$page; ?>;
    const baseGroup    = <?php echo json_encode($searchGroup, JSON_UNESCAPED_UNICODE); ?>;
    const baseWord     = <?php echo json_encode($searchWord, JSON_UNESCAPED_UNICODE); ?>;

    const $ = (id) => document.getElementById(id);
    let mode = 'create'; // 'create' | 'update'

    // 목록 이동 (menu.php의 loadPage 사용, 없으면 일반 이동)
    function goTo(page, group, word) {
        const params = new URLSearchParams();
        params.set('page', page);
        if (group) params.set('group', group);
        if (word)  params.set('q', word);
        const targetUrl = currentFileUrl + '?' + params.toString();
        if (typeof window.loadPage === 'function') {
            window.loadPage(targetUrl, '공통코드');
        } else {
            window.location.href = targetUrl;
        }
    }
    window.ccGoToPage = function(page) { goTo(page, baseGroup, baseWord); };

    // 조회
    function doSearch() {
        goTo(1, $('ccSearchGroup').value, $('ccSearchWord').value.trim());
    }
    $('ccBtnSearch').addEventListener('click', doSearch);
    $('ccSearchWord').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); doSearch(); }
    });
    $('ccSearchGroup').addEventListener('change', doSearch);

    // 페이징
    document.querySelectorAll('.cc-pagination button[data-page]').forEach(function(btn) {
        btn.addEventListener('click', function() { window.ccGoToPage(btn.getAttribute('data-page')); });
    });

    // 팝업 열기/닫기
    function openModal(item) {
        mode = item ? 'update' : 'create';
        $('ccModalTitle').textContent = item ? '공통코드 수정' : '공통코드 등록';

        // PK(그룹/코드)는 수정 시 변경 불가
        $('ccCodeGroup').value = item ? item.code_group : (baseGroup || '');
        $('ccCode').value      = item ? item.code : '';
        $('ccCodeGroup').readOnly = !!item;
        $('ccCode').readOnly      = !!item;

        $('ccCodeNm').value      = item ? item.code_nm : '';
        $('ccDescription').value = item && item.description ? item.description : '';
        $('ccSortOrder').value   = item ? item.sort_order : 0;
        $('ccUseYn').value       = item ? item.use_yn : 'Y';

        let hasExtra = false;
        for (let i = 1; i <= 5; i++) {
            const v = item && item['extra_value' + i] ? item['extra_value' + i] : '';
            $('ccExtra' + i).value = v;
            if (v !== '') hasExtra = true;
        }
        $('ccExtraBox').open = hasExtra;

        $('ccBtnModalDelete').style.display = item ? '' : 'none';
        $('ccBtnModalSave').disabled = false;
        $('ccModal').classList.add('cc-open');
        (item ? $('ccCodeNm') : $('ccCodeGroup')).focus();
    }
    function closeModal() { $('ccModal').classList.remove('cc-open'); }

    $('ccBtnNew').addEventListener('click', function() { openModal(null); });
    $('ccBtnModalCancel').addEventListener('click', closeModal);

    // 그리드 행 클릭 → 수정 팝업 (삭제는 팝업의 삭제 버튼)
    document.querySelectorAll('tr.cc-item').forEach(function(tr) {
        const item = JSON.parse(tr.getAttribute('data-item'));
        tr.addEventListener('click', function() { openModal(item); });
    });

    // 서버 통신 공통
    async function post(formData) {
        const res = await fetch(currentFileUrl, { method: 'POST', body: formData });
        let result;
        try {
            result = await res.json();
        } catch (e) {
            throw new Error('서버 응답을 처리하지 못했습니다.');
        }
        if (!res.ok || !result.success) {
            throw new Error(result.message || '처리 중 오류가 발생했습니다.');
        }
        // index.php의 전역 공통코드 캐시를 최신 상태로 갱신
        if (result.codes !== undefined) {
            window.GLOBAL_COMMON_CODES = Array.isArray(result.codes) && result.codes.length === 0 ? {} : result.codes;
        }
        return result;
    }

    // 저장 (등록/수정)
    $('ccBtnModalSave').addEventListener('click', async function() {
        const group = $('ccCodeGroup').value.trim();
        const code  = $('ccCode').value.trim();
        const nm    = $('ccCodeNm').value.trim();
        if (!group) { alert('코드 그룹을 입력해 주세요.'); $('ccCodeGroup').focus(); return; }
        if (!code)  { alert('코드를 입력해 주세요.'); $('ccCode').focus(); return; }
        if (!nm)    { alert('코드명을 입력해 주세요.'); $('ccCodeNm').focus(); return; }

        const fd = new FormData();
        fd.append('action', mode);
        fd.append('code_group', group);
        fd.append('code', code);
        fd.append('code_nm', nm);
        fd.append('description', $('ccDescription').value);
        fd.append('sort_order', $('ccSortOrder').value);
        fd.append('use_yn', $('ccUseYn').value);
        for (let i = 1; i <= 5; i++) fd.append('extra_value' + i, $('ccExtra' + i).value);

        const btn = $('ccBtnModalSave');
        btn.disabled = true;
        try {
            await post(fd);
            closeModal();
            // 등록 직후에는 1페이지, 수정 시에는 보던 페이지 유지
            window.ccGoToPage(mode === 'create' ? 1 : currentPage);
        } catch (err) {
            alert(err.message);
            btn.disabled = false;
        }
    });

    // 삭제
    async function deleteCode(group, code) {
        if (!confirm('[' + group + '] ' + code + ' 코드를 삭제할까요?')) return;
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('code_group', group);
        fd.append('code', code);
        try {
            await post(fd);
            closeModal();
            window.ccGoToPage(currentPage);
        } catch (err) {
            alert(err.message);
        }
    }
    $('ccBtnModalDelete').addEventListener('click', function() {
        deleteCode($('ccCodeGroup').value, $('ccCode').value);
    });
})();
</script>