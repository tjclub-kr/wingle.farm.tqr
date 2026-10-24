<?php
require_once __DIR__ . '/db_connect.php';

// 오늘의 할일 첨부파일 설정 (백엔드 기본값)
$TODO_ATTACHMENT_MAX_FILES = 5;
$TODO_ATTACHMENT_EXTENSIONS = ['jpg','jpeg','png','gif','webp','pdf','txt','doc','docx','xls','xlsx','hwp','hwpx','zip'];
$TODO_ATTACHMENT_MAX_SIZE = 20 * 1024 * 1024;

function todoAttachmentGroupId(int $taskId): int { return -abs($taskId); }
function todoUploadFiles(array $files, int $taskId, PDO $pdo, int $maxFiles, array $allowedExt, int $maxSize): array {
    $groupId = todoAttachmentGroupId($taskId);
    $uploadDir = __DIR__ . '/uploads/attachments/' . date('Y') . '/' . date('m');
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) throw new RuntimeException('첨부파일 저장 폴더를 만들 수 없습니다.');
    $names = $files['name'] ?? [];
    $tmpNames = $files['tmp_name'] ?? [];
    $errors = $files['error'] ?? [];
    $sizes = $files['size'] ?? [];
    if (!is_array($names)) { $names=[$names]; $tmpNames=[$tmpNames]; $errors=[$errors]; $sizes=[$sizes]; }
    $validIndexes=[];
    foreach ($names as $i=>$name) { if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) $validIndexes[]=$i; }
    if (count($validIndexes) > $maxFiles) throw new RuntimeException('첨부파일은 최대 '.$maxFiles.'개까지 등록할 수 있습니다.');
    $saved=[];
    foreach ($validIndexes as $i) {
        if (($errors[$i] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('파일 업로드 중 오류가 발생했습니다.');
        $original=basename((string)$names[$i]); $size=(int)$sizes[$i];
        if ($size <= 0 || $size > $maxSize) throw new RuntimeException($original.' 파일의 크기가 허용 범위를 초과했습니다.');
        $ext=strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext,$allowedExt,true)) throw new RuntimeException($original.' 파일은 허용되지 않는 확장자입니다.');
        $stored=bin2hex(random_bytes(16)).($ext ? '.'.$ext : '');
        $target=$uploadDir.'/'.$stored;
        if (!move_uploaded_file($tmpNames[$i], $target)) throw new RuntimeException($original.' 파일 저장에 실패했습니다.');
        $mime=function_exists('mime_content_type') ? (mime_content_type($target) ?: null) : null;
        $checksum=hash_file('sha256',$target) ?: null;
        $path='uploads/attachments/'.date('Y').'/'.date('m').'/'.$stored;
        $st=$pdo->prepare("INSERT INTO attachment (att_grp_id, original_name, stored_name, storage_type, storage_path, mime_type, file_size, extension, checksum, status, created_at, updated_at) VALUES (?, ?, ?, 'LOCAL', ?, ?, ?, ?, ?, 'ACTIVE', NOW(), NOW())");
        $st->execute([$groupId,$original,$stored,$path,$mime,$size,$ext,$checksum]);
        $saved[]=(int)$pdo->lastInsertId();
    }
    return $saved;
}

function todoDeleteAttachment(int $attId, PDO $pdo): void {
    $st=$pdo->prepare("SELECT storage_path FROM attachment WHERE att_id=? AND status='ACTIVE'"); $st->execute([$attId]); $row=$st->fetch(PDO::FETCH_ASSOC);
    if ($row) { 
        $path = $row['storage_path'];
        // 이관된 다른 할일에서 동일한 실제 파일을 참조하고 있는지 확인 후 실체 파일 삭제
        $chk = $pdo->prepare("SELECT COUNT(*) FROM attachment WHERE storage_path=? AND status='ACTIVE' AND att_id != ?");
        $chk->execute([$path, $attId]);
        if ((int)$chk->fetchColumn() === 0) {
            $file=__DIR__.'/'.ltrim($path,'/'); if (is_file($file)) @unlink($file); 
        }
    }
    $pdo->prepare("UPDATE attachment SET status='DELETED', updated_at=NOW() WHERE att_id=?")->execute([$attId]);
}

$today = date('Y-m-d');
$viewDate = isset($_GET['d']) ? (string)$_GET['d'] : $today;
// 형식이 올바르지 않거나 오늘 이후 날짜이면 오늘로 보정 (오늘 이후 날짜로는 이동할 수 없음)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $viewDate) || $viewDate > $today) $viewDate = $today;

$dateStatus = 0;
if ($viewDate < $today) $dateStatus = -1;
if ($viewDate > $today) $dateStatus = 1;

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action) {
    if ($action === 'add_todo') {
        try {
            $content = trim($_POST['content'] ?? '');
            if (!$content) throw new RuntimeException('할일 내용을 입력해 주세요.');
            // 날짜는 받지 않는다 → task_date = NULL (완료 시점에 완료 일자가 기록됨)
            // 순서 : 기존 할일의 sort_order 최대값 + 1 (최초 등록이면 1)
            $stmt = $pdo->prepare("INSERT INTO dailytasksT (task_date, content, completion_status, sort_order) SELECT NULL, ?, '', COALESCE(MAX(sort_order), 0) + 1 FROM dailytasksT");
            $stmt->execute([$content]);
            $taskId=(int)$pdo->lastInsertId();
            if (isset($_FILES['attachments'])) todoUploadFiles($_FILES['attachments'],$taskId,$pdo,$TODO_ATTACHMENT_MAX_FILES,$TODO_ATTACHMENT_EXTENSIONS,$TODO_ATTACHMENT_MAX_SIZE);
            echo "success";
        } catch (Throwable $e) { http_response_code(400); echo $e->getMessage(); }
        exit;
    }
    if ($action === 'move_task') {
        // 위/아래 화살표 : 화면에서 바로 위(아래)에 있던 할일과 sort_order 값을 서로 바꾼다. (2개 Row UPDATE)
        try {
            $id = (int)($_POST['id'] ?? 0);
            $targetId = (int)($_POST['target_id'] ?? 0);
            if (!$id || !$targetId || $id === $targetId) throw new RuntimeException('이동 대상이 올바르지 않습니다.');

            $pdo->beginTransaction();

            $readStmt = $pdo->prepare("SELECT id, sort_order, completion_status FROM dailytasksT WHERE id IN (?, ?) FOR UPDATE");
            $readStmt->execute([$id, $targetId]);
            $rows = [];
            foreach ($readStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $rows[(int)$r['id']] = $r;
            if (count($rows) !== 2) throw new RuntimeException('할일을 찾을 수 없습니다. 화면을 다시 조회해 주세요.');
            foreach ($rows as $r) {
                if ($r['completion_status'] !== null && $r['completion_status'] !== '') throw new RuntimeException('미완료 할일끼리만 순서를 바꿀 수 있습니다.');
            }

            // 두 할일의 sort_order 가 같으면 값을 바꿔도 순서가 변하지 않으므로,
            // 미완료 할일 전체를 현재 조회 순서(sort_order, 등록일, id)대로 1..n 으로 다시 부여한 뒤 교환한다.
            if ((int)$rows[$id]['sort_order'] === (int)$rows[$targetId]['sort_order']) {
                $pdo->exec("UPDATE dailytasksT t JOIN (SELECT id, ROW_NUMBER() OVER (ORDER BY sort_order ASC, created_at ASC, id ASC) AS rn FROM dailytasksT WHERE completion_status IS NULL OR completion_status = '') x ON x.id = t.id SET t.sort_order = x.rn");
                $readStmt->execute([$id, $targetId]);
                $rows = [];
                foreach ($readStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $rows[(int)$r['id']] = $r;
            }

            $upd = $pdo->prepare("UPDATE dailytasksT SET sort_order = ? WHERE id = ?");
            $upd->execute([(int)$rows[$targetId]['sort_order'], $id]);
            $upd->execute([(int)$rows[$id]['sort_order'], $targetId]);

            $pdo->commit();
            echo 'success';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(400);
            echo $e->getMessage();
        }
        exit;
    }
    if ($action === 'delete_attachment') { 
        try { 
            $attId=(int)($_POST['att_id']??0); 
            if(!$attId) throw new RuntimeException('첨부파일 ID가 없습니다.'); 
            todoDeleteAttachment($attId,$pdo); 
            echo 'success'; 
        } catch(Throwable $e){ http_response_code(400); echo $e->getMessage(); } 
        exit; 
    }
    if ($action === 'attachments') {
        $taskId=(int)($_GET['task_id']??0); 
        $st=$pdo->prepare("SELECT att_id, original_name, storage_path, file_size, extension FROM attachment WHERE att_grp_id=? AND status='ACTIVE' ORDER BY att_id ASC"); 
        $st->execute([todoAttachmentGroupId($taskId)]); 
        header('Content-Type: application/json; charset=utf-8'); 
        echo json_encode($st->fetchAll(PDO::FETCH_ASSOC),JSON_UNESCAPED_UNICODE); 
        exit;
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            // 해당 할일의 첨부파일 정리 (이관된 복사본이 같은 실제 파일을 참조 중이면 파일은 유지됨)
            $stAtt = $pdo->prepare("SELECT att_id FROM attachment WHERE att_grp_id = ? AND status = 'ACTIVE'");
            $stAtt->execute([todoAttachmentGroupId($id)]);
            foreach ($stAtt->fetchAll(PDO::FETCH_COLUMN) as $attId) {
                todoDeleteAttachment((int)$attId, $pdo);
            }
            $stmt = $pdo->prepare("DELETE FROM dailytasksT WHERE id = ?");
            $stmt->execute([$id]);
            echo "success";
        }
        exit;
    }
    if ($action === 'update_content') {
        try {
            $id = (int)($_POST['id'] ?? 0);
            $content = trim($_POST['content'] ?? '');
            if (!$id || $content === '') throw new RuntimeException('수정 내용을 입력해 주세요.');

            $stmt = $pdo->prepare("UPDATE dailytasksT SET content = ? WHERE id = ?");
            $stmt->execute([$content, $id]);

            // 새 첨부파일 업로드 처리
            if (isset($_FILES['attachments'])) {
                $st = $pdo->prepare("SELECT COUNT(*) FROM attachment WHERE att_grp_id = ? AND status = 'ACTIVE'");
                $st->execute([todoAttachmentGroupId($id)]);
                $currentCount = (int)$st->fetchColumn();

                $files = $_FILES['attachments'];
                $newCount = is_array($files['name']) ? count(array_filter($files['name'], fn($v) => $v !== '')) : 1;

                if (($currentCount + $newCount) > $TODO_ATTACHMENT_MAX_FILES) {
                    throw new RuntimeException('기존 파일을 포함하여 최대 ' . $TODO_ATTACHMENT_MAX_FILES . '개까지만 유지할 수 있습니다.');
                }
                todoUploadFiles($files, $id, $pdo, $TODO_ATTACHMENT_MAX_FILES, $TODO_ATTACHMENT_EXTENSIONS, $TODO_ATTACHMENT_MAX_SIZE);
            }
            echo "success";
        } catch (Throwable $e) {
            http_response_code(400);
            echo $e->getMessage();
        }
        exit;
    }
    if ($action === 'complete') {
        $id = $_POST['id'] ?? 0;
        if ($id) {
            // 완료 일자(오늘)를 task_date 에 기록 → 해당 일자를 조회할 때 완료된 할일로 표시됨
            $stmt = $pdo->prepare("UPDATE dailytasksT SET completion_status = '완료', task_date = ? WHERE id = ?");
            $stmt->execute([$today, $id]);
            echo "success";
        }
        exit;
    }
    if ($action === 'undo_complete') {
        $id = $_POST['id'] ?? 0;
        if ($id) {
            // 완료 취소 → 다시 미완료(일자 없음) 상태로
            $stmt = $pdo->prepare("UPDATE dailytasksT SET completion_status = '', task_date = NULL WHERE id = ?");
            $stmt->execute([$id]);
            echo "success";
        }
        exit;
    }
}

// 오늘의 할일 목록
//  - 오늘     : 미완료 전체 + 오늘 완료한 할일
//  - 오늘 이전 : 그 날짜에 완료한 할일만
//  - 미완료를 먼저, 완료를 나중에 표시한다.
//  - 순번(seq)은 미완료 할일끼리만 조회된 순서대로 SQL 에서 새로 부여한다. (정렬 : sort_order → 등록일 → id)
$isToday = ($dateStatus === 0);
$whereSql = $isToday
    ? "((t.completion_status IS NULL OR t.completion_status = '') OR (t.completion_status = '완료' AND t.task_date = ?))"
    : "(t.completion_status = '완료' AND t.task_date = ?)";
$stmt = $pdo->prepare("SELECT t.*,
        ROW_NUMBER() OVER (PARTITION BY (CASE WHEN t.completion_status = '완료' THEN 1 ELSE 0 END) ORDER BY t.sort_order ASC, t.created_at ASC, t.id ASC) AS seq,
        (SELECT COUNT(*) FROM attachment a WHERE a.att_grp_id = -t.id AND a.status='ACTIVE') AS attachment_count
    FROM dailytasksT t
    WHERE $whereSql
    ORDER BY (CASE WHEN t.completion_status = '완료' THEN 1 ELSE 0 END) ASC, t.sort_order ASC, t.created_at ASC, t.id ASC");
$stmt->execute([$viewDate]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<style>
    .date-nav-container { display: flex; justify-content: center; align-items: center; position: relative; padding: 15px 0; margin-bottom: 10px; background-color: #f8f9fa; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .date-nav-btn { position: absolute; top: 50%; transform: translateY(-50%); background: none; border: none; font-size: 20px; color: #2c3e50; cursor: pointer; padding: 10px 15px; font-weight: bold; }
    .date-nav-btn.prev { left: 10px; }
    .date-nav-btn.next { right: 10px; }
    .date-nav-btn:disabled { opacity: 0.25; cursor: default; }
    .date-title-wrap { position: relative; font-size: 18px; font-weight: bold; color: #2c3e50; display: flex; align-items: center; }
    .today-badge { position: absolute; left: 100%; margin-left: 8px; font-size: 14px; color: #e74c3c; white-space: nowrap; }
    .task-list { list-style: none; padding: 0; margin: 0; }
    .task-item { display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 15px 0 15px 1px; margin-bottom: 10px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.05); border: 1px solid #eee; }
    .task-seq { flex-shrink: 0; min-width: 20px; margin-right: 6px; text-align: right; font-size: 13px; font-weight: bold; color: #7f8c8d; }
    .task-move { display: flex; flex-shrink: 0; gap: 2px; margin-right: 0; }
    /* 위/아래 화살표 : 글꼴(문자)에 의존하지 않도록 CSS 삼각형으로 그림 */
    .task-move-btn { position: relative; display: block; flex-shrink: 0; width: 16px; height: 28px; padding: 0; border: 1px solid #b0b7bf; border-radius: 6px; background: #fff; cursor: pointer; -webkit-appearance: none; appearance: none; }
    .task-move-btn::before { content: ''; position: absolute; left: 50%; top: 50%; width: 0; height: 0; margin-left: -6px; border-left: 6px solid transparent; border-right: 6px solid transparent; }
    .task-move-btn.move-up::before { margin-top: -4px; border-bottom: 8px solid #2c3e50; }
    .task-move-btn.move-down::before { margin-top: -4px; border-top: 8px solid #2c3e50; }
    .task-move-btn:active { background: #e9ecef; }
    .task-move-btn:disabled { background: #f5f5f5; border-color: #e3e3e3; cursor: default; }
    .task-move-btn.move-up:disabled::before { border-bottom-color: #c8c8c8; }
    .task-move-btn.move-down:disabled::before { border-top-color: #c8c8c8; }
    .task-content-line { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 15px; cursor: pointer; color: #333; }
    .task-content-line.completed { color: #95a5a6; }
    .task-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
    .task-attachment-icon { font-size: 17px; cursor: pointer; color: #7f8c8d; transition: color 0.2s; }
    .task-attachment-icon:hover { color: #8e44ad; }
    .attachment-box { margin-bottom: 12px; border-top: 1px solid #eee; padding-top: 10px; }
    .attachment-title { font-size: 13px; font-weight: bold; color: #555; margin-bottom: 7px; }
    .attachment-list { display:flex; flex-direction:column; gap:5px; margin-bottom:8px; max-height:120px; overflow-y:auto; }
    .attachment-row { display:flex; align-items:center; gap:6px; font-size:12px; }
    .attachment-name { flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; text-decoration:none; color:#2c3e50; }
    .attachment-delete { border:0; background:none; cursor:pointer; padding:2px 4px; }
    .selected-file-badge { display:inline-flex; align-items:center; background:#f1f8e9; padding:4px 8px; margin:2px 4px 2px 0; border-radius:4px; border:1px solid #c5e1a5; color:#2e7d32; font-size:11px; }
    .selected-file-badge-name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:200px; }
    .selected-file-badge-remove { margin-left:6px; cursor:pointer; color:#d32f2f; font-weight:bold; }
    .attachment-help { font-size:10px; color:#888; margin-top:5px; line-height:1.4; }
    .task-delete-btn { background: none; border: none; color: #e74c3c; font-size: 18px; cursor: pointer; padding: 5px 0 5px 5px; margin: 0 3px 0 0; }
    .task-status-tag { font-weight: bold; font-size: 14px; white-space: nowrap; }
    .status-completed { color: #2ecc71; }
    .status-incomplete { color: #e74c3c; }
    .detail-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9998; justify-content: center; align-items: center; }
    .detail-modal-content { background: #fff; width: 90%; max-width: 360px; padding: 20px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
    .detail-modal-content textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; resize: none; margin-bottom: 15px; background-color: #fff; color: #333; box-sizing: border-box; }
    .modal-btn-group { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }
    .modal-btn { padding: 10px 14px; border: none; border-radius: 6px; font-size: 14px; font-weight: bold; cursor: pointer; color: #fff; }
    .btn-edit { background-color: #3498db; }
    .btn-complete { background-color: #2ecc71; }
    .btn-undo { background-color: #e67e22; }
    .btn-close { background-color: #95a5a6; }
    .bottom-spacer { height: 30px; }
</style>

<input type="hidden" id="current-view-date" value="<?= htmlspecialchars($viewDate, ENT_QUOTES) ?>">
<input type="hidden" id="current-date-status" value="<?= $dateStatus ?>">

<div class="date-nav-container">
    <button class="date-nav-btn prev" onclick="moveDate(-1, '<?= htmlspecialchars($viewDate, ENT_QUOTES) ?>')">&#10094;</button>
    <div class="date-title-wrap">
        <?= htmlspecialchars($viewDate) ?>
        <?php if ($dateStatus === 0): ?><span class="today-badge">(오늘)</span><?php endif; ?>
    </div>
    <button class="date-nav-btn next" onclick="moveDate(1, '<?= htmlspecialchars($viewDate, ENT_QUOTES) ?>')" <?= $dateStatus >= 0 ? 'disabled' : '' ?>>&#10095;</button>
</div>

<ul class="task-list">
    <?php if (count($tasks) === 0): ?>
        <li style="text-align: center; color: #999; padding: 20px;"><?= $isToday ? '할일이 없어! 새 할일을 추가해 볼까?' : '이 날 완료한 할일이 없어!' ?></li>
    <?php else: ?>
        <?php foreach ($tasks as $idx => $task): 
            $status = $task['completion_status'];
            $isCompleted = ($status === '완료');
            // 순서 변경은 미완료 할일끼리만 : 바로 위/아래 할일이 미완료일 때만 이동 가능
            $prevTaskId = (!$isCompleted && ($tasks[$idx - 1]['completion_status'] ?? '완료') !== '완료') ? ($tasks[$idx - 1]['id'] ?? 0) : 0;
            $nextTaskId = (!$isCompleted && ($tasks[$idx + 1]['completion_status'] ?? '완료') !== '완료') ? ($tasks[$idx + 1]['id'] ?? 0) : 0;
            $isDoneAny = $isCompleted;
            $canDelete = !$isCompleted;   // 미완료 할일은 언제든 삭제 가능
        ?>
            <li class="task-item">
                <span class="task-seq"><?= $isCompleted ? '' : ((int)$task['seq'] . '.') ?></span>
                <div class="task-content-line <?= $isDoneAny ? 'completed' : '' ?>" 
                     data-id="<?= $task['id'] ?>" 
                     data-content="<?= htmlspecialchars($task['content'], ENT_QUOTES) ?>" 
                     data-status="<?= htmlspecialchars($status ?? '', ENT_QUOTES) ?>"
                     onclick="openTaskDetail(this)">
                    <?= htmlspecialchars($task['content']) ?>
                </div>
                <div class="task-actions">
                    <?php if ($task['attachment_count'] > 0): ?>
                        <span class="task-attachment-icon" title="첨부파일 <?= (int)$task['attachment_count'] ?>개" onclick="openTaskDetail(this.closest('.task-item').querySelector('.task-content-line'))">📎</span>
                    <?php endif; ?>

                    <?php if (!$isCompleted): ?>
                    <span class="task-move">
                        <button type="button" class="task-move-btn move-up" title="위로" aria-label="위로"
                            <?= $prevTaskId ? 'onclick="moveTask(' . (int)$task['id'] . ', ' . (int)$prevTaskId . ')"' : 'disabled' ?>></button>
                        <button type="button" class="task-move-btn move-down" title="아래로" aria-label="아래로"
                            <?= $nextTaskId ? 'onclick="moveTask(' . (int)$task['id'] . ', ' . (int)$nextTaskId . ')"' : 'disabled' ?>></button>
                    </span>
                    <?php endif; ?>

                    <?php if ($isCompleted): ?>
                        <span class="task-status-tag status-completed">[완료]</span>
                    <?php elseif ($dateStatus <= 0): ?>
                        <span class="task-status-tag status-incomplete">[미완료]</span>
                    <?php endif; ?>

                    <?php if ($canDelete): ?>
                        <button class="task-delete-btn" onclick="deleteDailyTask(<?= $task['id'] ?>)">🗑️</button>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    <?php endif; ?>
</ul>

<div class="bottom-spacer"></div>

<div class="detail-modal-overlay" id="taskDetailModal">
    <div class="detail-modal-content">
        <input type="hidden" id="modal-task-id">
        <textarea id="modal-task-content" rows="6"></textarea>
        
        <div class="attachment-box">
            <div class="attachment-title">기존 첨부파일 <span id="modal-attachment-count"></span></div>
            <div id="modal-attachment-list" class="attachment-list"></div>
            
            <div style="margin-top: 10px; border-top: 1px dashed #eee; padding-top: 8px;">
                <label for="modal-attachments" style="font-size: 12px; font-weight: bold; color: #555;">새 첨부파일 추가:</label>
                <input type="file" id="modal-attachments" multiple style="margin-top: 4px; width: 100%; font-size: 12px;">
                <div id="modal-selected-files-container" style="display:flex; flex-wrap:wrap; gap:4px; margin-top:6px;"></div>
                <div id="modal-attachment-help" class="attachment-help"></div>
            </div>
        </div>

        <div class="modal-btn-group">
            <button class="modal-btn btn-edit" id="btnEdit" onclick="updateTaskContent()">수정</button>
            <button class="modal-btn btn-complete" id="btnComplete" onclick="setTaskComplete()">완료</button>
            <button class="modal-btn btn-undo" id="btnUndo" onclick="undoTaskComplete()">완료취소</button>
            <button class="modal-btn btn-close" onclick="closeTaskDetail()">닫기</button>
        </div>
    </div>
</div>

<script>
    // loadPage() 로 화면이 다시 로드되어 스크립트가 재실행돼도 오류가 나지 않도록 var 사용
    var existingFileCountForDetail = 0;
    var editSelectedFilesForDetail = [];

    function refreshCurrentView(targetDate) {
        if (typeof loadPage === 'function') {
            loadPage('dailytasks.php?d=' + targetDate, '오늘의 할일');
        } else {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('d', targetDate);
            window.location.href = currentUrl.toString();
        }
    }

    function moveDate(offset, baseDate) {
        if (offset > 0 && getCurrentDateStatus() >= 0) return; // 오늘 이후 날짜로는 이동 불가
        const parts = baseDate.split('-');
        const dateObj = new Date(parts[0], parts[1] - 1, parts[2]);
        dateObj.setDate(dateObj.getDate() + offset);
        const yyyy = dateObj.getFullYear();
        const mm = String(dateObj.getMonth() + 1).padStart(2, '0');
        const dd = String(dateObj.getDate()).padStart(2, '0');
        refreshCurrentView(`${yyyy}-${mm}-${dd}`);
    }

    function getCurrentViewDate() { return document.getElementById('current-view-date').value; }
    function getCurrentDateStatus() { return parseInt(document.getElementById('current-date-status').value, 10); }

    function deleteDailyTask(id) {
        if (confirm('이 할일을 삭제할까?')) {
            const formData = new FormData();
            formData.append('id', id);
            fetch('dailytasks.php?action=delete', { method: 'POST', body: formData })
            .then(res => res.text()).then(data => {
                if (data.trim() === 'success') { refreshCurrentView(getCurrentViewDate()); }
            });
        }
    }

    // 위/아래 화살표 : 바로 위(아래)에 있던 할일(targetId)과 순서(sort_order)를 바꾸고 재조회
    var movingTask = false;
    function moveTask(id, targetId) {
        if (movingTask) return; // 연속 터치 방지
        movingTask = true;

        const formData = new FormData();
        formData.append('id', id);
        formData.append('target_id', targetId);
        fetch('dailytasks.php?action=move_task', { method: 'POST', body: formData })
        .then(res => res.text().then(text => ({ ok: res.ok, text: text.trim() })))
        .then(result => {
            movingTask = false;
            if (result.ok && result.text === 'success') {
                refreshCurrentView(getCurrentViewDate());
            } else {
                alert('순서를 바꾸지 못했어: ' + result.text);
            }
        })
        .catch(() => {
            movingTask = false;
            alert('순서 변경 중 통신 오류가 발생했어.');
        });
    }

    function openTaskDetail(element) {
        const id = element.getAttribute('data-id');
        const content = element.getAttribute('data-content');
        const status = element.getAttribute('data-status');
        const dateStatus = getCurrentDateStatus();
        
        document.getElementById('modal-task-id').value = id;
        document.getElementById('modal-task-content').value = content;
        
        const btnEdit = document.getElementById('btnEdit');
        const btnComplete = document.getElementById('btnComplete');
        const btnUndo = document.getElementById('btnUndo');
        const textarea = document.getElementById('modal-task-content');

        btnEdit.style.display = 'none';
        btnComplete.style.display = 'none';
        btnUndo.style.display = 'none';
        textarea.removeAttribute('readonly');

        if (status === '완료') {
            // 완료된 할일: 읽기 전용, 과거 일자가 아니면 완료취소 가능
            textarea.setAttribute('readonly', true);
            if (dateStatus !== -1) { btnUndo.style.display = 'block'; }
        } else {
            // 미완료 할일: 수정 / 완료 가능
            btnEdit.style.display = 'block';
            btnComplete.style.display = 'block';
        }

        // 새 첨부파일 선택 목록 초기화
        editSelectedFilesForDetail = [];
        const fileInput = document.getElementById('modal-attachments');
        if (fileInput) {
            fileInput.value = '';
            if (window.TODO_ATTACHMENT_EXTENSIONS) {
                fileInput.accept = window.TODO_ATTACHMENT_EXTENSIONS.map(e => '.' + e).join(',');
            }
        }
        syncDetailSelectedFilesAndRender();
        
        const helpText = document.getElementById('modal-attachment-help');
        if (helpText) {
            const max = window.TODO_ATTACHMENT_MAX_FILES || 5;
            const ext = (window.TODO_ATTACHMENT_EXTENSIONS || []).join(', ').toUpperCase();
            helpText.textContent = `최대 ${max}개 / ${ext} / 파일당 20MB`;
        }

        document.getElementById('taskDetailModal').style.display = 'flex';
        loadTaskAttachments(id);
    }

    function loadTaskAttachments(taskId) {
        const list = document.getElementById('modal-attachment-list');
        const count = document.getElementById('modal-attachment-count');
        list.innerHTML = '<span style="color:#999;font-size:11px;">불러오는 중...</span>';
        
        fetch('dailytasks.php?action=attachments&task_id=' + encodeURIComponent(taskId))
            .then(r => r.json())
            .then(files => {
                existingFileCountForDetail = files.length;
                count.textContent = '(' + files.length + '개)';
                if (!files.length) {
                    list.innerHTML = '<span style="color:#999;font-size:11px;">첨부파일이 없습니다.</span>';
                    return;
                }
                list.innerHTML = files.map(f => `
                    <div class="attachment-row" id="att-row-${f.att_id}">
                        <a class="attachment-name" href="${encodeURI(f.storage_path)}" target="_blank">📎 ${escapeHtml(f.original_name)}</a>
                        <button type="button" class="attachment-delete" onclick="deleteTaskAttachment(${f.att_id})">🗑️</button>
                    </div>
                `).join('');
            })
            .catch(() => {
                existingFileCountForDetail = 0;
                list.innerHTML = '<span style="color:#e74c3c;font-size:11px;">첨부파일 조회 실패</span>';
            });
    }

    // 파일 개별 삭제: 팝업을 닫지 않고 해당 아이템만 제거
    function deleteTaskAttachment(attId) {
        if (!confirm('이 첨부파일을 삭제할까?')) return;
        
        const fd = new FormData();
        fd.append('att_id', attId);
        
        fetch('dailytasks.php?action=delete_attachment', { method: 'POST', body: fd })
            .then(async r => {
                const t = await r.text();
                if (!r.ok) throw new Error(t);
                return t;
            })
            .then(() => {
                const row = document.getElementById('att-row-' + attId);
                if (row) row.remove();
                
                existingFileCountForDetail = Math.max(0, existingFileCountForDetail - 1);
                
                const count = document.getElementById('modal-attachment-count');
                if (count) count.textContent = '(' + existingFileCountForDetail + '개)';
                
                const list = document.getElementById('modal-attachment-list');
                if (list && list.children.length === 0) {
                    list.innerHTML = '<span style="color:#999;font-size:11px;">첨부파일이 없습니다.</span>';
                }

                // 메인 목록 아이콘 표시 실시간 동기화
                const taskId = document.getElementById('modal-task-id').value;
                const taskItem = document.querySelector(`.task-content-line[data-id="${taskId}"]`);
                if (taskItem) {
                    const taskActions = taskItem.closest('.task-item').querySelector('.task-actions');
                    const clipIcon = taskActions?.querySelector('.task-attachment-icon');
                    if (clipIcon) {
                        if (existingFileCountForDetail === 0) {
                            clipIcon.remove();
                        } else {
                            clipIcon.title = `첨부파일 ${existingFileCountForDetail}개`;
                        }
                    }
                }
            })
            .catch(e => alert(e.message || '파일 삭제 중 오류가 발생했어.'));
    }

    // 파일 선택 시 이벤트
    if (window.__dtChangeHandler) document.removeEventListener('change', window.__dtChangeHandler);
    window.__dtChangeHandler = function(e) {
        if (e.target && e.target.id === 'modal-attachments') {
            const maxFiles = window.TODO_ATTACHMENT_MAX_FILES || 5;
            const allowedExt = window.TODO_ATTACHMENT_EXTENSIONS || [];
            const newFiles = Array.from(e.target.files || []);

            for (const file of newFiles) {
                if (existingFileCountForDetail + editSelectedFilesForDetail.length >= maxFiles) {
                    alert(`기존 파일을 포함해 최대 ${maxFiles}개까지만 선택할 수 있어!`);
                    break;
                }

                const extParts = file.name.split('.');
                const ext = extParts.length > 1 ? extParts.pop().toLowerCase() : '';

                if (allowedExt.length > 0 && !allowedExt.includes(ext)) {
                    alert(`허용되지 않는 파일 형식이야: ${file.name}`);
                    continue;
                }

                const isDuplicate = editSelectedFilesForDetail.some(item => 
                    item.name === file.name && item.size === file.size && item.lastModified === file.lastModified
                );

                if (!isDuplicate) {
                    editSelectedFilesForDetail.push(file);
                }
            }
            syncDetailSelectedFilesAndRender();
        }
    };
    document.addEventListener('change', window.__dtChangeHandler);

    function removeDetailSelectedFile(index) {
        if (index >= 0 && index < editSelectedFilesForDetail.length) {
            editSelectedFilesForDetail.splice(index, 1);
            syncDetailSelectedFilesAndRender();
        }
    }

    function syncDetailSelectedFilesAndRender() {
        const fileInput = document.getElementById('modal-attachments');
        const container = document.getElementById('modal-selected-files-container');
        
        if (fileInput) {
            const dt = new DataTransfer();
            editSelectedFilesForDetail.forEach(f => dt.items.add(f));
            fileInput.files = dt.files;
        }

        if (!container) return;
        container.innerHTML = '';

        editSelectedFilesForDetail.forEach((file, index) => {
            const badge = document.createElement('div');
            badge.className = 'selected-file-badge';

            const nameSpan = document.createElement('span');
            nameSpan.className = 'selected-file-badge-name';
            nameSpan.textContent = '📎 ' + file.name;

            const removeBtn = document.createElement('span');
            removeBtn.className = 'selected-file-badge-remove';
            removeBtn.innerHTML = '&times;';
            removeBtn.title = '제외';
            removeBtn.onclick = function(evt) {
                evt.stopPropagation();
                removeDetailSelectedFile(index);
            };

            badge.appendChild(nameSpan);
            badge.appendChild(removeBtn);
            container.appendChild(badge);
        });
    }

    function escapeHtml(v) { const d=document.createElement('div'); d.textContent=v??''; return d.innerHTML; }

    function closeTaskDetail() { document.getElementById('taskDetailModal').style.display = 'none'; }

    // [수정] 버튼 클릭 시 할일 내용 수정과 새 첨부파일 업로드를 동시 처리
    function updateTaskContent() {
        const id = document.getElementById('modal-task-id').value;
        const content = document.getElementById('modal-task-content').value;
        if (!content.trim()) return alert('내용을 입력해 줘!');

        const maxFiles = window.TODO_ATTACHMENT_MAX_FILES || 5;
        if (existingFileCountForDetail + editSelectedFilesForDetail.length > maxFiles) {
            return alert(`기존 파일을 포함해 최대 ${maxFiles}개까지만 유지할 수 있습니다!`);
        }

        const btnEdit = document.getElementById('btnEdit');
        if (btnEdit) btnEdit.disabled = true;

        const formData = new FormData();
        formData.append('id', id);
        formData.append('content', content);

        editSelectedFilesForDetail.forEach(file => {
            formData.append('attachments[]', file);
        });

        fetch('dailytasks.php?action=update_content', { method: 'POST', body: formData })
            .then(async res => {
                const text = await res.text();
                if (!res.ok) throw new Error(text);
                return text;
            })
            .then(data => {
                if (data.trim() === 'success') {
                    closeTaskDetail();
                    refreshCurrentView(getCurrentViewDate());
                } else {
                    alert(data);
                }
            })
            .catch(err => {
                alert(err.message || '수정 중 오류가 발생했어.');
            })
            .finally(() => {
                if (btnEdit) btnEdit.disabled = false;
            });
    }

    function setTaskComplete() {
        const id = document.getElementById('modal-task-id').value;
        const formData = new FormData();
        formData.append('id', id);
        fetch('dailytasks.php?action=complete', { method: 'POST', body: formData })
        .then(res => res.text()).then(data => {
            if (data.trim() === 'success') { closeTaskDetail(); refreshCurrentView(getCurrentViewDate()); }
        });
    }

    function undoTaskComplete() {
        const id = document.getElementById('modal-task-id').value;
        const formData = new FormData();
        formData.append('id', id);
        fetch('dailytasks.php?action=undo_complete', { method: 'POST', body: formData })
        .then(res => res.text()).then(data => {
            if (data.trim() === 'success') { closeTaskDetail(); refreshCurrentView(getCurrentViewDate()); }
        });
    }
</script>